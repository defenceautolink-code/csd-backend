<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Quotation;
use App\Models\VehicleModel;
use App\Models\VehiclePriceLog;
use App\Models\VehicleVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehiclePriceMasterController extends Controller
{
    /**
     * Get Complete Vehicle Price Master & Calculator Page Data (Combined Dashboard)
     */
    public function getPricingMaster(Request $request)
    {
        // Selected or Default Variant (e.g. Maruti Suzuki Grand Vitara Alpha+ Strong Hybrid)
        $variantId = $request->input('variant_id');
        $variant = null;

        if ($variantId) {
            $variant = VehicleVariant::with(['brand', 'model'])->find($variantId);
        }

        if (!$variant) {
            $variant = VehicleVariant::with(['brand', 'model'])
                ->where('name', 'like', '%Alpha+%')
                ->first() 
                ?? VehicleVariant::with(['brand', 'model'])->first();
        }

        // Section 1: Update Charges Form Data
        $formData = $this->buildUpdateFormData($variant);

        // Section 2: Live On-Road Calculation Breakdown
        $onRoadBreakdown = $this->calculateOnRoadBreakdown(
            $formData['ex_showroom_price'],
            $formData['rto_road_tax'],
            $formData['insurance'],
            $formData['fastag_logistics'],
            $variant
        );

        // Section 3: Recent Price Revisions & Audit Log
        $revisionsRes = $this->fetchRecentRevisions($request);

        // Brands / Models / Variants dropdown master tree
        $brandTree = Brand::with(['models.variants'])->where('status', 'Active')->get();

        return response()->json([
            'status' => true,
            'message' => 'Vehicle price master & calculator retrieved successfully',
            'data' => [
                'selected_variant' => $formData,
                'estimated_on_road_breakdown' => $onRoadBreakdown,
                'recent_price_revisions' => $revisionsRes['items'],
                'pagination' => $revisionsRes['pagination'],
                'brands_master' => $brandTree,
            ],
        ]);
    }

    /**
     * API for Section 1: Get Pricing Details for a Selected Variant
     */
    public function getVariantPricing(Request $request, $variantId)
    {
        $variant = VehicleVariant::with(['brand', 'model'])->find($variantId);

        if (!$variant) {
            return response()->json([
                'status' => false,
                'message' => 'Vehicle variant not found',
            ], 404);
        }

        $formData = $this->buildUpdateFormData($variant);
        $onRoadBreakdown = $this->calculateOnRoadBreakdown(
            $formData['ex_showroom_price'],
            $formData['rto_road_tax'],
            $formData['insurance'],
            $formData['fastag_logistics'],
            $variant
        );

        return response()->json([
            'status' => true,
            'message' => 'Variant pricing retrieved successfully',
            'data' => [
                'variant_pricing' => $formData,
                'estimated_on_road_breakdown' => $onRoadBreakdown,
            ],
        ]);
    }

    /**
     * API for Section 1 Action: Update & Publish Vehicle Price
     */
    public function updateAndPublishPrice(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|exists:vehicle_variants,id',
            'ex_showroom_price' => 'required|numeric|min:0',
            'rto_road_tax' => 'nullable|numeric|min:0',
            'insurance' => 'nullable|numeric|min:0',
            'fastag_logistics' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        $updatedByName = $user ? $user->name : 'Alexander Vance';

        $variant = VehicleVariant::with(['brand', 'model'])->findOrFail($request->variant_id);

        $previousExShowroom = (float) ($variant->ex_showroom_price > 0 ? $variant->ex_showroom_price : $variant->price);
        $previousOnRoad = (float) $variant->calculated_on_road_price;

        $revisedExShowroom = (float) $request->ex_showroom_price;
        $rtoTax = $request->has('rto_road_tax') ? (float) $request->rto_road_tax : round($revisedExShowroom * 0.10, 2);
        $insurance = $request->has('insurance') ? (float) $request->insurance : 68000.00;
        $fastagLogistics = $request->has('fastag_logistics') ? (float) $request->fastag_logistics : 2500.00;

        $revisedOnRoad = round($revisedExShowroom + $rtoTax + $insurance + $fastagLogistics, 2);
        $netDifference = round($revisedExShowroom - $previousExShowroom, 2);

        // Update Variant Record
        $variant->update([
            'price' => $revisedExShowroom,
            'ex_showroom_price' => $revisedExShowroom,
            'rto_road_tax' => $rtoTax,
            'insurance' => $insurance,
            'fastag_logistics' => $fastagLogistics,
            'on_road_price' => $revisedOnRoad,
        ]);

        // Record Audit Log Entry in vehicle_price_logs
        $modelVariantName = $variant->model ? "{$variant->name} ({$variant->model->name})" : $variant->name;
        $brandName = $variant->brand ? $variant->brand->name : ($variant->model?->brand_name ?? 'Maruti Suzuki');

        $priceLog = VehiclePriceLog::create([
            'variant_id' => $variant->id,
            'model_id' => $variant->model_id,
            'brand_id' => $variant->brand_id,
            'model_variant_name' => $modelVariantName,
            'brand_name' => $brandName,
            'previous_ex_showroom' => $previousExShowroom,
            'revised_ex_showroom' => $revisedExShowroom,
            'net_difference' => $netDifference,
            'rto_road_tax' => $rtoTax,
            'insurance' => $insurance,
            'fastag_logistics' => $fastagLogistics,
            'previous_on_road' => $previousOnRoad,
            'revised_on_road' => $revisedOnRoad,
            'updated_by_id' => $user?->id,
            'updated_by_name' => $updatedByName,
            'revision_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'Active',
        ]);

        $formData = $this->buildUpdateFormData($variant->fresh(['brand', 'model']));
        $onRoadBreakdown = $this->calculateOnRoadBreakdown($revisedExShowroom, $rtoTax, $insurance, $fastagLogistics, $variant);

        return response()->json([
            'status' => true,
            'message' => 'Vehicle price updated and published successfully',
            'data' => [
                'variant_pricing' => $formData,
                'estimated_on_road_breakdown' => $onRoadBreakdown,
                'price_revision_log' => [
                    'id' => $priceLog->id,
                    'revision_date' => Carbon::parse($priceLog->revision_date)->format('M d, Y'),
                    'vehicle_model_variant' => $priceLog->model_variant_name,
                    'brand' => $priceLog->brand_name,
                    'previous_price' => $priceLog->previous_ex_showroom,
                    'formatted_previous_price' => "₹" . number_format($priceLog->previous_ex_showroom, 0),
                    'revised_ex_showroom' => $priceLog->revised_ex_showroom,
                    'formatted_revised_ex_showroom' => "₹" . number_format($priceLog->revised_ex_showroom, 0),
                    'net_difference' => $priceLog->net_difference,
                    'formatted_net_difference' => ($priceLog->net_difference >= 0 ? "+₹" : "-₹") . number_format(abs($priceLog->net_difference), 0),
                    'updated_by' => $priceLog->updated_by_name,
                    'status' => $priceLog->status,
                ],
            ],
        ]);
    }

    /**
     * API for Section 2: Live On-Road Price Calculator
     */
    public function calculateOnRoad(Request $request)
    {
        $exShowroom = (float) ($request->input('ex_showroom_price') ?? $request->input('ex_showroom_base_price') ?? 1999000.00);
        $rtoTax = (float) ($request->input('rto_road_tax') ?? $request->input('rto_tax') ?? round($exShowroom * 0.10, 2));
        $insurance = (float) ($request->input('insurance') ?? 68000.00);
        $fastagLogistics = (float) ($request->input('fastag_logistics') ?? $request->input('fastag_hypothecation') ?? 2500.00);

        $variant = null;
        if ($request->filled('variant_id')) {
            $variant = VehicleVariant::with(['brand', 'model'])->find($request->variant_id);
        }

        $onRoadBreakdown = $this->calculateOnRoadBreakdown($exShowroom, $rtoTax, $insurance, $fastagLogistics, $variant);

        return response()->json([
            'status' => true,
            'message' => 'On-road price calculated successfully',
            'data' => $onRoadBreakdown,
        ]);
    }

    /**
     * API for Section 3: Recent Price Revisions & Audit Log
     */
    public function getPriceRevisions(Request $request)
    {
        $res = $this->fetchRecentRevisions($request);

        return response()->json([
            'status' => true,
            'message' => 'Recent price revisions and audit logs retrieved successfully',
            'data' => $res['items'],
            'pagination' => $res['pagination'],
            'total' => $res['pagination']['total'],
        ]);
    }

    /**
     * Header Action: Send Quotation based on selected variant rates
     */
    public function sendQuotation(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|exists:vehicle_variants,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $variant = VehicleVariant::with(['brand', 'model'])->findOrFail($request->variant_id);
        $exShowroom = (float) ($variant->ex_showroom_price > 0 ? $variant->ex_showroom_price : $variant->price);
        $rtoTax = (float) ($variant->rto_road_tax > 0 ? $variant->rto_road_tax : round($exShowroom * 0.10, 2));
        $insurance = (float) ($variant->insurance > 0 ? $variant->insurance : 68000.00);
        $fastag = (float) ($variant->fastag_logistics > 0 ? $variant->fastag_logistics : 2500.00);
        $onRoad = round($exShowroom + $rtoTax + $insurance + $fastag, 2);

        $quotationNumber = 'QUO-' . date('Y') . '-' . str_pad((string) (Quotation::count() + 1), 4, '0', STR_PAD_LEFT);

        return response()->json([
            'status' => true,
            'message' => 'Quotation payload initialized successfully for ' . $variant->name,
            'data' => [
                'quotation_number' => $quotationNumber,
                'vehicle_summary' => [
                    'brand_name' => $variant->brand?->name ?? 'Maruti Suzuki',
                    'model_name' => $variant->model?->name ?? 'Grand Vitara',
                    'variant_name' => $variant->name,
                ],
                'pricing_breakdown' => [
                    'ex_showroom_price' => $exShowroom,
                    'rto_road_tax' => $rtoTax,
                    'insurance' => $insurance,
                    'fastag_logistics' => $fastag,
                    'total_estimated_on_road_price' => $onRoad,
                    'formatted_on_road_price' => "₹" . number_format($onRoad, 0),
                ],
            ],
        ]);
    }

    /**
     * Header Action: Export Price Master File
     */
    public function exportPriceMaster(Request $request)
    {
        $variants = VehicleVariant::with(['brand', 'model'])->get();

        $exportData = $variants->map(function ($v) {
            $exShowroom = (float) ($v->ex_showroom_price > 0 ? $v->ex_showroom_price : $v->price);
            $rto = (float) ($v->rto_road_tax > 0 ? $v->rto_road_tax : round($exShowroom * 0.10, 2));
            $insurance = (float) ($v->insurance > 0 ? $v->insurance : 68000.00);
            $fastag = (float) ($v->fastag_logistics > 0 ? $v->fastag_logistics : 2500.00);
            $onRoad = round($exShowroom + $rto + $insurance + $fastag, 2);

            return [
                'brand' => $v->brand?->name ?? 'N/A',
                'model' => $v->model?->name ?? 'N/A',
                'variant' => $v->name,
                'ex_showroom_price' => $exShowroom,
                'rto_road_tax' => $rto,
                'insurance' => $insurance,
                'fastag_logistics' => $fastag,
                'on_road_price' => $onRoad,
                'status' => $v->status,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Vehicle Price Master export ready',
            'export_filename' => 'vehicle_price_master_' . date('Y_m_d') . '.csv',
            'total_items' => count($exportData),
            'data' => $exportData,
        ]);
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Build Update Charges Form Structure for a Variant
     */
    private function buildUpdateFormData($variant): array
    {
        if (!$variant) {
            return [
                'vehicle_brand' => 'Maruti Suzuki',
                'brand_id' => 1,
                'model' => 'Grand Vitara',
                'model_id' => 1,
                'variant' => 'Alpha+ Strong Hybrid e-CVT',
                'variant_id' => 1,
                'ex_showroom_price' => 1999000.00,
                'formatted_ex_showroom' => '₹ 19,99,000',
                'rto_road_tax' => 199900.00,
                'formatted_rto_road_tax' => '₹ 1,99,900',
                'insurance' => 68000.00,
                'formatted_insurance' => '₹ 68,000',
                'fastag_logistics' => 2500.00,
                'formatted_fastag_logistics' => '₹ 2,500',
            ];
        }

        $exShowroom = (float) ($variant->ex_showroom_price > 0 ? $variant->ex_showroom_price : $variant->price);
        $rtoTax = (float) ($variant->rto_road_tax > 0 ? $variant->rto_road_tax : round($exShowroom * 0.10, 2));
        $insurance = (float) ($variant->insurance > 0 ? $variant->insurance : 68000.00);
        $fastag = (float) ($variant->fastag_logistics > 0 ? $variant->fastag_logistics : 2500.00);

        return [
            'vehicle_brand' => $variant->brand?->name ?? ($variant->model?->brand_name ?? 'Maruti Suzuki'),
            'brand_id' => $variant->brand_id,
            'model' => $variant->model?->name ?? 'Grand Vitara',
            'model_id' => $variant->model_id,
            'variant' => $variant->name,
            'variant_id' => $variant->id,
            'ex_showroom_price' => $exShowroom,
            'formatted_ex_showroom' => '₹ ' . number_format($exShowroom, 0),
            'rto_road_tax' => $rtoTax,
            'formatted_rto_road_tax' => '₹ ' . number_format($rtoTax, 0),
            'insurance' => $insurance,
            'formatted_insurance' => '₹ ' . number_format($insurance, 0),
            'fastag_logistics' => $fastag,
            'formatted_fastag_logistics' => '₹ ' . number_format($fastag, 0),
        ];
    }

    /**
     * Calculate Live On-Road Price Breakdown
     */
    private function calculateOnRoadBreakdown(float $exShowroom, float $rtoTax, float $insurance, float $fastagLogistics, $variant = null): array
    {
        $onRoadTotal = round($exShowroom + $rtoTax + $insurance + $fastagLogistics, 2);

        $brandName = $variant?->brand?->name ?? 'Maruti Suzuki';
        $modelName = $variant?->model?->name ?? 'Grand Vitara';
        $variantName = $variant?->name ?? 'Alpha+ Strong Hybrid e-CVT';

        return [
            'header' => [
                'tag' => 'Live Calculation',
                'title' => 'Estimated On-Road Breakdown',
                'subtitle' => "{$brandName} • {$modelName} ({$variantName})",
            ],
            'line_items' => [
                [
                    'label' => 'Ex-Showroom Price',
                    'amount' => $exShowroom,
                    'formatted_amount' => '₹' . number_format($exShowroom, 0),
                ],
                [
                    'label' => 'RTO & Road Tax (~10%)',
                    'amount' => $rtoTax,
                    'formatted_amount' => '₹' . number_format($rtoTax, 0),
                ],
                [
                    'label' => 'Comprehensive Insurance (1+3 Yr)',
                    'amount' => $insurance,
                    'formatted_amount' => '₹' . number_format($insurance, 0),
                ],
                [
                    'label' => 'Fastag & Logistics',
                    'amount' => $fastagLogistics,
                    'formatted_amount' => '₹' . number_format($fastagLogistics, 0),
                ],
            ],
            'total' => [
                'label' => 'Total Estimated On-Road Price:',
                'amount' => $onRoadTotal,
                'formatted_amount' => '₹' . number_format($onRoadTotal, 0),
                'badge' => 'Official Dealership Certified Price',
            ],
        ];
    }

    /**
     * Fetch Recent Price Revisions & Audit Log with Pagination
     */
    private function fetchRecentRevisions(Request $request): array
    {
        $query = VehiclePriceLog::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('model_variant_name', 'like', "%{$search}%")
                  ->orWhere('brand_name', 'like', "%{$search}%")
                  ->orWhere('updated_by_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        $isAll = $request->boolean('all') || $request->get('per_page') === 'all' || (int) $request->get('per_page') === -1 || $request->get('paginate') === 'false';

        if (!$isAll) {
            $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? $request->get('pageSize') ?? 10);
            $perPage = $perPage > 0 ? $perPage : 10;
            $paginated = $query->latest('revision_date')->latest('id')->paginate($perPage);

            $items = collect($paginated->items())->map(function ($log) {
                $netDiff = (float) $log->net_difference;
                return [
                    'id' => $log->id,
                    'revision_date' => Carbon::parse($log->revision_date)->format('M d, Y'),
                    'vehicle_model_variant' => $log->model_variant_name,
                    'brand' => $log->brand_name,
                    'previous_price' => (float) $log->previous_ex_showroom,
                    'formatted_previous_price' => '₹' . number_format($log->previous_ex_showroom, 0),
                    'revised_ex_showroom' => (float) $log->revised_ex_showroom,
                    'formatted_revised_ex_showroom' => '₹' . number_format($log->revised_ex_showroom, 0),
                    'net_difference' => $netDiff,
                    'formatted_net_difference' => ($netDiff >= 0 ? '+₹' : '-₹') . number_format(abs($netDiff), 0),
                    'updated_by' => $log->updated_by_name,
                    'status' => $log->status,
                ];
            })->toArray();

            return [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
            ];
        }

        $logs = $query->latest('revision_date')->latest('id')->get();
        $items = $logs->map(function ($log) {
            $netDiff = (float) $log->net_difference;
            return [
                'id' => $log->id,
                'revision_date' => Carbon::parse($log->revision_date)->format('M d, Y'),
                'vehicle_model_variant' => $log->model_variant_name,
                'brand' => $log->brand_name,
                'previous_price' => (float) $log->previous_ex_showroom,
                'formatted_previous_price' => '₹' . number_format($log->previous_ex_showroom, 0),
                'revised_ex_showroom' => (float) $log->revised_ex_showroom,
                'formatted_revised_ex_showroom' => '₹' . number_format($log->revised_ex_showroom, 0),
                'net_difference' => $netDiff,
                'formatted_net_difference' => ($netDiff >= 0 ? '+₹' : '-₹') . number_format(abs($netDiff), 0),
                'updated_by' => $log->updated_by_name,
                'status' => $log->status,
            ];
        })->toArray();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($items),
                'total' => count($items),
                'from' => count($items) > 0 ? 1 : null,
                'to' => count($items),
            ],
        ];
    }
}
