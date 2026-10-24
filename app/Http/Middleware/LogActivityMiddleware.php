<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ActivityLog;

class LogActivityMiddleware
{
    /**
     * Handle an incoming request and automatically log write actions (POST, PUT, DELETE, Export).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log successful operations
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $path = $request->path();

        // Skip logging activity-logs routes to avoid infinite logging loops
        if (str_contains($path, 'activity-logs') || str_contains($path, 'auth/profile') || $path === 'up') {
            return $response;
        }

        $method = strtoupper($request->method());
        $isExport = str_contains($path, 'export') || $request->has('export');
        $isImport = str_contains($path, 'import');

        // We log state-changing methods (POST, PUT, PATCH, DELETE) and Export/Import operations
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']) || $isExport || $isImport) {
            // Don't duplicate if already logged by AuthController
            if (str_contains($path, 'auth/login') || str_contains($path, 'auth/logout')) {
                return $response;
            }

            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $module = $this->resolveModule($path);
            $action = $this->resolveAction($method, $isExport, $isImport);
            $title = $this->generateTitle($module, $action, $method);

            $description = $this->generateDescription($request, $module, $action);

            ActivityLog::record([
                'user_id' => $user?->id,
                'title' => $title,
                'module' => $module,
                'action' => $action,
                'method' => $method,
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'description' => $description,
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
            str_contains($pathLower, 'lead-source') => 'Lead Sources',
            str_contains($pathLower, 'lead-status') => 'Lead Statuses',
            str_contains($pathLower, 'follow-up') => 'Follow-Ups',
            str_contains($pathLower, 'lead') => 'Leads',
            str_contains($pathLower, 'deal-payment') => 'Deal Payments',
            str_contains($pathLower, 'deal') => 'Deals',
            str_contains($pathLower, 'quotation') => 'Quotations',
            str_contains($pathLower, 'brand') => 'Brands',
            str_contains($pathLower, 'vehicle-variant') || str_contains($pathLower, 'variant') => 'Vehicle Variants',
            str_contains($pathLower, 'vehicle-model') || str_contains($pathLower, 'model') => 'Vehicle Models',
            str_contains($pathLower, 'vehicle-price') || str_contains($pathLower, 'price') => 'Vehicle Pricing',
            str_contains($pathLower, 'insurance') => 'Insurance',
            str_contains($pathLower, 'expense-categor') => 'Expense Categories',
            str_contains($pathLower, 'expense') => 'Expenses',
            str_contains($pathLower, 'token-billing') || str_contains($pathLower, 'invoice') => 'Invoices & Billing',
            str_contains($pathLower, 'user') => 'Users & Team',
            str_contains($pathLower, 'report') => 'Reports',
            str_contains($pathLower, 'discount-approval') => 'Discount Approval',
            str_contains($pathLower, 'auth') => 'Authentication',
            default => 'General',
        };
    }

    /**
     * Detect action category
     */
    private function resolveAction(string $method, bool $isExport, bool $isImport): string
    {
        if ($isExport) return 'export';
        if ($isImport) return 'import';

        return match ($method) {
            'POST' => 'insert',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'other',
        };
    }

    /**
     * Generate clear readable title
     */
    private function generateTitle(string $module, string $action, string $method): string
    {
        return match ($action) {
            'insert' => "New {$module} Entry Added",
            'update' => "{$module} Record Updated",
            'delete' => "{$module} Record Deleted",
            'export' => "{$module} Data Exported",
            'import' => "{$module} Data Imported",
            default => "{$module} Activity ({$method})",
        };
    }

    /**
     * Provide extra contextual description if available
     */
    private function generateDescription(Request $request, string $module, string $action): ?string
    {
        $info = [];
        if ($request->filled('name')) {
            $info[] = "Name: " . $request->input('name');
        } elseif ($request->filled('title')) {
            $info[] = "Title: " . $request->input('title');
        } elseif ($request->filled('customer_name')) {
            $info[] = "Customer: " . $request->input('customer_name');
        } elseif ($request->filled('lead_id')) {
            $info[] = "Lead ID: #" . $request->input('lead_id');
        }

        if ($request->filled('status')) {
            $info[] = "Status: " . $request->input('status');
        }

        $id = $request->route('id') ?? $request->route('lead') ?? $request->route('deal') ?? $request->route('user') ?? $request->input('id');
        if ($id) {
            $info[] = "Record ID: #{$id}";
        }

        return !empty($info) ? implode(' | ', $info) : "Action '{$action}' executed on {$module}";
    }
}
