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
        Schema::table('deal_payments', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->unique('invoice_no')->after('payment_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deal_payments', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
