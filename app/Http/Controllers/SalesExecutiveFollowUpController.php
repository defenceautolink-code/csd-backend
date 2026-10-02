<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;

class SalesExecutiveFollowUpController extends Controller
{
    /**
     * Get follow-up history for a specific lead
     */
    public function index(Request $request, $leadId)
    {
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
    }

    /**
     * Create/log a new follow-up interaction on a lead
     */
    public function store(Request $request, $leadId)
    {
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
        ]);

        $userId = $user ? $user->id : ($lead->assigned_to ?? User::first()?->id);

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
        ]);

        // If lead status updated as part of follow-up interaction
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

        $followUp->load('user');

        return response()->json([
            'status' => true,
            'message' => 'Follow-up logged successfully',
            'data' => $followUp,
        ], 201);
    }
}
