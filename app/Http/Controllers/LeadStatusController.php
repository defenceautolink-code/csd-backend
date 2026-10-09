<?php

namespace App\Http\Controllers;

use App\Models\LeadStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadStatusController extends Controller
{
    /**
     * Get list of all lead statuses
     */
    public function index(Request $request)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('LeadStatusController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching lead statuses.',
            ], 500);
        }
    }

    /**
     * Create a new lead status
     */
    public function store(Request $request)
    {
        try {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('LeadStatusController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating lead status.',
            ], 500);
        }
    }

    /**
     * Get a single lead status
     */
    public function show($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('LeadStatusController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching lead status details.',
            ], 500);
        }
    }

    /**
     * Update an existing lead status
     */
    public function update(Request $request, $id)
    {
        try {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('LeadStatusController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating lead status.',
            ], 500);
        }
    }

    /**
     * Delete a lead status
     */
    public function destroy($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::error('LeadStatusController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting lead status.',
            ], 500);
        }
    }
}
