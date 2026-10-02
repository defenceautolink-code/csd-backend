<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of expense categories with search, status filters & summary stats
     */
    public function index(Request $request)
    {
        $query = ExpenseCategory::withCount('expenses')
            ->withSum('expenses as total_expenses_amount', 'amount')
            ->with('creator:id,name,role');

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status Filter (1 / 0 / active / inactive)
        if ($request->has('status') && $request->status !== '') {
            $status = in_array(strtolower($request->status), ['1', 'true', 'active']) ? 1 : 0;
            $query->where('status', $status);
        }

        // Pagination or Full List
        $perPage = (int) $request->get('per_page', 0);
        if ($perPage > 0 || $request->filled('page')) {
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->latest('id')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Expense categories retrieved successfully',
                'data' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $categories = $query->latest('id')->get();

        return response()->json([
            'status' => true,
            'message' => 'Expense categories retrieved successfully',
            'data' => $categories,
            'total' => $categories->count(),
        ]);
    }

    /**
     * Store a newly created expense category
     */
    public function store(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|in:0,1,true,false,active,inactive',
        ]);

        $status = 1;
        if (isset($validated['status'])) {
            $status = in_array(strtolower((string)$validated['status']), ['1', 'true', 'active']) ? 1 : 0;
        }

        $category = ExpenseCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $status,
            'created_by' => $user?->id,
        ]);

        $category->load('creator:id,name,role');

        return response()->json([
            'status' => true,
            'message' => 'Expense category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified expense category
     */
    public function show($id)
    {
        $category = ExpenseCategory::withCount('expenses')
            ->withSum('expenses as total_expenses_amount', 'amount')
            ->with(['creator:id,name,role', 'expenses' => function ($q) {
                $q->latest('expense_date')->limit(10);
            }])
            ->find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Expense category not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Expense category details retrieved successfully',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified expense category
     */
    public function update(Request $request, $id)
    {
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Expense category not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|in:0,1,true,false,active,inactive',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = in_array(strtolower((string)$validated['status']), ['1', 'true', 'active']) ? 1 : 0;
        }

        $category->update($validated);
        $category->load('creator:id,name,role');

        return response()->json([
            'status' => true,
            'message' => 'Expense category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * Toggle active/inactive status
     */
    public function toggleStatus($id)
    {
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Expense category not found',
            ], 404);
        }

        $category->status = $category->status == 1 ? 0 : 1;
        $category->save();

        return response()->json([
            'status' => true,
            'message' => 'Expense category status updated to ' . ($category->status ? 'Active' : 'Inactive'),
            'data' => $category,
        ]);
    }

    /**
     * Soft delete the specified expense category
     */
    public function destroy($id)
    {
        $category = ExpenseCategory::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Expense category not found',
            ], 404);
        }

        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Expense category deleted successfully',
        ]);
    }
}
