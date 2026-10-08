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
        // 1. Add Price Breakdown Columns to vehicle_variants table
        Schema::table('vehicle_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_variants', 'ex_showroom_price')) {
                $table->decimal('ex_showroom_price', 12, 2)->default(0.00)->after('price');
            }
            if (!Schema::hasColumn('vehicle_variants', 'rto_road_tax')) {
                $table->decimal('rto_road_tax', 12, 2)->default(0.00)->after('ex_showroom_price');
            }
            if (!Schema::hasColumn('vehicle_variants', 'insurance')) {
                $table->decimal('insurance', 12, 2)->default(0.00)->after('rto_road_tax');
            }
            if (!Schema::hasColumn('vehicle_variants', 'fastag_logistics')) {
                $table->decimal('fastag_logistics', 12, 2)->default(0.00)->after('insurance');
            }
            if (!Schema::hasColumn('vehicle_variants', 'on_road_price')) {
                $table->decimal('on_road_price', 12, 2)->default(0.00)->after('fastag_logistics');
            }
        });

        // 2. Create vehicle_price_logs Table for Recent Price Revisions & Audit Log
        if (!Schema::hasTable('vehicle_price_logs')) {
            Schema::create('vehicle_price_logs', function (Blueprint $table) {
                $table->id();
                
                $table->foreignId('variant_id')->constrained('vehicle_variants')->cascadeOnDelete();
                $table->foreignId('model_id')->nullable()->constrained('vehicle_models')->nullOnDelete();
                $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
                
                $table->string('model_variant_name');
                $table->string('brand_name')->nullable();
                
                $table->decimal('previous_ex_showroom', 12, 2)->default(0.00);
                $table->decimal('revised_ex_showroom', 12, 2)->default(0.00);
                $table->decimal('net_difference', 12, 2)->default(0.00);
                
                $table->decimal('rto_road_tax', 12, 2)->default(0.00);
                $table->decimal('insurance', 12, 2)->default(0.00);
                $table->decimal('fastag_logistics', 12, 2)->default(0.00);
                
                $table->decimal('previous_on_road', 12, 2)->default(0.00);
                $table->decimal('revised_on_road', 12, 2)->default(0.00);
                
                $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('updated_by_name')->default('Alexander Vance');
                
                $table->date('revision_date');
                $table->string('status')->default('Active'); // Active / Archived
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_price_logs');
        
        Schema::table('vehicle_variants', function (Blueprint $table) {
            $table->dropColumn([
                'ex_showroom_price',
                'rto_road_tax',
                'insurance',
                'fastag_logistics',
                'on_road_price',
            ]);
        });
    }
};
