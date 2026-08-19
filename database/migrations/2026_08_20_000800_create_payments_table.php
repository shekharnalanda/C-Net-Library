<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_membership_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no')->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->date('payment_date');
            $table->enum('payment_mode', ['cash', 'upi', 'card', 'bank_transfer', 'other'])->default('cash');
            $table->string('transaction_ref')->nullable();
            $table->enum('payment_status', ['paid', 'partial', 'pending', 'refunded'])->default('paid');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'payment_date']);
            $table->index(['student_membership_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
