<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\DealPayment;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Insurance;
use App\Models\LeadFollowUp;
use App\Models\LeadStatus;
use App\Models\LeadSource;

class LogActivityMiddleware
{
    /**
     * Handle incoming request and automatically log write actions (add, edit, delete, trash, restore, send, print, export, import).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Skip logging activity-logs routes to avoid recursive logging loops
        if (str_contains($path, 'activity-logs') || str_contains($path, 'auth/profile') || $path === 'up') {
            return $next($request);
        }

        $method = strtoupper($request->method());
        $pathLower = strtolower($path);
        $isExport = str_contains($pathLower, 'export') || $request->has('export');
        $isImport = str_contains($pathLower, 'import');
        $isPrintPdf = str_contains($pathLower, 'quotation') && str_contains($pathLower, 'pdf');

        $module = $this->resolveModule($path);
        $action = $this->resolveAction($method, $path, $request, $isExport, $isImport);

        // Pre-capture entity details for DELETE, PUT, PATCH, bulk operations, send, and print BEFORE the DB change occurs
        $preEntity = $this->captureEntityDetails($request, $module);

        $response = $next($request);

        // Only log successful operations
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        // We log state-changing methods (POST, PUT, PATCH, DELETE), Export/Import, and Quotation PDF downloads
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) || $isExport || $isImport || $isPrintPdf) {
            // Login/Logout are handled specifically in AuthController
            if (str_contains($path, 'auth/login') || str_contains($path, 'auth/logout')) {
                return $response;
            }

            // Skip if the request is already an internal export-logging endpoint to prevent double logging
            if (str_contains($path, 'log-export')) {
                return $response;
            }

            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $detail = $this->generateDetail($request, $response, $module, $action, $preEntity);

            ActivityLog::record([
                'user_id' => $user?->id,
                'module' => $module,
                'action' => $action,
                'detail' => $detail,
                'ip_address' => ActivityLog::getClientIpv4($request),
            ]);
        }

        return $response;
    }

    /**
     * Detect module name from API URL path
     */
    private function resolveModule(string $path): string
    {
        $pathLower = strtolower($path);

        return match (true) {
            str_contains($pathLower, 'lead-assignment') => 'Lead Assignment',
            str_contains($pathLower, 'lead-source') => 'Lead Source',
            str_contains($pathLower, 'lead-status') => 'Lead Status',
            str_contains($pathLower, 'follow-up') => 'Follow-up',
            str_contains($pathLower, 'lead') => 'Lead',
            str_contains($pathLower, 'deal-payment') || str_contains($pathLower, 'payments') => 'Deal Payment',
            str_contains($pathLower, 'deal') => 'Deal',
            str_contains($pathLower, 'quotation') => 'Quotation',
            str_contains($pathLower, 'brand') => 'Brand',
            str_contains($pathLower, 'vehicle-variant') || str_contains($pathLower, 'variant') => 'Vehicle Variant',
            str_contains($pathLower, 'vehicle-model') || str_contains($pathLower, 'model') => 'Vehicle Model',
            str_contains($pathLower, 'vehicle-price') || str_contains($pathLower, 'update-price') || str_contains($pathLower, 'price') => 'Vehicle Pricing',
            str_contains($pathLower, 'insurance') => 'Insurance',
            str_contains($pathLower, 'expense-categor') => 'Expense Category',
            str_contains($pathLower, 'expense') => 'Expense',
            str_contains($pathLower, 'token-billing') || str_contains($pathLower, 'invoice') => 'Invoices & Billing',
            str_contains($pathLower, 'user') => 'User',
            str_contains($pathLower, 'report') => 'Report',
            str_contains($pathLower, 'discount-approval') => 'Discount Approval',
            str_contains($pathLower, 'auth') => 'Authentication',
            default => 'General',
        };
    }

