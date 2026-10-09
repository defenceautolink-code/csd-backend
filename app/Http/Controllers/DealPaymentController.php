<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DealPaymentController extends Controller
{
    /**
     * Display a listing of payment receipts with filters & search
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $query = DealPayment::with([
                'deal:id,deal_number,customer_name,customer_phone,model_variant,total_amount,net_amount,total_paid,balance_due,payment_status',
                'lead:id,name,phone,email',
                'recordedBy:id,name,role',
                'verifiedBy:id,name,role',
            ]);

            // Filter by Deal ID
            if ($request->filled('deal_id')) {
                $query->where('deal_id', $request->deal_id);
            }

            // Filter by Lead ID
            if ($request->filled('lead_id')) {
                $query->where('lead_id', $request->lead_id);
            }

            // Filter by Status (pending, cleared, bounced, rejected, refunded)
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by Payment Type (token_advance, down_payment, bank_finance, exchange_bonus, balance_payment, refund)
            if ($request->filled('payment_type')) {
                $query->where('payment_type', $request->payment_type);
            }

            // Filter by Payment Mode (upi, neft_rtgs, cheque, cash, card_pos, bank_disbursal)
            if ($request->filled('payment_mode')) {
                $query->where('payment_mode', $request->payment_mode);
            }

            // Date range filters
            if ($request->filled('from_date')) {
                $query->whereDate('payment_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('payment_date', '<=', $request->to_date);
            }

            // Search Filter (Receipt Number, Transaction reference, Bank name, or Customer name)
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('receipt_number', 'like', "%{$search}%")
                      ->orWhere('transaction_reference', 'like', "%{$search}%")
                      ->orWhere('bank_name', 'like', "%{$search}%")
                      ->orWhereHas('deal', function ($dq) use ($search) {
                          $dq->where('customer_name', 'like', "%{$search}%")
                             ->orWhere('customer_phone', 'like', "%{$search}%")
                             ->orWhere('deal_number', 'like', "%{$search}%");
                      });
                });
            }

            // Pagination or Full List
            $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1;

            if (!$isAll) {
                $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->latest('id')->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Payments retrieved successfully',
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

            $payments = $query->latest('id')->get();

            return response()->json([
                'status' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $payments->count(),
                    'total' => $payments->count(),
                    'from' => $payments->count() > 0 ? 1 : null,
                    'to' => $payments->count(),
                ],
                'total' => $payments->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching payments.',
            ], 500);
        }
    }

    /**
     * Get payment history and ledger for a specific Deal
     */
    public function getByDeal($deal_id)
    {
        try {
            $deal = Deal::find($deal_id);

            if (!$deal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Deal not found',
                ], 404);
            }

            $deal->recalculateFinancials();

            $payments = DealPayment::with([
                'recordedBy:id,name,role',
                'verifiedBy:id,name,role',
            ])
            ->where('deal_id', $deal_id)
            ->orderBy('payment_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

            return response()->json([
                'status' => true,
                'message' => 'Deal payments retrieved successfully',
                'data' => [
                    'deal_id' => $deal->id,
                    'deal_number' => $deal->deal_number,
                    'customer_name' => $deal->customer_name,
                    'customer_phone' => $deal->customer_phone,
                    'model_variant' => $deal->model_variant,
                    'financial_summary' => [
                        'total_amount' => $deal->total_amount,
                        'discount_amount' => $deal->discount_amount,
                        'net_amount' => $deal->net_amount,
                        'total_paid' => $deal->total_paid,
                        'balance_due' => $deal->balance_due,
                        'payment_percentage' => $deal->payment_percentage,
                        'payment_status' => $deal->payment_status,
                    ],
                    'payments' => $payments,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@getByDeal error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching deal payments.',
            ], 500);
        }
    }

    /**
     * Get pending payment clearances for Accountant queue
     */
    public function getPendingClearances(Request $request)
    {
        try {
            $payments = DealPayment::with([
                'deal:id,deal_number,customer_name,customer_phone,model_variant,net_amount,total_paid,balance_due',
                'recordedBy:id,name,role',
            ])
            ->where('status', 'pending')
            ->latest('id')
            ->get();

            return response()->json([
                'status' => true,
                'message' => 'Pending payment clearances retrieved successfully',
                'data' => $payments,
                'total_pending' => $payments->count(),
                'total_pending_amount' => (float) $payments->sum('amount'),
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@getPendingClearances error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching pending clearances.',
            ], 500);
        }
    }

    /**
     * Record a new payment transaction against a Deal
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            // Merge common alias field names if primary field names are missing
            if (!$request->has('transaction_reference')) {
                $ref = $request->input('reference') ?? $request->input('utr') ?? $request->input('transaction_no') ?? $request->input('reference_no') ?? $request->input('reference_number');
                if ($ref !== null) {
                    $request->merge(['transaction_reference' => $ref]);
                }
            }
            if (!$request->has('bank_name')) {
                $bank = $request->input('bank');
                if ($bank !== null) {
                    $request->merge(['bank_name' => $bank]);
                }
            }

            $validated = $request->validate([
                'deal_id' => 'required|exists:deals,id',
                'amount' => 'required|numeric|min:1',
                'payment_type' => 'required|string|in:token_advance,down_payment,bank_finance,exchange_bonus,part_payment,balance_payment,accessory_payment,refund',
                'payment_mode' => 'required|string|in:upi,neft_rtgs,cheque,cash,card_pos,bank_disbursal',
                'payment_date' => 'required|date',
                'transaction_reference' => 'nullable|string|max:150',
                'reference' => 'nullable|string|max:150',
                'utr' => 'nullable|string|max:150',
                'transaction_no' => 'nullable|string|max:150',
                'bank_name' => 'nullable|string|max:100',
                'bank' => 'nullable|string|max:100',
                'cheque_date' => 'nullable|date',
                'payment_proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
                'received_by' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'auto_clear' => 'nullable|boolean', // If accountant or cashier directly creates cleared payment
            ]);

            $deal = Deal::findOrFail($validated['deal_id']);

            // Handle payment proof upload if provided
            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $filename = 'payment_proof_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $proofPath = $file->storeAs('payment_proofs', $filename, 'public');
            }

            // Auto-clear logic: If user is Accountant/Admin or requested auto_clear with authority
            $isAccountantOrAdmin = $user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['admin', 'super_admin', 'accountant', 'accounts_manager', 'cashier']);
            $shouldClear = (!empty($validated['auto_clear']) && $isAccountantOrAdmin) || ($validated['payment_mode'] === 'cash' && $isAccountantOrAdmin);

            $payment = DealPayment::create([
                'receipt_number' => DealPayment::generateReceiptNumber(),
                'deal_id' => $deal->id,
                'lead_id' => $deal->lead_id,
                'payment_type' => $validated['payment_type'],
                'amount' => (float) $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_mode' => $validated['payment_mode'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'cheque_date' => $validated['cheque_date'] ?? null,
                'cheque_status' => $validated['payment_mode'] === 'cheque' ? 'pending_clearance' : null,
                'payment_proof_path' => $proofPath,
                'received_by' => $validated['received_by'] ?? null,
                'status' => $shouldClear ? 'cleared' : 'pending',
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $user?->id,
                'recorded_by_name' => $user?->name,
                'verified_by' => $shouldClear ? $user?->id : null,
                'verified_by_name' => $shouldClear ? $user?->name : null,
                'verified_at' => $shouldClear ? now() : null,
            ]);

            $deal->refresh();
            $deal->load(['lead', 'quotation', 'brand', 'salesExecutive', 'payments']);
            $payment->load(['deal', 'lead', 'recordedBy', 'verifiedBy']);

            return response()->json([
                'status' => true,
                'message' => "Payment receipt #{$payment->receipt_number} recorded successfully",
                'data' => [
                    'payment' => $payment,
                    'deal_financial_summary' => [
                        'deal_id' => $deal->id,
                        'deal_number' => $deal->deal_number,
                        'net_amount' => $deal->net_amount,
                        'total_paid' => $deal->total_paid,
                        'balance_due' => $deal->balance_due,
                        'payment_status' => $deal->payment_status,
                    ],
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while recording payment.',
            ], 500);
        }
    }

    /**
     * Display a specific payment receipt
     */
    public function show($id)
    {
        try {
            $payment = DealPayment::with([
                'deal.lead',
                'deal.brand',
                'deal.salesExecutive',
                'recordedBy',
                'verifiedBy',
            ])->find($id);

            if (!$payment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment receipt not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Payment receipt details retrieved successfully',
                'data' => $payment,
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching payment details.',
            ], 500);
        }
    }

    /**
     * Update payment details (if not yet locked/cleared or updated by Accountant/Admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $payment = DealPayment::find($id);

            if (!$payment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment receipt not found',
                ], 404);
            }

            // Merge common alias field names if primary field names are missing
            if (!$request->has('transaction_reference')) {
                $ref = $request->input('reference') ?? $request->input('utr') ?? $request->input('transaction_no') ?? $request->input('reference_no') ?? $request->input('reference_number');
                if ($ref !== null) {
                    $request->merge(['transaction_reference' => $ref]);
                }
            }
            if (!$request->has('bank_name')) {
                $bank = $request->input('bank');
                if ($bank !== null) {
                    $request->merge(['bank_name' => $bank]);
                }
            }

            $validated = $request->validate([
                'amount' => 'sometimes|numeric|min:1',
                'payment_type' => 'sometimes|string|in:token_advance,down_payment,bank_finance,exchange_bonus,part_payment,balance_payment,accessory_payment,refund',
                'payment_mode' => 'sometimes|string|in:upi,neft_rtgs,cheque,cash,card_pos,bank_disbursal',
                'payment_date' => 'sometimes|date',
                'transaction_reference' => 'nullable|string|max:150',
                'reference' => 'nullable|string|max:150',
                'utr' => 'nullable|string|max:150',
                'transaction_no' => 'nullable|string|max:150',
                'bank_name' => 'nullable|string|max:100',
                'bank' => 'nullable|string|max:100',
                'cheque_date' => 'nullable|date',
                'received_by' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            $payment->update($validated);

            if ($payment->deal) {
                $payment->deal->recalculateFinancials();
                $payment->deal->refresh();
            }

            $payment->load(['deal', 'lead', 'recordedBy', 'verifiedBy']);

            return response()->json([
                'status' => true,
                'message' => 'Payment receipt updated successfully',
                'data' => $payment,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating payment.',
            ], 500);
        }
    }

    /**
     * Accountant / Cashier Verification endpoint (Clear, Reject, or Bounce payment)
     */
    public function verify(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $payment = DealPayment::find($id);

            if (!$payment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment receipt not found',
                ], 404);
            }

            $validated = $request->validate([
                'action' => 'required|string|in:clear,reject,bounce',
                'rejection_reason' => 'required_if:action,reject|nullable|string|max:500',
                'notes' => 'nullable|string',
            ]);

            $action = $validated['action'];

            if ($action === 'clear') {
                $payment->status = 'cleared';
                $payment->verified_by = $user?->id;
                $payment->verified_by_name = $user?->name ?? 'Accountant';
                $payment->verified_at = now();
                $payment->rejection_reason = null;
                if ($payment->payment_mode === 'cheque') {
                    $payment->cheque_status = 'cleared';
                }
                $message = "Payment receipt #{$payment->receipt_number} of " . number_format($payment->amount, 2) . " has been verified & cleared!";
            } elseif ($action === 'reject') {
                $payment->status = 'rejected';
                $payment->rejection_reason = $validated['rejection_reason'] ?? 'Payment verification rejected by accounts.';
                $payment->verified_by = $user?->id;
                $payment->verified_by_name = $user?->name ?? 'Accountant';
                $payment->verified_at = now();
                $message = "Payment receipt #{$payment->receipt_number} has been rejected.";
            } elseif ($action === 'bounce') {
                $payment->status = 'bounced';
                $payment->cheque_status = 'bounced';
                $payment->rejection_reason = $validated['rejection_reason'] ?? 'Cheque / transaction returned or bounced.';
                $payment->verified_by = $user?->id;
                $payment->verified_by_name = $user?->name ?? 'Accountant';
                $payment->verified_at = now();
                $message = "Payment receipt #{$payment->receipt_number} marked as bounced.";
            }

            if (!empty($validated['notes'])) {
                $payment->notes = trim(($payment->notes ? $payment->notes . "\n" : "") . "Verification Note: " . $validated['notes']);
            }

            $payment->save();

            if ($payment->deal) {
                $payment->deal->recalculateFinancials();
                $payment->deal->refresh();
            }

            $payment->load(['deal', 'lead', 'recordedBy', 'verifiedBy']);

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'payment' => $payment,
                    'deal_financial_summary' => [
                        'deal_id' => $payment->deal->id,
                        'deal_number' => $payment->deal->deal_number,
                        'net_amount' => $payment->deal->net_amount,
                        'total_paid' => $payment->deal->total_paid,
                        'balance_due' => $payment->deal->balance_due,
                        'payment_status' => $payment->deal->payment_status,
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@verify error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while verifying payment.',
            ], 500);
        }
    }

    /**
     * Remove / Void the specified payment receipt
     */
    public function destroy($id)
    {
        try {
            $payment = DealPayment::find($id);

            if (!$payment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment receipt not found',
                ], 404);
            }

            $deal = $payment->deal;
            $receiptNumber = $payment->receipt_number;

            $payment->delete();

            if ($deal) {
                $deal->recalculateFinancials();
            }

            return response()->json([
                'status' => true,
                'message' => "Payment receipt #{$receiptNumber} deleted successfully",
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting payment.',
            ], 500);
        }
    }

    /**
     * Payment collection analytics & summary KPIs
     */
    public function stats(Request $request)
    {
        try {
            $today = now()->format('Y-m-d');
            $thisMonthStart = now()->startOfMonth()->format('Y-m-d');

            $totalClearedOverall = (float) DealPayment::where('status', 'cleared')->sum('amount');
            $clearedToday = (float) DealPayment::where('status', 'cleared')->whereDate('payment_date', $today)->sum('amount');
            $clearedThisMonth = (float) DealPayment::where('status', 'cleared')->whereDate('payment_date', '>=', $thisMonthStart)->sum('amount');

            $pendingClearancesCount = DealPayment::where('status', 'pending')->count();
            $pendingClearancesAmount = (float) DealPayment::where('status', 'pending')->sum('amount');

            // Payment mode breakdown
            $modeBreakdown = DealPayment::where('status', 'cleared')
                ->select('payment_mode', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('payment_mode')
                ->get();

            // Payment type breakdown
            $typeBreakdown = DealPayment::where('status', 'cleared')
                ->select('payment_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                ->groupBy('payment_type')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Payment collection metrics retrieved successfully',
                'data' => [
                    'total_cleared_overall' => $totalClearedOverall,
                    'cleared_today' => $clearedToday,
                    'cleared_this_month' => $clearedThisMonth,
                    'pending_clearances_count' => $pendingClearancesCount,
                    'pending_clearances_amount' => $pendingClearancesAmount,
                    'mode_breakdown' => $modeBreakdown,
                    'type_breakdown' => $typeBreakdown,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DealPaymentController@stats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching payment statistics.',
            ], 500);
        }
    }
}
