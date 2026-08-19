<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('monthly_fee', 10, 2);
            $table->decimal('quarterly_fee', 10, 2)->nullable();
            $table->decimal('half_yearly_fee', 10, 2)->nullable();
            $table->decimal('yearly_fee', 10, 2)->nullable();
            $table->decimal('admission_fee', 10, 2)->default(0);
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->unsignedInteger('validity_days')->default(30);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_plans');
    }
};
