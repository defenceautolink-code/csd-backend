<?php

namespace App\Http\Controllers;

use App\Mail\QuotationMailable;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuotationController extends Controller
{
    /**
     * Get lead details formatted specifically for quotation creation
     */
    public function getLeadForQuotation($lead_id)
    {
        $user = auth()->user();

        $lead = Lead::with(['brand', 'model', 'variant', 'source', 'status', 'assignedUser'])->find($lead_id);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $isAssigned = ($lead->assigned_to == $user->id) ||
                (stripos($lead->assigned_user_name ?? '', $user->name) !== false);
            if (!$isAssigned) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to access quotations for this lead.',
                ], 403);
            }
        }

        $address = trim(implode(', ', array_filter([$lead->city, $lead->state])));
        $subject = 'Price Quotation – ' . ($lead->model_variant ?: ($lead->brand_name ? $lead->brand_name . ' Vehicle' : 'Vehicle Requirement'));

        return response()->json([
            'status' => true,
            'message' => 'Lead quotation data retrieved successfully',
            'data' => [
                'lead_id' => $lead->id,
                'customer_name' => $lead->name,
                'company_name' => '',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'address' => $address,
                'city' => $lead->city,
                'state' => $lead->state,
                'vehicle_segment' => $lead->vehicle_segment,
                'brand_id' => $lead->brand_id,
                'brand_name' => $lead->brand_name,
                'model_variant' => $lead->model_variant,
                'suggested_subject' => $subject,
                'suggested_item' => [
                    'name' => $lead->model_variant ?: ($lead->brand_name ?: 'Vehicle Purchase'),
                    'description' => trim(implode(' • ', array_filter([$lead->vehicle_segment, $lead->brand_name]))),
                    'quantity' => 1,
                    'unit_price' => 0,
                    'discount' => 0,
                    'tax' => 18, // standard GST percentage default
                ],
            ],
        ]);
    }

    /**
     * Get complete lead details along with all quotations for the lead
     */
    public function getByLead(Request $request, $lead_id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $lead = Lead::with([
            'brand',
            'model',
            'variant',
            'source',
            'status',
            'assignedUser:id,name,email,phone,role',
            'latestAssignment',
            'quotations' => function ($q) {
                $q->with(['items', 'creator:id,name,email,phone,role'])->latest('id');
            }
        ])->find($lead_id);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isAssigned = ($lead->assigned_to == $user->id) ||
                (stripos($lead->assigned_user_name ?? '', $user->name) !== false);
            if (!$isAssigned) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to access quotations for this lead.',
                ], 403);
            }
        }

        $address = trim(implode(', ', array_filter([$lead->city, $lead->state])));
        $subject = 'Price Quotation – ' . ($lead->model_variant ?: ($lead->brand_name ? $lead->brand_name . ' Vehicle' : 'Vehicle Requirement'));

        $quotations = $lead->quotations ?? collect();

        $summary = [
            'total_quotations' => $quotations->count(),
            'latest_quotation_id' => $quotations->first()?->id,
            'latest_quotation_number' => $quotations->first()?->quotation_number,
            'latest_quotation_amount' => $quotations->first()?->grand_total,
            'latest_quotation_status' => $quotations->first()?->status,
            'has_accepted_quotation' => $quotations->contains('status', 'accepted'),
        ];

        return response()->json([
            'status' => true,
            'message' => 'Lead and quotations data retrieved successfully',
            'data' => [
                'lead' => [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'city' => $lead->city,
                    'state' => $lead->state,
                    'address' => $address,
                    'birth_date' => $lead->birth_date ? ($lead->birth_date instanceof \DateTimeInterface ? $lead->birth_date->format('Y-m-d') : $lead->birth_date) : null,
                    'anniversary_date' => $lead->anniversary_date ? ($lead->anniversary_date instanceof \DateTimeInterface ? $lead->anniversary_date->format('Y-m-d') : $lead->anniversary_date) : null,
                    'is_birthday_today' => $lead->is_birthday_today,
                    'is_anniversary_today' => $lead->is_anniversary_today,
                    'vehicle_segment' => $lead->vehicle_segment,
                    'brand_id' => $lead->brand_id,
                    'brand_name' => $lead->brand_name,
                    'brand' => $lead->brand,
                    'model_variant' => $lead->model_variant,
                    'priority' => $lead->priority,
                    'purchase_timeline' => $lead->purchase_timeline,
                    'source_id' => $lead->source_id,
                    'source_name' => $lead->source_name,
                    'source' => $lead->source,
                    'status_id' => $lead->status_id,
                    'status_name' => $lead->status_name,
                    'status' => $lead->status,
                    'assigned_to' => $lead->assigned_to,
                    'assigned_user_name' => $lead->assigned_user_name,
                    'assigned_to_display' => $lead->assigned_to_display,
                    'assigned_user' => $lead->assignedUser,
                    'suggested_subject' => $subject,
                    'suggested_item' => [
                        'name' => $lead->model_variant ?: ($lead->brand_name ?: 'Vehicle Purchase'),
                        'description' => trim(implode(' • ', array_filter([$lead->vehicle_segment, $lead->brand_name]))),
                        'quantity' => 1,
                        'unit_price' => 0,
                        'discount' => 0,
                        'tax' => 18,
                    ],
                    'created_at' => $lead->created_at,
                    'updated_at' => $lead->updated_at,
                ],
                'summary' => $summary,
                'quotations' => $quotations,
            ],
        ]);
    }

    /**
     * Display a listing of quotations (with search, status filter, date filter, pagination)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Quotation::with([
            'lead:id,name,phone,email,model_variant,brand_name,vehicle_segment,assigned_to,assigned_user_name',
            'creator:id,name,role',
            'items',
        ]);

        // Scoping for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('lead', function ($leadQuery) use ($user) {
                      $leadQuery->where('assigned_to', $user->id)
                                ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
                  });
            });
        }

        // Search Filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Status Filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtolower($request->status));
        }

        // Filter by Lead ID
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by Quotation Date Range
        if ($request->filled('date')) {
            $query->whereDate('quotation_date', $request->date);
        }
        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('quotation_date', [$request->from_date, $request->to_date]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $quotations = $query->latest('id')->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Quotations retrieved successfully',
            'data' => $quotations->items(),
            'pagination' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Store a newly created quotation with items
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'quotation_number' => 'nullable|string|max:100|unique:quotations,quotation_number',
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'customer_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:1000',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,sent,accepted,rejected,expired',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        // Permission check if lead is specified by Sales Executive
        if ($user && $user->role === 'Sales Executive' && !empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            if ($lead) {
                $isAssigned = ($lead->assigned_to == $user->id) ||
                    (stripos($lead->assigned_user_name ?? '', $user->name) !== false);
                if (!$isAssigned) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You do not have permission to create a quotation for this lead.',
                    ], 403);
                }
            }
        }

        // Auto-generate unique Quotation Number if empty
        $quotationNumber = !empty($validated['quotation_number'])
            ? trim($validated['quotation_number'])
            : Quotation::generateQuotationNumber();

        // Calculate authoritative financials on backend
        [$subtotal, $totalDiscount, $totalTax, $grandTotal, $processedItems] = $this->calculateFinancials($validated['items']);

        $quotation = DB::transaction(function () use ($validated, $quotationNumber, $subtotal, $totalDiscount, $totalTax, $grandTotal, $processedItems, $user) {
            $quote = Quotation::create([
                'lead_id' => $validated['lead_id'] ?? null,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'customer_name' => $validated['customer_name'],
                'company_name' => $validated['company_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'subject' => $validated['subject'],
                'description' => $validated['description'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'tax' => $totalTax,
                'grand_total' => $grandTotal,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'delivery_terms' => $validated['delivery_terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'created_by' => $user?->id,
            ]);

            foreach ($processedItems as $itemData) {
                $itemData['quotation_id'] = $quote->id;
                QuotationItem::create($itemData);
            }

            return $quote;
        });

        $quotation->load(['items', 'lead', 'creator']);

        // If quotation is created directly with 'sent' status and linked to a lead, log follow-up entry
        if (($validated['status'] ?? 'draft') === 'sent' && !empty($validated['lead_id'])) {
            $lead = Lead::find($validated['lead_id']);
            if ($lead) {
                LeadFollowUp::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user?->id ?? ($lead->assigned_to ?? 1),
                    'follow_up_date' => now()->toDateString(),
                    'follow_up_time' => now()->format('h:i A'),
                    'type' => 'Email',
                    'notes' => "Quotation Sent: Official Quotation #{$quotationNumber} generated & sent for {$validated['subject']} (Grand Total: ₹" . number_format($grandTotal, 2) . ").",
                    'status' => 'Completed',
                ]);

                $quotationSentStatus = LeadStatus::where('name', 'Quotation Sent')->first();
                $lead->update([
                    'status_name' => 'Quotation Sent',
                    'status_id' => $quotationSentStatus ? $quotationSentStatus->id : $lead->status_id,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Quotation created successfully',
            'data' => $quotation,
        ], 201);
    }

    /**
     * Display the specified quotation
     */
    public function show($id)
    {
        $user = auth()->user();

        $quotation = Quotation::with([
            'items',
            'lead:id,name,phone,email,model_variant,brand_name,vehicle_segment,city,state,assigned_to,assigned_user_name',
            'creator:id,name,email,role',
        ])->find($id);

        if (!$quotation) {
            return response()->json([
                'status' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $hasAccess = ($quotation->created_by == $user->id) ||
                ($quotation->lead && ($quotation->lead->assigned_to == $user->id || stripos($quotation->lead->assigned_user_name ?? '', $user->name) !== false));
            if (!$hasAccess) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to view this quotation.',
                ], 403);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Quotation details retrieved successfully',
            'data' => $quotation,
        ]);
    }

    /**
     * Update the specified quotation
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $quotation = Quotation::with('lead')->find($id);

        if (!$quotation) {
            return response()->json([
                'status' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $hasAccess = ($quotation->created_by == $user->id) ||
                ($quotation->lead && ($quotation->lead->assigned_to == $user->id || stripos($quotation->lead->assigned_user_name ?? '', $user->name) !== false));
            if (!$hasAccess) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to modify this quotation.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'quotation_number' => 'nullable|string|max:100|unique:quotations,quotation_number,' . $id,
            'quotation_date' => 'required|date',
            'valid_until' => 'nullable|date',
            'customer_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:1000',
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,sent,accepted,rejected,expired',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        [$subtotal, $totalDiscount, $totalTax, $grandTotal, $processedItems] = $this->calculateFinancials($validated['items']);

        DB::transaction(function () use ($quotation, $validated, $subtotal, $totalDiscount, $totalTax, $grandTotal, $processedItems) {
            $quotation->update([
                'lead_id' => $validated['lead_id'] ?? $quotation->lead_id,
                'quotation_number' => $validated['quotation_number'] ?? $quotation->quotation_number,
                'quotation_date' => $validated['quotation_date'],
                'valid_until' => $validated['valid_until'] ?? null,
                'customer_name' => $validated['customer_name'],
                'company_name' => $validated['company_name'] ?? null,
                'customer_email' => $validated['customer_email'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'subject' => $validated['subject'],
                'description' => $validated['description'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'tax' => $totalTax,
                'grand_total' => $grandTotal,
                'payment_terms' => $validated['payment_terms'] ?? null,
                'delivery_terms' => $validated['delivery_terms'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => $validated['status'] ?? $quotation->status,
            ]);

            // Sync items: remove old ones and insert recalculated items
            $quotation->items()->delete();
            foreach ($processedItems as $itemData) {
                $itemData['quotation_id'] = $quotation->id;
                QuotationItem::create($itemData);
            }
        });

        $quotation->load(['items', 'lead', 'creator']);

        return response()->json([
            'status' => true,
            'message' => 'Quotation updated successfully',
            'data' => $quotation,
        ]);
    }

    /**
     * Remove the specified quotation
     */
    public function destroy($id)
    {
        $user = auth()->user();

        $quotation = Quotation::with('lead')->find($id);

        if (!$quotation) {
            return response()->json([
                'status' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $hasAccess = ($quotation->created_by == $user->id) ||
                ($quotation->lead && ($quotation->lead->assigned_to == $user->id || stripos($quotation->lead->assigned_user_name ?? '', $user->name) !== false));
            if (!$hasAccess) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to delete this quotation.',
                ], 403);
            }
        }

        $quotation->delete();

        return response()->json([
            'status' => true,
            'message' => 'Quotation deleted successfully',
        ]);
    }

    /**
     * Send Quotation to customer email with attached PDF
     */
    public function sendEmail(Request $request, $id)
    {
        $user = auth()->user();

        $quotation = Quotation::with(['items', 'lead', 'creator'])->find($id);

        if (!$quotation) {
            return response()->json([
                'status' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $hasAccess = ($quotation->created_by == $user->id) ||
                ($quotation->lead && ($quotation->lead->assigned_to == $user->id || stripos($quotation->lead->assigned_user_name ?? '', $user->name) !== false));
            if (!$hasAccess) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to send this quotation.',
                ], 403);
            }
        }

        $recipientEmail = trim($request->input('recipient_email', $quotation->customer_email));

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => false,
                'message' => 'A valid customer email address is required to send the quotation.',
            ], 422);
        }

        try {
            // Send email using Mailable
            Mail::to($recipientEmail)->send(new QuotationMailable(
                $quotation,
                $request->input('custom_subject'),
                $request->input('custom_message')
            ));

            // Update quotation status & timestamp
            $quotation->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Add one entry in lead follow-up & update lead status to 'Quotation Sent'
            if ($quotation->lead_id) {
                $lead = Lead::find($quotation->lead_id);
                if ($lead) {
                    LeadFollowUp::create([
                        'lead_id' => $lead->id,
                        'user_id' => $user?->id ?? ($quotation->created_by ?? ($lead->assigned_to ?? 1)),
                        'follow_up_date' => now()->toDateString(),
                        'follow_up_time' => now()->format('h:i A'),
                        'type' => 'Email',
                        'notes' => "Quotation Sent: Official Quotation #{$quotation->quotation_number} emailed to {$recipientEmail} for {$quotation->subject} (Grand Total: ₹" . number_format($quotation->grand_total, 2) . ").",
                        'status' => 'Completed',
                    ]);

                    $quotationSentStatus = LeadStatus::where('name', 'Quotation Sent')->first();
                    $lead->update([
                        'status_name' => 'Quotation Sent',
                        'status_id' => $quotationSentStatus ? $quotationSentStatus->id : $lead->status_id,
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'message' => "Quotation #{$quotation->quotation_number} has been successfully sent to {$recipientEmail}.",
                'data' => $quotation->fresh(['items', 'lead', 'creator']),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send quotation email [ID: {$id}]: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send quotation email. Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download or stream generated Quotation PDF
     */
    public function downloadPdf($id)
    {
        $user = auth()->user();

        $quotation = Quotation::with(['items', 'lead', 'creator'])->find($id);

        if (!$quotation) {
            return response()->json([
                'status' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        // Permission check for Sales Executive
        if ($user && $user->role === 'Sales Executive') {
            $hasAccess = ($quotation->created_by == $user->id) ||
                ($quotation->lead && ($quotation->lead->assigned_to == $user->id || stripos($quotation->lead->assigned_user_name ?? '', $user->name) !== false));
            if (!$hasAccess) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to access this quotation.',
                ], 403);
            }
        }

        $pdf = Pdf::loadView('pdf.quotation', ['quotation' => $quotation]);
        $fileName = "Quotation-{$quotation->quotation_number}.pdf";

        return $pdf->download($fileName);
    }

    /**
     * Helper to compute financials with 100% precision
     */
    protected function calculateFinancials(array $items): array
    {
        $subtotal = 0.00;
        $totalDiscount = 0.00;
        $totalTax = 0.00;
        $processedItems = [];

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $itemDiscount = (float) ($item['discount'] ?? 0);
            $taxPercent = (float) ($item['tax'] ?? 0);

            $itemSubtotal = $qty * $unitPrice;
            $taxableAmount = max(0, $itemSubtotal - $itemDiscount);
            $itemTaxAmount = $taxPercent > 0 ? ($taxableAmount * ($taxPercent / 100)) : 0;
            $lineTotal = round($taxableAmount + $itemTaxAmount, 2);

            $subtotal += $itemSubtotal;
            $totalDiscount += $itemDiscount;
            $totalTax += $itemTaxAmount;

            $processedItems[] = [
                'item_name' => $item['item_name'],
                'description' => $item['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $itemDiscount,
                'tax' => $taxPercent,
                'total' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $totalDiscount = round($totalDiscount, 2);
        $totalTax = round($totalTax, 2);
        $grandTotal = round(max(0, $subtotal - $totalDiscount + $totalTax), 2);

        return [$subtotal, $totalDiscount, $totalTax, $grandTotal, $processedItems];
    }
}
