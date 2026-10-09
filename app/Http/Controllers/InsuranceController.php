<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Insurance;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InsuranceController extends Controller
{
    /**
     * Display a listing of insurance records with search and filters
     */
    public function index(Request $request)
    {
        $query = Insurance::with([
            'deal:id,deal_number,customer_name,customer_phone,model_variant,actual_delivery_date',
            'lead:id,name,phone,email',
            'creator:id,name,role',
            'salesExecutive:id,name,role',
        ]);

        // Filter by Deal ID
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }

        // Filter by Lead ID
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by Insurance Company
        if ($request->filled('insurance_company')) {
            $query->where('insurance_company', 'like', '%' . trim($request->insurance_company) . '%');
        }

        // Filter by Policy Type (New Policy, Renewal, Rollover)
        if ($request->filled('policy_type')) {
            $query->where('policy_type', $request->policy_type);
        }

        // Filter by Status (active, expiring_soon, expired, renewed)
        if ($request->filled('status')) {
            $status = $request->status;
            $today = Carbon::today()->format('Y-m-d');
            $thirtyDaysFromNow = Carbon::today()->addDays(30)->format('Y-m-d');

            if ($status === 'expiring_soon') {
                $query->where('status', '!=', 'renewed')
                      ->whereDate('next_insurance_date', '>=', $today)
                      ->whereDate('next_insurance_date', '<=', $thirtyDaysFromNow);
            } elseif ($status === 'expired') {
                $query->where('status', '!=', 'renewed')
                      ->whereDate('next_insurance_date', '<', $today);
            } elseif ($status === 'active') {
                $query->where('status', '!=', 'renewed')
                      ->whereDate('next_insurance_date', '>', $thirtyDaysFromNow);
            } else {
                $query->where('status', $status);
            }
        }

        // Search Filter (Policy Number, Customer Name, Phone, Vehicle, Registration, Chassis Number)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('policy_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('vehicle_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%")
                  ->orWhere('vin_chassis_number', 'like', "%{$search}%")
                  ->orWhere('insurance_company', 'like', "%{$search}%");
            });
        }

        // Date range filters for next_insurance_date
        if ($request->filled('from_date')) {
            $query->whereDate('next_insurance_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('next_insurance_date', '<=', $request->to_date);
        }

        // Pagination or Full List
        $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1;

        if (!$isAll) {
            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->orderBy('next_insurance_date', 'asc')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Insurance records retrieved successfully',
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

        $insurances = $query->orderBy('next_insurance_date', 'asc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Insurance records retrieved successfully',
            'data' => $insurances,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $insurances->count(),
                'total' => $insurances->count(),
                'from' => $insurances->count() > 0 ? 1 : null,
                'to' => $insurances->count(),
            ],
            'total' => $insurances->count(),
        ]);
    }

    /**
     * Get upcoming due insurance renewal reminders (for operator dashboard & reminder page)
     */
    public function dueReminders(Request $request)
    {
        $withinDays = (int) $request->get('within_days', 30);
        $today = Carbon::today()->format('Y-m-d');
        $dueDateLimit = Carbon::today()->addDays($withinDays)->format('Y-m-d');

        $query = Insurance::with([
            'deal:id,deal_number,customer_name,customer_phone,model_variant,actual_delivery_date',
            'salesExecutive:id,name,role',
        ])
        ->where('status', '!=', 'renewed')
        ->whereDate('next_insurance_date', '<=', $dueDateLimit);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('policy_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('vehicle_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        // Summary counts before pagination
        $allMatching = (clone $query)->get();
        $expiredCount = $allMatching->filter(fn($i) => Carbon::parse($i->next_insurance_date)->lt(Carbon::today()))->count();
        $expiringSoonCount = $allMatching->filter(fn($i) => Carbon::parse($i->next_insurance_date)->gte(Carbon::today()))->count();

        // Pagination or Full List
        $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1;

        if (!$isAll && ($request->filled('per_page') || $request->filled('page') || $request->filled('limit') || $request->filled('pageSize'))) {
            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->orderBy('next_insurance_date', 'asc')->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Upcoming insurance renewal reminders retrieved successfully',
                'data' => [
                    'reminders' => $paginated->items(),
                    'summary' => [
                        'total_due_reminders' => $allMatching->count(),
                        'expired_count' => $expiredCount,
                        'expiring_soon_count' => $expiringSoonCount,
                        'filter_within_days' => $withinDays,
                    ],
                ],
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

        $reminders = $query->orderBy('next_insurance_date', 'asc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Upcoming insurance renewal reminders retrieved successfully',
            'data' => [
                'reminders' => $reminders,
                'summary' => [
                    'total_due_reminders' => $reminders->count(),
                    'expired_count' => $expiredCount,
                    'expiring_soon_count' => $expiringSoonCount,
                    'filter_within_days' => $withinDays,
                ],
            ],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $reminders->count(),
                'total' => $reminders->count(),
                'from' => $reminders->count() > 0 ? 1 : null,
                'to' => $reminders->count(),
            ],
        ]);
    }

    /**
     * Summary KPIs for Insurance module
     */
    public function stats(Request $request)
    {
        $today = Carbon::today()->format('Y-m-d');
        $thirtyDaysFromNow = Carbon::today()->addDays(30)->format('Y-m-d');
        $thisMonthStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $thisMonthEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

        $totalInsurances = Insurance::count();
        $totalPremium = (float) Insurance::sum('premium_amount');

        $activeCount = Insurance::where('status', '!=', 'renewed')
            ->whereDate('next_insurance_date', '>', $thirtyDaysFromNow)
            ->count();

        $expiringSoonCount = Insurance::where('status', '!=', 'renewed')
            ->whereDate('next_insurance_date', '>=', $today)
            ->whereDate('next_insurance_date', '<=', $thirtyDaysFromNow)
            ->count();

        $expiredCount = Insurance::where('status', '!=', 'renewed')
            ->whereDate('next_insurance_date', '<', $today)
            ->count();

        $renewedCount = Insurance::where('status', 'renewed')->count();

        $dueThisMonthCount = Insurance::where('status', '!=', 'renewed')
            ->whereDate('next_insurance_date', '>=', $thisMonthStart)
            ->whereDate('next_insurance_date', '<=', $thisMonthEnd)
            ->count();

        $dueThisMonthPremium = (float) Insurance::where('status', '!=', 'renewed')
            ->whereDate('next_insurance_date', '>=', $thisMonthStart)
            ->whereDate('next_insurance_date', '<=', $thisMonthEnd)
            ->sum('premium_amount');

        // Company breakdown
        $companyBreakdown = Insurance::select('insurance_company', DB::raw('count(*) as count'), DB::raw('sum(premium_amount) as total_premium'))
            ->groupBy('insurance_company')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Insurance KPI metrics retrieved successfully',
            'data' => [
                'total_insurances' => $totalInsurances,
                'total_premium_collected' => $totalPremium,
                'active_policies' => $activeCount,
                'expiring_soon' => $expiringSoonCount,
                'expired' => $expiredCount,
                'renewed' => $renewedCount,
                'due_this_month_count' => $dueThisMonthCount,
                'due_this_month_premium' => $dueThisMonthPremium,
                'company_breakdown' => $companyBreakdown,
            ],
        ]);
    }

    /**
     * Get auto-filled insurance template from a Deal ID
     */
    public function getDealTemplate($dealId)
    {
        $deal = Deal::with(['lead', 'salesExecutive'])->find($dealId);

        if (!$deal) {
            return response()->json([
                'status' => false,
                'message' => 'Deal not found',
            ], 404);
        }

        $deliveryDate = $deal->actual_delivery_date ?? $deal->expected_delivery_date ?? $deal->booking_date ?? Carbon::today()->format('Y-m-d');
        $startDate = Carbon::parse($deliveryDate)->format('Y-m-d');
        $expiryDate = Carbon::parse($startDate)->addYear()->subDay()->format('Y-m-d');
        $nextInsuranceDate = Carbon::parse($startDate)->addYear()->format('Y-m-d');

        return response()->json([
            'status' => true,
            'message' => 'Deal insurance template generated successfully',
            'data' => [
                'deal_id' => $deal->id,
                'lead_id' => $deal->lead_id,
                'customer_name' => $deal->customer_name,
                'customer_phone' => $deal->customer_phone,
                'customer_email' => $deal->customer_email,
                'customer_address' => $deal->customer_address,
                'vehicle_name' => $deal->model_variant ?? trim("{$deal->brand_name} Vehicle"),
                'registration_number' => $deal->registration_number ?? '',
                'vin_chassis_number' => $deal->vin_chassis_number ?? '',
                'engine_number' => $deal->engine_number ?? '',
                'delivery_date' => $deliveryDate,
                'start_date' => $startDate,
                'expiry_date' => $expiryDate,
                'next_insurance_date' => $nextInsuranceDate,
                'insurance_company' => 'ICICI Lombard',
                'insurance_type' => 'Comprehensive',
                'policy_type' => 'New Policy',
                'premium_amount' => 0.00,
                'idv_amount' => (float) ($deal->net_amount ?? 0.00),
                'sales_executive_id' => $deal->sales_executive_id,
                'sales_executive_name' => $deal->sales_executive_name,
            ],
        ]);
    }

    /**
     * Store a newly created insurance record
     */
    public function store(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $validated = $request->validate([
            'deal_id' => 'nullable|exists:deals,id',
            'lead_id' => 'nullable|exists:leads,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'insurance_company' => 'required|string|max:255',
            'policy_number' => 'nullable|string|max:100',
            'insurance_type' => 'nullable|string|max:100',
            'policy_type' => 'nullable|string|max:100',
            'vehicle_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'vin_chassis_number' => 'nullable|string|max:100',
            'engine_number' => 'nullable|string|max:100',
            'premium_amount' => 'required|numeric|min:0',
            'idv_amount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'next_insurance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'sales_executive_id' => 'nullable|exists:users,id',
            'sales_executive_name' => 'nullable|string|max:150',
        ]);

        // Auto-calculate dates if not provided
        $deliveryDate = $validated['delivery_date'] ?? null;
        $startDate = $validated['start_date'] ?? ($deliveryDate ?? Carbon::today()->format('Y-m-d'));

        $expiryDate = $validated['expiry_date'] ?? Carbon::parse($startDate)->addYear()->subDay()->format('Y-m-d');
        $nextInsuranceDate = $validated['next_insurance_date'] ?? Carbon::parse($startDate)->addYear()->format('Y-m-d');

        // Determine initial status
        $today = Carbon::today();
        $nextDateObj = Carbon::parse($nextInsuranceDate);
        if ($nextDateObj->lt($today)) {
            $status = 'expired';
        } elseif ($nextDateObj->diffInDays($today, false) <= 30 && $nextDateObj->gte($today)) {
            $status = 'expiring_soon';
        } else {
            $status = 'active';
        }

        $insurance = Insurance::create([
            'deal_id' => $validated['deal_id'] ?? null,
            'lead_id' => $validated['lead_id'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'customer_address' => $validated['customer_address'] ?? null,
            'insurance_company' => $validated['insurance_company'],
            'policy_number' => $validated['policy_number'] ?? ('POL-' . rand(100000, 999999)),
            'insurance_type' => $validated['insurance_type'] ?? 'Comprehensive',
            'policy_type' => $validated['policy_type'] ?? 'New Policy',
            'vehicle_name' => $validated['vehicle_name'] ?? null,
            'registration_number' => $validated['registration_number'] ?? null,
            'vin_chassis_number' => $validated['vin_chassis_number'] ?? null,
            'engine_number' => $validated['engine_number'] ?? null,
            'premium_amount' => (float) $validated['premium_amount'],
            'idv_amount' => isset($validated['idv_amount']) ? (float) $validated['idv_amount'] : null,
            'delivery_date' => $deliveryDate,
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'next_insurance_date' => $nextInsuranceDate,
            'status' => $status,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $user?->id,
            'sales_executive_id' => $validated['sales_executive_id'] ?? $user?->id,
            'sales_executive_name' => $validated['sales_executive_name'] ?? $user?->name,
        ]);

        $insurance->load(['deal', 'lead', 'creator', 'salesExecutive']);

        return response()->json([
            'status' => true,
            'message' => "Insurance policy #{$insurance->policy_number} recorded successfully",
            'data' => $insurance,
        ], 201);
    }

    /**
     * Display a specific insurance record
     */
    public function show($id)
    {
        $insurance = Insurance::with([
            'deal',
            'lead',
            'creator',
            'salesExecutive',
        ])->find($id);

        if (!$insurance) {
            return response()->json([
                'status' => false,
                'message' => 'Insurance policy record not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Insurance policy details retrieved successfully',
            'data' => $insurance,
        ]);
    }

    /**
     * Update specified insurance record
     */
    public function update(Request $request, $id)
    {
        $insurance = Insurance::find($id);

        if (!$insurance) {
            return response()->json([
                'status' => false,
                'message' => 'Insurance policy record not found',
            ], 404);
        }

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'customer_phone' => 'sometimes|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'insurance_company' => 'sometimes|string|max:255',
            'policy_number' => 'nullable|string|max:100',
            'insurance_type' => 'nullable|string|max:100',
            'policy_type' => 'nullable|string|max:100',
            'vehicle_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'vin_chassis_number' => 'nullable|string|max:100',
            'engine_number' => 'nullable|string|max:100',
            'premium_amount' => 'sometimes|numeric|min:0',
            'idv_amount' => 'nullable|numeric|min:0',
            'delivery_date' => 'nullable|date',
            'start_date' => 'sometimes|date',
            'expiry_date' => 'sometimes|date',
            'next_insurance_date' => 'sometimes|date',
            'status' => 'sometimes|string|in:active,expiring_soon,expired,renewed',
            'notes' => 'nullable|string',
            'sales_executive_id' => 'nullable|exists:users,id',
            'sales_executive_name' => 'nullable|string|max:150',
        ]);

        $insurance->update($validated);
        $insurance->load(['deal', 'lead', 'creator', 'salesExecutive']);

        return response()->json([
            'status' => true,
            'message' => 'Insurance policy record updated successfully',
            'data' => $insurance,
        ]);
    }

    /**
     * Policy Renewal Endpoint (Renews an existing policy and logs next renewal record)
     */
    public function renew(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $oldPolicy = Insurance::find($id);

        if (!$oldPolicy) {
            return response()->json([
                'status' => false,
                'message' => 'Original insurance policy record not found',
            ], 404);
        }

        $validated = $request->validate([
            'new_policy_number' => 'nullable|string|max:100',
            'new_insurance_company' => 'nullable|string|max:255',
            'new_premium_amount' => 'required|numeric|min:0',
            'new_idv_amount' => 'nullable|numeric|min:0',
            'new_start_date' => 'nullable|date',
            'new_expiry_date' => 'nullable|date',
            'new_next_insurance_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Mark old policy as renewed
        $oldPolicy->status = 'renewed';
        $oldPolicy->save();

        // Calculate renewal dates
        $newStartDate = $validated['new_start_date'] ?? Carbon::parse($oldPolicy->expiry_date)->addDay()->format('Y-m-d');
        $newExpiryDate = $validated['new_expiry_date'] ?? Carbon::parse($newStartDate)->addYear()->subDay()->format('Y-m-d');
        $newNextDate = $validated['new_next_insurance_date'] ?? Carbon::parse($newStartDate)->addYear()->format('Y-m-d');

        // Create new renewed policy record
        $newPolicy = Insurance::create([
            'deal_id' => $oldPolicy->deal_id,
            'lead_id' => $oldPolicy->lead_id,
            'customer_name' => $oldPolicy->customer_name,
            'customer_phone' => $oldPolicy->customer_phone,
            'customer_email' => $oldPolicy->customer_email,
            'customer_address' => $oldPolicy->customer_address,
            'vehicle_name' => $oldPolicy->vehicle_name,
            'registration_number' => $oldPolicy->registration_number,
            'vin_chassis_number' => $oldPolicy->vin_chassis_number,
            'engine_number' => $oldPolicy->engine_number,
            'insurance_company' => $validated['new_insurance_company'] ?? $oldPolicy->insurance_company,
            'policy_number' => $validated['new_policy_number'] ?? ('REN-' . rand(100000, 999999)),
            'insurance_type' => $oldPolicy->insurance_type,
            'policy_type' => 'Renewal',
            'premium_amount' => (float) $validated['new_premium_amount'],
            'idv_amount' => isset($validated['new_idv_amount']) ? (float) $validated['new_idv_amount'] : $oldPolicy->idv_amount,
            'delivery_date' => $oldPolicy->delivery_date,
            'start_date' => $newStartDate,
            'expiry_date' => $newExpiryDate,
            'next_insurance_date' => $newNextDate,
            'status' => 'active',
            'notes' => "Renewed from policy #{$oldPolicy->policy_number}. " . ($validated['notes'] ?? ''),
            'created_by' => $user?->id,
            'sales_executive_id' => $oldPolicy->sales_executive_id,
            'sales_executive_name' => $oldPolicy->sales_executive_name,
        ]);

        $newPolicy->load(['deal', 'lead', 'creator', 'salesExecutive']);

        return response()->json([
            'status' => true,
            'message' => "Policy renewed successfully! New policy #{$newPolicy->policy_number} generated for next insurance date {$newNextDate}",
            'data' => [
                'renewed_policy' => $newPolicy,
                'previous_policy_id' => $oldPolicy->id,
            ],
        ], 201);
    }

    /**
     * Remove the specified insurance record
     */
    public function destroy($id)
    {
        $insurance = Insurance::find($id);

        if (!$insurance) {
            return response()->json([
                'status' => false,
                'message' => 'Insurance policy record not found',
            ], 404);
        }

        $policyNo = $insurance->policy_number;
        $insurance->delete();

        return response()->json([
            'status' => true,
            'message' => "Insurance policy #{$policyNo} deleted successfully",
        ]);
    }
}
