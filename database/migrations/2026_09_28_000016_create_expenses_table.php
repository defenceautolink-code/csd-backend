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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50); // Cash, Bank Transfer, UPI, Cheque, Credit Card, Debit Card
            $table->string('reference_no', 100)->nullable(); // Transaction ID, Cheque No, Invoice / Receipt Ref
            $table->string('attachment', 255)->nullable(); // Receipt / Invoice document upload path
            $table->string('status', 20)->default('Paid'); // Paid, Pending, Approved, Cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
