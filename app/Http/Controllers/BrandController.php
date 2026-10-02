<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    /**
     * Get list of all brands
     */
    public function index(Request $request)
    {
        $query = Brand::query();

        // Optional search by name
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Optional filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Optional filter by vehicle type (e.g. "2 Wheeler" or "4 Wheeler")
        if ($request->filled('vehicle_type')) {
            $type = $request->vehicle_type;
            $query->where(function ($q) use ($type) {
                $q->whereJsonContains('vehicle_type', $type)
                  ->orWhere('vehicle_type', 'like', '%' . $type . '%');
            });
        }

        $brands = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Brands retrieved successfully',
            'data' => $brands,
        ]);
    }

    /**
     * Create a new brand
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        // Process vehicle_type (can be array or JSON string from FormData)
        $vehicleType = $request->vehicle_type;
        if (is_string($vehicleType)) {
            $decoded = json_decode($vehicleType, true);
            $vehicleType = is_array($decoded) ? $decoded : [$vehicleType];
        }
        if (empty($vehicleType)) {
            $vehicleType = ['4 Wheeler'];
        }

        // Process Logo upload if provided
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $file->move(public_path('uploads/brands'), $filename);
            $logoPath = '/uploads/brands/' . $filename;
        } elseif ($request->filled('logo') && is_string($request->logo)) {
            $logoPath = $request->logo;
        }

        $brand = Brand::create([
            'name' => $request->name,
            'vehicle_type' => $vehicleType,
            'logo' => $logoPath,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Brand created successfully',
            'data' => $brand,
        ], 201);
    }

    /**
     * Get a single brand
     */
    public function show($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Brand details',
            'data' => $brand,
        ]);
    }

    /**
     * Update an existing brand
     */
    public function update(Request $request, $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:Active,Inactive,active,inactive',
        ]);

        // Process vehicle_type
        $vehicleType = $request->vehicle_type;
        if (is_string($vehicleType)) {
            $decoded = json_decode($vehicleType, true);
            $vehicleType = is_array($decoded) ? $decoded : [$vehicleType];
        }
        if (empty($vehicleType)) {
            $vehicleType = $brand->vehicle_type;
        }

        // Process Logo
        $logoPath = $brand->logo;
        if ($request->hasFile('logo')) {
            // Delete old file if exists in uploads
            if ($brand->logo && file_exists(public_path($brand->logo))) {
                @unlink(public_path($brand->logo));
            }
            $file = $request->file('logo');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $file->move(public_path('uploads/brands'), $filename);
            $logoPath = '/uploads/brands/' . $filename;
        } elseif ($request->has('logo') && is_string($request->logo)) {
            $logoPath = $request->logo;
        }

        $brand->update([
            'name' => $request->name,
            'vehicle_type' => $vehicleType,
            'logo' => $logoPath,
            'status' => ucfirst(strtolower($request->status)),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand,
        ]);
    }

    /**
     * Delete a brand
     */
    public function destroy($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'status' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        if ($brand->logo && file_exists(public_path($brand->logo))) {
            @unlink(public_path($brand->logo));
        }

        $brand->delete();

        return response()->json([
            'status' => true,
            'message' => 'Brand deleted successfully',
        ]);
    }
}
