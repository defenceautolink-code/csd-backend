<?php

namespace App\Http\Controllers;

use App\Models\CallRecording;
use App\Models\LeadFollowUp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CallRecordingController extends Controller
{
    private const ALLOWED_EXTENSIONS = [
        'mp3', 'wav', 'm4a', 'aac', 'ogg', 'oga', 'opus',
        'amr', '3gp', '3gpp', 'webm', 'mp4', 'mpeg', 'mpga', 'flac', 'wma',
    ];

    private const MAX_FILE_BYTES = 52428800; // 50 MB

    private function normalizeRole(?User $user): string
    {
        if (!$user || !$user->role) {
            return '';
        }
        return strtolower(str_replace([' ', '-'], '_', trim((string) $user->role)));
    }

    private function isAdminOrManager(?User $user): bool
    {
        return in_array($this->normalizeRole($user), ['super_admin', 'admin', 'sales_manager', 'manager'], true);
    }

    private function resolveAuthUser(Request $request): ?User
    {
        return $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();
    }

    /**
     * List call recordings (scoped to self for staff, all for Admin & Sales Manager).
     */
    public function index(Request $request)
    {
        try {
            $user = $this->resolveAuthUser($request);
            $isManager = $this->isAdminOrManager($user);

            $query = CallRecording::with([
                'user:id,name,email,phone,role',
                'lead:id,name,phone,model_variant',
                'reviewer:id,name,role',
            ]);

            if (!$isManager && $user) {
                $query->where('user_id', $user->id);
            } elseif ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($isManager && $request->filled('role') && $request->role !== 'all') {
                $roleFilter = strtolower(str_replace([' ', '-'], '_', trim((string) $request->role)));
                $query->whereHas('user', function ($uq) use ($roleFilter) {
                    if ($roleFilter === 'sales_executive') {
                        $uq->whereIn('role', ['Sales Executive', 'sales_executive', 'Sales Rep', 'Sales Consultant']);
                    } elseif ($roleFilter === 'receptionist') {
                        $uq->whereIn('role', ['Receptionist', 'receptionist']);
                    } else {
                        $uq->where('role', 'like', '%' . str_replace('_', ' ', $roleFilter) . '%');
                    }
                });
            }

            if ($request->filled('date')) {
                $query->whereDate('recording_date', $request->date);
            } else {
                $from = $request->input('from_date') ?? $request->input('start_date');
                $to = $request->input('to_date') ?? $request->input('end_date');
                if ($from) {
                    $query->whereDate('recording_date', '>=', $from);
                }
                if ($to) {
                    $query->whereDate('recording_date', '<=', $to);
                }
            }

            if ($request->filled('review_status') && $request->review_status !== 'all') {
                $query->where('review_status', $request->review_status);
            }

            if ($request->filled('batch_id')) {
                $query->where('batch_id', $request->batch_id);
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('original_name', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('lead', function ($lq) use ($search) {
                          $lq->withTrashed()->where(function ($sq) use ($search) {
                              $sq->where('name', 'like', "%{$search}%")
                                 ->orWhere('phone', 'like', "%{$search}%");
                          });
                      });
                });
            }

            $query->orderByDesc('recording_date')->orderByDesc('id');

            $isAll = $request->boolean('all') || $request->get('per_page') === 'all';
            if ($isAll) {
                $items = $query->get();
                return response()->json([
                    'status' => true,
                    'message' => 'Call recordings fetched successfully',
                    'data' => $items,
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $items->count(),
                        'total' => $items->count(),
                    ],
                ]);
            }

            $perPage = max(1, (int) $request->get('per_page', 20));
            $paginated = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'message' => 'Call recordings fetched successfully',
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
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@index error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to fetch call recordings.',
            ], 500);
        }
    }

    /**
     * Daily summary comparing Follow-ups taken vs Call Recordings uploaded.
     */
    public function dailySummary(Request $request)
    {
        try {
            $user = $this->resolveAuthUser($request);
            $isManager = $this->isAdminOrManager($user);

            $singleDate = $request->input('date');
            $fromDate = $request->input('from_date') ?? $request->input('start_date');
            $toDate = $request->input('to_date') ?? $request->input('end_date');

            if ($singleDate) {
                $fromDate = $singleDate;
                $toDate = $singleDate;
            }

            $applyFollowUpDate = function ($q) use ($fromDate, $toDate) {
                if ($fromDate) {
                    $q->whereDate('follow_up_date', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('follow_up_date', '<=', $toDate);
                }
            };

            $applyRecordingDate = function ($q) use ($fromDate, $toDate) {
                if ($fromDate) {
                    $q->whereDate('recording_date', '>=', $fromDate);
                }
                if ($toDate) {
                    $q->whereDate('recording_date', '<=', $toDate);
                }
            };

            // Staff view (Sales Executive / Receptionist): return own KPIs + paginated date-wise summary
            if (!$isManager && $user) {
                $followUpsBase = LeadFollowUp::query()
                    ->where('user_id', $user->id)
                    ->where(function ($q) {
                        $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
                    });
                $applyFollowUpDate($followUpsBase);

                $fuAgg = (clone $followUpsBase)
                    ->selectRaw('COUNT(*) as total_follow_ups, SUM(CASE WHEN LOWER(COALESCE(type, "")) LIKE "%call%" THEN 1 ELSE 0 END) as call_follow_ups')
                    ->first();

                $totalFollowUps = (int) ($fuAgg->total_follow_ups ?? 0);
                $callFollowUps = (int) ($fuAgg->call_follow_ups ?? 0);

                $recQuery = CallRecording::where('user_id', $user->id);
                $applyRecordingDate($recQuery);
                $recAgg = (clone $recQuery)
                    ->selectRaw('COUNT(*) as uploaded_count, SUM(CASE WHEN review_status = "pending" THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN review_status = "verified" THEN 1 ELSE 0 END) as verified_count, SUM(CASE WHEN review_status = "flagged" THEN 1 ELSE 0 END) as flagged_count')
                    ->first();

                $uploadedCount = (int) ($recAgg->uploaded_count ?? 0);

                // Per-date breakdown across follow-ups & recordings
                $fuByDate = (clone $followUpsBase)
                    ->selectRaw('DATE(follow_up_date) as day_date, COUNT(*) as total_follow_ups, SUM(CASE WHEN LOWER(COALESCE(type, "")) LIKE "%call%" THEN 1 ELSE 0 END) as call_follow_ups')
                    ->groupByRaw('DATE(follow_up_date)')
                    ->get()
                    ->keyBy('day_date');

                $recByDate = (clone $recQuery)
                    ->selectRaw('DATE(recording_date) as day_date, COUNT(*) as recordings_uploaded, SUM(CASE WHEN review_status = "pending" THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN review_status = "verified" THEN 1 ELSE 0 END) as verified_count, SUM(CASE WHEN review_status = "flagged" THEN 1 ELSE 0 END) as flagged_count')
                    ->groupByRaw('DATE(recording_date)')
                    ->get()
                    ->keyBy('day_date');

                $allDates = $fuByDate->keys()
                    ->merge($recByDate->keys())
                    ->filter()
                    ->unique()
                    ->sortDesc()
                    ->values();

                $perPage = max(1, min(50, (int) $request->input('per_page', 10)));
                $page = max(1, (int) $request->input('page', 1));
                $totalDays = $allDates->count();
                $lastPage = max(1, (int) ceil($totalDays / $perPage));
                $page = min($page, $lastPage);

                $pagedDates = $allDates->slice(($page - 1) * $perPage, $perPage)->values();

                $dailyRows = $pagedDates->map(function ($dayDate) use ($fuByDate, $recByDate) {
                    $fu = $fuByDate->get($dayDate);
                    $rec = $recByDate->get($dayDate);

                    $dayTotalFu = (int) ($fu->total_follow_ups ?? 0);
                    $dayCallFu = (int) ($fu->call_follow_ups ?? 0);
                    $dayUploaded = (int) ($rec->recordings_uploaded ?? 0);
                    $dayMissing = max(0, $dayCallFu - $dayUploaded);

                    $complianceStatus = 'no_calls';
                    if ($dayCallFu > 0 && $dayUploaded >= $dayCallFu) {
                        $complianceStatus = 'complete';
                    } elseif ($dayCallFu > 0 && $dayUploaded < $dayCallFu) {
                        $complianceStatus = 'missing';
                    } elseif ($dayUploaded > 0) {
                        $complianceStatus = 'uploaded';
                    }

                    return [
                        'date' => $dayDate,
                        'total_follow_ups' => $dayTotalFu,
                        'call_follow_ups' => $dayCallFu,
                        'recordings_uploaded' => $dayUploaded,
                        'missing_count' => $dayMissing,
                        'pending_count' => (int) ($rec->pending_count ?? 0),
                        'verified_count' => (int) ($rec->verified_count ?? 0),
                        'flagged_count' => (int) ($rec->flagged_count ?? 0),
                        'compliance_status' => $complianceStatus,
                    ];
                })->values();

                return response()->json([
                    'status' => true,
                    'date_range' => ['from' => $fromDate, 'to' => $toDate],
                    'summary' => [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'role' => $user->role,
                        'total_follow_ups' => $totalFollowUps,
                        'call_follow_ups' => $callFollowUps,
                        'recordings_uploaded' => $uploadedCount,
                        'pending_review' => (int) ($recAgg->pending_count ?? 0),
                        'verified_count' => (int) ($recAgg->verified_count ?? 0),
                        'flagged_count' => (int) ($recAgg->flagged_count ?? 0),
                        'missing_count' => max(0, $callFollowUps - $uploadedCount),
                    ],
                    'daily_rows' => $dailyRows,
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $totalDays,
                    ],
                ]);
            }

            // Admin / Sales Manager view: per-staff daily audit summary
            $staffUsersQuery = User::query()
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereIn('role', [
                        'Sales Executive', 'sales_executive', 'Sales Rep', 'Sales Consultant',
                        'Receptionist', 'receptionist',
                    ]);
                });

            if ($request->filled('role') && $request->role !== 'all') {
                $roleFilter = strtolower(str_replace([' ', '-'], '_', trim((string) $request->role)));
                if ($roleFilter === 'sales_executive') {
                    $staffUsersQuery->whereIn('role', ['Sales Executive', 'sales_executive', 'Sales Rep', 'Sales Consultant']);
                } elseif ($roleFilter === 'receptionist') {
                    $staffUsersQuery->whereIn('role', ['Receptionist', 'receptionist']);
                }
            }

            $staffOptions = (clone $staffUsersQuery)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'email', 'phone']);

            if ($request->filled('user_id') && $request->user_id !== 'all') {
                $staffUsersQuery->where('id', $request->user_id);
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $staffUsersQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $staffUsers = $staffUsersQuery->orderBy('name')->get(['id', 'name', 'role', 'email', 'phone', 'status']);
            $staffIds = $staffUsers->pluck('id')->all();

            // Aggregate follow-ups and recordings per (user_id, date)
            $fuGrouped = collect();
            $recGrouped = collect();

            if (!empty($staffIds)) {
                $fuQuery = LeadFollowUp::query()
                    ->whereIn('user_id', $staffIds)
                    ->where(function ($q) {
                        $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
                    });
                $applyFollowUpDate($fuQuery);
                $fuGrouped = $fuQuery
                    ->selectRaw('user_id, DATE(follow_up_date) as day_date, COUNT(*) as total_follow_ups, SUM(CASE WHEN LOWER(COALESCE(type, "")) LIKE "%call%" THEN 1 ELSE 0 END) as call_follow_ups')
                    ->groupByRaw('user_id, DATE(follow_up_date)')
                    ->get();

                $recQuery = CallRecording::query()->whereIn('user_id', $staffIds);
                $applyRecordingDate($recQuery);
                $recGrouped = $recQuery
                    ->selectRaw('user_id, DATE(recording_date) as day_date, COUNT(*) as recordings_uploaded, SUM(CASE WHEN review_status = "pending" THEN 1 ELSE 0 END) as pending_count, SUM(CASE WHEN review_status = "verified" THEN 1 ELSE 0 END) as verified_count, SUM(CASE WHEN review_status = "flagged" THEN 1 ELSE 0 END) as flagged_count')
                    ->groupByRaw('user_id, DATE(recording_date)')
                    ->get();
            }

            $fuByUser = $fuGrouped->groupBy('user_id')->map(fn ($items) => $items->keyBy('day_date'));
            $recByUser = $recGrouped->groupBy('user_id')->map(fn ($items) => $items->keyBy('day_date'));

            $isSingleDay = $fromDate && $toDate && $fromDate === $toDate;

            $buildRow = function ($staff, ?string $dayDate, $fu, $rec) {
                $totalFollowUps = (int) ($fu->total_follow_ups ?? 0);
                $callFollowUps = (int) ($fu->call_follow_ups ?? 0);
                $uploaded = (int) ($rec->recordings_uploaded ?? 0);
                $missing = max(0, $callFollowUps - $uploaded);

                $complianceStatus = 'no_calls';
                if ($callFollowUps > 0 && $uploaded >= $callFollowUps) {
                    $complianceStatus = 'complete';
                } elseif ($callFollowUps > 0 && $uploaded < $callFollowUps) {
                    $complianceStatus = 'missing';
                } elseif ($uploaded > 0) {
                    $complianceStatus = 'uploaded';
                }

                return [
                    'date' => $dayDate,
                    'user_id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $staff->role,
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'total_follow_ups' => $totalFollowUps,
                    'call_follow_ups' => $callFollowUps,
                    'recordings_uploaded' => $uploaded,
                    'missing_count' => $missing,
                    'pending_count' => (int) ($rec->pending_count ?? 0),
                    'verified_count' => (int) ($rec->verified_count ?? 0),
                    'flagged_count' => (int) ($rec->flagged_count ?? 0),
                    'compliance_status' => $complianceStatus,
                ];
            };

            $rows = collect();
            foreach ($staffUsers as $staff) {
                $userFuMap = $fuByUser->get($staff->id, collect());
                $userRecMap = $recByUser->get($staff->id, collect());

                if ($isSingleDay) {
                    $rows->push($buildRow(
                        $staff,
                        $fromDate,
                        $userFuMap->get($fromDate),
                        $userRecMap->get($fromDate)
                    ));
                } else {
                    $dates = $userFuMap->keys()
                        ->merge($userRecMap->keys())
                        ->filter()
                        ->unique()
                        ->sortDesc()
                        ->values();

                    if ($dates->isEmpty()) {
                        $rows->push($buildRow($staff, null, null, null));
                    } else {
                        foreach ($dates as $dayDate) {
                            $rows->push($buildRow(
                                $staff,
                                $dayDate,
                                $userFuMap->get($dayDate),
                                $userRecMap->get($dayDate)
                            ));
                        }
                    }
                }
            }

            $rows = $rows->sort(function ($a, $b) {
                $dateA = $a['date'] ?? '';
                $dateB = $b['date'] ?? '';
                if ($dateA !== $dateB) {
                    return strcmp($dateB, $dateA); // newest dates first, empty dates last
                }
                $scoreA = ($a['recordings_uploaded'] * 100) + $a['call_follow_ups'];
                $scoreB = ($b['recordings_uploaded'] * 100) + $b['call_follow_ups'];
                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }
                return strcasecmp((string) $a['name'], (string) $b['name']);
            })->values();

            $kpis = [
                'total_staff' => $staffUsers->count(),
                'total_follow_ups' => $rows->sum('total_follow_ups'),
                'total_call_follow_ups' => $rows->sum('call_follow_ups'),
                'total_recordings' => $rows->sum('recordings_uploaded'),
                'total_missing' => $rows->sum('missing_count'),
                'staff_with_missing' => $rows->where('missing_count', '>', 0)->pluck('user_id')->unique()->count(),
                'pending_review' => $rows->sum('pending_count'),
                'verified_count' => $rows->sum('verified_count'),
                'flagged_count' => $rows->sum('flagged_count'),
            ];

            return response()->json([
                'status' => true,
                'date_range' => ['from' => $fromDate, 'to' => $toDate],
                'kpis' => $kpis,
                'staff_options' => $staffOptions,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@dailySummary error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to fetch daily call recording summary.',
            ], 500);
        }
    }

    /**
     * Get a specific staff member's follow-ups and uploaded recordings for a date/range.
     */
    public function staffDetail(Request $request, $userId)
    {
        try {
            $authUser = $this->resolveAuthUser($request);
            if (!$this->isAdminOrManager($authUser) && (int) $authUser?->id !== (int) $userId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized to view this staff detail.',
                ], 403);
            }

            $staff = User::find($userId, ['id', 'name', 'role', 'email', 'phone']);
            if (!$staff) {
                return response()->json([
                    'status' => false,
                    'message' => 'Staff user not found.',
                ], 404);
            }

            $singleDate = $request->input('date');
            $fromDate = $request->input('from_date') ?? $request->input('start_date') ?? $singleDate;
            $toDate = $request->input('to_date') ?? $request->input('end_date') ?? $singleDate;

            $fuQuery = LeadFollowUp::with(['lead:id,name,phone,model_variant'])
                ->where('user_id', $staff->id)
                ->where(function ($q) {
                    $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
                });
            if ($fromDate) {
                $fuQuery->whereDate('follow_up_date', '>=', $fromDate);
            }
            if ($toDate) {
                $fuQuery->whereDate('follow_up_date', '<=', $toDate);
            }
            $followUps = $fuQuery->orderByDesc('id')->get();

            $recQuery = CallRecording::with(['lead:id,name,phone', 'reviewer:id,name,role'])
                ->where('user_id', $staff->id);
            if ($fromDate) {
                $recQuery->whereDate('recording_date', '>=', $fromDate);
            }
            if ($toDate) {
                $recQuery->whereDate('recording_date', '<=', $toDate);
            }
            $recordings = $recQuery->orderByDesc('id')->get();

            return response()->json([
                'status' => true,
                'staff' => $staff,
                'date_range' => ['from' => $fromDate, 'to' => $toDate],
                'follow_ups' => $followUps,
                'recordings' => $recordings,
            ]);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@staffDetail error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to load staff detail.',
            ], 500);
        }
    }

    /**
     * Store single or bulk call recordings (supports `file` or `files[]`).
     */
    public function store(Request $request)
    {
        try {
            $user = $this->resolveAuthUser($request);
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $request->validate([
                'recording_date' => 'nullable|date',
                'batch_id' => 'nullable|string|max:64',
                'lead_id' => 'nullable|exists:leads,id',
                'follow_up_id' => 'nullable|exists:lead_follow_ups,id',
                'call_time' => 'nullable|string|max:50',
                'phone_number' => 'nullable|string|max:30',
                'notes' => 'nullable|string|max:2000',
            ]);

            $uploadedFiles = [];
            if ($request->hasFile('files')) {
                $filesInput = $request->file('files');
                $uploadedFiles = is_array($filesInput) ? $filesInput : [$filesInput];
            } elseif ($request->hasFile('file')) {
                $uploadedFiles = [$request->file('file')];
            }

            if (empty($uploadedFiles)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please select at least one audio recording file to upload.',
                ], 422);
            }

            foreach ($uploadedFiles as $idx => $file) {
                if (!$file || !$file->isValid()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'One of the selected audio files failed to upload properly.',
                    ], 422);
                }

                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    return response()->json([
                        'status' => false,
                        'message' => "Unsupported file format (.{$ext}) for '{$file->getClientOriginalName()}'. Allowed: " . implode(', ', self::ALLOWED_EXTENSIONS),
                    ], 422);
                }

                if ($file->getSize() > self::MAX_FILE_BYTES) {
                    return response()->json([
                        'status' => false,
                        'message' => "File '{$file->getClientOriginalName()}' exceeds the 50MB limit.",
                    ], 422);
                }
            }

            $recordingDate = $request->filled('recording_date')
                ? Carbon::parse($request->recording_date)->toDateString()
                : Carbon::today()->toDateString();

            $batchId = $request->filled('batch_id')
                ? $request->batch_id
                : ('batch_' . $user->id . '_' . now()->format('YmdHis') . '_' . Str::random(5));

            $relativeDir = 'uploads/call-recordings/' . $recordingDate;
            $destinationPath = public_path($relativeDir);
            if (!File::isDirectory($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true, true);
            }

            $created = [];
            foreach ($uploadedFiles as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getClientMimeType() ?: $file->getMimeType();
                $fileSize = (int) $file->getSize();

                $storedName = 'rec_' . $user->id . '_' . time() . '_' . Str::random(8) . '.' . $ext;
                $file->move($destinationPath, $storedName);
                $fullStoredPath = $destinationPath . DIRECTORY_SEPARATOR . $storedName;

                // Auto-detect 10-digit phone number from filename if not supplied explicitly
                $phoneNumber = $request->phone_number;
                if (!$phoneNumber && preg_match('/(?:\+91[\s-]?)?([6-9]\d{9})/', $originalName, $matches)) {
                    $phoneNumber = $matches[1];
                }

                try {
                    $recording = CallRecording::create([
                        'user_id' => $user->id,
                        'lead_id' => $request->lead_id ?: null,
                        'follow_up_id' => $request->follow_up_id ?: null,
                        'batch_id' => $batchId,
                        'recording_date' => $recordingDate,
                        'call_time' => $request->call_time,
                        'phone_number' => $phoneNumber,
                        'file_path' => '/' . $relativeDir . '/' . $storedName,
                        'original_name' => $originalName,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'notes' => $request->notes,
                        'review_status' => 'pending',
                    ]);
                } catch (\Throwable $dbEx) {
                    if (File::exists($fullStoredPath)) {
                        File::delete($fullStoredPath);
                    }
                    throw $dbEx;
                }

                $recording->load(['user:id,name,role', 'lead:id,name,phone']);
                $created[] = $recording;
            }

            return response()->json([
                'status' => true,
                'message' => count($created) . ' call recording(s) uploaded successfully.',
                'batch_id' => $batchId,
                'data' => $created,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'Failed to upload call recording(s).',
            ], 500);
        }
    }

    /**
     * Stream an audio recording securely (supports HTTP Range requests via response()->file).
     */
    public function stream(Request $request, $id)
    {
        try {
            $user = $this->resolveAuthUser($request);
            $recording = CallRecording::find($id);

            if (!$recording) {
                return response()->json(['status' => false, 'message' => 'Recording not found.'], 404);
            }

            if (!$this->isAdminOrManager($user) && (int) $recording->user_id !== (int) $user?->id) {
                return response()->json(['status' => false, 'message' => 'Unauthorized to access this recording.'], 403);
            }

            $relative = ltrim((string) $recording->file_path, '/');
            $fullPath = public_path($relative);
            if (!File::exists($fullPath)) {
                $altPath = storage_path('app/public/' . $relative);
                if (File::exists($altPath)) {
                    $fullPath = $altPath;
                } else {
                    return response()->json(['status' => false, 'message' => 'Audio file missing on server.'], 404);
                }
            }

            $mime = $recording->mime_type ?: File::mimeType($fullPath) ?: 'audio/mpeg';

            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($recording->original_name ?: basename($fullPath)) . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@stream error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Unable to stream audio recording.',
            ], 500);
        }
    }

    /**
     * Verify or Flag a call recording (Admin & Sales Manager only).
     */
    public function review(Request $request, $id)
    {
        try {
            $user = $this->resolveAuthUser($request);
            if (!$this->isAdminOrManager($user)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only Admin or Sales Manager can verify or flag call recordings.',
                ], 403);
            }

            $recording = CallRecording::find($id);
            if (!$recording) {
                return response()->json(['status' => false, 'message' => 'Recording not found.'], 404);
            }

            $validated = $request->validate([
                'review_status' => 'required|in:pending,verified,flagged',
                'review_remark' => 'nullable|string|max:2000',
            ]);

            $recording->update([
                'review_status' => $validated['review_status'],
                'review_remark' => $validated['review_remark'] ?? null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            $recording->load(['user:id,name,role', 'lead:id,name,phone', 'reviewer:id,name,role']);

            return response()->json([
                'status' => true,
                'message' => 'Call recording marked as ' . $validated['review_status'] . '.',
                'data' => $recording,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@review error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to update review status.',
            ], 500);
        }
    }

    /**
     * Delete a call recording (owner if still pending, or Admin/Sales Manager).
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $this->resolveAuthUser($request);
            $recording = CallRecording::find($id);

            if (!$recording) {
                return response()->json(['status' => false, 'message' => 'Recording not found.'], 404);
            }

            $isManager = $this->isAdminOrManager($user);
            $isOwner = (int) $recording->user_id === (int) $user?->id;

            if (!$isManager && !$isOwner) {
                return response()->json(['status' => false, 'message' => 'Unauthorized to delete this recording.'], 403);
            }

            if (!$isManager && $recording->review_status === 'verified') {
                return response()->json([
                    'status' => false,
                    'message' => 'Verified recordings cannot be deleted.',
                ], 403);
            }

            $relative = ltrim((string) $recording->file_path, '/');
            $fullPath = public_path($relative);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }

            $recording->delete();

            return response()->json([
                'status' => true,
                'message' => 'Call recording deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('CallRecordingController@destroy error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete call recording.',
            ], 500);
        }
    }
}
