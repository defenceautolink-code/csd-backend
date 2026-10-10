<?php

namespace App\Http\Controllers;

use App\Models\VehiclePriceLog;
use App\Models\VehicleVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VehicleVariantController extends Controller
{
    /**
     * Get list of all vehicle variants with their associated brand and model
     * Supports:
     * - Filtering by brand_id, model_id, status, search (variant, model, brand name)
     * - Pagination using page and per_page (returns previous_price for each variant)
     */
    public function index(Request $request)
    {
        try {
            $query = VehicleVariant::with(['brand', 'model']);

            // Optional search by variant name, model name, or brand name
            if ($request->filled('search')) {
                $search = trim($request->search);
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
     * When created for the first time, previous_price is set to NULL.
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
                'previous_price' => null, // NULL when created for the first time
                'ex_showroom_price' => $request->price,
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
     * Whenever the Ex-Showroom price is updated, store the old price in previous_price.
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

            $oldPrice = (float) ($variant->price > 0 ? $variant->price : $variant->ex_showroom_price);
            $newPrice = (float) $request->price;

            $updateData = [
                'name' => $request->name,
                'brand_id' => $request->brand_id,
                'model_id' => $request->model_id,
                'price' => $newPrice,
                'ex_showroom_price' => $newPrice,
                'status' => ucfirst(strtolower($request->status)),
            ];

            // Update previous_price if price has changed
            if ($oldPrice > 0 && (float) $oldPrice !== (float) $newPrice) {
                $updateData['previous_price'] = $oldPrice;
            }

            $variant->update($updateData);

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
     * POST /api/variants/bulk-update-prices
     * Bulk update Ex-Showroom prices for multiple variants.
     * Stores each variant's old Ex-Showroom price in previous_price before updating to revised_price,
     * and records audit entries in vehicle_price_logs.
     */
    public function bulkUpdatePrices(Request $request)
    {
        try {
            $request->validate([
                'variants' => 'required|array|min:1',
                'variants.*.id' => 'required|exists:vehicle_variants,id',
                'variants.*.revised_price' => 'required|numeric|min:0',
            ]);

            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
            $updatedByName = $user ? trim("{$user->first_name} {$user->last_name}") : 'System Admin';

            $updatedVariants = [];

            foreach ($request->variants as $item) {
                $variant = VehicleVariant::with(['brand', 'model'])->find($item['id']);
                if (!$variant) {
                    continue;
                }

                $oldExShowroom = (float) ($variant->price > 0 ? $variant->price : ($variant->ex_showroom_price > 0 ? $variant->ex_showroom_price : 0.00));
                $revisedExShowroom = (float) $item['revised_price'];

                // 1. Store old Ex-Showroom price in previous_price column
                $variant->previous_price = $oldExShowroom > 0 ? $oldExShowroom : null;
                $variant->price = $revisedExShowroom;
                $variant->ex_showroom_price = $revisedExShowroom;

                // Dynamic On-Road calculations
                $rtoTax = (float) ($variant->rto_road_tax > 0 ? $variant->rto_road_tax : round($revisedExShowroom * 0.10, 2));
                $insurance = (float) ($variant->insurance > 0 ? $variant->insurance : 68000.00);
                $fastagLogistics = (float) ($variant->fastag_logistics > 0 ? $variant->fastag_logistics : 2500.00);

                $previousOnRoad = round($oldExShowroom + $rtoTax + $insurance + $fastagLogistics, 2);
                $revisedOnRoad = round($revisedExShowroom + $rtoTax + $insurance + $fastagLogistics, 2);
                $netDifference = round($revisedExShowroom - $oldExShowroom, 2);

                $variant->on_road_price = $revisedOnRoad;
                $variant->save();

                // 2. Audit Log Entry in vehicle_price_logs table
                $modelVariantName = $variant->model ? "{$variant->name} ({$variant->model->name})" : $variant->name;
                $brandName = $variant->brand ? $variant->brand->name : ($variant->model?->brand_name ?? 'Maruti Suzuki');

                VehiclePriceLog::create([
                    'variant_id' => $variant->id,
                    'model_id' => $variant->model_id,
                    'brand_id' => $variant->brand_id,
                    'model_variant_name' => $modelVariantName,
                    'brand_name' => $brandName,
                    'previous_ex_showroom' => $oldExShowroom,
                    'revised_ex_showroom' => $revisedExShowroom,
                    'net_difference' => $netDifference,
                    'rto_road_tax' => $rtoTax,
                    'insurance' => $insurance,
                    'fastag_logistics' => $fastagLogistics,
                    'previous_on_road' => $previousOnRoad,
                    'revised_on_road' => $revisedOnRoad,
                    'updated_by_id' => $user?->id,
                    'updated_by_name' => $updatedByName,
                    'revision_date' => Carbon::now()->format('Y-m-d'),
                    'status' => 'Active',
                ]);

                $updatedVariants[] = [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'previous_price' => $variant->previous_price,
                    'price' => $variant->price,
                    'ex_showroom_price' => $variant->ex_showroom_price,
                    'on_road_price' => $variant->on_road_price,
                ];
            }

            return response()->json([
                'status' => true,
                'message' => count($updatedVariants) . ' variant prices updated successfully',
                'data' => $updatedVariants,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('VehicleVariantController@bulkUpdatePrices error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while bulk updating vehicle prices.',
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
