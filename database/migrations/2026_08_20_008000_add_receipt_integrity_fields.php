<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('series_key', 32);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['series_key', 'year']);
            $table->index(['branch_id', 'year']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('receipt_previous_paid', 10, 2)->nullable();
            $table->decimal('receipt_balance_due', 10, 2)->nullable();
            $table->decimal('receipt_membership_fee', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_previous_paid',
                'receipt_balance_due',
                'receipt_membership_fee',
            ]);
        });

        Schema::dropIfExists('receipt_sequences');
    }
};
