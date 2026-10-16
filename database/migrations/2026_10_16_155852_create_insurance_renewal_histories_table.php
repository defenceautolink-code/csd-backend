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
        Schema::create('insurance_renewal_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('insurance_id')->index();
            $table->unsignedBigInteger('deal_id')->nullable()->index();
            $table->string('policy_number', 100)->nullable();
            $table->string('insurance_company', 150)->nullable();
            $table->string('insurance_type', 50)->default('Comprehensive');
            $table->date('policy_start_date')->nullable();
            $table->date('policy_expiry_date')->nullable();
            $table->decimal('premium_amount', 12, 2)->default(0.00);
            $table->integer('reminder_days_before')->default(15);
            $table->date('reminder_date')->nullable();
            $table->string('renewal_status', 50)->default('Renewed');
            $table->timestamp('renewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('insurance_id')->references('id')->on('insurances')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_renewal_histories');
    }
};
