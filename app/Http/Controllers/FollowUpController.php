<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    /**
     * Get list of all follow-ups managed by role with advanced filters & KPIs
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $query = LeadFollowUp::with([
            'user:id,name,email,phone,role',
            'lead:id,name,email,phone,brand_id,brand_name,model_variant,priority,status_id,status_name,assigned_to,assigned_user_name',
        ]);

        // Role-based Access Control:
        // Sales Executive sees only follow-ups for their assigned leads or logged by them
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('lead', function ($leadQuery) use ($user) {
                      $leadQuery->where('assigned_to', $user->id)
                                ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
                  });
            });
        }

        // Filter by Lead ID
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by Sales Executive User ID
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by Interaction Type (Phone Call, WhatsApp, Email, Showroom Visit, Test Drive)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by Outcome Status (Interested, Follow-Up Needed, Quote Sent, Won, Lost, etc.)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by Due Schedule: 'today', 'overdue', 'upcoming'
        $today = Carbon::today()->toDateString();
        if ($request->filled('due_filter')) {
            $dueFilter = strtolower($request->due_filter);
            if ($dueFilter === 'today') {
                $query->whereDate('next_follow_up_date', $today);
            } elseif ($dueFilter === 'overdue') {
                $query->whereDate('next_follow_up_date', '<', $today)
                      ->whereNotIn('status', ['Won', 'Lost', 'Completed', 'Closed']);
            } elseif ($dueFilter === 'upcoming') {
                $query->whereDate('next_follow_up_date', '>', $today)
                      ->whereDate('next_follow_up_date', '<=', Carbon::today()->addDays(7)->toDateString());
            }
        }

        // Date Range on follow_up_date
        if ($request->filled('date_from')) {
            $query->whereDate('follow_up_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('follow_up_date', '<=', $request->date_to);
        }

        // Search filter across customer name, phone, notes, executive name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', '%' . $search . '%')
                  ->orWhere('type', 'like', '%' . $search . '%')
                  ->orWhere('status', 'like', '%' . $search . '%')
                  ->orWhereHas('lead', function ($lq) use ($search) {
                      $lq->where('name', 'like', '%' . $search . '%')
                         ->orWhere('phone', 'like', '%' . $search . '%')
                         ->orWhere('email', 'like', '%' . $search . '%')
                         ->orWhere('model_variant', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // KPI statistics calculation (scoped to current role access)
        $kpiBaseQuery = clone $query;
        $overdueCount = (clone $kpiBaseQuery)->whereDate('next_follow_up_date', '<', $today)
            ->whereNotIn('status', ['Won', 'Lost', 'Completed', 'Closed'])->count();
        $dueTodayCount = (clone $kpiBaseQuery)->whereDate('next_follow_up_date', $today)->count();
        $upcomingCount = (clone $kpiBaseQuery)->whereDate('next_follow_up_date', '>', $today)
            ->whereDate('next_follow_up_date', '<=', Carbon::today()->addDays(7)->toDateString())->count();
        $totalCount = (clone $kpiBaseQuery)->count();

        // Handle Pagination or Full List
        $perPage = (int) $request->get('per_page', 0);
        if ($perPage > 0 || $request->filled('page')) {
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Follow-ups retrieved successfully',
                'kpis' => [
                    'overdue' => $overdueCount,
                    'due_today' => $dueTodayCount,
                    'upcoming' => $upcomingCount,
                    'total' => $totalCount,
                ],
                'data' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $followUps = $query->latest('id')->get();

        return response()->json([
            'status' => true,
            'message' => 'Follow-ups retrieved successfully',
            'kpis' => [
                'overdue' => $overdueCount,
                'due_today' => $dueTodayCount,
                'upcoming' => $upcomingCount,
                'total' => $totalCount,
            ],
            'data' => $followUps,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($followUps),
                'total' => count($followUps),
            ],
        ]);
    }

    /**
     * Log / Create a new follow-up
     */
    public function store(Request $request, $leadId = null)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $targetLeadId = $leadId ?: $request->input('lead_id');

        if (!$targetLeadId) {
            return response()->json([
                'status' => false,
                'message' => 'The lead_id field is required.',
            ], 422);
        }

        $leadQuery = Lead::where('id', $targetLeadId);

        // Security check for Sales Executive role
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $leadQuery->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
            });
        }

        $lead = $leadQuery->first();

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found or you do not have permission to add a follow-up to this lead.',
            ], 404);
        }

        $request->validate([
            'follow_up_date' => 'required|date',
            'follow_up_time' => 'nullable|string|max:50',
            'type' => 'required|string|max:100',
            'notes' => 'nullable|string|max:5000',
            'next_follow_up_date' => 'nullable|date',
            'next_follow_up_time' => 'nullable|string|max:50',
            'status' => 'required|string|max:50',
            'lead_status_id' => 'nullable|exists:lead_statuses,id',
            'lead_status_name' => 'nullable|string|max:100',
        ]);

        $userId = $user ? $user->id : ($lead->assigned_to ?? User::first()?->id);

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

        $followUp->load(['user', 'lead']);

        return response()->json([
            'status' => true,
            'message' => 'Follow-up logged successfully',
            'data' => $followUp,
        ], 201);
    }

    /**
     * Display a specific follow-up details
     */
    public function show(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $followUp = LeadFollowUp::with(['user', 'lead'])->find($id);

        if (!$followUp) {
            return response()->json([
                'status' => false,
                'message' => 'Follow-up record not found.',
            ], 404);
        }

        // Security check for Sales Executive role
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isOwner = ($followUp->user_id == $user->id) ||
                       ($followUp->lead && ($followUp->lead->assigned_to == $user->id || stripos($followUp->lead->assigned_user_name, $user->name) !== false));

            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to view this follow-up record.',
                ], 403);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Follow-up details retrieved successfully',
            'data' => $followUp,
        ]);
    }

    /**
     * Update a follow-up record
     */
    public function update(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $followUp = LeadFollowUp::find($id);

        if (!$followUp) {
            return response()->json([
                'status' => false,
                'message' => 'Follow-up record not found.',
            ], 404);
        }

        // Security check for Sales Executive role
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isOwner = ($followUp->user_id == $user->id) ||
                       ($followUp->lead && ($followUp->lead->assigned_to == $user->id || stripos($followUp->lead->assigned_user_name, $user->name) !== false));

            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to update this follow-up record.',
                ], 403);
            }
        }

        $request->validate([
            'follow_up_date' => 'sometimes|required|date',
            'follow_up_time' => 'nullable|string|max:50',
            'type' => 'sometimes|required|string|max:100',
            'notes' => 'nullable|string|max:5000',
            'next_follow_up_date' => 'nullable|date',
            'next_follow_up_time' => 'nullable|string|max:50',
            'status' => 'sometimes|required|string|max:50',
            'lead_status_id' => 'nullable|exists:lead_statuses,id',
            'lead_status_name' => 'nullable|string|max:100',
        ]);

        $updateData = [];
        if ($request->has('follow_up_date')) $updateData['follow_up_date'] = date('Y-m-d', strtotime($request->follow_up_date));
        if ($request->has('follow_up_time')) $updateData['follow_up_time'] = $request->follow_up_time;
        if ($request->has('type')) $updateData['type'] = $request->type;
        if ($request->has('notes')) $updateData['notes'] = $request->notes;
        if ($request->has('next_follow_up_date')) $updateData['next_follow_up_date'] = $request->filled('next_follow_up_date') ? date('Y-m-d', strtotime($request->next_follow_up_date)) : null;
        if ($request->has('next_follow_up_time')) $updateData['next_follow_up_time'] = $request->next_follow_up_time;
        if ($request->has('status')) $updateData['status'] = ucfirst(strtolower($request->status));

        $followUp->update($updateData);

        // If lead status updated
        if ($followUp->lead && ($request->filled('lead_status_id') || $request->filled('lead_status_name'))) {
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
                $followUp->lead->update([
                    'status_id' => $statusId ?: $followUp->lead->status_id,
                    'status_name' => $statusName ?: $followUp->lead->status_name,
                ]);
            }
        }

        $followUp->load(['user', 'lead']);

        return response()->json([
            'status' => true,
            'message' => 'Follow-up updated successfully',
            'data' => $followUp,
        ]);
    }

    /**
     * Delete a follow-up record
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $followUp = LeadFollowUp::find($id);

        if (!$followUp) {
            return response()->json([
                'status' => false,
                'message' => 'Follow-up record not found.',
            ], 404);
        }

        // Security check for Sales Executive role
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isOwner = ($followUp->user_id == $user->id);
            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to delete this follow-up record.',
                ], 403);
            }
        }

        $followUp->delete();

        return response()->json([
            'status' => true,
            'message' => 'Follow-up record deleted successfully',
        ]);
    }

    /**
     * Get follow-up history for a specific lead
     */
    public function getByLead(Request $request, $leadId)
    {
        $request->merge(['lead_id' => $leadId]);
        return $this->index($request);
    }
}
