<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['leads', 'lead_follow_ups', 'quotations', 'users'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'record_status')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('record_status', 20)->default('active')->after('id')->index();
                });

                // Backfill existing records
                DB::table($table)->whereNull('record_status')->update(['record_status' => 'active']);

                // If deleted_at exists and is not null, mark as deleted
                if (Schema::hasColumn($table, 'deleted_at')) {
                    DB::table($table)->whereNotNull('deleted_at')->update(['record_status' => 'deleted']);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['leads', 'lead_follow_ups', 'quotations', 'users'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'record_status')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('record_status');
                });
            }
        }
    }
};
