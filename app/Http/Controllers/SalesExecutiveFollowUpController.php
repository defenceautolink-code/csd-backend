<?php

namespace App\Http\Controllers;

use App\Models\DiscountApproval;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesExecutiveFollowUpController extends Controller
{
    /**
     * Get follow-up history for a specific lead
     */
    public function index(Request $request, $leadId)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $query = Lead::where('id', $leadId);

            // Security check for Sales Executive role
            if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
                });
            }

            $lead = $query->first();

            if (!$lead) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lead not found or you do not have permission to view its follow-ups.',
                ], 404);
            }

            $followUps = LeadFollowUp::with('user')
                ->where('lead_id', $leadId)
                ->latest('id')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Follow-up history fetched successfully',
                'lead' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'status_name' => $lead->status_name,
                    'assigned_user_name' => $lead->assigned_user_name,
                ],
                'data' => $followUps,
            ]);
        } catch (\Throwable $e) {
            Log::error('SalesExecutiveFollowUpController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching follow-up history.',
            ], 500);
        }
    }

    /**
     * Create/log a new follow-up interaction on a lead
     */
    public function store(Request $request, $leadId)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $query = Lead::where('id', $leadId);

            // Security check for Sales Executive role
            if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
                });
            }

            $lead = $query->first();

            if (!$lead) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lead not found or you do not have permission to add a follow-up.',
                ], 404);
            }

            $request->validate([
                'follow_up_date' => 'required|date',
                'follow_up_time' => 'nullable|string|max:50',
                'type' => 'required|string|max:100',
                'notes' => 'nullable|string|max:3000',
                'next_follow_up_date' => 'nullable|date',
                'next_follow_up_time' => 'nullable|string|max:50',
                'status' => 'required|string|max:50',
                'lead_status_id' => 'nullable|exists:lead_statuses,id',
                'lead_status_name' => 'nullable|string|max:100',
                'is_discount_requested' => 'nullable',
                'discount_amount' => 'nullable|numeric|min:0',
                'proposed_price' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string|max:5000',
            ]);

            $userId = $user ? $user->id : ($lead->assigned_to ?? User::first()?->id);

            // Check if a special discount is being requested
            $isDiscountRequested = filter_var($request->input('is_discount_requested'), FILTER_VALIDATE_BOOLEAN);
            $discountAmount = $request->filled('discount_amount') ? (float) $request->input('discount_amount') : 0.0;
            $proposedPrice = $request->filled('proposed_price') ? (float) $request->input('proposed_price') : null;
            $reason = $request->input('reason') ?: $request->input('notes');

            if ($isDiscountRequested && $discountAmount <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'The discount_amount field is required and must be greater than 0 when requesting a discount.',
                    'errors' => [
                        'discount_amount' => ['The discount amount must be greater than 0 when is_discount_requested is true.'],
                    ],
                ], 422);
            }

            // Auto-assign user_id from authenticated token or lead executive
            $followUp = LeadFollowUp::create([
                'lead_id' => $lead->id,
                'user_id' => $userId,
                'follow_up_date' => date('Y-m-d', strtotime($request->follow_up_date)),
                'follow_up_time' => $request->follow_up_time,
                'type' => $request->type,
                'notes' => $request->notes,
                'next_follow_up_date' => $request->filled('next_follow_up_date') ? date('Y-m-d', strtotime($request->next_follow_up_date)) : null,
                'next_follow_up_time' => $request->next_follow_up_time,
                'status' => ucfirst(strtolower($request->status)),
                'is_discount_requested' => $isDiscountRequested,
                'discount_amount' => $isDiscountRequested ? $discountAmount : null,
                'proposed_price' => $isDiscountRequested ? $proposedPrice : null,
            ]);

            if ($isDiscountRequested) {
                // Create pending discount approval record
                DiscountApproval::create([
                    'lead_id' => $lead->id,
                    'follow_up_id' => $followUp->id,
                    'requested_by' => $userId,
                    'discount_amount' => $discountAmount,
                    'proposed_price' => $proposedPrice,
                    'reason' => $reason,
                    'status' => 'pending',
                ]);

                // Keep main lead status as Follow-up while marking discount_approval_status as pending
                $lead->update([
                    'status_name' => 'Follow-up',
                    'discount_approval_status' => 'pending',
                    'requested_discount' => $discountAmount,
                ]);
            } else {
                // If lead status updated as part of regular follow-up interaction
                if ($request->filled('lead_status_id') || $request->filled('lead_status_name')) {
                    $statusName = $request->lead_status_name;
                    $statusId = $request->lead_status_id;

                    if ($statusId && !$statusName) {
                        $st = LeadStatus::find($statusId);
                        $statusName = $st ? $st->name : null;
                    } elseif ($statusName && !$statusId) {
                        $st = LeadStatus::where('name', $statusName)->first();
                        $statusId = $st ? $st->id : null;
                    }

                    if ($statusName || $statusId) {
                        $lead->update([
                            'status_id' => $statusId ?: $lead->status_id,
                            'status_name' => $statusName ?: $lead->status_name,
                        ]);
                    }
                }
            }

            $followUp->load(['user', 'discountApproval']);

            return response()->json([
                'status' => true,
                'message' => 'Follow-up logged successfully',
                'data' => $followUp,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('SalesExecutiveFollowUpController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while logging follow-up.',
            ], 500);
        }
    }
}
