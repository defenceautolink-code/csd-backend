<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Insurance;
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
            $insurances = Insurance::with(['deal'])->latest('id')->get();

            $records = collect();

            // Process only Insurance table records
            foreach ($insurances as $ins) {
                $deliveryDate = $ins->delivery_date 
                    ?? $ins->start_date 
                    ?? $ins->deal?->actual_delivery_date 
                    ?? $ins->deal?->expected_delivery_date 
                    ?? $ins->created_at->format('Y-m-d');

                $deliveryCarbon = Carbon::parse($deliveryDate);

                // Insurance Expire Date = 365 days (1 year) after delivery date
                $expireDate = $ins->next_insurance_date 
                    ?? $ins->expiry_date 
                    ?? $deliveryCarbon->copy()->addDays(365)->format('Y-m-d');

                // Reminder Date = 15 days before expire date (350 days after delivery)
                $reminderDate = Carbon::parse($expireDate)->subDays(15)->format('Y-m-d');

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
                    'customer_name' => $ins->customer_name ?? $ins->deal?->customer_name ?? '',
                    'customer_number' => $ins->customer_phone ?? $ins->deal?->customer_phone ?? '',
                    'customer_email' => $ins->customer_email ?? $ins->deal?->customer_email ?? '',
                    'customer_city' => $ins->deal?->customer_city ?? $ins->customer_address ?? '',
                    'model_variant' => $ins->vehicle_name ?? $ins->deal?->model_variant ?? '',
                    'color' => $ins->deal?->color ?? '',
                    'delivery_date' => Carbon::parse($deliveryDate)->format('Y-m-d'),
                    'insurance_expire_date' => Carbon::parse($expireDate)->format('Y-m-d'),
                    'reminder_date' => $reminderDate,
                    'status' => $status,
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
                // By default: Fetch only records whose insurance_expire_date falls between Today and Today + 15 Days
                $records = $records->filter(fn($item) => $item['insurance_expire_date'] >= $today && $item['insurance_expire_date'] <= $targetExpiryEnd);
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
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'required|string|max:50',
                'customer_number' => 'nullable|string|max:50',
                'customer_email' => 'nullable|email|max:255',
                'customer_address' => 'nullable|string',
                'customer_city' => 'nullable|string|max:100',
                'vehicle_name' => 'nullable|string|max:255',
                'model_variant' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:100',
                'registration_number' => 'nullable|string|max:100',
                'vin_chassis_number' => 'nullable|string|max:100',
                'engine_number' => 'nullable|string|max:100',
                'premium_amount' => 'nullable|numeric|min:0',
                'idv_amount' => 'nullable|numeric|min:0',
                'delivery_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            $customerPhone = $validated['customer_number'] ?? $validated['customer_phone'];
            $modelVariant = $validated['model_variant'] ?? $validated['vehicle_name'] ?? 'Vehicle Policy';
            $customerCity = $validated['customer_city'] ?? $validated['customer_address'] ?? '';

            // Fetch color from Deal model if deal_id is present
            $deal = !empty($validated['deal_id']) ? Deal::find($validated['deal_id']) : null;
            $color = $deal?->color ?? $validated['color'] ?? '';

            // Calculate Delivery, Expire (365 days), and Reminder (15 days before expire / 350 days after delivery)
            $deliveryDate = $validated['delivery_date'] ?? $deal?->actual_delivery_date ?? $deal?->expected_delivery_date ?? now()->format('Y-m-d');
            $deliveryCarbon = Carbon::parse($deliveryDate);

            $expireDate = $deliveryCarbon->copy()->addDays(365)->format('Y-m-d');
            $reminderDate = Carbon::parse($expireDate)->subDays(15)->format('Y-m-d');

            $insurance = Insurance::create([
                'deal_id' => $validated['deal_id'] ?? null,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $customerPhone,
                'customer_email' => $validated['customer_email'] ?? $deal?->customer_email,
                'customer_address' => $customerCity ?: ($deal?->customer_city ?? ''),
                'vehicle_name' => $modelVariant,
                'registration_number' => $validated['registration_number'] ?? null,
                'vin_chassis_number' => $validated['vin_chassis_number'] ?? null,
                'engine_number' => $validated['engine_number'] ?? null,
                'premium_amount' => (float) ($validated['premium_amount'] ?? 0.00),
                'idv_amount' => isset($validated['idv_amount']) ? (float) $validated['idv_amount'] : null,
                'delivery_date' => $deliveryDate,
                'start_date' => $deliveryDate,
                'expiry_date' => $expireDate,
                'next_insurance_date' => $expireDate,
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
                    'color' => $color,
                    'delivery_date' => Carbon::parse($deliveryDate)->format('Y-m-d'),
                    'insurance_expire_date' => Carbon::parse($expireDate)->format('Y-m-d'),
                    'reminder_date' => $reminderDate,
                    'status' => 'active',
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
     * 3. RENEW / UPDATE API: Renew policy for next year
     * Calculation when renewed/updated:
     * - Adds 365 Days to current expire date (e.g. 24-10-2026 -> 24-10-2027)
     * - New Reminder Date = 15 Days before new expire date (e.g. 09-10-2027)
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

            // Current Expire Date
            $currentExpire = $insurance->next_insurance_date ?? $insurance->expiry_date ?? now()->format('Y-m-d');
            $currentExpireCarbon = Carbon::parse($currentExpire);

            // Add 365 Days for Next Year Renewal Expiry Date
            $newExpireDate = $currentExpireCarbon->copy()->addDays(365)->format('Y-m-d');

            // New Reminder Date = 15 Days before new expire date (350 days after previous expire)
            $newReminderDate = Carbon::parse($newExpireDate)->subDays(15)->format('Y-m-d');

            // Update Insurance Record
            $insurance->expiry_date = $newExpireDate;
            $insurance->next_insurance_date = $newExpireDate;
            $insurance->status = 'active';
            $insurance->save();

            $deal = $insurance->deal ?? Deal::find($insurance->deal_id);

            return response()->json([
                'status' => true,
                'message' => "Insurance policy renewed successfully for next year. New Expire Date: {$newExpireDate}, Reminder Date: {$newReminderDate}",
                'data' => [
                    'id' => $insurance->id,
                    'deal_id' => $insurance->deal_id ?? $insurance->id,
                    'customer_name' => $insurance->customer_name ?? $deal?->customer_name ?? '',
                    'customer_number' => $insurance->customer_phone ?? $deal?->customer_phone ?? '',
                    'customer_city' => $deal?->customer_city ?? $insurance->customer_address ?? '',
                    'model_variant' => $insurance->vehicle_name ?? $deal?->model_variant ?? '',
                    'color' => $deal?->color ?? '',
                    'delivery_date' => Carbon::parse($insurance->delivery_date ?? $deal?->actual_delivery_date)->format('Y-m-d'),
                    'insurance_expire_date' => $newExpireDate,
                    'reminder_date' => $newReminderDate,
                    'status' => 'active',
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
}
