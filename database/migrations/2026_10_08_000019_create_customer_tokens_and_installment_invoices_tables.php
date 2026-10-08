<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Customer Tokens Table (Multi-Bill Ledgers)
        if (!Schema::hasTable('customer_tokens')) {
            Schema::create('customer_tokens', function (Blueprint $table) {
                $table->id();
                
                // Unique Token Number (e.g. TKN-2026-1042)
                $table->string('token_number')->unique();
                $table->date('token_date');
                
                // Optional relations to Deals and Leads
                $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                
                // Customer Details & CSD Service Card Info
                $table->string('customer_name');
                $table->string('customer_phone');
                $table->string('customer_email')->nullable();
                $table->string('service_id')->nullable(); // CSD Rank / Army Number e.g. JC-458921X
                $table->string('rank_designation')->nullable(); // e.g. Subedar, Major, Havildar, Captain
                
                // Booked Vehicle Snapshot
                $table->string('brand_name')->nullable();
                $table->string('vehicle_name'); // e.g. Maruti Suzuki Grand Vitara
                $table->string('model_variant'); // e.g. Zeta 1.5L Smart Hybrid Petrol
                
                // Deal Financial Ledgers
                $table->decimal('total_deal_amount', 12, 2)->default(0.00);
                $table->decimal('total_paid_amount', 12, 2)->default(0.00);
                $table->decimal('remaining_balance', 12, 2)->default(0.00);
                $table->float('paid_percentage')->default(0.0);
                $table->integer('invoices_count')->default(0);
                
                // Ledger Status (Partial, Settled, Pending)
                $table->string('status')->default('Partial');
                $table->text('notes')->nullable();
                
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. Installment Invoices Table (Sequential Invoices: DAL-/YYYY/MM/XXXX)
        if (!Schema::hasTable('installment_invoices')) {
            Schema::create('installment_invoices', function (Blueprint $table) {
                $table->id();
                
                // Sequential Invoice Number (e.g. DAL-2026/09/0001)
                $table->string('invoice_number')->unique();
                $table->date('invoice_date');
                
                // Parent Customer Token Ledger
                $table->foreignId('token_id')->constrained('customer_tokens')->cascadeOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                
                // Customer Snapshot
                $table->string('customer_name');
                $table->string('customer_phone');
                $table->string('service_id')->nullable();
                $table->string('booked_vehicle');
                
                // Invoice Type & Breakdown
                $table->string('bill_type')->default('Token Advance'); // Token Advance, Part Payment 1, Final Settlement
                $table->decimal('invoice_amount', 12, 2)->default(0.00);
                $table->decimal('tax_amount', 12, 2)->default(0.00);
                $table->decimal('net_payable', 12, 2)->default(0.00);
                
                $table->string('payment_mode')->default('NEFT/RTGS'); // Bank Transfer, UPI, Cheque, Cash
                $table->string('transaction_reference')->nullable();
                
                // Status (Paid, Partial, Pending, Overdue)
                $table->string('payment_status')->default('Paid');
                $table->string('status')->default('Active'); // Active, Cancelled
                
                $table->string('pdf_path')->nullable();
                $table->text('notes')->nullable();
                
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_invoices');
        Schema::dropIfExists('customer_tokens');
    }
};
