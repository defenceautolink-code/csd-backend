<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\LeadAssignmentHistoryController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadSourceController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TokenBillingController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DealPaymentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SalesExecutiveLeadController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleModelController;
use App\Http\Controllers\VehiclePriceMasterController;
use App\Http\Controllers\VehicleVariantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication API
Route::post('/auth/login', [AuthController::class, 'login']);

// Sales Executive Dedicated APIs (Strictly scoped to authenticated user)
Route::middleware('auth:sanctum')->prefix('sales-executive')->group(function () {
    Route::get('/leads', [SalesExecutiveLeadController::class, 'index']);
    Route::get('/leads/{lead}', [SalesExecutiveLeadController::class, 'show']);
    Route::get('/leads/{lead}/follow-ups', [FollowUpController::class, 'getByLead']);
    Route::post('/leads/{lead}/follow-ups', [FollowUpController::class, 'store']);
});

// Lead Sources Master CRUD API
Route::apiResource('lead-sources', LeadSourceController::class);

// Lead Statuses Master CRUD API
Route::apiResource('lead-statuses', LeadStatusController::class);

// Brand Master CRUD API
Route::apiResource('brands', BrandController::class);

// Vehicle Model Master CRUD API
Route::apiResource('models', VehicleModelController::class);

// Vehicle Variant Master CRUD API
Route::apiResource('variants', VehicleVariantController::class);

// Customer Leads Bulk Operations API
Route::post('leads/bulk-delete', [LeadController::class, 'bulkDelete']);
Route::post('leads/bulk-status', [LeadController::class, 'bulkStatus']);
Route::post('leads/bulk-priority', [LeadController::class, 'bulkPriority']);
Route::post('leads/bulk-assign', [LeadController::class, 'bulkAssign']);
Route::post('leads/bulk-import', [LeadController::class, 'bulkImport']);
Route::post('leads/import', [LeadController::class, 'bulkImport']);

// Specific Lead Assignment History Route
Route::get('leads/{id}/assignments', [LeadAssignmentHistoryController::class, 'getByLead']);

// Unified Lead Follow-Ups API (Role Managed)
Route::get('leads/{id}/follow-ups', [FollowUpController::class, 'getByLead']);
Route::post('leads/{id}/follow-ups', [FollowUpController::class, 'store']);
Route::apiResource('follow-ups', FollowUpController::class);

// Get Lead details formatted for Quotation creation
Route::get('leads/{id}/quotation', [QuotationController::class, 'getLeadForQuotation']);
// Get all Quotations and complete Lead data for a specific Lead
Route::get('leads/{id}/quotations', [QuotationController::class, 'getByLead']);

// Lead Assignment History Master CRUD API
Route::apiResource('lead-assignments', LeadAssignmentHistoryController::class);

// Trigger Birthday & Anniversary Greetings Dispatch (GET method only)
Route::get('leads/send-greetings-now', [LeadController::class, 'sendGreetingsNow']);

// Customer Leads Master CRUD API
Route::apiResource('leads', LeadController::class);

// Users Master CRUD API
Route::apiResource('users', UserController::class);

// Quotations API Routes
Route::post('quotations/{id}/send', [QuotationController::class, 'sendEmail']);
Route::get('quotations/{id}/pdf', [QuotationController::class, 'downloadPdf']);
Route::apiResource('quotations', QuotationController::class);

// Deals & Bookings API Routes
Route::get('deals/stats', [DealController::class, 'stats']);
Route::get('deals/convert-lead/{leadId}', [DealController::class, 'getLeadForDeal']);
Route::post('deals/convert-lead', [DealController::class, 'convertLead']);
Route::get('deals/{dealId}/payments', [DealPaymentController::class, 'getByDeal']);
Route::apiResource('deals', DealController::class);

// Deal Payments & Payment Receipts API Routes
Route::get('payments/stats', [DealPaymentController::class, 'stats']);
Route::get('payments/pending-clearance', [DealPaymentController::class, 'getPendingClearances']);
Route::post('payments/{id}/verify', [DealPaymentController::class, 'verify']);
Route::apiResource('payments', DealPaymentController::class);

// Expense Master & Expense Tracking API Routes
Route::patch('expense-categories/{id}/toggle-status', [ExpenseCategoryController::class, 'toggleStatus']);
Route::apiResource('expense-categories', ExpenseCategoryController::class);

Route::get('expenses/stats', [ExpenseController::class, 'stats']);
Route::apiResource('expenses', ExpenseController::class);


// Reports & Dealership Analytics API Routes
Route::prefix('reports')->group(function () {
    Route::get('dealership-analytics', [ReportController::class, 'dealershipAnalytics']);
    Route::get('kpi-summary', [ReportController::class, 'kpiSummary']);
    Route::get('conversion-funnel', [ReportController::class, 'conversionFunnel']);
    Route::get('lead-source-attribution', [ReportController::class, 'leadSourceAttribution']);
    Route::get('executive-leaderboard', [ReportController::class, 'executiveLeaderboard']);
    Route::get('export', [ReportController::class, 'exportReportData']);
});

// Vehicle Price Master & Calculator API Routes
Route::prefix('price-master')->group(function () {
    Route::get('/', [VehiclePriceMasterController::class, 'getPricingMaster']);
    Route::get('variant/{id}', [VehiclePriceMasterController::class, 'getVariantPricing']);
    Route::post('update-price', [VehiclePriceMasterController::class, 'updateAndPublishPrice']);
    Route::post('calculate-on-road', [VehiclePriceMasterController::class, 'calculateOnRoad']);
    Route::get('revisions', [VehiclePriceMasterController::class, 'getPriceRevisions']);
    Route::post('send-quotation', [VehiclePriceMasterController::class, 'sendQuotation']);
    Route::get('export', [VehiclePriceMasterController::class, 'exportPriceMaster']);
});

// Generate Invoice & Token Billing API Routes
Route::prefix('token-billing')->group(function () {
    Route::get('/', [TokenBillingController::class, 'getTokenBillingDashboard']);
    Route::get('kpis', [TokenBillingController::class, 'getKpis']);
    Route::get('tokens', [TokenBillingController::class, 'getCustomerTokens']);
    Route::post('tokens', [TokenBillingController::class, 'storeToken']);
    Route::get('tokens/{id}', [TokenBillingController::class, 'getSingleTokenLedger']);
    Route::get('invoices', [TokenBillingController::class, 'getAllInvoices']);
    Route::post('generate-invoice', [TokenBillingController::class, 'generateInvoice']);
    Route::get('export-csv', [TokenBillingController::class, 'exportInvoicesCsv']);
    Route::post('reset-demo', [TokenBillingController::class, 'resetDemo']);
});



