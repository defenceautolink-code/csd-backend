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
        Schema::create('insurances', function (Blueprint $table) {
            $table->id();

            // Related Deal & Lead
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            // Customer Details
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->text('customer_address')->nullable();

            // Vehicle Details
            $table->string('vehicle_name')->nullable(); // e.g. Maruti Swift VXI
            $table->string('registration_number')->nullable()->index();
            $table->string('vin_chassis_number')->nullable();
            $table->string('engine_number')->nullable();

            // Financial & Policy Amounts
            $table->decimal('premium_amount', 12, 2)->default(0.00);
            $table->decimal('idv_amount', 12, 2)->nullable(); // Insured Declared Value

            // Policy Dates & Reminders
            $table->date('delivery_date')->nullable(); // Vehicle delivery date
            $table->date('start_date'); // Policy start date / Issue date
            $table->date('expiry_date'); // Policy expiry date
            $table->date('next_insurance_date')->index(); // Next insurance renewal due date

            // Status & Reminder Trackers
            // active, expiring_soon, expired, renewed
            $table->string('status')->default('active')->index();
            $table->boolean('reminder_sent')->default(false);
            $table->text('notes')->nullable();

            // Staff / Staff Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sales_executive_name')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurances');
    }
};
