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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // 1. Customer Details
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            
            // 2. Vehicle Requirement & Lead Priority
            $table->string('vehicle_segment')->default('4 Wheeler'); // 2 Wheeler / 4 Wheeler
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('model_variant'); // e.g. Brezza ZDI, Classic 350
            $table->string('priority')->default('Hot'); // Hot / Warm / Cold
            $table->string('purchase_timeline')->nullable(); // Immediate (Within 7 Days), 15-30 Days, etc.
            
            // 3. Lead Tracking & Assignment
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->string('source_name')->nullable(); // Website, Showroom Walk-in, etc.
            $table->foreignId('status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->string('status_name')->default('New'); // New, In Follow-Up, Deal Won, etc.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_user_name')->nullable(); // David Miller, Alexander Vance, etc.
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
