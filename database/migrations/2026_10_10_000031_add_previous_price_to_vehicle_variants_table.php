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
        Schema::table('vehicle_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_variants', 'previous_price')) {
                $table->decimal('previous_price', 12, 2)->nullable()->default(null)->after('price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_variants', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_variants', 'previous_price')) {
                $table->dropColumn('previous_price');
            }
        });
    }
};
