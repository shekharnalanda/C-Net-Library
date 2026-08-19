<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('application_no')->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('study_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['new', 'under_review', 'approved', 'rejected', 'converted'])->default('new');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
