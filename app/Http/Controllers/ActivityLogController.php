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
     * Get paginated activity logs with search, user, module, and date range filters
     */
    public function index(Request $request)
    {
        try {
            $query = ActivityLog::with(['user:id,name,email,role'])->orderBy('id', 'desc');

            // 1. Search Query (Title, Module, Description, or User Name/Email)
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('module', 'like', "%{$search}%")
                      ->orWhere('action', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
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

            // 4. Action Filter (insert, update, delete, login, logout, export)
            if ($request->filled('action') && $request->action !== 'all') {
                $query->where('action', $request->action);
            }

            // 5. Date Range Filters (Start Date & End Date)
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            $perPage = min((int) ($request->input('per_page', 15)), 100);
            $logs = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs->items(),
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
     * Get distinct modules and users for filter dropdowns
     */
    public function getFilters()
    {
        try {
            $modules = ActivityLog::select('module')
                ->distinct()
                ->whereNotNull('module')
                ->pluck('module')
                ->values();

            $users = User::select('id', 'name', 'email', 'role')
                ->orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'modules' => $modules,
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
     * Export activity logs matching current filters to CSV/Excel
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with(['user:id,name,email,role'])->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
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
            $query->where('action', $request->action);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        $filename = "activity_logs_" . Carbon::now()->format('Y-m-d_H-i-s') . ".csv";

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel compatibility with special characters
            fputs($handle, "\xEF\xBB\xBF");

            // Header row matching requested fields
            fputcsv($handle, [
                'ID',
                'Title',
                'Module',
                'Action Type',
                'Updated By (User ID)',
                'User Name',
                'User Email',
                'IP Address',
                'Description / Details',
                'Date & Time',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->title,
                        $log->module,
                        strtoupper($log->action),
                        $log->user_id ? "#{$log->user_id}" : 'N/A',
                        $log->user ? $log->user->name : 'System / Deleted User',
                        $log->user ? $log->user->email : 'N/A',
                        $log->ip_address ?? 'N/A',
                        $log->description ?? 'N/A',
                        $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
