<?php

namespace App\Http\Controllers;

use App\Models\LeadSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadSourceController extends Controller
{
    /**
     * Get list of all lead sources
     */
    public function index(Request $request)
    {
        try {
            $query = LeadSource::query();

            // Optional search by title
            if ($request->filled('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
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
                    'message' => 'Lead sources retrieved successfully',
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

            $leadSources = $query->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Lead sources retrieved successfully',
                'data' => $leadSources,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($leadSources),
                    'total' => count($leadSources),
                    'from' => count($leadSources) > 0 ? 1 : null,
                    'to' => count($leadSources),
                ],
                'total' => $leadSources->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('LeadSourceController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching lead sources.',
            ], 500);
        }
    }

    /**
     * Create a new lead source
     */
    public function store(Request $request)
    {
        try {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('LeadSourceController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating lead source.',
            ], 500);
        }
    }

    /**
     * Get a single lead source
     */
    public function show($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('LeadSourceController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching lead source details.',
            ], 500);
        }
    }

    /**
     * Update an existing lead source
     */
    public function update(Request $request, $id)
    {
        try {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('LeadSourceController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating lead source.',
            ], 500);
        }
    }

    /**
     * Delete a lead source
     */
    public function destroy($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('LeadSourceController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting lead source.',
            ], 500);
        }
    }
}
