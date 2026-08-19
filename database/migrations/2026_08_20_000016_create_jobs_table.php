<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('organization');
            $table->enum('job_type', ['government', 'private', 'internship', 'apprenticeship'])->default('government');
            $table->string('category')->nullable();
            $table->string('qualification')->nullable();
            $table->string('location')->nullable();
            $table->date('published_date')->nullable();
            $table->date('last_date')->nullable();
            $table->text('summary')->nullable();
            $table->string('official_url', 2048);
            $table->boolean('is_featured')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['job_type', 'status']);
            $table->index('last_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
