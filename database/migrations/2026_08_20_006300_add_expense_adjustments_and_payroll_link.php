<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->unique('payroll_id');
        });

        Schema::create('expense_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['reversal', 'correction', 'refund']);
            $table->decimal('amount', 12, 2);
            $table->string('reason', 1000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_adjustments');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['payroll_id']);
            $table->dropConstrainedForeignId('payroll_id');
        });
    }
};
