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
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->date('follow_up_date');
            $table->string('follow_up_time')->nullable();
            
            $table->string('type')->default('Call'); // Call, Meeting, WhatsApp, Email, Visit, Other
            $table->text('notes')->nullable();
            
            $table->date('next_follow_up_date')->nullable();
            $table->string('next_follow_up_time')->nullable();
            
            $table->string('status')->default('Pending'); // Pending, Completed, Cancelled
            
            $table->timestamps();

            // Indexes for fast lookup
            $table->index('lead_id');
            $table->index('user_id');
            $table->index('follow_up_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_follow_ups');
    }
};
