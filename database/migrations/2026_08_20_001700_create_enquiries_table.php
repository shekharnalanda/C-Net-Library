<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('enquiry_no')->unique();
            $table->string('name');
            $table->string('mobile');
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('interested_plan')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'contacted', 'follow_up', 'qualified', 'converted', 'lost'])->default('new');
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->foreignId('converted_admission_id')->nullable()->constrained('admissions')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'follow_up_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