    /**
     * Detect action category: add, edit, delete, trash, restore, send, print, export, import
     */
    private function resolveAction(string $method, string $path, Request $request, bool $isExport, bool $isImport): string
    {
        if ($isExport) return 'export';
        if ($isImport) return 'import';

        $pathLower = strtolower($path);

        // 1. Quotation Send Email
        if (str_contains($pathLower, 'quotation') && (str_contains($pathLower, 'send') || $request->input('status') === 'sent')) {
            return 'send';
        }

        // 2. Quotation Print / PDF
        if (str_contains($pathLower, 'quotation') && (str_contains($pathLower, 'pdf') || str_contains($pathLower, 'print') || in_array($request->input('status'), ['print', 'printed']))) {
            return 'print';
        }

        // 3. Restore from Trash
        if (str_contains($pathLower, 'restore')) {
            return 'restore';
        }

        // 4. Force / Permanent Delete from Trash
        if (str_contains($pathLower, '/force') || $request->has('force')) {
            return 'delete';
        }

        // 5. Delete method / Bulk delete:
        // Soft-deletable models (Lead, Quotation, User, Follow-up) go to Trash
        if ($method === 'DELETE' || str_contains($pathLower, 'bulk-delete')) {
            if (
                str_contains($pathLower, 'lead') ||
                str_contains($pathLower, 'quotation') ||
                str_contains($pathLower, 'user') ||
                str_contains($pathLower, 'follow-up')
            ) {
                return 'trash';
            }
            return 'delete';
        }

        return match ($method) {
            'POST' => 'add',
            'PUT', 'PATCH' => 'edit',
            default => 'other',
        };
    }

    /**
     * Resolve target record ID from URL segments, route params, or request inputs.
     * Works seamlessly in Global Middleware both before and after routing.
     */
    private function resolveEntityId(Request $request): ?int
    {
        // 1. Extract numeric ID from URL path segments (e.g. /api/leads/5 or /api/leads/5/force or /api/quotations/12/send)
        $segments = $request->segments();
        foreach ($segments as $segment) {
            if (is_numeric($segment)) {
                return (int) $segment;
            }
        }

        // 2. Check route parameters (available after router matches)
        $route = $request->route();
        if ($route) {
            $keys = [
                'id', 'variant', 'vehicle_variant', 'model', 'vehicle_model',
                'brand', 'lead', 'deal', 'quotation', 'user', 'expense',
                'category', 'insurance', 'follow_up', 'status', 'source'
            ];

            foreach ($keys as $key) {
                $val = $route->parameter($key);
                if ($val) {
                    if (is_object($val) && isset($val->id)) {
                        return (int) $val->id;
                    }
                    if (is_numeric($val)) {
                        return (int) $val;
                    }
                }
            }
        }

        // 3. Check request body inputs
        $inputKeys = ['id', 'lead_id', 'deal_id', 'quotation_id', 'variant_id', 'model_id', 'brand_id', 'user_id', 'follow_up_id'];
        foreach ($inputKeys as $key) {
            if ($request->filled($key) && is_numeric($request->input($key))) {
                return (int) $request->input($key);
            }
        }

        return null;
    }

