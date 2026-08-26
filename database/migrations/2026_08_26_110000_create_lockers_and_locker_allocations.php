<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->boolean('wants_locker')->default(false)->after('fee_plan_id');
        });

        Schema::create('lockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('locker_no', 50);
            $table->string('location', 120)->nullable();
            $table->decimal('monthly_charge', 10, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'locker_no']);
        });

        Schema::create('locker_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locker_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('allocated_from');
            $table->date('allocated_to')->nullable();
            $table->decimal('monthly_charge', 10, 2)->default(0);
            $table->string('status', 30)->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['locker_id', 'status', 'allocated_from', 'allocated_to'], 'locker_allocation_lookup');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locker_allocations');
        Schema::dropIfExists('lockers');

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropColumn('wants_locker');
        });
    }
};
