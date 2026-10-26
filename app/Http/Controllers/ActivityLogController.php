<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    /**
     * Get paginated activity logs with search, user, module, action, and date filters
     */
    public function index(Request $request)
    {
        try {
            $query = ActivityLog::with(['user:id,name,email,phone,role'])->orderBy('id', 'desc');

            // 1. Search Query (Module, Action, Detail, IP Address, or User Name/Phone/ID)
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('module', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('detail', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%")
                             ->orWhere('phone', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%")
                             ->orWhere('id', $search);
                      });
                });
            }

            // 2. User Filter
            if ($request->filled('user_id') && $request->user_id !== 'all') {
                $query->where('user_id', $request->user_id);
            }

            // 3. Module Filter
            if ($request->filled('module') && $request->module !== 'all') {
                $query->where('module', $request->module);
            }

            // 4. Action Filter (add, edit, delete, login, logout, export)
            if ($request->filled('action') && $request->action !== 'all') {
                $query->where('action', strtolower($request->action));
            }

            // 5. Date Range Filters (Calculated in Asia/Kolkata IST)
            if ($request->filled('start_date')) {
                $start = Carbon::parse($request->start_date, 'Asia/Kolkata')->startOfDay()->setTimezone('UTC');
                $query->where('created_at', '>=', $start);
            }

            if ($request->filled('end_date')) {
                $end = Carbon::parse($request->end_date, 'Asia/Kolkata')->endOfDay()->setTimezone('UTC');
                $query->where('created_at', '<=', $end);
            }

            $perPage = min((int) ($request->input('per_page', 15)), 100);
            $logs = $query->paginate($perPage);

            // Map data items with User info and Indian Standard Time (IST)
            $items = collect($logs->items())->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name ?? ($log->user_id ? "User #{$log->user_id}" : 'System'),
                    'user_phone' => $log->user?->phone ?? '-',
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'phone' => $log->user->phone,
                        'email' => $log->user->email,
                    ] : null,
                    'module' => $log->module,
                    'action' => $log->action,
                    'detail' => $log->detail ?? '-',
                    'ip_address' => $log->ip_address ?? '127.0.0.1',
                    'created_at' => $log->created_at ? $log->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i:s A') : '-',
                    'raw_time' => $log->created_at ? $log->created_at->toIso8601String() : null,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Activity logs retrieved successfully',
                'data' => $items,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem() ?? 0,
                    'to' => $logs->lastItem() ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch activity logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get distinct modules, actions, and users for filter dropdowns
     */
    public function getFilters()
    {
        try {
            $modules = ActivityLog::select('module')
                ->distinct()
                ->whereNotNull('module')
                ->orderBy('module')
                ->pluck('module')
                ->values();

            $actions = ActivityLog::select('action')
                ->distinct()
                ->whereNotNull('action')
                ->orderBy('action')
                ->pluck('action')
                ->values();

            $users = User::select('id', 'name', 'phone', 'email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'modules' => $modules,
                    'actions' => $actions,
                    'users' => $users,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch filters: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export activity logs matching current filters to CSV
     */
    public function export(Request $request)
    {
        $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
        ActivityLog::record([
            'user_id' => $user?->id,
            'module' => 'Activity Logs',
            'action' => 'export',
            'detail' => 'export of activity logs - CSV',
            'ip_address' => ActivityLog::getClientIpv4($request),
        ]);

        $query = ActivityLog::with(['user:id,name,email,phone,role'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('detail', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('id', $search);
                  });
            });
        }

        if ($request->filled('user_id') && $request->user_id !== 'all') {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('module') && $request->module !== 'all') {
            $query->where('module', $request->module);
        }

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', strtolower($request->action));
        }

        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date, 'Asia/Kolkata')->startOfDay()->setTimezone('UTC');
            $query->where('created_at', '>=', $start);
        }

        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->end_date, 'Asia/Kolkata')->endOfDay()->setTimezone('UTC');
            $query->where('created_at', '<=', $end);
        }

        $filename = "activity_logs_" . Carbon::now('Asia/Kolkata')->format('Y-m-d_H-i-s') . ".csv";

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Header: ID, User ID, User Name, User Mobile Number, Module, Action, Detail, IP Address, Date & Time (IST)
            fputcsv($handle, [
                'ID',
                'User ID',
                'User Name',
                'User Mobile Number',
                'Module',
                'Action',
                'Detail',
                'IP Address (IPv4)',
                'Date & Time (IST)',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->user_id ? "#{$log->user_id}" : 'System',
                        $log->user ? $log->user->name : ($log->user_id ? "User #{$log->user_id}" : 'System'),
                        $log->user?->phone ?? 'N/A',
                        $log->module,
                        strtoupper($log->action),
                        $log->detail ?? 'N/A',
                        $log->ip_address ?? '127.0.0.1',
                        $log->created_at ? $log->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i:s A') : 'N/A',
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Log client-side actions (exports, imports, bulk operations, etc.)
     */
    public function logAction(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
            $module = $request->input('module', 'General');
            $action = strtolower($request->input('action', 'export'));
            $detail = $request->input('detail');

            if (empty($detail)) {
                $count = $request->input('count');
                $format = strtoupper($request->input('format', 'CSV'));
                $target = $count ? "{$count} records ({$format})" : "all records ({$format})";
                $detail = "{$action} of " . strtolower($module) . " - {$target}";
            }

            $log = ActivityLog::record([
                'user_id' => $user?->id,
                'module' => $module,
                'action' => $action,
                'detail' => $detail,
                'ip_address' => ActivityLog::getClientIpv4($request),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Action logged successfully',
                'data' => $log,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
