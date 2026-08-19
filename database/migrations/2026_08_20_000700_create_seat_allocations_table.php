<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->date('allocated_from');
            $table->date('allocated_to')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('status', ['reserved', 'active', 'released', 'cancelled'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['seat_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['allocated_from', 'allocated_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_allocations');
    }
};
