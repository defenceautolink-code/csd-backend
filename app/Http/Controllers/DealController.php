<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealPayment;
use App\Models\Insurance;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DealController extends Controller
{
    /**
     * Display a listing of deals with comprehensive filters and role scoping
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $query = Deal::with([
                'lead:id,name,phone,email,city,state,brand_name,model_variant,status_name,assigned_to,assigned_user_name',
                'quotation:id,quotation_number,grand_total,status',
                'brand:id,name',
                'salesExecutive:id,name,email,role',
                'payments' => function ($q) {
                    $q->select('id', 'deal_id', 'receipt_number', 'payment_type', 'amount', 'payment_date', 'payment_mode', 'transaction_reference', 'bank_name', 'cheque_date', 'cheque_status', 'received_by', 'status', 'notes', 'recorded_by', 'recorded_by_name', 'verified_by', 'verified_by_name', 'verified_at')
                      ->orderBy('payment_date', 'asc');
                },
            ]);

            // Sales Executive role scoping
            if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
                $query->where(function ($q) use ($user) {
                    $q->where('sales_executive_id', $user->id)
                      ->orWhere('sales_executive_name', 'like', '%' . $user->name . '%')
                      ->orWhereHas('lead', function ($lq) use ($user) {
                          $lq->where('assigned_to', $user->id)
                             ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
                      });
                });
            }

            // Search Filter
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('deal_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%")
                      ->orWhere('model_variant', 'like', "%{$search}%")
                      ->orWhere('vin_chassis_number', 'like', "%{$search}%")
                      ->orWhere('brand_name', 'like', "%{$search}%");
                });
            }

            // Filter by Deal Status
            if ($request->filled('deal_status')) {
                $query->where('deal_status', $request->deal_status);
            }

            // Filter by Payment Status (unpaid, partially_paid, paid, refunded)
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Filter by Brand
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // Filter by Sales Executive
            if ($request->filled('sales_executive_id')) {
                $query->where('sales_executive_id', $request->sales_executive_id);
            }

            // Date range filters
            $startDate = $request->input('start_date') ?? $request->input('startDate') ?? $request->input('from_date') ?? $request->input('date_from');
            $endDate = $request->input('end_date') ?? $request->input('endDate') ?? $request->input('to_date') ?? $request->input('date_to');
            $singleDate = $request->input('date');

            $dateField = $request->input('date_field', 'booking_date');
            if (!in_array($dateField, ['booking_date', 'created_at', 'expected_delivery_date', 'actual_delivery_date'])) {
                $dateField = 'booking_date';
            }

            if (!empty($singleDate)) {
                $query->whereDate($dateField, $singleDate);
            } else {
                if (!empty($startDate)) {
                    $query->whereDate($dateField, '>=', $startDate);
                }
                if (!empty($endDate)) {
                    $query->whereDate($dateField, '<=', $endDate);
                }
            }

            // Pagination or Full List
            $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1;

            if (!$isAll) {
                $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
                $perPage = $perPage > 0 ? $perPage : 15;
                $paginated = $query->latest('id')->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Deals retrieved successfully',
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

            $deals = $query->latest('id')->get();

            return response()->json([
                'status' => true,
                'message' => 'Deals retrieved successfully',
                'data' => $deals,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => count($deals),
                    'total' => count($deals),
                    'from' => count($deals) > 0 ? 1 : null,
                    'to' => count($deals),
                ],
                'total' => $deals->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching deals.',
            ], 500);
        }
    }

    /**
     * Get pre-filled deal structure from a Lead (and optional Quotation)
     */
    public function getLeadForDeal($lead_id)
    {
        try {
            $lead = Lead::with(['brand', 'model', 'variant', 'source', 'status', 'assignedUser', 'latestQuotation.items'])->find($lead_id);

            if (!$lead) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lead not found',
                ], 404);
            }

            $latestQuotation = $lead->latestQuotation;
            $totalAmount = $latestQuotation ? (float) $latestQuotation->grand_total : 0.0;

            return response()->json([
                'status' => true,
                'message' => 'Lead deal template retrieved successfully',
                'data' => [
                    'lead_id' => $lead->id,
                    'quotation_id' => $latestQuotation?->id,
                    'quotation_number' => $latestQuotation?->quotation_number,
                    'customer_name' => $lead->name,
                    'customer_email' => $lead->email,
                    'customer_phone' => $lead->phone,
                    'customer_city' => $lead->city,
                    'customer_state' => $lead->state,
                    'customer_address' => trim(implode(', ', array_filter([$lead->city, $lead->state]))),
                    'vehicle_segment' => $lead->vehicle_segment ?? '4 Wheeler',
                    'brand_id' => $lead->brand_id,
                    'brand_name' => $lead->brand_name,
                    'model_variant' => $lead->model_variant,
                    'color' => '',
                    'total_amount' => $totalAmount,
                    'discount_amount' => 0.0,
                    'net_amount' => $totalAmount,
                    'booking_date' => now()->format('Y-m-d'),
                    'expected_delivery_date' => now()->addDays(14)->format('Y-m-d'),
                    'sales_executive_id' => $lead->assigned_to,
                    'sales_executive_name' => $lead->assigned_user_name,
                    'payment_terms' => $latestQuotation?->payment_terms ?? 'Booking token advance on order, balance payment before vehicle registration and delivery.',
                    'delivery_terms' => $latestQuotation?->delivery_terms ?? 'Subject to vehicle allocation, PDI clearance, and full payment receipt.',
                    'notes' => "Converted from Lead #{$lead->id} (" . ($lead->name) . ")",
                    'insurance' => 'Yes',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@getLeadForDeal error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while getting lead details.',
            ], 500);
        }
    }

    /**
     * Convert a Lead into a Deal (with optional Quotation and initial token advance payment)
     */
    public function convertLead(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $validated = $request->validate([
                'lead_id' => 'required|exists:leads,id',
                'quotation_id' => 'nullable|exists:quotations,id',
                'total_amount' => 'required|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'color' => 'nullable|string|max:100',
                'vin_chassis_number' => 'nullable|string|max:100',
                'engine_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'booking_date' => 'nullable|date',
                'expected_delivery_date' => 'nullable|date',
                'deal_status' => 'nullable|string|max:50',
                'payment_terms' => 'nullable|string',
                'delivery_terms' => 'nullable|string',
                'notes' => 'nullable|string',
                'sales_executive_id' => 'nullable|exists:users,id',
                'sales_executive_name' => 'nullable|string|max:150',
                'received_by' => 'nullable|string|max:255',
                'transaction_reference' => 'nullable|string|max:150',
                'reference' => 'nullable|string|max:150',
                'utr' => 'nullable|string|max:150',
                'transaction_no' => 'nullable|string|max:150',
                'reference_no' => 'nullable|string|max:150',
                'bank_name' => 'nullable|string|max:100',
                'bank' => 'nullable|string|max:100',

                // Optional Customer details overrides
                'customer_name' => 'nullable|string|max:255',
                'customer_email' => 'nullable|email|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_city' => 'nullable|string|max:100',
                'customer_state' => 'nullable|string|max:100',
                'customer_address' => 'nullable|string',

                // Optional Vehicle & Brand details
                'vehicle_segment' => 'nullable|string|max:50',
                'brand_id' => 'nullable',
                'brand_name' => 'nullable|string|max:100',
                'model_variant' => 'nullable|string|max:255',

                // Optional Insurance flag & details ('Yes' to create insurance record)
                'insurance' => 'nullable',
                'insurance_premium' => 'nullable|numeric|min:0',
                'premium_amount' => 'nullable|numeric|min:0',
                'insurance_amount' => 'nullable|numeric|min:0',
                'idv_amount' => 'nullable|numeric|min:0',
                'insurance_notes' => 'nullable|string',

                // Optional Initial Payment details
                'initial_payment' => 'nullable|array',
                'initial_payment.amount' => 'nullable|numeric|min:1',
                'initial_payment.payment_type' => 'nullable|string',
                'initial_payment.payment_mode' => 'nullable|string',
                'initial_payment.payment_date' => 'nullable|date',
                'initial_payment.transaction_reference' => 'nullable|string|max:150',
                'initial_payment.reference' => 'nullable|string|max:150',
                'initial_payment.utr' => 'nullable|string|max:150',
                'initial_payment.transaction_no' => 'nullable|string|max:150',
                'initial_payment.reference_no' => 'nullable|string|max:150',
                'initial_payment.reference_number' => 'nullable|string|max:150',
                'initial_payment.bank_name' => 'nullable|string|max:100',
                'initial_payment.bank' => 'nullable|string|max:100',
                'initial_payment.received_by' => 'nullable|string|max:255',
                'initial_payment.notes' => 'nullable|string',
                'initial_payment.auto_clear' => 'nullable|boolean',
            ]);

            return DB::transaction(function () use ($validated, $user, $request) {
                $lead = Lead::findOrFail($validated['lead_id']);

                $totalAmount = (float) $validated['total_amount'];
                $discountAmount = (float) ($validated['discount_amount'] ?? 0);
                $netAmount = max(0.0, $totalAmount - $discountAmount);

                // Resolve Brand ID safely (validate against brands table to prevent 1452 foreign key error)
                $brandId = $validated['brand_id'] ?? $request->input('brand_id') ?? $lead->brand_id;
                $brandName = $validated['brand_name'] ?? $request->input('brand_name') ?? $lead->brand_name;

                if (!empty($brandId)) {
                    $brandExists = DB::table('brands')->where('id', $brandId)->exists();
                    if (!$brandExists) {
                        // If provided brand_id does not exist, try finding brand by name or set to null
                        if (!empty($brandName)) {
                            $foundBrand = DB::table('brands')->where('name', $brandName)->first();
                            $brandId = $foundBrand?->id;
                        } else {
                            $brandId = null;
                        }
                    }
                } elseif (!empty($brandName)) {
                    $foundBrand = DB::table('brands')->where('name', $brandName)->first();
                    $brandId = $foundBrand?->id;
                } else {
                    $brandId = null;
                }

                // Resolve Quotation ID safely
                $quotationId = $validated['quotation_id'] ?? $request->input('quotation_id') ?? null;
                if ($quotationId && !DB::table('quotations')->where('id', $quotationId)->exists()) {
                    $quotationId = null;
                }

                // Resolve Sales Executive ID safely
                $salesExecId = $validated['sales_executive_id'] ?? $request->input('sales_executive_id') ?? $lead->assigned_to ?? $user?->id;
                if ($salesExecId && !DB::table('users')->where('id', $salesExecId)->exists()) {
                    $salesExecId = ($user?->id && DB::table('users')->where('id', $user->id)->exists()) ? $user->id : null;
                }
                $salesExecName = $validated['sales_executive_name'] ?? $request->input('sales_executive_name') ?? $lead->assigned_user_name ?? $user?->name;

                // Resolve Creator ID safely
                $creatorId = $user?->id;
                if ($creatorId && !DB::table('users')->where('id', $creatorId)->exists()) {
                    $creatorId = null;
                }

                $customerName = $validated['customer_name'] ?? $request->input('customer_name') ?? $lead->name;
                $customerEmail = $validated['customer_email'] ?? $request->input('customer_email') ?? $lead->email;
                $customerPhone = $validated['customer_phone'] ?? $request->input('customer_phone') ?? $lead->phone;
                $customerCity = $validated['customer_city'] ?? $request->input('customer_city') ?? $lead->city;
                $customerState = $validated['customer_state'] ?? $request->input('customer_state') ?? $lead->state;
                $customerAddress = $validated['customer_address'] ?? $request->input('customer_address') ?? trim(implode(', ', array_filter([$customerCity, $customerState])));
                $vehicleSegment = $validated['vehicle_segment'] ?? $request->input('vehicle_segment') ?? $lead->vehicle_segment ?? '4 Wheeler';
                $modelVariant = $validated['model_variant'] ?? $request->input('model_variant') ?? $lead->model_variant;

                // Create Deal record
                $deal = Deal::create([
                    'deal_number' => Deal::generateDealNumber(),
                    'lead_id' => $lead->id,
                    'quotation_id' => $quotationId,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'customer_city' => $customerCity,
                    'customer_state' => $customerState,
                    'customer_address' => $customerAddress,
                    'vehicle_segment' => $vehicleSegment,
                    'brand_id' => $brandId,
                    'brand_name' => $brandName,
                    'model_variant' => $modelVariant,
                    'color' => $validated['color'] ?? $request->input('color') ?? null,
                    'vin_chassis_number' => $validated['vin_chassis_number'] ?? $request->input('vin_chassis_number') ?? null,
                    'engine_number' => $validated['engine_number'] ?? $request->input('engine_number') ?? null,
                    'registration_number' => $validated['registration_number'] ?? $request->input('registration_number') ?? null,
                    'total_amount' => $totalAmount,
                    'discount_amount' => $discountAmount,
                    'net_amount' => $netAmount,
                    'total_paid' => 0.0,
                    'balance_due' => $netAmount,
                    'payment_status' => 'unpaid',
                    'deal_status' => $validated['deal_status'] ?? 'booking_confirmed',
                    'booking_date' => $validated['booking_date'] ?? now()->format('Y-m-d'),
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                    'sales_executive_id' => $salesExecId,
                    'sales_executive_name' => $salesExecName,
                    'created_by' => $creatorId,
                    'payment_terms' => $validated['payment_terms'] ?? null,
                    'delivery_terms' => $validated['delivery_terms'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Conditionally create Insurance record for this Deal only when insurance is 'Yes'
                $insuranceInput = $validated['insurance'] ?? $request->input('insurance');
                $shouldCreateInsurance = false;

                if (is_string($insuranceInput)) {
                    $cleaned = strtolower(trim($insuranceInput));
                    $shouldCreateInsurance = in_array($cleaned, ['yes', 'y', 'true', '1']);
                } elseif (is_bool($insuranceInput)) {
                    $shouldCreateInsurance = $insuranceInput;
                } elseif (is_numeric($insuranceInput)) {
                    $shouldCreateInsurance = (int) $insuranceInput === 1;
                } elseif (is_array($insuranceInput)) {
                    $status = $insuranceInput['status'] ?? $insuranceInput['insurance'] ?? null;
                    if (is_string($status)) {
                        $shouldCreateInsurance = in_array(strtolower(trim($status)), ['yes', 'y', 'true', '1']);
                    } else {
                        $shouldCreateInsurance = !empty($status);
                    }
                }

                if ($shouldCreateInsurance) {
                    $delivDate = $deal->actual_delivery_date 
                        ?? $deal->expected_delivery_date 
                        ?? $deal->booking_date 
                        ?? now()->format('Y-m-d');
                    $expireDate = \Illuminate\Support\Carbon::parse($delivDate)->addDays(365)->format('Y-m-d');

                    $premiumAmount = (float) (
                        $validated['premium_amount'] 
                        ?? $validated['insurance_premium'] 
                        ?? $validated['insurance_amount'] 
                        ?? $request->input('premium_amount') 
                        ?? $request->input('insurance_premium') 
                        ?? $request->input('insurance_amount') 
                        ?? 0.00
                    );

                    $idvAmount = isset($validated['idv_amount']) 
                        ? (float) $validated['idv_amount'] 
                        : ($request->filled('idv_amount') ? (float) $request->input('idv_amount') : null);

                    $insuranceNotes = $validated['insurance_notes'] 
                        ?? $request->input('insurance_notes') 
                        ?? $request->input('insurance_note') 
                        ?? null;

                    Insurance::create([
                        'deal_id' => $deal->id,
                        'lead_id' => $lead->id,
                        'customer_name' => $deal->customer_name,
                        'customer_phone' => $deal->customer_phone,
                        'customer_email' => $deal->customer_email,
                        'customer_address' => $deal->customer_city,
                        'vehicle_name' => $deal->model_variant,
                        'registration_number' => $deal->registration_number,
                        'vin_chassis_number' => $deal->vin_chassis_number,
                        'engine_number' => $deal->engine_number,
                        'premium_amount' => $premiumAmount,
                        'idv_amount' => $idvAmount,
                        'delivery_date' => $delivDate,
                        'start_date' => $delivDate,
                        'expiry_date' => $expireDate,
                        'next_insurance_date' => $expireDate,
                        'status' => 'active',
                        'notes' => $insuranceNotes,
                        'created_by' => $creatorId,
                        'sales_executive_id' => $deal->sales_executive_id,
                        'sales_executive_name' => $deal->sales_executive_name,
                    ]);
                }

                // Update Lead status to "Deal Won" / Converted
                $dealWonStatus = LeadStatus::where('name', 'like', '%Deal Won%')
                    ->orWhere('name', 'like', '%Won%')
                    ->orWhere('name', 'like', '%Converted%')
                    ->first();

                if ($dealWonStatus) {
                    $lead->status_id = $dealWonStatus->id;
                    $lead->status_name = $dealWonStatus->name;
                } else {
                    $lead->status_name = 'Deal Won';
                }
                $lead->save();

                // Log Follow-up history entry
                LeadFollowUp::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user?->id ?? $lead->assigned_to ?? \App\Models\User::first()?->id ?? 1,
                    'user_name' => $user?->name ?? 'System',
                    'follow_up_date' => now()->format('Y-m-d'),
                    'follow_up_time' => now()->format('H:i:s'),
                    'type' => 'Note',
                    'call_outcome' => 'Deal Won',
                    'notes' => "Lead converted into Deal #{$deal->deal_number}. Total Deal Value: " . number_format($netAmount, 2),
                    'next_follow_up_date' => null,
                    'status' => 'Completed',
                ]);

                // If Quotation exists, mark it as accepted
                if (!empty($quotationId)) {
                    Quotation::where('id', $quotationId)->update([
                        'status' => 'accepted',
                    ]);
                }

                // Create initial payment receipt if provided
                $initPay = $validated['initial_payment'] ?? $request->input('initial_payment') ?? [];
                $initAmount = (float) ($initPay['amount'] ?? $request->input('initial_payment.amount') ?? $request->input('amount') ?? 0);

                if ($initAmount > 0) {
                    $autoClear = !empty($initPay['auto_clear']) 
                        || !empty($request->input('initial_payment.auto_clear')) 
                        || ($user && in_array(strtolower(str_replace(' ', '_', $user->role ?? '')), ['admin', 'super_admin', 'accountant', 'accounts_manager', 'cashier']));

                    $transactionRef = $initPay['transaction_reference'] 
                        ?? $initPay['reference'] 
                        ?? $initPay['utr'] 
                        ?? $initPay['transaction_no'] 
                        ?? $initPay['reference_no'] 
                        ?? $initPay['reference_number'] 
                        ?? $request->input('initial_payment.transaction_reference') 
                        ?? $request->input('initial_payment.reference') 
                        ?? $request->input('initial_payment.utr') 
                        ?? $request->input('initial_payment.transaction_no') 
                        ?? $request->input('initial_payment.reference_no') 
                        ?? $request->input('initial_payment.reference_number') 
                        ?? $validated['transaction_reference'] 
                        ?? $validated['reference'] 
                        ?? $validated['utr'] 
                        ?? $validated['transaction_no'] 
                        ?? $validated['reference_no'] 
                        ?? $request->input('transaction_reference') 
                        ?? $request->input('reference') 
                        ?? $request->input('utr') 
                        ?? $request->input('transaction_no') 
                        ?? $request->input('reference_no') 
                        ?? null;

                    $bankName = $initPay['bank_name'] 
                        ?? $initPay['bank'] 
                        ?? $request->input('initial_payment.bank_name') 
                        ?? $request->input('initial_payment.bank') 
                        ?? $validated['bank_name'] 
                        ?? $validated['bank'] 
                        ?? $request->input('bank_name') 
                        ?? $request->input('bank') 
                        ?? null;

                    $receivedBy = $initPay['received_by'] 
                        ?? $request->input('initial_payment.received_by') 
                        ?? $validated['received_by'] 
                        ?? $request->input('received_by') 
                        ?? null;

                    $payment = DealPayment::create([
                        'receipt_number' => DealPayment::generateReceiptNumber(),
                        'deal_id' => $deal->id,
                        'lead_id' => $lead->id,
                        'payment_type' => $initPay['payment_type'] ?? $request->input('initial_payment.payment_type') ?? 'token_advance',
                        'amount' => $initAmount,
                        'payment_date' => $initPay['payment_date'] ?? $request->input('initial_payment.payment_date') ?? now()->format('Y-m-d'),
                        'payment_mode' => $initPay['payment_mode'] ?? $request->input('initial_payment.payment_mode') ?? 'upi',
                        'transaction_reference' => $transactionRef,
                        'bank_name' => $bankName,
                        'received_by' => $receivedBy,
                        'status' => $autoClear ? 'cleared' : 'pending',
                        'notes' => $initPay['notes'] ?? $request->input('initial_payment.notes') ?? 'Initial booking token advance payment',
                        'recorded_by' => $user?->id,
                        'recorded_by_name' => $user?->name,
                        'verified_by' => $autoClear ? $user?->id : null,
                        'verified_by_name' => $autoClear ? $user?->name : null,
                        'verified_at' => $autoClear ? now() : null,
                    ]);

                    $deal->refresh();
                }

                $deal->load(['lead', 'quotation', 'brand', 'salesExecutive', 'payments', 'insurances']);

                return response()->json([
                    'status' => true,
                    'message' => "Lead successfully converted to Deal #{$deal->deal_number}",
                    'data' => $deal,
                ], 201);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealController@convertLead error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while converting lead to deal.',
            ], 500);
        }
    }

    /**
     * Store a newly created Deal directly
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $validated = $request->validate([
                'lead_id' => 'required|exists:leads,id',
                'quotation_id' => 'nullable|exists:quotations,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'customer_email' => 'nullable|email|max:255',
                'customer_city' => 'nullable|string|max:100',
                'customer_state' => 'nullable|string|max:100',
                'customer_address' => 'nullable|string',
                'vehicle_segment' => 'nullable|string|max:50',
                'brand_id' => 'nullable|exists:brands,id',
                'brand_name' => 'nullable|string|max:100',
                'model_variant' => 'required|string|max:255',
                'color' => 'nullable|string|max:100',
                'vin_chassis_number' => 'nullable|string|max:100',
                'engine_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'total_amount' => 'required|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'booking_date' => 'required|date',
                'expected_delivery_date' => 'nullable|date',
                'deal_status' => 'nullable|string|max:50',
                'sales_executive_id' => 'nullable|exists:users,id',
                'sales_executive_name' => 'nullable|string|max:150',
                'payment_terms' => 'nullable|string',
                'delivery_terms' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $totalAmount = (float) $validated['total_amount'];
            $discountAmount = (float) ($validated['discount_amount'] ?? 0);
            $netAmount = max(0.0, $totalAmount - $discountAmount);

            $deal = Deal::create(array_merge($validated, [
                'deal_number' => Deal::generateDealNumber(),
                'net_amount' => $netAmount,
                'total_paid' => 0.0,
                'balance_due' => $netAmount,
                'payment_status' => 'unpaid',
                'created_by' => $user?->id,
            ]));

            $deal->load(['lead', 'quotation', 'brand', 'salesExecutive', 'payments']);

            return response()->json([
                'status' => true,
                'message' => "Deal #{$deal->deal_number} created successfully",
                'data' => $deal,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating deal.',
            ], 500);
        }
    }

    /**
     * Display the specified Deal with complete payment history and ledger
     */
    public function show($id)
    {
        try {
            $deal = Deal::with([
                'lead',
                'quotation.items',
                'brand',
                'salesExecutive',
                'creator',
                'payments.recordedBy:id,name,role',
                'payments.verifiedBy:id,name,role',
            ])->find($id);

            if (!$deal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Deal not found',
                ], 404);
            }

            // Recalculate and ensure consistency
            $deal->recalculateFinancials();

            return response()->json([
                'status' => true,
                'message' => 'Deal details retrieved successfully',
                'data' => $deal,
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@show error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching deal details.',
            ], 500);
        }
    }

    /**
     * Update the specified Deal
     */
    public function update(Request $request, $id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Deal not found',
                ], 404);
            }

            $validated = $request->validate([
                'customer_name' => 'sometimes|string|max:255',
                'customer_phone' => 'sometimes|string|max:50',
                'customer_email' => 'nullable|email|max:255',
                'customer_city' => 'nullable|string|max:100',
                'customer_state' => 'nullable|string|max:100',
                'customer_address' => 'nullable|string',
                'vehicle_segment' => 'nullable|string|max:50',
                'brand_id' => 'nullable|exists:brands,id',
                'brand_name' => 'nullable|string|max:100',
                'model_variant' => 'sometimes|string|max:255',
                'color' => 'nullable|string|max:100',
                'vin_chassis_number' => 'nullable|string|max:100',
                'engine_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'total_amount' => 'sometimes|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'booking_date' => 'sometimes|date',
                'expected_delivery_date' => 'nullable|date',
                'actual_delivery_date' => 'nullable|date',
                'deal_status' => 'sometimes|string|max:50',
                'payment_status' => 'sometimes|string|max:50',
                'sales_executive_id' => 'nullable|exists:users,id',
                'sales_executive_name' => 'nullable|string|max:150',
                'payment_terms' => 'nullable|string',
                'delivery_terms' => 'nullable|string',
                'notes' => 'nullable|string',
                'cancellation_reason' => 'nullable|string',
            ]);

            $deal->fill($validated);

            if (isset($validated['total_amount']) || isset($validated['discount_amount'])) {
                $totalAmount = (float) ($validated['total_amount'] ?? $deal->total_amount);
                $discountAmount = (float) ($validated['discount_amount'] ?? $deal->discount_amount);
                $deal->net_amount = max(0.0, $totalAmount - $discountAmount);
            }

            $deal->save();
            $deal->recalculateFinancials();

            $deal->load(['lead', 'quotation', 'brand', 'salesExecutive', 'payments']);

            return response()->json([
                'status' => true,
                'message' => 'Deal updated successfully',
                'data' => $deal,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('DealController@update error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating deal.',
            ], 500);
        }
    }

    /**
     * Remove the specified Deal from storage
     */
    public function destroy($id)
    {
        try {
            $deal = Deal::find($id);

            if (!$deal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Deal not found',
                ], 404);
            }

            $deal->delete();

            return response()->json([
                'status' => true,
                'message' => "Deal #{$deal->deal_number} deleted successfully",
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@destroy error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while deleting deal.',
            ], 500);
        }
    }

    /**
     * Aggregate metrics & KPIs for Deals and Bookings
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $dealQuery = Deal::query();
            $paymentQuery = DealPayment::query();

            if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
                $dealQuery->where(function ($q) use ($user) {
                    $q->where('sales_executive_id', $user->id)
                      ->orWhere('sales_executive_name', 'like', '%' . $user->name . '%');
                });
            }

            // Optional date range filtering for stats
            $startDate = $request->input('start_date') ?? $request->input('startDate') ?? $request->input('from_date') ?? $request->input('date_from');
            $endDate = $request->input('end_date') ?? $request->input('endDate') ?? $request->input('to_date') ?? $request->input('date_to');
            if (!empty($startDate)) {
                $dealQuery->whereDate('booking_date', '>=', $startDate);
            }
            if (!empty($endDate)) {
                $dealQuery->whereDate('booking_date', '<=', $endDate);
            }

            $totalDeals = (clone $dealQuery)->count();
            $totalGrossValue = (float) (clone $dealQuery)->sum('net_amount');
            $totalCollected = (float) (clone $dealQuery)->sum('total_paid');
            $totalPendingBalance = (float) (clone $dealQuery)->sum('balance_due');

            $activeBookings = (clone $dealQuery)->whereNotIn('deal_status', ['delivered', 'cancelled'])->count();
            $deliveredDeals = (clone $dealQuery)->where('deal_status', 'delivered')->count();
            $fullyPaidDeals = (clone $dealQuery)->where('payment_status', 'paid')->count();
            $pendingPaymentsCount = (clone $paymentQuery)->where('status', 'pending')->count();

            return response()->json([
                'status' => true,
                'message' => 'Deal analytics retrieved successfully',
                'data' => [
                    'total_deals' => $totalDeals,
                    'total_gross_value' => $totalGrossValue,
                    'total_collected' => $totalCollected,
                    'total_pending_balance' => $totalPendingBalance,
                    'active_bookings' => $activeBookings,
                    'delivered_deals' => $deliveredDeals,
                    'fully_paid_deals' => $fullyPaidDeals,
                    'pending_payment_verifications' => $pendingPaymentsCount,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DealController@stats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching deal statistics.',
            ], 500);
        }
    }
}
