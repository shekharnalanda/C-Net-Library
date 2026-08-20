<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'expired'])->default('active');
            $table->dateTime('reserved_at');
            $table->dateTime('expires_at');
            $table->dateTime('fulfilled_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['book_copy_id', 'status', 'expires_at']);
            $table->index(['student_id', 'status', 'expires_at']);
        });

        Schema::create('library_charge_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_issue_id')->constrained()->restrictOnDelete();
            $table->enum('charge_type', ['fine', 'loss']);
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('payment_mode', ['cash', 'upi', 'card', 'bank_transfer', 'other'])->default('cash');
            $table->string('transaction_ref')->nullable()->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['book_issue_id', 'charge_type']);
            $table->index(['payment_date', 'charge_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_charge_payments');
        Schema::dropIfExists('book_reservations');
    }
};
