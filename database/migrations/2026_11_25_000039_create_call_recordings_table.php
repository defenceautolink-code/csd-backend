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
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained('lead_follow_ups')->nullOnDelete();
            $table->string('batch_id', 64)->nullable()->index();
            $table->date('recording_date')->index();
            $table->string('call_time', 50)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->text('notes')->nullable();
            $table->string('review_status', 30)->default('pending')->index(); // pending, verified, flagged
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remark')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'recording_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
    }
};
