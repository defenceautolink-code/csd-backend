<?php

namespace App\Http\Controllers;

use App\Models\VehicleVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VehicleVariantController extends Controller
{
    /**
     * Get list of all vehicle variants with their associated brand and model
     */
    public function index(Request $request)
    {
        try {
            $query = VehicleVariant::with(['brand', 'model']);

            // Optional search by variant name or model name
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhereHas('model', function ($mq) use ($search) {
                          $mq->where('name', 'like', '%' . $search . '%');
                      })
                      ->orWhereHas('brand', function ($bq) use ($search) {
                          $bq->where('name', 'like', '%' . $search . '%');
                      });
                });
            }

            // Optional filter by brand
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Optional filter by model
            if ($request->filled('model_id')) {
                $query->where('model_id', $request->model_id);
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
                    'message' => 'Vehicle variants retrieved successfully',
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

            $variants = $query->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Vehicle variants retrieved successfully',
                'data' => $variants,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($variants),
                    'total' => count($variants),
                    'from' => count($variants) > 0 ? 1 : null,
                    'to' => count($variants),
                ],
                'total' => $variants->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching vehicle variants.',
            ], 500);
        }
    }

    /**
     * Create a new vehicle variant
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'model_id' => 'required|exists:vehicle_models,id',
                'price' => 'required|numeric|min:0',
                'status' => 'required|string|in:Active,Inactive,active,inactive',
            ]);

            $variant = VehicleVariant::create([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'model_id' => $request->model_id,
                'price' => $request->price,
                'status' => ucfirst(strtolower($request->status)),
            ]);

            $variant->load(['brand', 'model']);

            return response()->json([
                'status' => true,
                'message' => 'Vehicle variant created successfully',
                'data' => $variant,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating vehicle variant.',
            ], 500);
        }
    }

    /**
     * Get a single vehicle variant
     */
    public function show($id)
    {
        try {
            $variant = VehicleVariant::with(['brand', 'model'])->find($id);

            if (!$variant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle variant not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Vehicle variant details',
                'data' => $variant,
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching vehicle variant details.',
            ], 500);
        }
    }

    /**
     * Update an existing vehicle variant
     */
    public function update(Request $request, $id)
    {
        try {
            $variant = VehicleVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle variant not found',
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'brand_id' => 'required|exists:brands,id',
                'model_id' => 'required|exists:vehicle_models,id',
                'price' => 'required|numeric|min:0',
                'status' => 'required|string|in:Active,Inactive,active,inactive',
            ]);

            $variant->update([
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'model_id' => $request->model_id,
                'price' => $request->price,
                'status' => ucfirst(strtolower($request->status)),
            ]);

            $variant->load(['brand', 'model']);

            return response()->json([
                'status' => true,
                'message' => 'Vehicle variant updated successfully',
                'data' => $variant,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating vehicle variant.',
            ], 500);
        }
    }

    /**
     * Delete a vehicle variant
     */
    public function destroy($id)
    {
        try {
            $variant = VehicleVariant::find($id);

            if (!$variant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Vehicle variant not found',
                ], 404);
            }

            $variant->delete();

            return response()->json([
                'status' => true,
                'message' => 'Vehicle variant deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting vehicle variant.',
            ], 500);
        }
    }
}
