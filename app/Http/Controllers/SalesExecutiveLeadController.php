<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class SalesExecutiveLeadController extends Controller
{
    /**
     * Get leads assigned to the authenticated Sales Executive
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Scope leads strictly to authenticated Sales Executive
        $query = Lead::with([
            'brand',
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

        // Filter by Status (name or status_id)
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

        // Filter by Date (created_at)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('created_at', [$request->from_date, $request->to_date]);
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
    }

    /**
     * Get single lead details assigned to the authenticated Sales Executive
     */
    public function show($id)
    {
        $user = auth()->user();

        $lead = Lead::with([
            'brand',
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
    }
}
