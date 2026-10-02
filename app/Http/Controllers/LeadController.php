<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Get list of all leads with associated relationships & filters
     * Supports backend role-based access control for authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $query = Lead::with([
            'brand',
            'source',
            'status',
            'assignedUser',
            'latestAssignment.assignedByUser',
            'latestAssignment.assignedToUser',
        ]);

        // Backend Role-Based Lead Access:
        // Sales Executive receives only their assigned leads
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('assigned_user_name', 'like', '%' . $user->name . '%');
            });
        }

        // Search across customer name, phone, email, model/variant, city, brand
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('model_variant', 'like', '%' . $search . '%')
                  ->orWhere('city', 'like', '%' . $search . '%')
                  ->orWhere('brand_name', 'like', '%' . $search . '%');
            });
        }

        // Filter by Priority (Hot / Warm / Cold)
        if ($request->filled('priority')) {
            $query->where('priority', ucfirst(strtolower($request->priority)));
        }

        // Filter by Vehicle Segment (2 Wheeler / 4 Wheeler)
        if ($request->filled('vehicle_segment')) {
            $query->where('vehicle_segment', $request->vehicle_segment);
        }

        // Filter by Status Name or Status ID
        if ($request->filled('status')) {
            $query->where('status_name', $request->status);
        }
        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        // Filter by Brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Filter by Source
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }

        // Filter by Assigned User (Admin/Manager filter)
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Handle Pagination or Full List
        $perPage = (int) $request->get('per_page', 0);
        if ($perPage > 0 || $request->filled('page')) {
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $query->latest()->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Leads retrieved successfully',
                'data' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        $leads = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Leads retrieved successfully',
            'data' => $leads,
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => count($leads),
                'total' => count($leads),
            ],
        ]);
    }

    /**
     * Create a new customer lead
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'anniversary_date' => 'nullable|date',
            'vehicle_segment' => 'nullable|string',
            'brand_id' => 'nullable',
            'brand_name' => 'nullable|string|max:255',
            'model_variant' => 'required|string|max:255',
            'priority' => 'nullable|string',
            'purchase_timeline' => 'nullable|string|max:100',
            'source_id' => 'nullable',
            'source_name' => 'nullable|string|max:100',
            'status_id' => 'nullable',
            'status_name' => 'nullable|string|max:100',
            'assigned_to' => 'nullable',
            'assigned_user_name' => 'nullable|string|max:100',
        ]);

        // Auto-fill brand
        $brandId = $request->filled('brand_id') && is_numeric($request->brand_id) ? (int) $request->brand_id : null;
        $brandName = $request->brand_name;
        if ($brandId && !$brandName) {
            $brand = Brand::find($brandId);
            $brandName = $brand ? $brand->name : null;
        } elseif (!$brandId && $brandName) {
            $brand = Brand::where('name', $brandName)->first();
            $brandId = $brand ? $brand->id : null;
        }

        // Auto-fill source
        $sourceId = $request->filled('source_id') && is_numeric($request->source_id) ? (int) $request->source_id : null;
        $sourceName = $request->source_name;
        if ($sourceId && !$sourceName) {
            $source = LeadSource::find($sourceId);
            $sourceName = $source ? $source->title : null;
        } elseif (!$sourceId && $sourceName) {
            $source = LeadSource::where('title', $sourceName)->first();
            $sourceId = $source ? $source->id : null;
        }

        // Auto-fill status
        $statusId = $request->filled('status_id') && is_numeric($request->status_id) ? (int) $request->status_id : null;
        $statusName = $request->status_name;
        if ($statusId && !$statusName) {
            $status = LeadStatus::find($statusId);
            $statusName = $status ? $status->name : 'New';
        } elseif (!$statusId && $statusName) {
            $status = LeadStatus::where('name', $statusName)->first();
            $statusId = $status ? $status->id : null;
        } elseif (!$statusId && !$statusName) {
            $status = LeadStatus::where('name', 'New')->first() ?? LeadStatus::first();
            $statusId = $status ? $status->id : null;
            $statusName = $status ? $status->name : 'New';
        }

        // Vehicle segment normalization
        $segment = $request->filled('vehicle_segment') ? $request->vehicle_segment : '4 Wheeler';
        if (stripos($segment, '2') !== false) {
            $segment = '2 Wheeler';
        } else {
            $segment = '4 Wheeler';
        }

        // Priority normalization
        $priority = 'Hot';
        if ($request->filled('priority')) {
            $p = ucfirst(strtolower($request->priority));
            if (in_array($p, ['Hot', 'Warm', 'Cold'])) {
                $priority = $p;
            }
        }

        // Safe Date Parsing
        $birthDate = $request->filled('birth_date') ? date('Y-m-d', strtotime($request->birth_date)) : null;
        $anniversaryDate = $request->filled('anniversary_date') ? date('Y-m-d', strtotime($request->anniversary_date)) : null;

        [$assignedToId, $assignedUserName] = $this->resolveAssignToUser($request->assigned_to, $request->assigned_user_name);
        $assignById = $this->resolveAssignByUserId($request->assign_by);

        $lead = \Illuminate\Support\Facades\DB::transaction(function () use (
            $request, $brandId, $brandName, $sourceId, $sourceName, $statusId, $statusName,
            $segment, $priority, $birthDate, $anniversaryDate, $assignedToId, $assignedUserName, $assignById
        ) {
            $lead = Lead::create([
                'name' => $request->name,
                'email' => $request->filled('email') ? $request->email : null,
                'phone' => $request->phone,
                'city' => $request->filled('city') ? $request->city : null,
                'state' => $request->filled('state') ? $request->state : null,
                'birth_date' => $birthDate,
                'anniversary_date' => $anniversaryDate,
                'vehicle_segment' => $segment,
                'brand_id' => $brandId,
                'brand_name' => $brandName,
                'model_variant' => $request->model_variant,
                'priority' => $priority,
                'purchase_timeline' => $request->filled('purchase_timeline') ? $request->purchase_timeline : null,
                'source_id' => $sourceId,
                'source_name' => $sourceName,
                'status_id' => $statusId,
                'status_name' => $statusName,
                'assigned_to' => $assignedToId,
                'assigned_user_name' => $assignedUserName,
            ]);

            // Automatically log initial assignment history with resolved user IDs
            if ($assignedToId) {
                LeadAssignmentHistory::create([
                    'lead_id' => $lead->id,
                    'assign_to' => $assignedToId,
                    'assign_by' => $assignById,
                    'remarks' => $request->remarks ?: 'Initial lead creation assignment',
                ]);
            }

            return $lead;
        });

        $lead->load([
            'brand',
            'source',
            'status',
            'assignedUser',
            'latestAssignment.assignedByUser',
            'latestAssignment.assignedToUser',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Customer lead created successfully',
            'data' => $lead,
        ], 201);
    }

    /**
     * Get a single customer lead
     */
    public function show(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

        $lead = Lead::with([
            'brand',
            'source',
            'status',
            'assignedUser',
            'latestAssignment.assignedByUser',
            'latestAssignment.assignedToUser',
            'followUps.user',
            'assignmentHistories.assignedByUser',
        ])->find($id);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        // Scope check for Sales Executive
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isOwner = ($lead->assigned_to == $user->id) || (!empty($lead->assigned_user_name) && stripos($lead->assigned_user_name, $user->name) !== false);
            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to access this lead.',
                ], 403);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Lead details',
            'data' => $lead,
        ]);
    }

    /**
     * Update an existing customer lead
     */
    public function update(Request $request, $id)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        // Scope check for Sales Executive
        if ($user && in_array(strtolower(str_replace(' ', '_', $user->role)), ['sales_executive', 'sales_rep', 'sales_consultant'])) {
            $isOwner = ($lead->assigned_to == $user->id) || (!empty($lead->assigned_user_name) && stripos($lead->assigned_user_name, $user->name) !== false);
            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to update this lead.',
                ], 403);
            }
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'anniversary_date' => 'nullable|date',
            'vehicle_segment' => 'sometimes|required|string|in:2 Wheeler,4 Wheeler,2 wheeler,4 wheeler',
            'brand_id' => 'nullable|exists:brands,id',
            'brand_name' => 'nullable|string|max:255',
            'model_variant' => 'sometimes|required|string|max:255',
            'priority' => 'sometimes|required|string|in:Hot,Warm,Cold,hot,warm,cold',
            'purchase_timeline' => 'nullable|string|max:100',
            'source_id' => 'nullable|exists:lead_sources,id',
            'source_name' => 'nullable|string|max:100',
            'status_id' => 'nullable|exists:lead_statuses,id',
            'status_name' => 'nullable|string|max:100',
            'assigned_to' => 'nullable',
            'assigned_user_name' => 'nullable|string|max:100',
        ]);

        // Auto-fill brand name if brand_id provided
        $brandName = $request->has('brand_name') ? $request->brand_name : $lead->brand_name;
        if ($request->filled('brand_id')) {
            $brand = Brand::find($request->brand_id);
            $brandName = $brand ? $brand->name : $brandName;
        }

        // Auto-fill source name if source_id provided
        $sourceName = $request->has('source_name') ? $request->source_name : $lead->source_name;
        if ($request->filled('source_id')) {
            $source = LeadSource::find($request->source_id);
            $sourceName = $source ? $source->title : $sourceName;
        }

        // Auto-fill status name if status_id provided
        $statusName = $request->has('status_name') ? $request->status_name : $lead->status_name;
        if ($request->filled('status_id')) {
            $status = LeadStatus::find($request->status_id);
            $statusName = $status ? $status->name : $statusName;
        }

        $assignedToInput = $request->has('assigned_to') ? $request->assigned_to : $lead->assigned_to;
        $assignedUserNameInput = $request->has('assigned_user_name') ? $request->assigned_user_name : $lead->assigned_user_name;

        [$assignedToId, $assignedUserName] = $this->resolveAssignToUser($assignedToInput, $assignedUserNameInput);
        $assignById = $this->resolveAssignByUserId($request->assign_by);

        $isReassigned = ($assignedToId && $lead->assigned_to != $assignedToId) ||
                        ($assignedUserName && $lead->assigned_user_name !== $assignedUserName);

        $updateData = [
            'brand_name' => $brandName,
            'source_name' => $sourceName,
            'status_name' => $statusName,
            'assigned_to' => $assignedToId,
            'assigned_user_name' => $assignedUserName,
        ];

        if ($request->has('name')) $updateData['name'] = $request->name;
        if ($request->has('email')) $updateData['email'] = $request->email;
        if ($request->has('phone')) $updateData['phone'] = $request->phone;
        if ($request->has('city')) $updateData['city'] = $request->city;
        if ($request->has('state')) $updateData['state'] = $request->state;
        if ($request->has('birth_date')) $updateData['birth_date'] = $request->birth_date;
        if ($request->has('anniversary_date')) $updateData['anniversary_date'] = $request->anniversary_date;
        if ($request->has('vehicle_segment')) $updateData['vehicle_segment'] = $request->vehicle_segment;
        if ($request->has('brand_id')) $updateData['brand_id'] = $request->brand_id;
        if ($request->has('model_variant')) $updateData['model_variant'] = $request->model_variant;
        if ($request->has('priority')) $updateData['priority'] = ucfirst(strtolower($request->priority));
        if ($request->has('purchase_timeline')) $updateData['purchase_timeline'] = $request->purchase_timeline;
        if ($request->has('source_id')) $updateData['source_id'] = $request->source_id;
        if ($request->has('status_id')) $updateData['status_id'] = $request->status_id;

        $lead->update($updateData);

        // If assignment changed, log to assignment history
        if ($isReassigned && $assignedToId) {
            LeadAssignmentHistory::create([
                'lead_id' => $lead->id,
                'assign_to' => $assignedToId,
                'assign_by' => $assignById,
                'remarks' => $request->assignment_remarks ?: $request->remarks ?: 'Lead executive reassignment',
            ]);
        }

        $lead->load([
            'brand',
            'source',
            'status',
            'assignedUser',
            'latestAssignment.assignedByUser',
            'latestAssignment.assignedToUser',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Customer lead updated successfully',
            'data' => $lead,
        ]);
    }

    /**
     * Delete a customer lead
     */
    public function destroy($id)
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json([
                'status' => false,
                'message' => 'Lead not found',
            ], 404);
        }

        $lead->delete();

        return response()->json([
            'status' => true,
            'message' => 'Customer lead deleted successfully',
        ]);
    }

    /**
     * Bulk Delete multiple customer leads
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required_without:lead_ids|array',
            'ids.*' => 'integer',
            'lead_ids' => 'required_without:ids|array',
            'lead_ids.*' => 'integer',
        ]);

        $ids = $request->input('ids', $request->input('lead_ids', []));
        $count = Lead::whereIn('id', $ids)->delete();

        return response()->json([
            'status' => true,
            'message' => "Successfully deleted {$count} selected leads.",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Bulk Update Status for multiple leads
     */
    public function bulkStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required_without:lead_ids|array',
            'ids.*' => 'integer',
            'lead_ids' => 'required_without:ids|array',
            'lead_ids.*' => 'integer',
            'status_id' => 'nullable|exists:lead_statuses,id',
            'status_name' => 'nullable|string|max:100',
        ]);

        $ids = $request->input('ids', $request->input('lead_ids', []));
        $statusName = $request->status_name;
        $statusId = $request->status_id;

        if ($statusId && !$statusName) {
            $status = LeadStatus::find($statusId);
            $statusName = $status ? $status->name : 'New';
        } elseif ($statusName && !$statusId) {
            $status = LeadStatus::where('name', $statusName)->first();
            $statusId = $status ? $status->id : null;
        }

        $count = Lead::whereIn('id', $ids)->update([
            'status_id' => $statusId,
            'status_name' => $statusName ?: 'New',
        ]);

        return response()->json([
            'status' => true,
            'message' => "Updated status for {$count} selected leads to '{$statusName}'.",
            'updated_count' => $count,
        ]);
    }

    /**
     * Bulk Update Priority for multiple leads
     */
    public function bulkPriority(Request $request)
    {
        $request->validate([
            'ids' => 'required_without:lead_ids|array',
            'ids.*' => 'integer',
            'lead_ids' => 'required_without:ids|array',
            'lead_ids.*' => 'integer',
            'priority' => 'required|string|in:Hot,Warm,Cold,hot,warm,cold',
        ]);

        $ids = $request->input('ids', $request->input('lead_ids', []));
        $priority = ucfirst(strtolower($request->priority));
        $count = Lead::whereIn('id', $ids)->update([
            'priority' => $priority,
        ]);

        return response()->json([
            'status' => true,
            'message' => "Updated priority for {$count} selected leads to '{$priority}'.",
            'updated_count' => $count,
        ]);
    }

    /**
     * Bulk Assign Executive for multiple leads
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'ids' => 'required_without:lead_ids|array',
            'ids.*' => 'integer',
            'lead_ids' => 'required_without:ids|array',
            'lead_ids.*' => 'integer',
            'assigned_to' => 'nullable',
            'assigned_user_name' => 'nullable|string|max:255',
            'assign_by' => 'nullable',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $ids = $request->input('ids', $request->input('lead_ids', []));

        [$assignedToId, $assignedUserName] = $this->resolveAssignToUser($request->assigned_to, $request->assigned_user_name);
        $assignById = $this->resolveAssignByUserId($request->assign_by);

        $count = Lead::whereIn('id', $ids)->update([
            'assigned_to' => $assignedToId,
            'assigned_user_name' => $assignedUserName,
        ]);

        // Bulk record assignment history
        $historyData = [];
        $now = now();

        foreach ($ids as $leadId) {
            $historyData[] = [
                'lead_id' => $leadId,
                'assign_to' => $assignedToId,
                'assign_by' => $assignById,
                'remarks' => $request->remarks ?: 'Bulk lead executive assignment',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($historyData)) {
            LeadAssignmentHistory::insert($historyData);
        }

        return response()->json([
            'status' => true,
            'message' => "Assigned {$count} selected leads to '{$assignedUserName}'.",
            'updated_count' => $count,
            'assigned_to' => $assignedToId,
            'assign_by' => $assignById,
        ]);
    }

    /**
     * Bulk Import Customer Leads from CSV file upload or JSON payload
     */
    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'nullable|file|mimes:csv,txt,text,plain|max:10240',
            'leads' => 'nullable|array',
            'duplicate_action' => 'nullable|string|in:skip,update,allow',
            'default_assigned_to' => 'nullable',
            'default_status' => 'nullable|string',
            'default_priority' => 'nullable|string',
            'default_vehicle_segment' => 'nullable|string',
            'default_source' => 'nullable|string',
        ]);

        $duplicateAction = $request->input('duplicate_action', 'skip');
        $rawRows = [];

        // 1. Process from uploaded CSV File
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->getRealPath();
            $handle = fopen($path, 'r');

            if ($handle === false) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to open uploaded CSV file.',
                ], 422);
            }

            // Detect and strip UTF-8 BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // Read header row
            $header = fgetcsv($handle);
            if (!$header || empty(array_filter($header))) {
                fclose($handle);
                return response()->json([
                    'status' => false,
                    'message' => 'The uploaded file is empty or has an invalid header row.',
                ], 422);
            }

            // Normalize header columns
            $normalizedHeader = array_map(function ($col) {
                $cleaned = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $col)));
                $cleaned = preg_replace('/_+/', '_', $cleaned);

                $map = [
                    'customer_name' => 'name',
                    'customer' => 'name',
                    'full_name' => 'name',
                    'mobile' => 'phone',
                    'mobile_number' => 'phone',
                    'phone_number' => 'phone',
                    'contact' => 'phone',
                    'contact_number' => 'phone',
                    'email_address' => 'email',
                    'mail' => 'email',
                    'segment' => 'vehicle_segment',
                    'type' => 'vehicle_segment',
                    'model' => 'model_variant',
                    'variant' => 'model_variant',
                    'vehicle' => 'model_variant',
                    'vehicle_model' => 'model_variant',
                    'car_model' => 'model_variant',
                    'lead_source' => 'source_name',
                    'source' => 'source_name',
                    'lead_status' => 'status_name',
                    'status' => 'status_name',
                    'assigned_to' => 'assigned_to',
                    'assigned_user' => 'assigned_user_name',
                    'executive' => 'assigned_user_name',
                    'sales_executive' => 'assigned_user_name',
                    'sales_rep' => 'assigned_user_name',
                    'dob' => 'birth_date',
                    'date_of_birth' => 'birth_date',
                    'anniversary' => 'anniversary_date',
                    'timeline' => 'purchase_timeline',
                ];

                return $map[$cleaned] ?? $cleaned;
            }, $header);

            while (($row = fgetcsv($handle)) !== false) {
                if (empty(array_filter($row))) continue;
                $rowAssoc = [];
                foreach ($normalizedHeader as $idx => $key) {
                    $rowAssoc[$key] = isset($row[$idx]) ? trim($row[$idx]) : null;
                }
                $rawRows[] = $rowAssoc;
            }
            fclose($handle);
        } elseif ($request->filled('leads') || $request->filled('data')) {
            $rawRows = $request->input('leads', $request->input('data', []));
        } elseif (is_array($request->all()) && !empty($request->all()) && isset($request->all()[0])) {
            $rawRows = $request->all();
        }

        if (empty($rawRows)) {
            return response()->json([
                'status' => false,
                'message' => 'No valid lead records found in the payload or file. Please provide a CSV file or a "leads" array.',
            ], 422);
        }

        // Preload Master Data for fast in-memory lookup
        $brands = Brand::all();
        $sources = LeadSource::all();
        $statuses = LeadStatus::all();

        // Default fallbacks
        $defaultAssignee = $request->default_assigned_to;
        $currentAssignById = $this->resolveAssignByUserId();

        $defaultStatus = $request->input('default_status', 'New');
        $defaultPriority = ucfirst(strtolower($request->input('default_priority', 'Hot')));
        $defaultSegment = $request->input('default_vehicle_segment', '4 Wheeler');
        $defaultSource = $request->input('default_source', 'Bulk CSV Import');

        $importedLeads = [];
        $updatedLeads = [];
        $skippedCount = 0;
        $errors = [];

        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            foreach ($rawRows as $index => $row) {
                $rowNum = $index + 2;

                $name = $row['name'] ?? $row['customer_name'] ?? null;
                $phone = $row['phone'] ?? $row['mobile'] ?? null;
                $modelVariant = $row['model_variant'] ?? $row['model'] ?? $row['variant'] ?? null;

                if (empty($name) || empty($phone)) {
                    $errors[] = [
                        'row' => $rowNum,
                        'name' => $name,
                        'phone' => $phone,
                        'error' => 'Customer Name and Phone number are required.',
                    ];
                    continue;
                }

                // Check for existing lead by phone or email
                $existingLead = null;
                if ($duplicateAction !== 'allow') {
                    $existingLead = Lead::where('phone', $phone)
                        ->orWhere(function ($q) use ($row) {
                            if (!empty($row['email'])) {
                                $q->where('email', $row['email']);
                            }
                        })
                        ->first();
                }

                if ($existingLead && $duplicateAction === 'skip') {
                    $skippedCount++;
                    continue;
                }

                // Clean & Parse dates
                $birthDate = null;
                if (!empty($row['birth_date'])) {
                    $time = strtotime(str_replace('/', '-', $row['birth_date']));
                    $birthDate = $time ? date('Y-m-d', $time) : null;
                }

                $anniversaryDate = null;
                if (!empty($row['anniversary_date'])) {
                    $time = strtotime(str_replace('/', '-', $row['anniversary_date']));
                    $anniversaryDate = $time ? date('Y-m-d', $time) : null;
                }

                // Segment
                $rawSegment = $row['vehicle_segment'] ?? $defaultSegment;
                $segment = (stripos($rawSegment, '2') !== false) ? '2 Wheeler' : '4 Wheeler';

                // Priority
                $rawPriority = $row['priority'] ?? $defaultPriority;
                $priority = in_array(ucfirst(strtolower($rawPriority)), ['Hot', 'Warm', 'Cold'])
                    ? ucfirst(strtolower($rawPriority))
                    : 'Hot';

                // Brand
                $brandId = !empty($row['brand_id']) && is_numeric($row['brand_id']) ? (int) $row['brand_id'] : null;
                $brandName = $row['brand_name'] ?? $row['brand'] ?? null;
                if (!$brandId && $brandName) {
                    $matchedBrand = $brands->first(fn($b) => strcasecmp($b->name, $brandName) === 0 || stripos($b->name, $brandName) !== false);
                    $brandId = $matchedBrand?->id;
                    $brandName = $matchedBrand ? $matchedBrand->name : $brandName;
                } elseif ($brandId && !$brandName) {
                    $matchedBrand = $brands->firstWhere('id', $brandId);
                    $brandName = $matchedBrand?->name;
                }

                // Source
                $sourceId = !empty($row['source_id']) && is_numeric($row['source_id']) ? (int) $row['source_id'] : null;
                $sourceName = $row['source_name'] ?? $row['source'] ?? $defaultSource;
                if (!$sourceId && $sourceName) {
                    $matchedSource = $sources->first(fn($s) => strcasecmp($s->title, $sourceName) === 0);
                    $sourceId = $matchedSource?->id;
                    $sourceName = $matchedSource ? $matchedSource->title : $sourceName;
                } elseif ($sourceId && !$sourceName) {
                    $matchedSource = $sources->firstWhere('id', $sourceId);
                    $sourceName = $matchedSource?->title;
                }

                // Status
                $statusId = !empty($row['status_id']) && is_numeric($row['status_id']) ? (int) $row['status_id'] : null;
                $statusName = $row['status_name'] ?? $row['status'] ?? $defaultStatus;
                if (!$statusId && $statusName) {
                    $matchedStatus = $statuses->first(fn($st) => strcasecmp($st->name, $statusName) === 0);
                    $statusId = $matchedStatus?->id;
                    $statusName = $matchedStatus ? $matchedStatus->name : $statusName;
                } elseif ($statusId && !$statusName) {
                    $matchedStatus = $statuses->firstWhere('id', $statusId);
                    $statusName = $matchedStatus?->name;
                }

                // Assignment
                $assignedToInput = $row['assigned_to'] ?? $row['assigned_user_name'] ?? $row['executive'] ?? $defaultAssignee;
                [$assignedToId, $assignedUserName] = $this->resolveAssignToUser($assignedToInput);

                $leadData = [
                    'name' => $name,
                    'email' => !empty($row['email']) ? $row['email'] : null,
                    'phone' => $phone,
                    'city' => !empty($row['city']) ? $row['city'] : null,
                    'state' => !empty($row['state']) ? $row['state'] : null,
                    'birth_date' => $birthDate,
                    'anniversary_date' => $anniversaryDate,
                    'vehicle_segment' => $segment,
                    'brand_id' => $brandId,
                    'brand_name' => $brandName,
                    'model_variant' => $modelVariant ?: 'Inquiry Model',
                    'priority' => $priority,
                    'purchase_timeline' => !empty($row['purchase_timeline']) ? $row['purchase_timeline'] : null,
                    'source_id' => $sourceId,
                    'source_name' => $sourceName,
                    'status_id' => $statusId,
                    'status_name' => $statusName ?: 'New',
                    'assigned_to' => $assignedToId,
                    'assigned_user_name' => $assignedUserName,
                ];

                if ($existingLead && $duplicateAction === 'update') {
                    $existingLead->update($leadData);
                    $updatedLeads[] = $existingLead->id;
                } else {
                    $lead = Lead::create($leadData);
                    $importedLeads[] = $lead->id;

                    // Log initial assignment history
                    if ($assignedToId) {
                        LeadAssignmentHistory::create([
                            'lead_id' => $lead->id,
                            'assign_to' => $assignedToId,
                            'assign_by' => $currentAssignById,
                            'remarks' => !empty($row['remarks']) ? $row['remarks'] : 'Bulk imported lead initial assignment',
                        ]);
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Bulk import failed due to an error: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => "Bulk import completed. " . count($importedLeads) . " imported, " . count($updatedLeads) . " updated, {$skippedCount} skipped, " . count($errors) . " failed.",
            'summary' => [
                'total_rows' => count($rawRows),
                'imported_count' => count($importedLeads),
                'updated_count' => count($updatedLeads),
                'skipped_count' => $skippedCount,
                'failed_count' => count($errors),
            ],
            'imported_ids' => $importedLeads,
            'updated_ids' => $updatedLeads,
            'errors' => $errors,
        ], 200);
    }

    /**
     * Helper to resolve valid assign_to user ID and name
     */
    protected function resolveAssignToUser($assignedTo = null, $assignedUserName = null)
    {
        $userId = null;
        $userName = $assignedUserName;

        if ($assignedTo && is_numeric($assignedTo)) {
            $user = User::find($assignedTo);
            if ($user) {
                $userId = $user->id;
                $userName = $userName ?: ($user->role ? "{$user->name} ({$user->role})" : $user->name);
            }
        }

        if (!$userId && $userName) {
            $nameQuery = trim(preg_replace('/\(.*?\)/', '', $userName));
            $user = User::where('name', 'like', "%{$nameQuery}%")->first();
            if ($user) {
                $userId = $user->id;
            }
        }

        if (!$userId && $assignedTo && is_string($assignedTo) && !is_numeric($assignedTo)) {
            $nameQuery = trim(preg_replace('/\(.*?\)/', '', $assignedTo));
            $user = User::where('name', 'like', "%{$nameQuery}%")->first();
            if ($user) {
                $userId = $user->id;
                $userName = $userName ?: ($user->role ? "{$user->name} ({$user->role})" : $user->name);
            }
        }

        if (!$userId && !$userName) {
            $user = User::where('status', 'Active')->first() ?? User::first();
            if ($user) {
                $userId = $user->id;
                $userName = $user->role ? "{$user->name} ({$user->role})" : $user->name;
            }
        }

        return [$userId, $userName];
    }

    /**
     * Helper to resolve valid assign_by user ID
     */
    protected function resolveAssignByUserId($assignBy = null)
    {
        if ($assignBy && is_numeric($assignBy)) {
            $user = User::find($assignBy);
            if ($user) {
                return $user->id;
            }
        } elseif ($assignBy && is_string($assignBy)) {
            $nameQuery = trim(preg_replace('/\(.*?\)/', '', $assignBy));
            $user = User::where('name', 'like', "%{$nameQuery}%")->first();
            if ($user) {
                return $user->id;
            }
        }

        $authUser = request()->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        if ($authUser) {
            return $authUser->id;
        }

        return User::where('role', 'Super Admin')->first()?->id ?? User::where('status', 'Active')->first()?->id ?? User::first()?->id;
    }

    /**
     * Trigger Birthday & Anniversary Greetings dispatch on-demand via API
     */
    public function sendGreetingsNow(Request $request)
    {
        $dryRun = $request->boolean('dry_run', false);
        $force = $request->boolean('force', false);

        $params = [];
        if ($dryRun) $params['--dry-run'] = true;
        if ($force) $params['--force'] = true;

        try {
            \Illuminate\Support\Facades\Artisan::call('leads:send-wishes', $params);
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status' => true,
                'message' => 'Greetings automation executed successfully.',
                'dry_run' => $dryRun,
                'force' => $force,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to execute greetings automation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
