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
        Schema::table('insurances', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('insurances', 'policy_number')) {
                $columnsToDrop[] = 'policy_number';
            }
            if (Schema::hasColumn('insurances', 'insurance_company')) {
                $columnsToDrop[] = 'insurance_company';
            }
            if (Schema::hasColumn('insurances', 'insurance_type')) {
                $columnsToDrop[] = 'insurance_type';
            }
            if (Schema::hasColumn('insurances', 'policy_type')) {
                $columnsToDrop[] = 'policy_type';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->string('policy_number')->nullable()->index();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_type')->default('Comprehensive');
            $table->string('policy_type')->default('New Policy');
        });
    }
};
