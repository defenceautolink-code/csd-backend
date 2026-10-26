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
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (Schema::hasColumn('activity_logs', 'user_id')) {
                    try {
                        $table->dropForeign(['user_id']);
                    } catch (\Throwable $e) {}
                    try {
                        $table->dropIndex(['user_id', 'created_at']);
                    } catch (\Throwable $e) {}
                    $table->dropColumn('user_id');
                }

                if (Schema::hasColumn('activity_logs', 'title')) {
                    $table->dropColumn('title');
                }
                if (Schema::hasColumn('activity_logs', 'method')) {
                    $table->dropColumn('method');
                }
                if (Schema::hasColumn('activity_logs', 'url')) {
                    $table->dropColumn('url');
                }
                if (Schema::hasColumn('activity_logs', 'user_agent')) {
                    $table->dropColumn('user_agent');
                }
                if (Schema::hasColumn('activity_logs', 'description')) {
                    $table->dropColumn('description');
                }
                if (!Schema::hasColumn('activity_logs', 'detail')) {
                    $table->text('detail')->nullable()->after('action');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
