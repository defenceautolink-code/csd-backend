<?php

namespace App\Http\Controllers;

use App\Models\DiscountApproval;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiscountApprovalController extends Controller
{
    /**
     * Get list of all pending discount approvals for Admin & Sales Manager Dashboard
     * 
     * Endpoint: GET /api/discount-approvals/pending
     */
    public function pending(Request $request)
    {
        try {
            $query = DiscountApproval::with([
                'lead.brand',
                'lead.model',
                'lead.variant',
                'requestedByUser',
                'actionByUser',
            ])->pending()->latest('id');

            // Optional filters
            if ($request->filled('lead_id')) {
                $query->where('lead_id', $request->lead_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                      ->orWhereHas('lead', function ($lq) use ($search) {
                          $lq->where('name', 'like', "%{$search}%")
                             ->orWhere('phone', 'like', "%{$search}%")
                             ->orWhere('model_variant', 'like', "%{$search}%");
                      })
                      ->orWhereHas('requestedByUser', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%");
                      });
                });
            }

            $approvals = $query->get();

            $formatted = $approvals->map(function ($approval) {
                return $this->formatApprovalItem($approval);
            });

            return response()->json([
                'status' => true,
                'data' => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('DiscountApprovalController@pending error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching pending discount approvals.',
            ], 500);
        }
    }

    /**
     * List all discount approvals with filtering & pagination
     * 
     * Endpoint: GET /api/discount-approvals
     */
    public function index(Request $request)
    {
        try {
            $query = DiscountApproval::with([
                'lead.brand',
                'lead.model',
                'lead.variant',
                'requestedByUser',
                'actionByUser',
            ])->latest('id');

            // Filter by status (pending, approved, rejected)
            if ($request->filled('status')) {
                $status = strtolower($request->status);
                if (in_array($status, ['pending', 'approved', 'rejected'])) {
                    $query->where('status', $status);
                }
            }

            // Filter by Lead ID
            if ($request->filled('lead_id')) {
                $query->where('lead_id', $request->lead_id);
            }

            // Filter by Sales Executive User ID
            if ($request->filled('requested_by')) {
                $query->where('requested_by', $request->requested_by);
            }

            $perPage = (int) $request->get('per_page', 0);
            if ($perPage > 0 || $request->filled('page')) {
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->paginate($perPage);

                $formattedItems = collect($paginated->items())->map(function ($item) {
                    return $this->formatApprovalItem($item);
                });

                return response()->json([
                    'status' => true,
                    'message' => 'Discount approvals retrieved successfully',
                    'data' => $formattedItems,
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ]);
            }

            $approvals = $query->get();
            $formatted = $approvals->map(function ($approval) {
                return $this->formatApprovalItem($approval);
            });

            return response()->json([
                'status' => true,
                'message' => 'Discount approvals retrieved successfully',
                'data' => $formatted,
            ]);
        } catch (\Throwable $e) {
            Log::error('DiscountApprovalController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching discount approvals.',
            ], 500);
        }
    }

    /**
     * Get single discount approval details
     * 
     * Endpoint: GET /api/discount-approvals/{id}
     */
    public function show($id)
    {
        try {
            $approval = DiscountApproval::with([
                'lead.brand',
                'lead.model',
                'lead.variant',
                'followUp',
                'requestedByUser',
                'actionByUser',
            ])->find($id);

            if (!$approval) {
                return response()->json([
                    'status' => false,
                    'message' => 'Discount approval record not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $this->formatApprovalItem($approval, true),
            ]);
        } catch (\Throwable $e) {
            Log::error('DiscountApprovalController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching discount approval details.',
            ], 500);
        }
    }

    /**
     * Approve or Reject a discount request
     * 
     * Endpoint: POST /api/discount-approvals/{id}/action
     * Payload: { "action": "approve"|"reject", "remarks": "..." }
     */
    public function action(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            // Authorization: Only Super Admin and Sales Manager can approve or reject
            if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant', 'receptionist', 'customer'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Only Sales Manager or Super Admin can approve or reject discount requests.',
                ], 403);
            }

            $request->validate([
                'action' => 'required|string|in:approve,reject,approved,rejected',
                'remarks' => 'nullable|string|max:5000',
            ]);

            $action = strtolower(trim($request->action));
            $isApprove = in_array($action, ['approve', 'approved']);
            $finalStatus = $isApprove ? 'approved' : 'rejected';

            $approval = DiscountApproval::with('lead')->find($id);

            if (!$approval) {
                return response()->json([
                    'status' => false,
                    'message' => 'Discount approval request not found.',
                ], 404);
            }

            $lead = $approval->lead;
            if (!$lead) {
                return response()->json([
                    'status' => false,
                    'message' => 'Associated lead record not found.',
                ], 404);
            }

            $userId = $user ? $user->id : (User::whereIn('role', ['Super Admin', 'Sales Manager'])->first()?->id ?? User::first()?->id);

            // Execute in database transaction to keep approval & lead status consistent
            DB::transaction(function () use ($approval, $lead, $finalStatus, $userId, $request, $isApprove) {
                $approval->status = $finalStatus;
                $approval->action_by = $userId;
                $approval->action_at = now();
                $approval->remarks = $request->input('remarks');
                $approval->save();

                $targetStatusName = $isApprove ? 'Discount Approved' : 'Discount Rejected';

                // Find or create the status in lead_statuses table
                $leadStatus = LeadStatus::firstOrCreate(
                    ['name' => $targetStatusName],
                    ['status' => 'Active']
                );

                $lead->update([
                    'status_id' => $leadStatus->id,
                    'status_name' => $targetStatusName,
                    'discount_approval_status' => $finalStatus,
                    'approved_discount' => $isApprove ? (float) $approval->discount_amount : 0.00,
                ]);
            });

            $approval->refresh()->load(['lead', 'requestedByUser', 'actionByUser']);

            $actionMessage = $isApprove 
                ? 'Discount request has been approved successfully.' 
                : 'Discount request has been rejected.';

            return response()->json([
                'status' => true,
                'message' => $actionMessage,
                'data' => $this->formatApprovalItem($approval, true),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DiscountApprovalController@action error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while processing the discount approval action.',
            ], 500);
        }
    }

    /**
     * Helper to format discount approval item matching frontend specification
     */
    protected function formatApprovalItem(DiscountApproval $approval, bool $detailed = false): array
    {
        $lead = $approval->lead;
        $requestedBy = $approval->requestedByUser;
        $actionBy = $approval->actionByUser;

        // Vehicle Name calculation
        $vehicleName = $lead?->model_variant;
        if ($lead && $lead->brand_name && !str_contains(strtolower($lead->model_variant ?? ''), strtolower($lead->brand_name))) {
            $vehicleName = trim("{$lead->brand_name} " . ($lead->model_variant ?? ''));
        }
        if (empty($vehicleName) && $lead) {
            $vehicleName = $lead->brand_name ?: 'Vehicle Requirement';
        }

        // Requested By Name calculation
        if ($requestedBy) {
            $requestedByName = $requestedBy->role ? "{$requestedBy->name} ({$requestedBy->role})" : $requestedBy->name;
        } elseif ($lead && !empty($lead->assigned_user_name)) {
            $requestedByName = $lead->assigned_user_name;
        } else {
            $requestedByName = 'Sales Executive';
        }

        $item = [
            'id' => $approval->id,
            'lead_id' => $approval->lead_id,
            'customer_name' => $lead?->name ?? 'N/A',
            'customer_phone' => $lead?->phone ?? 'N/A',
            'vehicle_name' => $vehicleName ?? 'N/A',
            'requested_by_name' => $requestedByName,
            'discount_amount' => (float) $approval->discount_amount,
            'proposed_price' => $approval->proposed_price !== null ? (float) $approval->proposed_price : null,
            'reason' => $approval->reason ?? '',
            'created_at' => $approval->created_at ? $approval->created_at->format('Y-m-d H:i:s') : null,
        ];

        if ($detailed || $approval->status !== 'pending') {
            $item['status'] = $approval->status;
            $item['remarks'] = $approval->remarks;
            $item['action_by'] = $approval->action_by;
            $item['action_by_name'] = $actionBy ? ($actionBy->role ? "{$actionBy->name} ({$actionBy->role})" : $actionBy->name) : null;
            $item['action_at'] = $approval->action_at ? $approval->action_at->format('Y-m-d H:i:s') : null;
            if ($lead) {
                $item['lead_status_name'] = $lead->status_name;
                $item['discount_approval_status'] = $lead->discount_approval_status;
                $item['approved_discount'] = (float) $lead->approved_discount;
            }
        }

        return $item;
    }
}
