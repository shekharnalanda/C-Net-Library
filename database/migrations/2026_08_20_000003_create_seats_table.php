<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_hall_id')->constrained()->cascadeOnDelete();
            $table->string('seat_no');
            $table->enum('seat_type', ['regular', 'premium', 'cabin'])->default('regular');
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['study_hall_id', 'seat_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
