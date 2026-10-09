<?php

namespace App\Http\Controllers;

use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleModelController extends Controller
{
    /**
     * Get list of all vehicle models with their associated brand
     */
    public function index(Request $request)
    {
        try {
            $query = VehicleModel::with('brand');

            // Optional search by model name
            if ($request->filled('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            // Optional filter by brand
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Optional filter by vehicle segment
            if ($request->filled('vehicle_segment')) {
                $query->where('vehicle_segment', $request->vehicle_segment);
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
                    'message' => 'Vehicle models retrieved successfully',
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

            $models = $query->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Vehicle models retrieved successfully',
                'data' => $models,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($models),
                    'total' => count($models),
                    'from' => count($models) > 0 ? 1 : null,
                    'to' => count($models),
                ],
                'total' => $models->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleModelController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching vehicle models.',
            ], 500);
        }
    }

    /**
     * Create a new vehicle model
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'vehicle_segment' => 'required|string|in:2 Wheeler,4 Wheeler,2 wheeler,4 wheeler',
                'status' => 'required|string|in:Active,Inactive,active,inactive',
            ]);

            $model = VehicleModel::create([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'vehicle_segment' => $request->vehicle_segment,
                'status' => ucfirst(strtolower($request->status)),
            ]);

            $model->load('brand');

            return response()->json([
                'status' => true,
                'message' => 'Vehicle model created successfully',
                'data' => $model,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('VehicleModelController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating vehicle model.',
            ], 500);
        }
    }

    /**
     * Get a single vehicle model
     */
    public function show($id)
    {
        try {
            $model = VehicleModel::with('brand')->find($id);

            if (!$model) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle model not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Vehicle model details',
                'data' => $model,
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleModelController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching vehicle model details.',
            ], 500);
        }
    }

    /**
     * Update an existing vehicle model
     */
    public function update(Request $request, $id)
    {
        try {
            $model = VehicleModel::find($id);

            if (!$model) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle model not found',
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'vehicle_segment' => 'required|string|in:2 Wheeler,4 Wheeler,2 wheeler,4 wheeler',
                'status' => 'required|string|in:Active,Inactive,active,inactive',
            ]);

            $model->update([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'vehicle_segment' => $request->vehicle_segment,
                'status' => ucfirst(strtolower($request->status)),
            ]);

            $model->load('brand');

            return response()->json([
                'status' => true,
                'message' => 'Vehicle model updated successfully',
                'data' => $model,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('VehicleModelController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating vehicle model.',
            ], 500);
        }
    }

    /**
     * Delete a vehicle model
     */
    public function destroy($id)
    {
        try {
            $model = VehicleModel::find($id);

            if (!$model) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle model not found',
                ], 404);
            }

            $model->delete();

            return response()->json([
                'status' => true,
                'message' => 'Vehicle model deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleModelController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting vehicle model.',
            ], 500);
        }
    }
}
