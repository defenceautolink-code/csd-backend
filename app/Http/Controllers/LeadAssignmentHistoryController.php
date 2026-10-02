<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\User;
use Illuminate\Http\Request;

class LeadAssignmentHistoryController extends Controller
{
    /**
     * Display a listing of lead assignment histories with ORM relations
     */
    public function index(Request $request)
    {
        $query = LeadAssignmentHistory::with(['lead', 'assignedToUser', 'assignedByUser']);

        // Filter by Lead ID
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by Assigned To User (ID or Name via relation)
        if ($request->filled('assign_to')) {
            $assignTo = $request->assign_to;
            $query->where(function ($q) use ($assignTo) {
                if (is_numeric($assignTo)) {
                    $q->where('assign_to', $assignTo);
                } else {
                    $q->whereHas('assignedToUser', function ($uQuery) use ($assignTo) {
                        $uQuery->where('name', 'like', '%' . $assignTo . '%')
                               ->orWhere('email', 'like', '%' . $assignTo . '%');
                    });
                }
            });
        }

        // Filter by Assigned By User (ID or Name via relation)
        if ($request->filled('assign_by')) {
            $assignBy = $request->assign_by;
            $query->where(function ($q) use ($assignBy) {
                if (is_numeric($assignBy)) {
                    $q->where('assign_by', $assignBy);
                } else {
                    $q->whereHas('assignedByUser', function ($uQuery) use ($assignBy) {
                        $uQuery->where('name', 'like', '%' . $assignBy . '%');
                    });
                }
            });
        }

        // Search across customer/lead name, remarks, or executive names
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'like', '%' . $search . '%')
                  ->orWhereHas('assignedToUser', function ($uQuery) use ($search) {
                      $uQuery->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('assignedByUser', function ($uQuery) use ($search) {
                      $uQuery->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('lead', function ($leadQuery) use ($search) {
                      $leadQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%');
                  });
            });
        }

        $histories = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead assignment histories retrieved successfully',
            'data' => $histories,
        ]);
    }

    /**
     * Store a new lead assignment history entry
     */
    public function store(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'assign_to' => 'nullable',
            'assign_by' => 'nullable',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $lead = Lead::findOrFail($request->lead_id);

        $assignToId = null;
        if ($request->filled('assign_to')) {
            if (is_numeric($request->assign_to)) {
                $assignToId = User::find($request->assign_to)?->id;
            } else {
                $nameQuery = trim(preg_replace('/\(.*?\)/', '', $request->assign_to));
                $assignToId = User::where('name', 'like', "%{$nameQuery}%")->first()?->id;
            }
        }

        // Fallback to first user if null
        if (!$assignToId) {
            $assignToId = User::first()?->id;
        }

        $assignById = null;
        if ($request->filled('assign_by') && is_numeric($request->assign_by)) {
            $assignById = User::find($request->assign_by)?->id;
        } elseif (auth()->check()) {
            $assignById = auth()->id();
        } else {
            $assignById = User::where('role', 'Super Admin')->first()?->id ?? User::first()?->id;
        }

        // Create assignment history record
        $history = LeadAssignmentHistory::create([
            'lead_id' => $lead->id,
            'assign_to' => $assignToId,
            'assign_by' => $assignById,
            'remarks' => $request->remarks,
        ]);

        // Keep the Lead record's active assigned executive synchronized
        if ($assignToId) {
            $targetUser = User::find($assignToId);
            $lead->update([
                'assigned_to' => $assignToId,
                'assigned_user_name' => $targetUser ? "{$targetUser->name} ({$targetUser->role})" : $lead->assigned_user_name,
            ]);
        }

        $history->load(['lead', 'assignedToUser', 'assignedByUser']);

        return response()->json([
            'status' => true,
            'message' => 'Lead assignment history recorded successfully',
            'data' => $history,
        ], 201);
    }

    /**
     * Display the specified lead assignment history
     */
    public function show($id)
    {
        $history = LeadAssignmentHistory::with(['lead', 'assignedToUser', 'assignedByUser'])->find($id);

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'Lead assignment history record not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lead assignment history details',
            'data' => $history,
        ]);
    }

    /**
     * Update an assignment history record (e.g. edit remarks)
     */
    public function update(Request $request, $id)
    {
        $history = LeadAssignmentHistory::find($id);

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'Lead assignment history record not found',
            ], 404);
        }

        $request->validate([
            'remarks' => 'nullable|string|max:1000',
            'assign_to' => 'nullable|exists:users,id',
            'assign_by' => 'nullable|exists:users,id',
        ]);

        $history->update($request->only([
            'remarks',
            'assign_to',
            'assign_by',
        ]));

        $history->load(['lead', 'assignedToUser', 'assignedByUser']);

        return response()->json([
            'status' => true,
            'message' => 'Lead assignment history updated successfully',
            'data' => $history,
        ]);
    }

    /**
     * Delete an assignment history record
     */
    public function destroy($id)
    {
        $history = LeadAssignmentHistory::find($id);

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'Lead assignment history record not found',
            ], 404);
        }

        $history->delete();

        return response()->json([
            'status' => true,
            'message' => 'Lead assignment history deleted successfully',
        ]);
    }

    /**
     * Get all assignment history for a specific lead using ORM relations
     */
    public function getByLead($leadId)
    {
        $lead = Lead::find($leadId);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $histories = LeadAssignmentHistory::with(['assignedToUser', 'assignedByUser'])
            ->where('lead_id', $leadId)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => "Assignment history for lead #{$leadId}",
            'lead' => $lead,
            'data' => $histories,
        ]);
    }
}
