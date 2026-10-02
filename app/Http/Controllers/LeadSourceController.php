<?php

namespace App\Http\Controllers;

use App\Models\LeadSource;
use Illuminate\Http\Request;

class LeadSourceController extends Controller
{
    /**
     * Get list of all lead sources
     */
    public function index(Request $request)
    {
        $query = LeadSource::query();

        // Optional search by title
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Optional filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leadSources = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Lead sources retrieved successfully',
            'data' => $leadSources,
        ]);
    }

    /**
     * Create a new lead source
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        $leadSource = LeadSource::create([
            'title' => $request->title,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lead source created successfully',
            'data' => $leadSource,
        ], 201);
    }

    /**
     * Get a single lead source
     */
    public function show($id)
    {
        $leadSource = LeadSource::find($id);

        if (!$leadSource) {
            return response()->json([
                'status' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Lead source details',
            'data' => $leadSource,
        ]);
    }

    /**
     * Update an existing lead source
     */
    public function update(Request $request, $id)
    {
        $leadSource = LeadSource::find($id);

        if (!$leadSource) {
            return response()->json([
                'status' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        $leadSource->update([
            'title' => $request->title,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Lead source updated successfully',
            'data' => $leadSource,
        ]);
    }

    /**
     * Delete a lead source
     */
    public function destroy($id)
    {
        $leadSource = LeadSource::find($id);

        if (!$leadSource) {
            return response()->json([
                'status' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        $leadSource->delete();

        return response()->json([
            'status' => true,
            'message' => 'Lead source deleted successfully',
        ]);
    }
}
