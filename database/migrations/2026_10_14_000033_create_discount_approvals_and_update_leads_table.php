<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add discount approval tracking columns to leads table
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'discount_approval_status')) {
                $table->string('discount_approval_status')->default('none')->after('status_name'); // none, pending, approved, rejected
            }
            if (!Schema::hasColumn('leads', 'requested_discount')) {
                $table->decimal('requested_discount', 12, 2)->default(0.00)->after('discount_approval_status');
            }
            if (!Schema::hasColumn('leads', 'approved_discount')) {
                $table->decimal('approved_discount', 12, 2)->default(0.00)->after('requested_discount');
            }
        });

        // 2. Add discount request fields to lead_follow_ups table
        Schema::table('lead_follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_follow_ups', 'is_discount_requested')) {
                $table->boolean('is_discount_requested')->default(false)->after('status');
            }
            if (!Schema::hasColumn('lead_follow_ups', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->nullable()->after('is_discount_requested');
            }
            if (!Schema::hasColumn('lead_follow_ups', 'proposed_price')) {
                $table->decimal('proposed_price', 12, 2)->nullable()->after('discount_amount');
            }
        });

        // 3. Create discount_approvals table
        if (!Schema::hasTable('discount_approvals')) {
            Schema::create('discount_approvals', function (Blueprint $table) {
                $table->id();

                $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('follow_up_id')->nullable()->constrained('lead_follow_ups')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();

                $table->decimal('discount_amount', 12, 2)->default(0.00);
                $table->decimal('proposed_price', 12, 2)->nullable();
                $table->text('reason')->nullable();

                $table->string('status')->default('pending'); // pending, approved, rejected
                $table->text('remarks')->nullable();
                $table->timestamp('action_at')->nullable();

                $table->timestamps();

                $table->index('lead_id');
                $table->index('requested_by');
                $table->index('status');
                $table->index('created_at');
            });
        }

        // 4. Seed master Lead Statuses for discount workflow
        $discountStatuses = ['Discount Pending', 'Discount Approved', 'Discount Rejected'];
        foreach ($discountStatuses as $statusName) {
            DB::table('lead_statuses')->updateOrInsert(
                ['name' => $statusName],
                ['status' => 'Active', 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_approvals');

        Schema::table('lead_follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('lead_follow_ups', 'proposed_price')) {
                $table->dropColumn('proposed_price');
            }
            if (Schema::hasColumn('lead_follow_ups', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('lead_follow_ups', 'is_discount_requested')) {
                $table->dropColumn('is_discount_requested');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'approved_discount')) {
                $table->dropColumn('approved_discount');
            }
            if (Schema::hasColumn('leads', 'requested_discount')) {
                $table->dropColumn('requested_discount');
            }
            if (Schema::hasColumn('leads', 'discount_approval_status')) {
                $table->dropColumn('discount_approval_status');
            }
        });
    }
};