    /**
     * Capture comprehensive entity details (name, model, brand, customer, etc.) BEFORE deletion or update
     */
    private function captureEntityDetails(Request $request, string $module): ?array
    {
        $id = $this->resolveEntityId($request);
        if (!$id) {
            return null;
        }

        try {
            switch ($module) {
                case 'Lead':
                    $l = Lead::withTrashed()->find($id);
                    if ($l) {
                        return [
                            'name' => $l->name,
                            'customer_name' => $l->name,
                            'phone' => $l->phone,
                            'vehicle' => $l->model_variant ?: $l->brand_name,
                        ];
                    }
                    break;

                case 'Follow-up':
                    $f = LeadFollowUp::withTrashed()->with('lead')->find($id);
                    if ($f) {
                        return [
                            'lead_name' => $f->lead?->name,
                            'name' => $f->lead?->name,
                            'type' => $f->type,
                            'date' => $f->follow_up_date,
                        ];
                    }
                    break;

                case 'Quotation':
                    $q = Quotation::withTrashed()->with('lead')->find($id);
                    if ($q) {
                        return [
                            'quotation_number' => $q->quotation_number,
                            'customer_name' => $q->customer_name ?: $q->lead?->name,
                            'customer_email' => $q->customer_email ?: $q->lead?->email,
                            'subject' => $q->subject,
                        ];
                    }
                    break;

                case 'Brand':
                    $b = Brand::find($id);
                    if ($b) {
                        return [
                            'name' => $b->name,
                            'brand_name' => $b->name,
                        ];
                    }
                    break;

                case 'Vehicle Model':
                    $m = VehicleModel::with('brand')->find($id);
                    if ($m) {
                        return [
                            'name' => $m->name,
                            'model_name' => $m->name,
                            'brand_name' => $m->brand?->name,
                        ];
                    }
                    break;

                case 'Vehicle Variant':
                case 'Vehicle Pricing':
                    $v = VehicleVariant::with(['brand', 'model'])->find($id);
                    if ($v) {
                        return [
                            'name' => $v->name,
                            'variant_name' => $v->name,
                            'model_name' => $v->model?->name,
                            'brand_name' => $v->brand?->name,
                            'price' => $v->price ?? $v->ex_showroom_price,
                        ];
                    }
                    break;

                case 'Deal':
                    $d = Deal::find($id);
                    if ($d) {
                        return [
                            'deal_number' => $d->deal_number,
                            'customer_name' => $d->customer_name,
                            'vehicle' => $d->model_variant,
                            'amount' => $d->net_amount ?? $d->total_amount,
                        ];
                    }
                    break;

                case 'Deal Payment':
                case 'Invoices & Billing':
                    $dp = DealPayment::with('deal')->find($id);
                    if ($dp) {
                        return [
                            'invoice_no' => $dp->invoice_no,
                            'deal_number' => $dp->deal?->deal_number,
                            'customer_name' => $dp->deal?->customer_name,
                            'amount' => $dp->amount,
                        ];
                    }
                    break;

                case 'User':
                    $u = User::withTrashed()->find($id);
                    if ($u) {
                        return [
                            'name' => $u->name,
                            'user_name' => $u->name,
                            'role' => $u->role,
                            'email' => $u->email,
                        ];
                    }
                    break;

                case 'Expense':
                    $e = Expense::with('category')->find($id);
                    if ($e) {
                        return [
                            'title' => $e->title,
                            'name' => $e->title,
                            'amount' => $e->amount,
                            'category' => $e->category?->name,
                        ];
                    }
                    break;

                case 'Expense Category':
                    $ec = ExpenseCategory::find($id);
                    if ($ec) {
                        return [
                            'name' => $ec->name,
                            'category_name' => $ec->name,
                        ];
                    }
                    break;

                case 'Insurance':
                    $ins = Insurance::find($id);
                    if ($ins) {
                        return [
                            'customer_name' => $ins->customer_name,
                            'policy_number' => $ins->policy_number,
                            'vehicle' => $ins->model_variant,
                        ];
                    }
                    break;

                case 'Lead Status':
                    $ls = LeadStatus::find($id);
                    if ($ls) {
                        return ['name' => $ls->name];
                    }
                    break;

                case 'Lead Source':
                    $lsrc = LeadSource::find($id);
                    if ($lsrc) {
                        return ['name' => $lsrc->name];
                    }
                    break;
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Provide clean, highly descriptive detail explaining WHAT action occurred on WHICH entity.
     * Format: "{action} of {module} - {target_entity_details}"
     */
    private function generateDetail(Request $request, Response $response, string $module, string $action, ?array $preEntity = null): string
    {
        // 1. Handle Import Action
        if ($action === 'import') {
            $importedCount = null;
            try {
                $content = $response->getContent();
                if ($content && ($resData = json_decode($content, true)) && is_array($resData)) {
                    if (isset($resData['imported_count'])) {
                        $importedCount = $resData['imported_count'];
                    } elseif (isset($resData['data']['imported_count'])) {
                        $importedCount = $resData['data']['imported_count'];
                    } elseif (isset($resData['imported_ids']) && is_array($resData['imported_ids'])) {
                        $importedCount = count($resData['imported_ids']);
                    }
                }
            } catch (\Throwable $e) {}

            $target = $importedCount ? "{$importedCount} records" : "bulk records";
            return "import of " . strtolower($module) . " - " . $target;
        }

        // 2. Handle Export Action
        if ($action === 'export') {
            $format = $request->input('format', 'CSV');
            return "export of " . strtolower($module) . " - " . strtoupper($format);
        }

        // 3. Extract data from Response payload if available
        $resItem = [];
        try {
            $content = $response->getContent();
            if ($content && ($res = json_decode($content, true)) && is_array($res)) {
                $data = $res['data'] ?? $res;
                if (is_array($data)) {
                    $resItem = $data;
                }
            }
        } catch (\Throwable $e) {}

        // Combine Pre-Action Entity, Response Data, and Request Inputs
        $target = $this->buildTargetDetail($request, $resItem, $preEntity, $module, $action);

        $actionLower = strtolower($action);
        $moduleLower = strtolower($module);

        if (!empty($target)) {
            return "{$actionLower} of {$moduleLower} - {$target}";
        }

        return "{$actionLower} of {$moduleLower}";
    }

    /**
     * Build rich context string for the entity (Variant, Model, Brand, Deal, Lead, etc.)
     */
    private function buildTargetDetail(Request $request, array $resItem, ?array $preEntity, string $module, string $action = 'add'): ?string
    {
        $data = array_merge($preEntity ?? [], $resItem);

        switch ($module) {
            case 'Lead':
                $name = $data['customer_name'] ?? $data['name'] ?? $request->input('name');
                $phone = $data['phone'] ?? $request->input('phone');
                $vehicle = $data['vehicle'] ?? $data['model_variant'] ?? $request->input('model_variant') ?? $request->input('brand_name');

                $meta = [];
                if (!empty($phone)) $meta[] = "Phone: {$phone}";
                if (!empty($vehicle)) $meta[] = "Vehicle: {$vehicle}";

                if (!empty($name)) {
                    return !empty($meta) ? "{$name} (" . implode(', ', $meta) . ")" : $name;
                }
                break;

            case 'Follow-up':
                $leadName = $data['lead_name'] ?? $data['name'] ?? null;
                if (!$leadName && ($leadId = $data['lead_id'] ?? $request->input('lead_id'))) {
                    try {
                        $leadName = Lead::withTrashed()->find($leadId)?->name;
                    } catch (\Throwable $e) {}
                }

                $type = $data['type'] ?? $request->input('type');
                $date = $data['date'] ?? $request->input('follow_up_date');

                $meta = [];
                if (!empty($type)) $meta[] = "Type: {$type}";
                if (!empty($date)) $meta[] = "Date: {$date}";

                if (!empty($leadName)) {
                    return !empty($meta) ? "{$leadName} (" . implode(', ', $meta) . ")" : "{$leadName}";
                }
                break;

            case 'Quotation':
                $qNo = $data['quotation_number'] ?? $request->input('quotation_number');
                $custName = $data['customer_name'] ?? $request->input('customer_name');
                $recipient = $request->input('recipient_email') ?? ($data['customer_email'] ?? null);

                if ($action === 'send') {
                    $target = $qNo ?: 'Quotation';
                    $meta = [];
                    if (!empty($custName)) $meta[] = "Customer: {$custName}";
                    if (!empty($recipient)) $meta[] = "Email: {$recipient}";
                    return !empty($meta) ? "{$target} (" . implode(', ', $meta) . ")" : $target;
                }

                if ($action === 'print') {
                    $target = $qNo ?: 'Quotation';
                    return !empty($custName) ? "{$target} (Customer: {$custName})" : $target;
                }

                if (!empty($qNo)) {
                    return !empty($custName) ? "{$qNo} (Customer: {$custName})" : $qNo;
                } elseif (!empty($custName)) {
                    return $custName;
                }
                break;

            case 'Brand':
                $brandName = $data['brand_name'] ?? $data['name'] ?? $request->input('name');
                if (!empty($brandName)) {
                    return $brandName;
                }
                break;

            case 'Vehicle Model':
                $modelName = $data['model_name'] ?? $data['name'] ?? $request->input('name');
                $brandName = $data['brand_name'] ?? ($resItem['brand']['name'] ?? null);

                if (!$brandName) {
                    $bId = $data['brand_id'] ?? $request->input('brand_id');
                    if ($bId) {
                        try {
                            $brandName = Brand::find($bId)?->name;
                        } catch (\Throwable $e) {}
                    }
                }

                if (!empty($modelName)) {
                    return !empty($brandName) ? "{$modelName} (Brand: {$brandName})" : $modelName;
                }
                break;

            case 'Vehicle Variant':
                $variantName = $data['variant_name'] ?? $data['name'] ?? $request->input('name');
                $modelName = $data['model_name'] ?? ($resItem['model']['name'] ?? null);
                $brandName = $data['brand_name'] ?? ($resItem['brand']['name'] ?? null);

                if (!$modelName) {
                    $mId = $data['model_id'] ?? $request->input('model_id');
                    if ($mId) {
                        try {
                            $model = VehicleModel::with('brand')->find($mId);
                            $modelName = $model?->name;
                            if (!$brandName) {
                                $brandName = $model?->brand?->name;
                            }
                        } catch (\Throwable $e) {}
                    }
                }

                if (!$brandName) {
                    $bId = $data['brand_id'] ?? $request->input('brand_id');
                    if ($bId) {
                        try {
                            $brandName = Brand::find($bId)?->name;
                        } catch (\Throwable $e) {}
                    }
                }

                $meta = [];
                if (!empty($modelName)) $meta[] = "Model: {$modelName}";
                if (!empty($brandName)) $meta[] = "Brand: {$brandName}";

                if (!empty($variantName)) {
                    return !empty($meta) ? "{$variantName} (" . implode(', ', $meta) . ")" : $variantName;
                }
                break;

            case 'Vehicle Pricing':
                $variantName = $data['variant_name'] ?? $data['name'] ?? $request->input('name');
                $modelName = $data['model_name'] ?? ($resItem['model']['name'] ?? null);
                $brandName = $data['brand_name'] ?? ($resItem['brand']['name'] ?? null);
                $price = $request->input('price') ?? ($data['price'] ?? null);

                $meta = [];
                if (!empty($modelName)) $meta[] = "Model: {$modelName}";
                if (!empty($brandName)) $meta[] = "Brand: {$brandName}";

                $desc = $variantName ?: "Variant";
                if (!empty($meta)) {
                    $desc .= " (" . implode(', ', $meta) . ")";
                }
                if ($price) {
                    $desc .= " - ₹" . number_format((float) $price);
                }
                return $desc;

            case 'Deal':
                $dealNo = $data['deal_number'] ?? $request->input('deal_number');
                $custName = $data['customer_name'] ?? $request->input('customer_name');
                $vehicle = $data['vehicle'] ?? $data['model_variant'] ?? $request->input('model_variant');

                $meta = [];
                if (!empty($custName)) $meta[] = "Customer: {$custName}";
                if (!empty($vehicle)) $meta[] = "Vehicle: {$vehicle}";

                if (!empty($dealNo)) {
                    return !empty($meta) ? "{$dealNo} (" . implode(', ', $meta) . ")" : $dealNo;
                } elseif (!empty($custName)) {
                    return !empty($vehicle) ? "{$custName} ({$vehicle})" : $custName;
                }
                break;

            case 'Deal Payment':
            case 'Invoices & Billing':
                $invNo = $data['invoice_no'] ?? $request->input('invoice_no');
                $dealNo = $data['deal_number'] ?? null;
                $custName = $data['customer_name'] ?? null;
                $amount = $data['amount'] ?? $request->input('amount');

                if (!$dealNo && ($dealId = $data['deal_id'] ?? $request->input('deal_id'))) {
                    try {
                        $deal = Deal::find($dealId);
                        $dealNo = $deal?->deal_number;
                        if (!$custName) $custName = $deal?->customer_name;
                    } catch (\Throwable $e) {}
                }

                $meta = [];
                if (!empty($dealNo)) $meta[] = "Deal: {$dealNo}";
                if (!empty($custName)) $meta[] = "Customer: {$custName}";
                if (!empty($amount)) $meta[] = "Amount: ₹" . number_format((float) $amount);

                if (!empty($invNo)) {
                    return !empty($meta) ? "Invoice {$invNo} (" . implode(', ', $meta) . ")" : "Invoice {$invNo}";
                }
                return !empty($meta) ? "Payment (" . implode(', ', $meta) . ")" : null;

            case 'User':
                $name = $data['user_name'] ?? $data['name'] ?? $request->input('name');
                $role = $data['role'] ?? $request->input('role');

                if (!empty($name)) {
                    return !empty($role) ? "{$name} (Role: " . ucwords(str_replace('_', ' ', $role)) . ")" : $name;
                }
                break;

            case 'Expense':
                $title = $data['title'] ?? $data['name'] ?? $request->input('title');
                $amount = $data['amount'] ?? $request->input('amount');
                $category = $data['category'] ?? null;
                if (is_array($category)) {
                    $category = $category['name'] ?? null;
                } elseif (is_object($category)) {
                    $category = $category->name ?? null;
                }

                if (!$category && ($catId = $data['category_id'] ?? $request->input('category_id'))) {
                    try {
                        $category = ExpenseCategory::find($catId)?->name;
                    } catch (\Throwable $e) {}
                }

                $meta = [];
                if (!empty($amount) && is_numeric($amount)) $meta[] = "Amount: ₹" . number_format((float) $amount);
                if (!empty($category) && is_string($category)) $meta[] = "Category: {$category}";

                if (!empty($title)) {
                    return !empty($meta) ? "{$title} (" . implode(', ', $meta) . ")" : $title;
                }
                break;

            case 'Expense Category':
                $name = $data['category_name'] ?? $data['name'] ?? $request->input('name');
                if (!empty($name)) return $name;
                break;

            case 'Insurance':
                $custName = $data['customer_name'] ?? $request->input('customer_name');
                $pol = $data['policy_number'] ?? $request->input('policy_number');
                $vehicle = $data['vehicle'] ?? $data['model_variant'] ?? $request->input('model_variant');

                $meta = [];
                if (!empty($custName)) $meta[] = "Customer: {$custName}";
                if (!empty($vehicle)) $meta[] = "Vehicle: {$vehicle}";

                if (!empty($pol)) {
                    return !empty($meta) ? "Policy {$pol} (" . implode(', ', $meta) . ")" : "Policy {$pol}";
                } elseif (!empty($custName)) {
                    return $custName;
                }
                break;

            case 'Lead Status':
            case 'Lead Source':
                $name = $data['name'] ?? $request->input('name');
                if (!empty($name)) return $name;
                break;
        }

        // Generic fallback for any other fields
        $genericFields = ['name', 'title', 'customer_name', 'email', 'deal_number', 'quotation_number'];
        foreach ($genericFields as $field) {
            if (!empty($data[$field]) && is_string($data[$field])) {
                return trim($data[$field]);
            }
            if ($request->filled($field)) {
                return trim((string) $request->input($field));
            }
        }

        // Bulk operations check (e.g. bulk delete or bulk restore)
        if ($request->has('lead_ids') && is_array($request->input('lead_ids'))) {
            $count = count($request->input('lead_ids'));
            return "{$count} leads";
        }
        if ($request->has('ids') && is_array($request->input('ids'))) {
            $count = count($request->input('ids'));
            return "{$count} records";
        }

        return null;
    }
}
