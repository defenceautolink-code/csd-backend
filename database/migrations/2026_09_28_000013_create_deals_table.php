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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            
            // Unique Deal & Booking Identifier (e.g. DEAL-2026-0001)
            $table->string('deal_number')->unique();
            
            // Related Lead & Optional Quotation
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            
            // Customer Snapshot
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone');
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->text('customer_address')->nullable();
            
            // Vehicle Snapshot & Specs
            $table->string('vehicle_segment')->default('4 Wheeler'); // 2 Wheeler / 4 Wheeler
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('model_variant'); // e.g. Brezza ZXi+, Mahindra Thar AX7
            $table->string('color')->nullable();
            $table->string('vin_chassis_number')->nullable();
            $table->string('engine_number')->nullable();
            $table->string('registration_number')->nullable(); // RTO assigned vehicle number
            
            // Financials Breakdown (in INR / Deal Currency)
            $table->decimal('total_amount', 12, 2)->default(0.00);      // Total agreed On-Road / Invoice Price
            $table->decimal('discount_amount', 12, 2)->default(0.00);   // Additional Deal Discount / Special Offer
            $table->decimal('net_amount', 12, 2)->default(0.00);        // total_amount - discount_amount
            $table->decimal('total_paid', 12, 2)->default(0.00);        // Auto-sum of cleared payments
            $table->decimal('balance_due', 12, 2)->default(0.00);       // net_amount - total_paid
            
            // Statuses
            $table->string('payment_status')->default('unpaid'); // unpaid, partially_paid, paid, overpaid, refunded
            $table->string('deal_status')->default('booking_confirmed'); // booking_confirmed, allotment_pending, allotted, in_pdi, ready_for_delivery, delivered, cancelled
            
            // Deal Timeline Dates
            $table->date('booking_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            
            // Sales Executive & Management Assignment
            $table->foreignId('sales_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sales_executive_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Notes & Commercial Terms
            $table->text('payment_terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
