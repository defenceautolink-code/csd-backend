<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesExecutiveLeadController extends Controller
{
    /**
     * Get leads assigned to the authenticated Sales Executive
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            // Scope leads strictly to authenticated Sales Executive
            $query = Lead::with([
                'brand',
                'vehicleModel',
                'variant',
                'source',
                'status',
                'assignedUser',
                'latestFollowUp',
                'latestAssignment.assignedByUser',
            ])->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
            });

            // Search within assigned leads
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('phone', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('model_variant', 'like', '%' . $search . '%')
                      ->orWhere('city', 'like', '%' . $search . '%')
                      ->orWhere('brand_name', 'like', '%' . $search . '%');
                });
            }

            // Filter by Status
            if ($request->filled('status')) {
                $query->where('status_name', $request->status);
            }
            if ($request->filled('status_id')) {
                $query->where('status_id', $request->status_id);
            }

            // Filter by Priority
            if ($request->filled('priority')) {
                $query->where('priority', ucfirst(strtolower($request->priority)));
            }

            // Filter by Date
            $startDate = $request->input('start_date') ?? $request->input('startDate') ?? $request->input('from_date') ?? $request->input('date_from');
            $endDate = $request->input('end_date') ?? $request->input('endDate') ?? $request->input('to_date') ?? $request->input('date_to');
            $singleDate = $request->input('date');

            if (!empty($singleDate)) {
                $query->whereDate('created_at', $singleDate);
            } else {
                if (!empty($startDate)) {
                    $query->whereDate('created_at', '>=', $startDate);
                }
                if (!empty($endDate)) {
                    $query->whereDate('created_at', '<=', $endDate);
                }
            }

            $perPage = (int) $request->get('per_page', 20);
            $leads = $query->latest()->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Assigned leads fetched successfully',
                'data' => $leads->items(),
                'pagination' => [
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage(),
                    'per_page' => $leads->perPage(),
                    'total' => $leads->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesExecutiveLeadController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching assigned leads.',
            ], 500);
        }
    }

    /**
     * Get single lead details assigned to the authenticated Sales Executive
     */
    public function show($id)
    {
        try {
            $user = auth()->user();

            $lead = Lead::with([
                'brand',
                'vehicleModel',
                'variant',
                'source',
                'status',
                'assignedUser',
                'followUps.user',
                'assignmentHistories.assignedByUser',
                'latestFollowUp',
            ])
            ->where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
            })
            ->first();

            if (!$lead) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lead not found or you do not have permission to access it.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Lead details fetched successfully',
                'data' => $lead,
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesExecutiveLeadController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching lead details.',
            ], 500);
        }
    }
}
