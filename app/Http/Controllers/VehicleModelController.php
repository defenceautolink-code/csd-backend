<?php

namespace App\Http\Controllers;

use App\Models\VehicleModel;
use Illuminate\Http\Request;

class VehicleModelController extends Controller
{
    /**
     * Get list of all vehicle models with their associated brand
     */
    public function index(Request $request)
    {
        $query = VehicleModel::with('brand');

        // Optional search by model name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Optional filter by brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Optional filter by vehicle segment (2 Wheeler / 4 Wheeler)
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
    }

    /**
     * Create a new vehicle model
     */
    public function store(Request $request)
    {
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
    }

    /**
     * Get a single vehicle model
     */
    public function show($id)
    {
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
    }

    /**
     * Update an existing vehicle model
     */
    public function update(Request $request, $id)
    {
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
    }

    /**
     * Delete a vehicle model
     */
    public function destroy($id)
    {
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
    }
}
