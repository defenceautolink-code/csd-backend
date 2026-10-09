<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with comprehensive filters and search
     */
    public function index(Request $request)
    {
        try {
            $query = Expense::with([
                'category:id,name,status',
                'creator:id,name,role',
            ]);

            // Filter by Category
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Filter by Payment Method (Cash, Bank Transfer, UPI, Cheque, Card, etc.)
            if ($request->filled('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filter by Status (Paid, Pending, Approved, Cancelled)
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by Date Range
            if ($request->filled('from_date')) {
                $query->whereDate('expense_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('expense_date', '<=', $request->to_date);
            }

            // Search Filter across Title, Description, Reference No, and Category Name
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('reference_no', 'like', "%{$search}%")
                      ->orWhereHas('category', function ($catQuery) use ($search) {
                          $catQuery->where('name', 'like', "%{$search}%");
                      });
                });
            }

            // Pagination or Full List
            $perPage = (int) $request->get('per_page', 0);
            if ($perPage > 0 || $request->filled('page')) {
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->latest('expense_date')->latest('id')->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Expenses retrieved successfully',
                    'data' => $paginated->items(),
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ]);
            }

            $expenses = $query->latest('expense_date')->latest('id')->get();

            return response()->json([
                'status' => true,
                'message' => 'Expenses retrieved successfully',
                'data' => $expenses,
                'total' => $expenses->count(),
                'total_amount' => (float) $expenses->sum('amount'),
            ]);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching expenses.',
            ], 500);
        }
    }

    /**
     * Store a newly created expense
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $validated = $request->validate([
                'expense_date' => 'required|date',
                'category_id' => 'required|exists:expense_categories,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string|max:50',
                'reference_no' => 'nullable|string|max:100',
                'attachment' => 'nullable',
                'status' => 'nullable|string|max:20',
            ]);

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'expense_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $attachmentPath = $file->storeAs('expenses', $filename, 'public');
            } elseif ($request->filled('attachment') && is_string($request->attachment)) {
                $attachmentPath = $request->attachment;
            }

            $expense = Expense::create([
                'expense_date' => $validated['expense_date'],
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'amount' => (float) $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'attachment' => $attachmentPath,
                'status' => $validated['status'] ?? 'Paid',
                'created_by' => $user?->id,
            ]);

            $expense->load(['category:id,name,status', 'creator:id,name,role']);

            return response()->json([
                'status' => true,
                'message' => 'Expense recorded successfully',
                'data' => $expense,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating expense.',
            ], 500);
        }
    }

    /**
     * Display the specified expense
     */
    public function show($id)
    {
        try {
            $expense = Expense::with([
                'category',
                'creator:id,name,role',
            ])->find($id);

            if (!$expense) {
                return response()->json([
                    'status' => false,
                    'message' => 'Expense not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Expense details retrieved successfully',
                'data' => $expense,
            ]);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching expense details.',
            ], 500);
        }
    }

    /**
     * Update the specified expense
     */
    public function update(Request $request, $id)
    {
        try {
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'status' => false,
                    'message' => 'Expense not found',
                ], 404);
            }

            $validated = $request->validate([
                'expense_date' => 'sometimes|required|date',
                'category_id' => 'sometimes|required|exists:expense_categories,id',
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'amount' => 'sometimes|required|numeric|min:0.01',
                'payment_method' => 'sometimes|required|string|max:50',
                'reference_no' => 'nullable|string|max:100',
                'attachment' => 'nullable',
                'status' => 'nullable|string|max:20',
            ]);

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'expense_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $validated['attachment'] = $file->storeAs('expenses', $filename, 'public');
            }

            $expense->update($validated);
            $expense->load(['category:id,name,status', 'creator:id,name,role']);

            return response()->json([
                'status' => true,
                'message' => 'Expense updated successfully',
                'data' => $expense,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating expense.',
            ], 500);
        }
    }

    /**
     * Soft delete the specified expense
     */
    public function destroy($id)
    {
        try {
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'status' => false,
                    'message' => 'Expense not found',
                ], 404);
            }

            $expense->delete();

            return response()->json([
                'status' => true,
                'message' => 'Expense deleted successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting expense.',
            ], 500);
        }
    }

    /**
     * Aggregate expense metrics and analytical breakdowns
     */
    public function stats(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $thisMonthStart = now()->startOfMonth()->format('Y-m-d');

            $totalExpensesOverall = (float) Expense::sum('amount');
            $totalExpensesToday = (float) Expense::whereDate('expense_date', $today)->sum('amount');
            $totalExpensesThisMonth = (float) Expense::whereDate('expense_date', '>=', $thisMonthStart)->sum('amount');
            $totalCount = Expense::count();

            // Breakdown by Category
            $categoryBreakdown = Expense::join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
                ->select(
                    'expense_categories.id as category_id',
                    'expense_categories.name as category_name',
                    DB::raw('count(expenses.id) as count'),
                    DB::raw('sum(expenses.amount) as total_amount')
                )
                ->whereNull('expenses.deleted_at')
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->orderByDesc('total_amount')
                ->get();

            // Breakdown by Payment Method
            $paymentMethodBreakdown = Expense::select(
                    'payment_method',
                    DB::raw('count(*) as count'),
                    DB::raw('sum(amount) as total_amount')
                )
                ->groupBy('payment_method')
                ->orderByDesc('total_amount')
                ->get();

            // Breakdown by Status
            $statusBreakdown = Expense::select(
                    'status',
                    DB::raw('count(*) as count'),
                    DB::raw('sum(amount) as total_amount')
                )
                ->groupBy('status')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Expense analytics retrieved successfully',
                'data' => [
                    'total_expenses_overall' => $totalExpensesOverall,
                    'total_expenses_today' => $totalExpensesToday,
                    'total_expenses_this_month' => $totalExpensesThisMonth,
                    'total_count' => $totalCount,
                    'category_breakdown' => $categoryBreakdown,
                    'payment_method_breakdown' => $paymentMethodBreakdown,
                    'status_breakdown' => $statusBreakdown,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('ExpenseController@stats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching expense statistics.',
            ], 500);
        }
    }
}
