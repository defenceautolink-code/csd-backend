<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Insurance;
use App\Models\InsuranceRenewalHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class InsuranceController extends Controller
{
    /**
     * 1. GET API: List vehicle insurance records for table view
     * Returns: deal_id, customer_name, customer_number, customer_city, model_variant, color, delivery_date, insurance_expire_date, reminder_date
     * Calculation: Expire Date = Delivery Date + 365 Days. Reminder Date = Expire Date - 15 Days (15 days before expiration).
     */
    public function index(Request $request)
    {
        try {
            $insurances = Insurance::with(['deal', 'renewalHistories'])->latest('id')->get();

            $records = collect();

            // Process only Insurance table records
            foreach ($insurances as $ins) {
                $deliveryDate = $ins->policy_start_date
                    ?? $ins->delivery_date 
                    ?? $ins->start_date 
                    ?? $ins->deal?->actual_delivery_date 
                    ?? $ins->deal?->expected_delivery_date 
                    ?? $ins->created_at->format('Y-m-d');

                $deliveryCarbon = Carbon::parse($deliveryDate);

                // Insurance Expire Date = policy_expiry_date or 365 days after start/delivery
                $expireDate = $ins->policy_expiry_date
                    ?? $ins->next_insurance_date 
                    ?? $ins->expiry_date 
                    ?? $deliveryCarbon->copy()->addDays(365)->format('Y-m-d');

                $reminderDays = (int) ($ins->reminder_days_before ?? 15);

                // Reminder Date = reminder_date or (expireDate - reminderDays)
                $reminderDate = $ins->reminder_date
                    ?? Carbon::parse($expireDate)->subDays($reminderDays)->format('Y-m-d');

                // Computed Status
                $todayStr = Carbon::today()->format('Y-m-d');

                $status = $ins->status;
                if ($status !== 'renewed') {
                    if ($todayStr > $expireDate) {
                        $status = 'expired';
                    } elseif ($todayStr >= $reminderDate) {
                        $status = 'expiring_soon';
                    } else {
                        $status = 'active';
                    }
                }

                $records->push([
                    'id' => $ins->id,
                    'deal_id' => $ins->deal_id ?? $ins->deal?->deal_number ?? $ins->id,
                    'customer_id' => $ins->customer_id ?? $ins->lead_id,
                    'customer_name' => $ins->customer_name ?? $ins->deal?->customer_name ?? '',
                    'customer_number' => $ins->customer_phone ?? $ins->deal?->customer_phone ?? '',
                    'customer_email' => $ins->customer_email ?? $ins->deal?->customer_email ?? '',
                    'customer_city' => $ins->deal?->customer_city ?? $ins->customer_address ?? '',
                    'model_variant' => $ins->vehicle_name ?? $ins->deal?->model_variant ?? '',
                    'vehicle_number' => $ins->vehicle_number ?? $ins->registration_number ?? $ins->deal?->registration_number ?? '',
                    'registration_number' => $ins->registration_number ?? $ins->vehicle_number ?? '',
                    'insurance_company' => $ins->insurance_company ?? '',
                    'policy_number' => $ins->policy_number ?? '',
                    'insurance_type' => $ins->insurance_type ?? 'Comprehensive',
                    'policy_start_date' => $ins->policy_start_date ? Carbon::parse($ins->policy_start_date)->format('Y-m-d') : ($ins->start_date ? Carbon::parse($ins->start_date)->format('Y-m-d') : Carbon::parse($deliveryDate)->format('Y-m-d')),
                    'policy_expiry_date' => $ins->policy_expiry_date ? Carbon::parse($ins->policy_expiry_date)->format('Y-m-d') : Carbon::parse($expireDate)->format('Y-m-d'),
                    'premium_amount' => (float) ($ins->premium_amount ?? 0),
                    'idv_amount' => (float) ($ins->idv_amount ?? 0),
                    'reminder_days_before' => (int) ($ins->reminder_days_before ?? 15),
                    'color' => $ins->deal?->color ?? '',
                    'delivery_date' => Carbon::parse($deliveryDate)->format('Y-m-d'),
                    'insurance_expire_date' => Carbon::parse($expireDate)->format('Y-m-d'),
                    'reminder_date' => (!empty($ins->policy_expiry_date) && !empty($ins->reminder_date)) ? Carbon::parse($ins->reminder_date)->format('Y-m-d') : '',
                    'status' => $status,
                    'renewal_status' => $ins->renewal_status ?? ($status === 'renewed' ? 'Renewed' : 'Pending'),
                    'notes' => $ins->notes ?? '',
                    'renewal_history' => $ins->renewalHistories ? $ins->renewalHistories->map(function ($h) {
                        return [
                            'id' => $h->id,
                            'policy_number' => $h->policy_number,
                            'insurance_company' => $h->insurance_company,
                            'insurance_type' => $h->insurance_type,
                            'policy_start_date' => $h->policy_start_date ? $h->policy_start_date->format('Y-m-d') : null,
                            'policy_expiry_date' => $h->policy_expiry_date ? $h->policy_expiry_date->format('Y-m-d') : null,
                            'premium_amount' => (float) ($h->premium_amount ?? 0),
                            'reminder_days_before' => (int) ($h->reminder_days_before ?? 15),
                            'reminder_date' => $h->reminder_date ? $h->reminder_date->format('Y-m-d') : null,
                            'renewal_status' => $h->renewal_status,
                            'renewed_at' => $h->renewed_at ? $h->renewed_at->format('Y-m-d H:i') : ($h->created_at ? $h->created_at->format('Y-m-d H:i') : null),
                            'notes' => $h->notes,
                        ];
                    })->values() : [],
                ]);
            }

            // -----------------------------------------------------------------
            // SEARCH & FIELD FILTERS
            // -----------------------------------------------------------------

            // Search (name, number, city, model_variant, color, deal_id)
            if ($request->filled('search')) {
                $search = strtolower(trim($request->search));
                $records = $records->filter(function ($item) use ($search) {
                    return str_contains(strtolower((string) $item['deal_id']), $search)
                        || str_contains(strtolower((string) $item['customer_name']), $search)
                        || str_contains(strtolower((string) $item['customer_number']), $search)
                        || str_contains(strtolower((string) $item['customer_city']), $search)
                        || str_contains(strtolower((string) $item['model_variant']), $search)
                        || str_contains(strtolower((string) $item['color']), $search);
                });
            }

            // City Filter
            $cityInput = $request->input('customer_city') ?? $request->input('city');
            if (!empty($cityInput)) {
                $city = strtolower(trim($cityInput));
                $records = $records->filter(fn($item) => str_contains(strtolower($item['customer_city']), $city));
            }

            // Model Variant Filter
            $modelInput = $request->input('model_variant') ?? $request->input('model');
            if (!empty($modelInput)) {
                $model = strtolower(trim($modelInput));
                $records = $records->filter(fn($item) => str_contains(strtolower($item['model_variant']), $model));
            }

            // Color Filter
            if ($request->filled('color')) {
                $color = strtolower(trim($request->color));
                $records = $records->filter(fn($item) => str_contains(strtolower($item['color']), $color));
            }

            // Deal ID Filter
            if ($request->filled('deal_id')) {
                $dealId = trim($request->deal_id);
                $records = $records->filter(fn($item) => (string) $item['deal_id'] === (string) $dealId);
            }

            // -----------------------------------------------------------------
            // NEXT 15 DAYS EXPIRY FILTER (Today to Today + 15 Days)
            // -----------------------------------------------------------------
            $today = Carbon::today()->format('Y-m-d');
            $daysCount = (int) ($request->get('days') ?? 15);
            $targetExpiryEnd = Carbon::today()->addDays($daysCount)->format('Y-m-d');

            $showAll = $request->boolean('all') 
                || $request->get('status') === 'all' 
                || $request->get('filter') === 'all';

            // Status & Reminder Filters
            $statusInput = $request->input('status') ?? $request->input('reminder_status');

            if (!empty($statusInput) && $statusInput !== 'all') {
                $statusVal = strtolower(trim($statusInput));

                if (in_array($statusVal, ['reminders_due', 'expiring_soon', 'due_reminders', 'reminder_due', 'near_expiry', 'near_reminder', 'due', 'next_15_days'])) {
                    $records = $records->filter(fn($item) => $item['insurance_expire_date'] >= $today && $item['insurance_expire_date'] <= $targetExpiryEnd);
                } elseif ($statusVal === 'expired') {
                    $records = $records->filter(fn($item) => $item['insurance_expire_date'] < $today);
                } elseif ($statusVal === 'active') {
                    $records = $records->filter(fn($item) => $item['insurance_expire_date'] > $today);
                } elseif ($statusVal === 'renewed') {
                    $records = $records->filter(fn($item) => $item['status'] === 'renewed');
                }
            } elseif (!$showAll && !$request->filled('insurance_expire_date') && !$request->filled('expiry_date') && !$request->filled('from_date') && !$request->filled('to_date') && !$request->filled('date')) {
                // By default: Fetch only records where 15 days or less are remaining until expiry (<= Today + 15 Days)
                $records = $records->filter(function ($item) use ($targetExpiryEnd) {
                    $renewalStatus = strtolower((string) ($item['renewal_status'] ?? $item['status']));
                    if ($renewalStatus === 'renewed') return false;
                    return $item['insurance_expire_date'] <= $targetExpiryEnd;
                });
            }

            // -----------------------------------------------------------------
            // DATE-BASED FILTERS
            // -----------------------------------------------------------------
            $dateField = $request->input('date_field', 'insurance_expire_date');
            if (!in_array($dateField, ['delivery_date', 'insurance_expire_date', 'reminder_date'])) {
                $dateField = 'insurance_expire_date';
            }

            // Direct field matches (e.g., expiry_date, insurance_expire_date, reminder_date)
            if ($request->filled('insurance_expire_date') || $request->filled('expiry_date')) {
                $expVal = $request->input('insurance_expire_date') ?? $request->input('expiry_date');
                $records = $records->filter(fn($item) => $item['insurance_expire_date'] === $expVal);
            }

            if ($request->filled('reminder_date')) {
                $remVal = $request->input('reminder_date');
                $records = $records->filter(fn($item) => $item['reminder_date'] === $remVal);
            }

            $startDate = $request->input('from_date') ?? $request->input('start_date') ?? $request->input('startDate') ?? $request->input('date_from');
            $endDate = $request->input('to_date') ?? $request->input('end_date') ?? $request->input('endDate') ?? $request->input('date_to');
            $singleDate = $request->input('date');

            if (!empty($singleDate)) {
                $records = $records->filter(fn($item) => $item[$dateField] === $singleDate);
            } else {
                if (!empty($startDate)) {
                    $records = $records->filter(fn($item) => $item[$dateField] >= $startDate);
                }
                if (!empty($endDate)) {
                    $records = $records->filter(fn($item) => $item[$dateField] <= $endDate);
                }
            }

            // Sort records by insurance expire date ascending
            $records = $records->sortBy('insurance_expire_date')->values();

            $total = $records->count();
            $isAll = $request->boolean('all') || $request->get('per_page') === 'all';

            if ($isAll) {
                return response()->json([
                    'status' => true,
                    'message' => 'Insurance records retrieved successfully',
                    'data' => $records->values(),
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $total,
                        'total' => $total,
                        'from' => $total > 0 ? 1 : null,
                        'to' => $total,
                    ],
                ]);
            }

            $page = max(1, (int) $request->get('page', 1));
            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;

            $offset = ($page - 1) * $perPage;
            $items = $records->slice($offset, $perPage)->values();
            $lastPage = max(1, (int) ceil($total / $perPage));

            return response()->json([
                'status' => true,
                'message' => 'Insurance records retrieved successfully',
                'data' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $items->count() > 0 ? $offset + 1 : null,
                    'to' => $items->count() > 0 ? $offset + $items->count() : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InsuranceController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching insurance records.',
            ], 500);
        }
    }

    /**
     * 2. POST API: Insert Insurance Record when Deal is created or manually
     * Automatically calculates:
     * - insurance_expire_date = delivery_date + 365 Days
     * - reminder_date = insurance_expire_date - 15 Days (350 Days after delivery)
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $validated = $request->validate([
                'deal_id' => 'nullable',
                'customer_id' => 'nullable',
                'vehicle_id' => 'nullable',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'customer_number' => 'nullable|string|max:50',
                'customer_email' => 'nullable|email|max:255',
                'customer_address' => 'nullable|string',
                'customer_city' => 'nullable|string|max:100',
                'vehicle_name' => 'nullable|string|max:255',
                'model_variant' => 'nullable|string|max:255',
                'vehicle_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'vin_chassis_number' => 'nullable|string|max:100',
                'engine_number' => 'nullable|string|max:100',
                'insurance_company' => 'nullable|string|max:150',
                'policy_number' => 'nullable|string|max:100',
                'insurance_type' => 'nullable|string|max:50',
                'policy_start_date' => 'nullable|date',
                'policy_expiry_date' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'premium_amount' => 'nullable|numeric|min:0',
                'idv_amount' => 'nullable|numeric|min:0',
                'reminder_days_before' => 'nullable|integer|min:1',
                'reminder_date' => 'nullable|date',
                'renewal_status' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $customerPhone = $validated['customer_number'] ?? $validated['customer_phone'];
            $modelVariant = $validated['model_variant'] ?? $validated['vehicle_name'] ?? 'Vehicle Policy';
            $customerCity = $validated['customer_city'] ?? $validated['customer_address'] ?? '';
            $vehicleNo = $validated['vehicle_number'] ?? $validated['registration_number'] ?? null;

            // Fetch color from Deal model if deal_id is present
            $deal = !empty($validated['deal_id']) ? Deal::find($validated['deal_id']) : null;
            $color = $deal?->color ?? $validated['color'] ?? '';

            $deliveryDate = $validated['policy_start_date'] 
                ?? $validated['delivery_date'] 
                ?? $deal?->actual_delivery_date 
                ?? $deal?->expected_delivery_date 
                ?? now()->format('Y-m-d');
            $deliveryCarbon = Carbon::parse($deliveryDate);

            $expireDate = $validated['policy_expiry_date'] 
                ?? $deliveryCarbon->copy()->addDays(365)->format('Y-m-d');

            $reminderDays = (int) ($validated['reminder_days_before'] ?? 15);
            $reminderDate = $validated['reminder_date'] 
                ?? Carbon::parse($expireDate)->subDays($reminderDays)->format('Y-m-d');

            $insurance = Insurance::create([
                'deal_id' => $validated['deal_id'] ?? null,
                'vehicle_id' => $validated['vehicle_id'] ?? $deal?->vehicle_variant_id ?? null,
                'customer_id' => $validated['customer_id'] ?? $deal?->lead_id ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $customerPhone,
                'customer_email' => $validated['customer_email'] ?? $deal?->customer_email,
                'customer_address' => $customerCity ?: ($deal?->customer_city ?? ''),
                'vehicle_name' => $modelVariant,
                'vehicle_number' => $vehicleNo,
                'registration_number' => $vehicleNo,
                'vin_chassis_number' => $validated['vin_chassis_number'] ?? $deal?->vin_chassis_number ?? null,
                'engine_number' => $validated['engine_number'] ?? $deal?->engine_number ?? null,
                'insurance_company' => $validated['insurance_company'] ?? null,
                'policy_number' => $validated['policy_number'] ?? null,
                'insurance_type' => $validated['insurance_type'] ?? 'Comprehensive',
                'policy_start_date' => $deliveryDate,
                'policy_expiry_date' => $expireDate,
                'delivery_date' => $deliveryDate,
                'start_date' => $deliveryDate,
                'expiry_date' => $expireDate,
                'next_insurance_date' => $expireDate,
                'reminder_days_before' => $reminderDays,
                'reminder_date' => $reminderDate,
                'renewal_status' => $validated['renewal_status'] ?? 'Pending',
                'premium_amount' => (float) ($validated['premium_amount'] ?? 0.00),
                'idv_amount' => isset($validated['idv_amount']) ? (float) $validated['idv_amount'] : null,
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Insurance record created successfully',
                'data' => [
                    'id' => $insurance->id,
                    'deal_id' => $insurance->deal_id ?? $insurance->id,
                    'customer_name' => $insurance->customer_name,
                    'customer_number' => $insurance->customer_phone,
                    'customer_city' => $customerCity,
                    'model_variant' => $modelVariant,
                    'vehicle_number' => $vehicleNo,
                    'registration_number' => $vehicleNo,
                    'insurance_company' => $insurance->insurance_company,
                    'policy_number' => $insurance->policy_number,
                    'insurance_type' => $insurance->insurance_type,
                    'policy_start_date' => $deliveryDate,
                    'policy_expiry_date' => $expireDate,
                    'color' => $color,
                    'delivery_date' => Carbon::parse($deliveryDate)->format('Y-m-d'),
                    'insurance_expire_date' => Carbon::parse($expireDate)->format('Y-m-d'),
                    'reminder_date' => $reminderDate,
                    'status' => 'active',
                    'renewal_status' => $insurance->renewal_status,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('InsuranceController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while creating insurance record.',
            ], 500);
        }
    }

    /**
     * 3. UPDATE API: Edit existing insurance policy record
     */
    public function update(Request $request, $id)
    {
        try {
            $insurance = Insurance::find($id);
            if (!$insurance) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insurance record not found.',
                ], 404);
            }

            $validated = $request->validate([
                'customer_name' => 'nullable|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_number' => 'nullable|string|max:50',
                'customer_email' => 'nullable|email|max:255',
                'customer_address' => 'nullable|string',
                'customer_city' => 'nullable|string|max:100',
                'vehicle_name' => 'nullable|string|max:255',
                'model_variant' => 'nullable|string|max:255',
                'vehicle_number' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'insurance_company' => 'nullable|string|max:150',
                'policy_number' => 'nullable|string|max:100',
                'insurance_type' => 'nullable|string|max:50',
                'policy_start_date' => 'nullable|date',
                'policy_expiry_date' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'premium_amount' => 'nullable|numeric|min:0',
                'idv_amount' => 'nullable|numeric|min:0',
                'reminder_days_before' => 'nullable|integer|min:1',
                'reminder_date' => 'nullable|date',
                'renewal_status' => 'nullable|string|max:50',
                'status' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
            ]);

            if (isset($validated['customer_name'])) $insurance->customer_name = $validated['customer_name'];
            if (isset($validated['customer_phone']) || isset($validated['customer_number'])) {
                $insurance->customer_phone = $validated['customer_phone'] ?? $validated['customer_number'];
            }
            if (isset($validated['customer_email'])) $insurance->customer_email = $validated['customer_email'];
            if (isset($validated['customer_city']) || isset($validated['customer_address'])) {
                $insurance->customer_address = $validated['customer_city'] ?? $validated['customer_address'];
            }
            if (isset($validated['vehicle_name']) || isset($validated['model_variant'])) {
                $insurance->vehicle_name = $validated['model_variant'] ?? $validated['vehicle_name'];
            }
            if (array_key_exists('vehicle_number', $validated) || array_key_exists('registration_number', $validated)) {
                $vNo = $validated['vehicle_number'] ?? $validated['registration_number'];
                $insurance->vehicle_number = $vNo;
                $insurance->registration_number = $vNo;
            }
            if (array_key_exists('insurance_company', $validated)) $insurance->insurance_company = $validated['insurance_company'];
            if (array_key_exists('policy_number', $validated)) $insurance->policy_number = $validated['policy_number'];
            if (array_key_exists('insurance_type', $validated)) $insurance->insurance_type = $validated['insurance_type'];
            if (array_key_exists('policy_start_date', $validated)) {
                $insurance->policy_start_date = $validated['policy_start_date'];
                $insurance->start_date = $validated['policy_start_date'];
            }
            if (array_key_exists('delivery_date', $validated)) {
                $insurance->delivery_date = $validated['delivery_date'];
            }
            if (array_key_exists('policy_expiry_date', $validated)) {
                $insurance->policy_expiry_date = $validated['policy_expiry_date'];
                $insurance->expiry_date = $validated['policy_expiry_date'];
                $insurance->next_insurance_date = $validated['policy_expiry_date'];
            }
            if (array_key_exists('premium_amount', $validated)) $insurance->premium_amount = (float) $validated['premium_amount'];
            if (array_key_exists('idv_amount', $validated)) $insurance->idv_amount = (float) $validated['idv_amount'];
            if (array_key_exists('reminder_days_before', $validated)) $insurance->reminder_days_before = (int) $validated['reminder_days_before'];
            if (array_key_exists('reminder_date', $validated)) {
                $insurance->reminder_date = $validated['reminder_date'];
            } elseif (!empty($insurance->expiry_date)) {
                $rDays = $insurance->reminder_days_before ?: 15;
                $insurance->reminder_date = Carbon::parse($insurance->expiry_date)->subDays($rDays)->format('Y-m-d');
            }
            if (array_key_exists('renewal_status', $validated)) $insurance->renewal_status = $validated['renewal_status'];
            if (array_key_exists('status', $validated)) $insurance->status = $validated['status'];
            if (array_key_exists('notes', $validated)) $insurance->notes = $validated['notes'];

            $insurance->save();

            return response()->json([
                'status' => true,
                'message' => 'Insurance record updated successfully.',
                'data' => $insurance,
            ]);
        } catch (\Throwable $e) {
            Log::error('InsuranceController@update error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while updating insurance.',
            ], 500);
        }
    }

    /**
     * 4. RENEW API: Renew policy with modal details or auto +365 days
     */
    public function renew(Request $request, $id)
    {
        try {
            $insurance = Insurance::find($id);

            if (!$insurance) {
                // Check if ID refers to a Deal record
                $deal = Deal::find($id);
                if ($deal) {
                    $deliveryDate = $deal->actual_delivery_date ?? $deal->expected_delivery_date ?? $deal->booking_date ?? now()->format('Y-m-d');
                    $deliveryCarbon = Carbon::parse($deliveryDate);
                    $initialExpire = $deliveryCarbon->copy()->addDays(365)->format('Y-m-d');

                    $insurance = Insurance::create([
                        'deal_id' => $deal->id,
                        'customer_name' => $deal->customer_name,
                        'customer_phone' => $deal->customer_phone,
                        'customer_email' => $deal->customer_email,
                        'customer_address' => $deal->customer_city,
                        'vehicle_name' => $deal->model_variant,
                        'vehicle_number' => $deal->registration_number,
                        'registration_number' => $deal->registration_number,
                        'delivery_date' => $deliveryDate,
                        'start_date' => $deliveryDate,
                        'expiry_date' => $initialExpire,
                        'next_insurance_date' => $initialExpire,
                        'status' => 'active',
                    ]);
                }
            }

            if (!$insurance) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insurance policy record not found.',
                ], 404);
            }

            // Read renewed values from modal request if passed
            $company = $request->input('insurance_company', $insurance->insurance_company);
            $policyNumber = $request->input('policy_number', $insurance->policy_number);
            $insuranceType = $request->input('insurance_type', $insurance->insurance_type ?: 'Comprehensive');
            $premiumAmount = $request->filled('premium_amount') ? (float) $request->input('premium_amount') : $insurance->premium_amount;
            $vehicleNumber = $request->input('vehicle_number', $insurance->vehicle_number ?? $insurance->registration_number);
            $notes = $request->input('notes', $insurance->notes);

            $newStartDate = $request->input('policy_start_date') 
                ?? $request->input('start_date') 
                ?? $insurance->next_insurance_date 
                ?? $insurance->expiry_date 
                ?? now()->format('Y-m-d');

            $reminderDays = (int) ($request->input('reminder_days_before') ?? $insurance->reminder_days_before ?? 15);

            if ($request->filled('policy_expiry_date')) {
                $newExpireDate = Carbon::parse($request->input('policy_expiry_date'))->format('Y-m-d');
            } elseif ($request->filled('expiry_date')) {
                $newExpireDate = Carbon::parse($request->input('expiry_date'))->format('Y-m-d');
            } else {
                $newExpireDate = Carbon::parse($newStartDate)->copy()->addDays(365)->format('Y-m-d');
            }

            $newReminderDate = $request->input('reminder_date') 
                ?? Carbon::parse($newExpireDate)->subDays($reminderDays)->format('Y-m-d');

            // Snapshot current policy into InsuranceRenewalHistory before applying renewal updates
            InsuranceRenewalHistory::create([
                'insurance_id' => $insurance->id,
                'deal_id' => $insurance->deal_id,
                'policy_number' => $insurance->policy_number ?: 'Previous Policy',
                'insurance_company' => $insurance->insurance_company ?: null,
                'insurance_type' => $insurance->insurance_type ?: 'Comprehensive',
                'policy_start_date' => $insurance->policy_start_date ?? $insurance->start_date ?? $insurance->delivery_date,
                'policy_expiry_date' => $insurance->policy_expiry_date ?? $insurance->expiry_date,
                'premium_amount' => (float) ($insurance->premium_amount ?? 0),
                'reminder_days_before' => (int) ($insurance->reminder_days_before ?? 15),
                'reminder_date' => $insurance->reminder_date,
                'renewal_status' => 'Renewed',
                'renewed_at' => now(),
                'notes' => $insurance->notes,
            ]);

            // Update Insurance Record
            $insurance->insurance_company = $company;
            $insurance->policy_number = $policyNumber;
            $insurance->insurance_type = $insuranceType;
            $insurance->policy_start_date = $newStartDate;
            $insurance->start_date = $newStartDate;
            $insurance->policy_expiry_date = $newExpireDate;
            $insurance->expiry_date = $newExpireDate;
            $insurance->next_insurance_date = $newExpireDate;
            $insurance->reminder_days_before = $reminderDays;
            $insurance->reminder_date = $newReminderDate;
            $insurance->premium_amount = $premiumAmount;
            $insurance->notes = $notes;
            if ($vehicleNumber) {
                $insurance->vehicle_number = $vehicleNumber;
                $insurance->registration_number = $vehicleNumber;
            }
            $insurance->status = 'active';
            $insurance->renewal_status = 'Renewed';
            $insurance->save();

            $deal = $insurance->deal ?? Deal::find($insurance->deal_id);

            return response()->json([
                'status' => true,
                'message' => "Insurance policy renewed successfully. New Expire Date: {$newExpireDate}, Reminder Date: {$newReminderDate}",
                'data' => [
                    'id' => $insurance->id,
                    'deal_id' => $insurance->deal_id ?? $insurance->id,
                    'customer_name' => $insurance->customer_name ?? $deal?->customer_name ?? '',
                    'customer_number' => $insurance->customer_phone ?? $deal?->customer_phone ?? '',
                    'customer_city' => $deal?->customer_city ?? $insurance->customer_address ?? '',
                    'model_variant' => $insurance->vehicle_name ?? $deal?->model_variant ?? '',
                    'vehicle_number' => $insurance->vehicle_number ?? $insurance->registration_number ?? '',
                    'insurance_company' => $insurance->insurance_company,
                    'policy_number' => $insurance->policy_number,
                    'insurance_type' => $insurance->insurance_type,
                    'premium_amount' => $insurance->premium_amount,
                    'color' => $deal?->color ?? '',
                    'delivery_date' => Carbon::parse($insurance->delivery_date ?? $deal?->actual_delivery_date)->format('Y-m-d'),
                    'policy_start_date' => $newStartDate,
                    'policy_expiry_date' => $newExpireDate,
                    'insurance_expire_date' => $newExpireDate,
                    'reminder_date' => $newReminderDate,
                    'status' => 'active',
                    'renewal_status' => 'Renewed',
                    'notes' => $insurance->notes,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('InsuranceController@renew error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while renewing insurance policy.',
            ], 500);
        }
    }

    /**
     * 5. GET SEPARATE API: Fetch all insurance records directly from insurances table
     * Endpoint: GET /api/insurance-records
     */
    public function getInsuranceRecords(Request $request)
    {
        try {
            $query = Insurance::with(['deal', 'lead', 'renewalHistories'])->latest('id');

            // Search Filter across all fields
            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_phone', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%")
                      ->orWhere('customer_address', 'like', "%{$search}%")
                      ->orWhere('vehicle_name', 'like', "%{$search}%")
                      ->orWhere('vehicle_number', 'like', "%{$search}%")
                      ->orWhere('registration_number', 'like', "%{$search}%")
                      ->orWhere('policy_number', 'like', "%{$search}%")
                      ->orWhere('insurance_company', 'like', "%{$search}%")
                      ->orWhere('deal_id', 'like', "%{$search}%");
                });
            }

            // Status Filter
            if ($request->filled('status') && $request->status !== 'all') {
                $status = $request->status;
                if ($status === 'expired') {
                    $query->where(function ($q) {
                        $q->where('expiry_date', '<', now()->format('Y-m-d'))
                          ->orWhere('policy_expiry_date', '<', now()->format('Y-m-d'));
                    });
                } elseif ($status === 'expiring_soon') {
                    $today = now()->format('Y-m-d');
                    $fifteenDays = now()->addDays(15)->format('Y-m-d');
                    $query->where(function ($q) use ($today, $fifteenDays) {
                        $q->whereBetween('expiry_date', [$today, $fifteenDays])
                          ->orWhereBetween('policy_expiry_date', [$today, $fifteenDays]);
                    });
                } else {
                    $query->where(function ($q) use ($status) {
                        $q->where('status', $status)
                          ->orWhere('renewal_status', $status);
                    });
                }
            }

            // Date Filters
            if ($request->filled('from_date')) {
                $query->where(function ($q) use ($request) {
                    $q->where('policy_start_date', '>=', $request->from_date)
                      ->orWhere('start_date', '>=', $request->from_date)
                      ->orWhere('delivery_date', '>=', $request->from_date);
                });
            }

            if ($request->filled('to_date')) {
                $query->where(function ($q) use ($request) {
                    $q->where('policy_expiry_date', '<=', $request->to_date)
                      ->orWhere('expiry_date', '<=', $request->to_date);
                });
            }

            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? 15);
            $paginator = $query->paginate($perPage);

            // Format items
            $items = collect($paginator->items())->map(function ($ins) {
                $deliveryDate = $ins->policy_start_date 
                    ?? $ins->delivery_date 
                    ?? $ins->start_date 
                    ?? $ins->deal?->actual_delivery_date 
                    ?? $ins->deal?->expected_delivery_date 
                    ?? $ins->created_at->format('Y-m-d');

                $expireDate = $ins->policy_expiry_date 
                    ?? $ins->next_insurance_date 
                    ?? $ins->expiry_date 
                    ?? Carbon::parse($deliveryDate)->addDays(365)->format('Y-m-d');

                $reminderDays = (int) ($ins->reminder_days_before ?? 15);
                $reminderDate = $ins->reminder_date 
                    ?? Carbon::parse($expireDate)->subDays($reminderDays)->format('Y-m-d');

                $todayStr = Carbon::today()->format('Y-m-d');
                $status = $ins->status;
                if ($status !== 'renewed') {
                    if ($todayStr > $expireDate) {
                        $status = 'expired';
                    } elseif ($todayStr >= $reminderDate) {
                        $status = 'expiring_soon';
                    } else {
                        $status = 'active';
                    }
                }

                return [
                    'id' => $ins->id,
                    'deal_id' => $ins->deal_id ?? $ins->deal?->deal_number ?? null,
                    'customer_id' => $ins->customer_id ?? $ins->lead_id,
                    'customer_name' => $ins->customer_name ?? $ins->deal?->customer_name ?? '',
                    'customer_number' => $ins->customer_phone ?? $ins->deal?->customer_phone ?? '',
                    'customer_phone' => $ins->customer_phone ?? $ins->deal?->customer_phone ?? '',
                    'customer_email' => $ins->customer_email ?? $ins->deal?->customer_email ?? '',
                    'customer_city' => $ins->deal?->customer_city ?? $ins->customer_address ?? '',
                    'model_variant' => $ins->vehicle_name ?? $ins->deal?->model_variant ?? '',
                    'vehicle_name' => $ins->vehicle_name ?? $ins->deal?->model_variant ?? '',
                    'vehicle_number' => $ins->vehicle_number ?? $ins->registration_number ?? $ins->deal?->registration_number ?? '',
                    'registration_number' => $ins->registration_number ?? $ins->vehicle_number ?? '',
                    'insurance_company' => $ins->insurance_company ?? '',
                    'policy_number' => $ins->policy_number ?? '',
                    'insurance_type' => $ins->insurance_type ?? '',
                    'policy_start_date' => $ins->policy_start_date ? Carbon::parse($ins->policy_start_date)->format('Y-m-d') : '',
                    'policy_expiry_date' => $ins->policy_expiry_date ? Carbon::parse($ins->policy_expiry_date)->format('Y-m-d') : '',
                    'delivery_date' => Carbon::parse($deliveryDate)->format('Y-m-d'),
                    'insurance_expire_date' => Carbon::parse($expireDate)->format('Y-m-d'),
                    'reminder_days_before' => $ins->reminder_days_before ? (int) $ins->reminder_days_before : '',
                    'reminder_date' => (!empty($ins->policy_expiry_date) && !empty($ins->reminder_date)) ? Carbon::parse($ins->reminder_date)->format('Y-m-d') : '',
                    'premium_amount' => (float) ($ins->premium_amount ?? 0),
                    'idv_amount' => (float) ($ins->idv_amount ?? 0),
                    'color' => $ins->deal?->color ?? 'Standard',
                    'status' => $status,
                    'renewal_status' => $ins->renewal_status ?? ($status === 'renewed' ? 'Renewed' : 'Pending'),
                    'notes' => $ins->notes ?? '',
                    'created_at' => $ins->created_at ? $ins->created_at->format('Y-m-d H:i:s') : null,
                    'renewal_history' => $ins->renewalHistories ? $ins->renewalHistories->map(function ($h) {
                        return [
                            'id' => $h->id,
                            'policy_number' => $h->policy_number,
                            'insurance_company' => $h->insurance_company,
                            'insurance_type' => $h->insurance_type,
                            'policy_start_date' => $h->policy_start_date ? $h->policy_start_date->format('Y-m-d') : null,
                            'policy_expiry_date' => $h->policy_expiry_date ? $h->policy_expiry_date->format('Y-m-d') : null,
                            'premium_amount' => (float) ($h->premium_amount ?? 0),
                            'reminder_days_before' => (int) ($h->reminder_days_before ?? 15),
                            'reminder_date' => $h->reminder_date ? $h->reminder_date->format('Y-m-d') : null,
                            'renewal_status' => $h->renewal_status,
                            'renewed_at' => $h->renewed_at ? $h->renewed_at->format('Y-m-d H:i') : ($h->created_at ? $h->created_at->format('Y-m-d H:i') : null),
                            'notes' => $h->notes,
                        ];
                    })->values() : [],
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Insurance records retrieved successfully from insurances table',
                'data' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('InsuranceController@getInsuranceRecords error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching insurance records.',
            ], 500);
        }
    }
}
