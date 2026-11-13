<?php

namespace App\Http\Controllers;

use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InsuranceCompanyController extends Controller
{
    /**
     * Ensure table exists gracefully
     */
    protected static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('insurance_companies')) {
                \Illuminate\Support\Facades\Schema::create('insurance_companies', function ($table) {
                    $table->id();
                    $table->string('name')->unique();
                    $table->string('code')->nullable();
                    $table->string('contact_person')->nullable();
                    $table->string('phone')->nullable();
                    $table->string('email')->nullable();
                    $table->text('notes')->nullable();
                    $table->enum('status', ['Active', 'Inactive'])->default('Active');
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Insurance companies table check: ' . $e->getMessage());
        }
    }

    /**
     * Get list of insurance companies
     */
    public function index(Request $request)
    {
        try {
            self::ensureTableExists();
            $query = InsuranceCompany::query();

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1 || $request->get('paginate') === 'false';

            if (!$isAll) {
                $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? 15);
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->latest()->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Insurance companies retrieved successfully',
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

            $companies = $query->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Insurance companies retrieved successfully',
                'data' => $companies,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $companies->count(),
                    'total' => $companies->count(),
                    'from' => $companies->count() > 0 ? 1 : 0,
                    'to' => $companies->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('InsuranceCompany index error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve insurance companies: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Active insurance companies list for dropdowns
     */
    public function activeList()
    {
        try {
            self::ensureTableExists();
            $companies = InsuranceCompany::active()->orderBy('name', 'asc')->get(['id', 'name', 'code', 'phone']);
            return response()->json([
                'status' => true,
                'data' => $companies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stats
     */
    public function stats()
    {
        try {
            self::ensureTableExists();
            $total = InsuranceCompany::count();
            $active = InsuranceCompany::whereIn('status', ['active', 'Active'])->count();
            $inactive = InsuranceCompany::whereIn('status', ['inactive', 'Inactive'])->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'total' => $total,
                    'active' => $active,
                    'inactive' => $inactive,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store new insurance company
     */
    public function store(Request $request)
    {
        self::ensureTableExists();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        try {
            $company = InsuranceCompany::create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Insurance company created successfully',
                'data' => $company,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create insurance company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show
     */
    public function show($id)
    {
        $company = InsuranceCompany::find($id);
        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['status' => true, 'data' => $company]);
    }

    /**
     * Update
     */
    public function update(Request $request, $id)
    {
        $company = InsuranceCompany::find($id);
        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        try {
            $company->update($validated);
            return response()->json([
                'status' => true,
                'message' => 'Insurance company updated successfully',
                'data' => $company,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Destroy
     */
    public function destroy($id)
    {
        $company = InsuranceCompany::find($id);
        if (!$company) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        try {
            $company->delete();
            return response()->json([
                'status' => true,
                'message' => 'Insurance company deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete: ' . $e->getMessage(),
            ], 500);
        }
    }
}

