<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locker_allocations', function (Blueprint $table) {
            $table->date('paid_through')->nullable()->after('monthly_charge');
        });

        Schema::create('locker_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locker_allocation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_no')->unique();
            $table->unsignedInteger('billing_months')->default(1);
            $table->decimal('monthly_charge', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->date('period_from');
            $table->date('period_to');
            $table->date('payment_date');
            $table->string('payment_mode', 30)->default('cash');
            $table->string('transaction_ref')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('paid');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'payment_date']);
            $table->index(['locker_allocation_id', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locker_payments');

        Schema::table('locker_allocations', function (Blueprint $table) {
            $table->dropColumn('paid_through');
        });
    }
};
