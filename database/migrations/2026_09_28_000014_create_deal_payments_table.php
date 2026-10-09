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
        Schema::create('deal_payments', function (Blueprint $table) {
            $table->id();
            
            // Unique Receipt Number (e.g. REC-2026-0001)
            $table->string('receipt_number')->unique();
            
            // Related Deal and Lead
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->cascadeOnDelete();
            
            // Payment Classification
            // token_advance, down_payment, bank_finance, exchange_bonus, part_payment, balance_payment, accessory_payment, refund
            $table->string('payment_type')->default('token_advance');
            
            // Financial Amount
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            
            // Payment Mode (upi, neft_rtgs, cheque, cash, card_pos, bank_disbursal)
            $table->string('payment_mode');
            
            // Transaction / Cheque / Bank details
            $table->string('transaction_reference')->nullable(); // UTR / Transaction ID / POS Ref / Cheque No
            $table->string('bank_name')->nullable();             // e.g. HDFC, SBI, ICICI, Axis
            $table->date('cheque_date')->nullable();
            $table->string('cheque_status')->nullable();         // pending_clearance, cleared, bounced
            
            // File attachment / Proof of receipt or deposit slip
            $table->string('payment_proof_path')->nullable();
            $table->string('received_by')->nullable();
            
            // Verification & Clearance Status (pending, cleared, bounced, rejected, refunded)
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Audit Trails & Staff
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recorded_by_name')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verified_by_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_payments');
    }
};
