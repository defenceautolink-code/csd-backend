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

        $leadStatuses = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead statuses retrieved successfully',
            'data' => $leadStatuses,
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
