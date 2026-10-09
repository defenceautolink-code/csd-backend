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
        if (Schema::hasTable('deal_payments') && !Schema::hasColumn('deal_payments', 'received_by')) {
            Schema::table('deal_payments', function (Blueprint $table) {
                $table->string('received_by')->nullable()->after('payment_proof_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('deal_payments') && Schema::hasColumn('deal_payments', 'received_by')) {
            Schema::table('deal_payments', function (Blueprint $table) {
                $table->dropColumn('received_by');
            });
        }
    }
};
