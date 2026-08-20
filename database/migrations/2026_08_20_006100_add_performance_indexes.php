<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['payment_date', 'payment_status'], 'payments_date_status_idx');
            $table->index(['student_membership_id', 'payment_status', 'id'], 'payments_membership_status_id_idx');
        });

        Schema::table('payment_adjustments', function (Blueprint $table) {
            $table->index(['payment_id', 'created_at'], 'payment_adjustments_payment_created_idx');
        });

        Schema::table('student_memberships', function (Blueprint $table) {
            $table->index(['status', 'expiry_date'], 'memberships_status_expiry_idx');
        });

        Schema::table('seat_allocations', function (Blueprint $table) {
            $table->index(
                ['seat_id', 'status', 'allocated_from', 'allocated_to'],
                'seat_allocations_overlap_idx'
            );
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['branch_id', 'check_in_at'], 'attendances_branch_checkin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_branch_checkin_idx');
        });

        Schema::table('seat_allocations', function (Blueprint $table) {
            $table->dropIndex('seat_allocations_overlap_idx');
        });

        Schema::table('student_memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_status_expiry_idx');
        });

        Schema::table('payment_adjustments', function (Blueprint $table) {
            $table->dropIndex('payment_adjustments_payment_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_membership_status_id_idx');
            $table->dropIndex('payments_date_status_idx');
        });
    }
};
