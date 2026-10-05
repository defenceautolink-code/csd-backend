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
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('model_id')->nullable()->after('brand_name')->constrained('vehicle_models')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->after('model_id')->constrained('vehicle_variants')->nullOnDelete();

            $table->index('model_id');
            $table->index('variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['model_id']);
            $table->dropForeign(['variant_id']);
            $table->dropIndex(['model_id']);
            $table->dropIndex(['variant_id']);
            $table->dropColumn(['model_id', 'variant_id']);
        });
    }
};
