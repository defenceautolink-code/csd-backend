<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Deal;
use App\Models\DealPayment;
use App\Models\DiscountApproval;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Quotation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get aggregate live statistics and metrics for Super Admin Dashboard
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            if ($request->filled('sales_executive_id') || ($user && in_array(strtolower(str_replace(' ', '_', $user->role ?? '')), ['sales_executive', 'sales_rep', 'sales_consultant']))) {
                return $this->salesExecutiveStats($request);
            }

            if ($request->get('role') === 'Receptionist' || ($user && in_array(strtolower(str_replace(' ', '_', $user->role ?? '')), ['receptionist']))) {
                return $this->receptionistStats($request);
            }

            // 1. KPI Cards (5 Primary Metrics for Dashboard)
            $totalLeads = Lead::count();
            
            // Calculate lead growth percentage comparing this month vs last month
            $startThisMonth = Carbon::now()->startOfMonth();
            $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endLastMonth = Carbon::now()->subMonth()->endOfMonth();

            $leadsThisMonth = Lead::where('created_at', '>=', $startThisMonth)->count();
            $leadsLastMonth = Lead::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();
            $leadGrowth = $leadsLastMonth > 0 
                ? round((($leadsThisMonth - $leadsLastMonth) / $leadsLastMonth) * 100)
                : ($leadsThisMonth > 0 ? 100 : 0);

            // 1) Total Follow-Up
            $totalFollowUp = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Follow%');
            })->count();
            if ($totalFollowUp === 0) {
                $totalFollowUp = LeadFollowUp::distinct('lead_id')->count() ?: LeadFollowUp::count();
            }

            // 2) Total Quotation
            $totalQuotation = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Quotation%')
                  ->orWhere('status_name', 'like', '%Quote%');
            })->count();
            if ($totalQuotation === 0) {
                $totalQuotation = Quotation::count();
            }

            // 3) Total Deal & Booking
            $totalDealBooking = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Deal%')
                  ->orWhere('status_name', 'like', '%Booking%')
                  ->orWhere('status_name', 'like', '%Won%');
            })->count();
            if ($totalDealBooking === 0) {
                $totalDealBooking = Deal::whereNotIn('deal_status', ['cancelled'])->count();
            }

            // 4) Total Lost
            $totalLost = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Lost%');
            })->count();

            // Additional supporting metrics
            $activeQuotes = Quotation::whereNotIn('status', ['cancelled', 'rejected'])->count();
            $confirmedDeals = Deal::whereNotIn('deal_status', ['cancelled', 'draft'])->count();
            $pendingPayRaw = (float) Deal::whereNotIn('deal_status', ['cancelled'])->sum('balance_due');
            $pendingPayFormatted = $this->formatCurrencyLakhs($pendingPayRaw);
            $inProcessDeals = Deal::whereIn('deal_status', [
                'booking_confirmed', 'booked', 'in_process', 'allotted', 'pdi_pending', 'pdi_cleared', 'in_transit', 'ready_for_delivery'
            ])->count();
            $deliveriesCount = Deal::where('deal_status', 'delivered')->count();
            $grossValueRaw = (float) Deal::whereNotIn('deal_status', ['cancelled'])->sum('net_amount');
            $grossValueFormatted = $this->formatCurrencyLakhs($grossValueRaw);

            // 2. Follow-Up Outcome Pipeline Stages
            $followUpBase = LeadFollowUp::where(function ($q) {
                $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
            });

            // 1) Interested / Call Back
            $countInterested = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Interested%')
                  ->orWhere('status', 'like', '%Call Back%');
            })->count();

            // 2) Quotation Requested
            $countQuotationReq = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Quotation%')
                  ->orWhere('status', 'like', '%Quote%')
                  ->orWhere('status', 'like', '%Sent%');
            })->count();

            // 3) Negotiation / Price Discussion
            $countNegotiation = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Negotiation%')
                  ->orWhere('status', 'like', '%Price%');
            })->count();

            // 4) Not Reachable / Busy
            $countNotReachable = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Not Reachable%')
                  ->orWhere('status', 'like', '%Busy%');
            })->count();

            // 5) Lost to Competitor / Lost of Lead
            $countLost = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Lost%');
            })->count();

            // 6) Deal Won / Booking
            $countDealWon = (clone $followUpBase)->where(function ($q) {
                $q->where('status', 'like', '%Deal%')
                  ->orWhere('status', 'like', '%Booking%')
                  ->orWhere('status', 'like', '%Won%')
                  ->orWhere('status', 'like', '%Completed%');
            })->count();

            // Backward compatibility pipeline counts
            $pipelineLead = Lead::where(function ($q) {
                $q->whereNull('status_name')
                  ->orWhere('status_name', 'like', '%Lead%')
                  ->orWhere('status_name', 'like', '%New%');
            })->count();
            $pipelineFollowUp = $totalFollowUp;
            $pipelineQuotation = Quotation::count();
            $pipelineNegotiation = $countNegotiation;
            $pipelineBooking = $totalDealBooking;
            $pipelineDelivered = $deliveriesCount;

            // 3. Upcoming Deliveries (from active deals)
            $dealsQuery = Deal::whereNotIn('deal_status', ['cancelled'])
                ->orderByRaw('CASE WHEN deal_status = "delivered" THEN 2 ELSE 1 END')
                ->latest('booking_date')
                ->limit(4)
                ->get();

            $upcomingDeliveries = $dealsQuery->map(function ($deal) {
                $rawDate = $deal->expected_delivery_date ?: $deal->booking_date;
                $dateFormatted = $rawDate ? Carbon::parse($rawDate)->format('d M') : 'Pending';
                $city = $deal->customer_city ?: 'Ahmedabad';

                $statusLabel = 'Booking Confirmed';
                $badgeBg = '#DCFCE7';
                $badgeColor = '#16A34A';

                $st = strtolower($deal->deal_status ?: '');
                if ($st === 'delivered') {
                    $statusLabel = 'Delivered';
                    $badgeBg = '#E0E7FF';
                    $badgeColor = '#4F46E5';
                } elseif (str_contains($st, 'pdi')) {
                    $statusLabel = 'PDI Pending';
                    $badgeBg = '#FEF3C7';
                    $badgeColor = '#D97706';
                } elseif (str_contains($st, 'transit')) {
                    $statusLabel = 'In Transit';
                    $badgeBg = '#E0E7FF';
                    $badgeColor = '#4F46E5';
                } elseif (str_contains($st, 'ready')) {
                    $statusLabel = 'Ready for Delivery';
                    $badgeBg = '#DCFCE7';
                    $badgeColor = '#16A34A';
                }

                return [
                    'id' => $deal->id,
                    'customer_name' => $deal->customer_name ?: 'Customer',
                    'detail' => "{$dateFormatted} • {$city}",
                    'vehicle' => $deal->model_variant ?: $deal->brand_name,
                    'status_label' => $statusLabel,
                    'badge_bg' => $badgeBg,
                    'badge_color' => $badgeColor,
                ];
            });

            // 4. Live Recent Activities (from activity_logs table)
            $recentActivities = ActivityLog::with(['user:id,name'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(function ($log) {
                    $color = '#2563EB'; // Blue
                    $act = strtolower($log->action ?: '');
                    $mod = strtolower($log->module ?: '');

                    if ($act === 'delete') {
                        $color = '#DC2626'; // Red
                    } elseif ($mod === 'payment' || str_contains($log->detail, 'payment') || str_contains($log->detail, 'Deal')) {
                        $color = '#16A34A'; // Green
                    } elseif ($mod === 'quotation' || $act === 'export') {
                        $color = '#F59E0B'; // Amber
                    }

                    $userName = $log->user ? $log->user->name : 'System';
                    $text = $log->detail ?: "{$log->action} on {$log->module} by {$userName}";

                    return [
                        'id' => $log->id,
                        'text' => $text,
                        'time_ago' => $log->created_at ? $log->created_at->diffForHumans() : 'Just now',
                        'dot_color' => $color,
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'date_display' => Carbon::now()->format('d M Y'),
                    'kpis' => [
                        // Task 2: The 5 core KPI metrics
                        'total_leads' => $totalLeads,
                        'lead_growth' => ($leadGrowth >= 0 ? "↑ {$leadGrowth}%" : "↓ " . abs($leadGrowth) . "%"),
                        'total_follow_up' => $totalFollowUp,
                        'in_follow_up' => $totalFollowUp,
                        'total_quotation' => $totalQuotation,
                        'total_deal_booking' => $totalDealBooking,
                        'total_lost' => $totalLost,

                        // Additional metric references
                        'active_quotes' => $activeQuotes,
                        'confirmed' => $confirmedDeals,
                        'pending_pay' => $pendingPayFormatted,
                        'pending_pay_raw' => $pendingPayRaw,
                        'in_process' => $inProcessDeals,
                        'deliveries' => $deliveriesCount,
                        'gross_profit' => $grossValueFormatted,
                        'gross_profit_raw' => $grossValueRaw,
                    ],
                    'pipeline' => [
                        // Task 3: Screenshot 3 follow-up outcome statuses
                        'interested' => $countInterested,
                        'quotation_requested' => $countQuotationReq,
                        'negotiation' => $countNegotiation,
                        'not_reachable' => $countNotReachable,
                        'lost' => $countLost,
                        'deal_won' => $countDealWon,

                        // Legacy names
                        'lead' => $pipelineLead,
                        'follow_up' => $pipelineFollowUp,
                        'quotation' => $pipelineQuotation,
                        'booking' => $pipelineBooking,
                        'delivered' => $pipelineDelivered,
                    ],
                    'upcoming_deliveries' => $upcomingDeliveries,
                    'recent_activities' => $recentActivities,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DashboardController@stats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching dashboard statistics.',
            ], 500);
        }
    }

    /**
     * Get aggregate live statistics and metrics for Sales Executive Dashboard
     * Strictly scoped to the authenticated or targeted sales executive
     */
    public function salesExecutiveStats(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            $targetUserId = null;
            $targetUserName = null;

            if ($request->filled('sales_executive_id')) {
                $targetUserId = (int) $request->sales_executive_id;
            } elseif ($request->filled('user_id')) {
                $targetUserId = (int) $request->user_id;
            } elseif ($user && in_array(strtolower(str_replace(' ', '_', $user->role ?? '')), ['sales_executive', 'sales_rep', 'sales_consultant', 'sales'])) {
                $targetUserId = (int) $user->id;
                $targetUserName = $user->name;
            }

            if (!$targetUserId && $user) {
                // If requested by super admin / manager without param, default to first sales executive or auth user
                $firstExec = User::where('role', 'like', '%Sales%')->orWhere('role', 'like', '%Executive%')->first();
                $targetUserId = $firstExec ? (int) $firstExec->id : (int) $user->id;
            }

            if ($targetUserId && !$targetUserName) {
                $targetUser = User::find($targetUserId);
                $targetUserName = $targetUser ? $targetUser->name : null;
            }

            // 1. Leads Base Query
            $leadQuery = Lead::where(function ($q) use ($targetUserId, $targetUserName) {
                $q->where('assigned_to', $targetUserId);
                if ($targetUserName) {
                    $q->orWhere('assigned_user_name', 'like', '%' . $targetUserName . '%');
                }
            });

            // 2. Follow-Up Base Query
            $followUpQuery = LeadFollowUp::where(function ($q) {
                $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
            })->where(function ($q) use ($targetUserId, $targetUserName) {
                $q->where('user_id', $targetUserId)
                  ->orWhereHas('lead', function ($lq) use ($targetUserId, $targetUserName) {
                      $lq->where('assigned_to', $targetUserId);
                      if ($targetUserName) {
                          $lq->orWhere('assigned_user_name', 'like', '%' . $targetUserName . '%');
                      }
                  });
            });

            // 3. Quotation Base Query
            $quotationQuery = Quotation::where(function ($q) use ($targetUserId, $targetUserName) {
                $q->where('created_by', $targetUserId)
                  ->orWhereHas('lead', function ($lq) use ($targetUserId, $targetUserName) {
                      $lq->where('assigned_to', $targetUserId);
                      if ($targetUserName) {
                          $lq->orWhere('assigned_user_name', 'like', '%' . $targetUserName . '%');
                      }
                  });
            });

            // 4. Deal Base Query
            $dealQuery = Deal::whereNotIn('deal_status', ['cancelled'])
                ->where(function ($q) use ($targetUserId, $targetUserName) {
                    $q->where('sales_executive_id', $targetUserId)
                      ->orWhere('created_by', $targetUserId);
                    if ($targetUserName) {
                        $q->orWhere('sales_executive_name', 'like', '%' . $targetUserName . '%');
                    }
                    $q->orWhereHas('lead', function ($lq) use ($targetUserId, $targetUserName) {
                        $lq->where('assigned_to', $targetUserId);
                        if ($targetUserName) {
                            $lq->orWhere('assigned_user_name', 'like', '%' . $targetUserName . '%');
                        }
                    });
                });

            // --- 5 CORE KPI METRICS ---
            // Total Leads
            $totalLeads = (clone $leadQuery)->count();

            // Lead Growth (%)
            $startThisMonth = Carbon::now()->startOfMonth();
            $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endLastMonth = Carbon::now()->subMonth()->endOfMonth();

            $leadsThisMonth = (clone $leadQuery)->where('created_at', '>=', $startThisMonth)->count();
            $leadsLastMonth = (clone $leadQuery)->whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();
            $leadGrowth = $leadsLastMonth > 0
                ? round((($leadsThisMonth - $leadsLastMonth) / $leadsLastMonth) * 100)
                : ($leadsThisMonth > 0 ? 100 : 0);

            // Total Follow-Up
            $totalFollowUp = (clone $leadQuery)->where(function ($q) {
                $q->where('status_name', 'like', '%Follow%');
            })->count();
            if ($totalFollowUp === 0) {
                $totalFollowUp = (clone $followUpQuery)->distinct('lead_id')->count() ?: (clone $followUpQuery)->count();
            }

            // Total Quotation
            $totalQuotation = (clone $leadQuery)->where(function ($q) {
                $q->where('status_name', 'like', '%Quotation%')
                  ->orWhere('status_name', 'like', '%Quote%');
            })->count();
            if ($totalQuotation === 0) {
                $totalQuotation = (clone $quotationQuery)->count();
            }

            // Total Deal & Booking
            $totalDealBooking = (clone $leadQuery)->where(function ($q) {
                $q->where('status_name', 'like', '%Deal%')
                  ->orWhere('status_name', 'like', '%Booking%')
                  ->orWhere('status_name', 'like', '%Won%');
            })->count();
            if ($totalDealBooking === 0) {
                $totalDealBooking = (clone $dealQuery)->count();
            }

            // Total Lost
            $totalLost = (clone $leadQuery)->where(function ($q) {
                $q->where('status_name', 'like', '%Lost%');
            })->count();

            // --- MY SALES PIPELINE (6 Follow-Up Outcome Statuses) ---
            $countInterested = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Interested%')
                  ->orWhere('status', 'like', '%Call Back%');
            })->count();

            $countQuotationReq = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Quotation%')
                  ->orWhere('status', 'like', '%Quote%')
                  ->orWhere('status', 'like', '%Sent%');
            })->count();

            $countNegotiation = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Negotiation%')
                  ->orWhere('status', 'like', '%Price%');
            })->count();

            $countNotReachable = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Not Reachable%')
                  ->orWhere('status', 'like', '%Busy%');
            })->count();

            $countLost = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Lost%');
            })->count();

            $countDealWon = (clone $followUpQuery)->where(function ($q) {
                $q->where('status', 'like', '%Deal%')
                  ->orWhere('status', 'like', '%Booking%')
                  ->orWhere('status', 'like', '%Won%')
                  ->orWhere('status', 'like', '%Completed%');
            })->count();

            // --- TODAY'S FOLLOW-UPS ---
            $today = Carbon::today()->toDateString();
            $todayFollowUpsRaw = (clone $followUpQuery)
                ->with(['lead' => function ($lq) {
                    $lq->withTrashed();
                }])
                ->where(function ($q) use ($today) {
                    $q->whereDate('next_follow_up_date', $today)
                      ->orWhere(function ($sq) use ($today) {
                          $sq->whereNull('next_follow_up_date')
                             ->whereDate('follow_up_date', $today);
                      });
                })
                ->orderBy('next_follow_up_time', 'asc')
                ->orderBy('follow_up_time', 'asc')
                ->latest('id')
                ->limit(10)
                ->get();

            $todayFollowups = $todayFollowUpsRaw->map(function ($fu) {
                $lead = $fu->lead;
                $leadName = $lead ? $lead->name : 'Customer';
                $leadModel = $lead ? ($lead->model_variant ?: $lead->brand_name ?: 'Vehicle Inquired') : 'Vehicle';
                $phone = $lead ? $lead->phone : '';
                $time = $fu->next_follow_up_time ?: $fu->follow_up_time ?: '10:00 AM';
                $lastContact = $fu->created_at ? $fu->created_at->diffForHumans() : 'Recently';

                return [
                    'id' => $fu->id,
                    'lead_id' => $fu->lead_id,
                    'name' => $leadName,
                    'model' => $leadModel,
                    'time' => $time,
                    'lastContact' => $lastContact,
                    'phone' => $phone,
                    'notes' => $fu->notes,
                    'status' => $fu->status,
                ];
            });

            // --- RECENT QUOTATIONS (Last 3 Sent) ---
            $recentQuotations = (clone $quotationQuery)
                ->with(['lead' => function ($lq) {
                    $lq->withTrashed();
                }])
                ->latest('quotation_date')
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(function ($q) {
                    $lead = $q->lead;
                    $model = $lead ? ($lead->model_variant ?: $lead->brand_name) : ($q->subject ?: 'Quotation');
                    $time = $q->created_at ? $q->created_at->diffForHumans() : ($q->quotation_date ? Carbon::parse($q->quotation_date)->format('d M') : 'Recent');

                    return [
                        'id' => $q->id,
                        'name' => $q->customer_name ?: ($lead ? $lead->name : 'Customer'),
                        'model' => $model,
                        'time' => $time,
                        'quotation_number' => $q->quotation_number,
                        'amount' => '₹' . number_format($q->grand_total ?: 0, 0),
                    ];
                });

            // --- RECENT FOLLOW-UPS (Last 3 from Follow-up Hub) ---
            $recentFollowups = (clone $followUpQuery)
                ->with(['lead' => function ($lq) {
                    $lq->withTrashed();
                }])
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(function ($fu) {
                    $lead = $fu->lead;
                    $status = $fu->status ?: 'Pending';
                    $badgeClass = 'bg-primary-subtle text-primary';
                    $st = strtolower($status);
                    if (str_contains($st, 'interested')) {
                        $badgeClass = 'bg-primary-subtle text-primary';
                    } elseif (str_contains($st, 'lost')) {
                        $badgeClass = 'bg-danger-subtle text-danger';
                    } elseif (str_contains($st, 'won') || str_contains($st, 'deal')) {
                        $badgeClass = 'bg-success-subtle text-success';
                    } elseif (str_contains($st, 'quote') || str_contains($st, 'quotation')) {
                        $badgeClass = 'bg-info-subtle text-info';
                    } elseif (str_contains($st, 'negotiation')) {
                        $badgeClass = 'bg-warning-subtle text-warning';
                    }

                    return [
                        'id' => $fu->id,
                        'lead_id' => $fu->lead_id,
                        'name' => $lead ? $lead->name : 'Customer',
                        'model' => $lead ? ($lead->model_variant ?: $lead->brand_name ?: 'Car Inquiry') : 'Vehicle',
                        'status' => $status,
                        'badgeClass' => $badgeClass,
                        'time' => $fu->created_at ? $fu->created_at->diffForHumans() : 'Recent',
                    ];
                });

            // --- RECENT BOOKINGS (Last 3 Records from Deals) ---
            $recentBookings = (clone $dealQuery)
                ->latest('booking_date')
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(function ($deal) {
                    $statusLabel = 'Conf';
                    $st = strtolower($deal->deal_status ?: '');
                    if ($st === 'delivered') {
                        $statusLabel = 'Delivered';
                    } elseif (str_contains($st, 'pdi')) {
                        $statusLabel = 'PDI';
                    } elseif (str_contains($st, 'transit')) {
                        $statusLabel = 'In Transit';
                    } elseif (str_contains($st, 'ready')) {
                        $statusLabel = 'Ready';
                    } elseif (str_contains($st, 'booked') || str_contains($st, 'confirm')) {
                        $statusLabel = 'Conf';
                    }

                    return [
                        'id' => $deal->id,
                        'name' => $deal->customer_name ?: 'Customer',
                        'model' => $deal->model_variant ?: $deal->brand_name ?: 'Vehicle',
                        'status' => $statusLabel,
                        'deal_number' => $deal->deal_number,
                        'amount' => '₹' . number_format($deal->net_amount ?: $deal->total_amount ?: 0, 0),
                    ];
                });

            return response()->json([
                'status' => true,
                'message' => 'Sales Executive dashboard statistics retrieved successfully',
                'data' => [
                    'date_display' => Carbon::now()->format('d M Y'),
                    'user_name' => $targetUserName,
                    'kpis' => [
                        'total_leads' => $totalLeads,
                        'lead_growth' => ($leadGrowth >= 0 ? "↑ {$leadGrowth}%" : "↓ " . abs($leadGrowth) . "%"),
                        'total_follow_up' => $totalFollowUp,
                        'total_quotation' => $totalQuotation,
                        'total_deal_booking' => $totalDealBooking,
                        'total_lost' => $totalLost,
                    ],
                    'pipeline' => [
                        'interested' => $countInterested,
                        'quotation_requested' => $countQuotationReq,
                        'negotiation' => $countNegotiation,
                        'not_reachable' => $countNotReachable,
                        'lost' => $countLost,
                        'deal_won' => $countDealWon,
                    ],
                    'today_followups' => $todayFollowups,
                    'recent_quotations' => $recentQuotations,
                    'recent_followups' => $recentFollowups,
                    'recent_bookings' => $recentBookings,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DashboardController@salesExecutiveStats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching sales executive dashboard statistics.',
            ], 500);
        }
    }

    /**
     * Get aggregate live statistics and metrics for Receptionist Dashboard
     */
    public function receptionistStats(Request $request)
    {
        try {
            $user = $request->user('sanctum') ?? auth('sanctum')->user() ?? auth()->user();

            // 1. KPI Cards (5 Primary Metrics matching Super Admin Dashboard)
            $totalLeads = Lead::count();

            // Calculate lead growth percentage comparing this month vs last month
            $startThisMonth = Carbon::now()->startOfMonth();
            $startLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endLastMonth = Carbon::now()->subMonth()->endOfMonth();

            $leadsThisMonth = Lead::where('created_at', '>=', $startThisMonth)->count();
            $leadsLastMonth = Lead::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();
            $leadGrowth = $leadsLastMonth > 0 
                ? round((($leadsThisMonth - $leadsLastMonth) / $leadsLastMonth) * 100)
                : ($leadsThisMonth > 0 ? 100 : 0);

            // 1) Total Follow-Up
            $totalFollowUp = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Follow%');
            })->count();
            if ($totalFollowUp === 0) {
                $totalFollowUp = LeadFollowUp::distinct('lead_id')->count() ?: LeadFollowUp::count();
            }

            // 2) Total Quotation
            $totalQuotation = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Quotation%')
                  ->orWhere('status_name', 'like', '%Quote%');
            })->count();
            if ($totalQuotation === 0) {
                $totalQuotation = Quotation::count();
            }

            // 3) Total Deal & Booking
            $totalDealBooking = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Deal%')
                  ->orWhere('status_name', 'like', '%Booking%')
                  ->orWhere('status_name', 'like', '%Won%');
            })->count();
            if ($totalDealBooking === 0) {
                $totalDealBooking = Deal::whereNotIn('deal_status', ['cancelled'])->count();
            }

            // 4) Total Lost
            $totalLost = Lead::where(function ($q) {
                $q->where('status_name', 'like', '%Lost%');
            })->count();

            // 2. Unassigned Leads (Leads created but not yet assigned to any Sales Executive)
            $unassignedLeads = Lead::where(function ($q) {
                $q->whereNull('assigned_to')
                  ->orWhere('assigned_to', 0)
                  ->orWhere('assigned_to', '')
                  ->orWhereNull('assigned_user_name')
                  ->orWhere('assigned_user_name', '');
            })
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function ($lead) {
                $name = $lead->name ?: 'Customer';
                $words = preg_split('/\s+/', trim($name));
                $initials = '';
                if (count($words) >= 2) {
                    $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($name, 0, min(2, strlen($name))));
                }

                return [
                    'id' => $lead->id,
                    'name' => $name,
                    'initials' => $initials ?: 'LD',
                    'vehicle' => $lead->model_variant ?: $lead->brand_name ?: 'Vehicle',
                    'source' => $lead->source_name ?: 'Web Form',
                    'phone' => $lead->phone,
                    'time' => $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently',
                ];
            });

            // 3. Recent Inquiries (Latest leads created in system)
            $recentInquiries = Lead::latest('id')
                ->limit(6)
                ->get()
                ->map(function ($lead) {
                    $source = $lead->source_name ?: 'Direct';
                    $srcLower = strtolower($source);
                    $sourceIcon = 'bi-globe text-info';
                    if (str_contains($srcLower, 'whatsapp')) {
                        $sourceIcon = 'bi-whatsapp text-success';
                    } elseif (str_contains($srcLower, 'call') || str_contains($srcLower, 'phone')) {
                        $sourceIcon = 'bi-telephone text-primary';
                    } elseif (str_contains($srcLower, 'walk')) {
                        $sourceIcon = 'bi-shop text-warning';
                    }

                    $status = $lead->status_name ?: 'New';
                    $stLower = strtolower($status);
                    $badgeBg = '#F1F5F9';
                    $badgeColor = '#475569';
                    if (str_contains($stLower, 'new')) {
                        $badgeBg = '#F4F8E8';
                        $badgeColor = '#4D5D25';
                    } elseif (str_contains($stLower, 'follow') || str_contains($stLower, 'progress')) {
                        $badgeBg = '#FEF3C7';
                        $badgeColor = '#D97706';
                    } elseif (str_contains($stLower, 'deal') || str_contains($stLower, 'won')) {
                        $badgeBg = '#DCFCE7';
                        $badgeColor = '#16A34A';
                    } elseif (str_contains($stLower, 'lost')) {
                        $badgeBg = '#FEE2E2';
                        $badgeColor = '#DC2626';
                    }

                    return [
                        'id' => $lead->id,
                        'contact' => $lead->name ?: 'Customer',
                        'phone' => $lead->phone,
                        'time' => $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently',
                        'vehicle' => $lead->model_variant ?: $lead->brand_name ?: 'Vehicle',
                        'source' => $source,
                        'sourceIcon' => $sourceIcon,
                        'status' => strtoupper($status),
                        'statusBadge' => [
                            'bg' => $badgeBg,
                            'color' => $badgeColor,
                            'border' => '#E2E8F0',
                        ],
                    ];
                });

            // 4. Today's Follow-ups
            $today = Carbon::today()->toDateString();
            $todayFollowups = LeadFollowUp::with(['lead' => function ($lq) {
                    $lq->withTrashed();
                }])
                ->where(function ($q) {
                    $q->whereNull('record_status')->orWhere('record_status', '!=', 'deleted');
                })
                ->where(function ($q) use ($today) {
                    $q->whereDate('next_follow_up_date', $today)
                      ->orWhere(function ($sq) use ($today) {
                          $sq->whereNull('next_follow_up_date')
                             ->whereDate('follow_up_date', $today);
                      });
                })
                ->orderBy('next_follow_up_time', 'asc')
                ->orderBy('follow_up_time', 'asc')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(function ($fu) {
                    $lead = $fu->lead;
                    $status = $fu->status ?: 'Pending';
                    $st = strtolower($status);
                    $type = (str_contains($st, 'won') || str_contains($st, 'completed') || str_contains($st, 'done')) ? 'done' : 'active';

                    return [
                        'id' => $fu->id,
                        'time' => $fu->next_follow_up_time ?: $fu->follow_up_time ?: '10:00 AM',
                        'name' => $lead ? $lead->name : 'Customer',
                        'vehicle' => $lead ? ($lead->model_variant ?: $lead->brand_name ?: 'Vehicle') : 'Vehicle',
                        'phone' => $lead ? $lead->phone : '',
                        'note' => $fu->notes ?: $status,
                        'status' => $status,
                        'type' => $type,
                    ];
                });

            // 5. Active Sales Executives List for quick assignment
            $executives = User::where(function ($q) {
                $q->where('role', 'like', '%Sales%')
                  ->orWhere('role', 'like', '%Executive%')
                  ->orWhere('role', 'like', '%Manager%');
            })
            ->where('status', 'Active')
            ->select('id', 'name', 'role')
            ->get();

            if ($executives->isEmpty()) {
                $executives = User::where('status', 'Active')->select('id', 'name', 'role')->get();
            }

            return response()->json([
                'status' => true,
                'message' => 'Receptionist dashboard statistics retrieved successfully',
                'data' => [
                    'date_display' => Carbon::now()->format('d M Y'),
                    'kpis' => [
                        'total_leads' => $totalLeads,
                        'lead_growth' => ($leadGrowth >= 0 ? "↑ {$leadGrowth}%" : "↓ " . abs($leadGrowth) . "%"),
                        'total_follow_up' => $totalFollowUp,
                        'total_quotation' => $totalQuotation,
                        'total_deal_booking' => $totalDealBooking,
                        'total_lost' => $totalLost,
                    ],
                    'unassigned_leads' => $unassignedLeads,
                    'recent_inquiries' => $recentInquiries,
                    'today_followups' => $todayFollowups,
                    'executives' => $executives,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('DashboardController@receptionistStats error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => $e->getMessage() ?: 'An error occurred while fetching receptionist dashboard statistics.',
            ], 500);
        }
    }

    /**
     * Helper to format currency into Indian Rupees Lakhs / Crores
     */
    private function formatCurrencyLakhs($amount)
    {
        $amount = (float) $amount;
        if ($amount >= 10000000) {
            return '₹' . number_format($amount / 10000000, 2) . ' Cr';
        } elseif ($amount >= 100000) {
            return '₹' . number_format($amount / 100000, 1) . 'L';
        } else {
            return '₹' . number_format($amount, 0);
        }
    }
}
