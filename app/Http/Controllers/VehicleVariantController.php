<?php

namespace App\Http\Controllers;

use App\Models\VehicleVariant;
use Illuminate\Http\Request;

class VehicleVariantController extends Controller
{
    /**
     * Get list of all vehicle variants with their associated brand and model
     */
    public function index(Request $request)
    {
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

        $variants = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Vehicle variants retrieved successfully',
            'data' => $variants,
        ]);
    }

    /**
     * Create a new vehicle variant
     */
    public function store(Request $request)
    {
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
    }

    /**
     * Get a single vehicle variant
     */
    public function show($id)
    {
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
    }

    /**
     * Update an existing vehicle variant
     */
    public function update(Request $request, $id)
    {
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
    }

    /**
     * Delete a vehicle variant
     */
    public function destroy($id)
    {
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
    }
}
