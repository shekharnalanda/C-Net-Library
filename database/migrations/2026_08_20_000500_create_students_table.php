<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('student_code')->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('mobile', 20);
            $table->string('alternate_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('photo')->nullable();
            $table->string('id_proof_type')->nullable();
            $table->string('id_proof_no')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_mobile', 20)->nullable();
            $table->date('joining_date');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
