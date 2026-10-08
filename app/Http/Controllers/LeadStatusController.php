<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\Request;

class LeadStatusController extends Controller
{
    /**
     * Get list of all lead statuses
     */
    public function index(Request $request)
    {
        $query = LeadStatus::query();

        // Optional search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Optional filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Handle Pagination or Full List
        $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1 || $request->get('paginate') === 'false';

        if (!$isAll) {
            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->latest()->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Lead statuses retrieved successfully',
                'data' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ]);
        }

        $leadStatuses = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead statuses retrieved successfully',
            'data' => $leadStatuses,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($leadStatuses),
                'total' => count($leadStatuses),
                'from' => count($leadStatuses) > 0 ? 1 : null,
                'to' => count($leadStatuses),
            ],
            'total' => $leadStatuses->count(),
        ]);
    }

    /**
     * Create a new lead status
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        $leadStatus = LeadStatus::create([
            'name' => $request->name,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lead status created successfully',
            'data' => $leadStatus,
        ], 201);
    }

    /**
     * Get a single lead status
     */
    public function show($id)
    {
        $leadStatus = LeadStatus::find($id);

        if (!$leadStatus) {
            return response()->json([
                'status' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lead status details',
            'data' => $leadStatus,
        ]);
    }

    /**
     * Update an existing lead status
     */
    public function update(Request $request, $id)
    {
        $leadStatus = LeadStatus::find($id);

        if (!$leadStatus) {
            return response()->json([
                'status' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        $leadStatus->update([
            'name' => $request->name,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lead status updated successfully',
            'data' => $leadStatus,
        ]);
    }

    /**
     * Delete a lead status
     */
    public function destroy($id)
    {
        $leadStatus = LeadStatus::find($id);

        if (!$leadStatus) {
            return response()->json([
                'status' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        $leadStatus->delete();

        return response()->json([
            'status' => true,
            'message' => 'Lead status deleted successfully',
        ]);
    }
}
