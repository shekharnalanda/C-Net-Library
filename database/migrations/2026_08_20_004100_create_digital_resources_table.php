<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('resource_type', ['pdf','ebook','notes','question_paper','video','link']);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->enum('access_type', ['public','members','premium'])->default('members');
            $table->boolean('download_allowed')->default(true);
            $table->boolean('status')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['resource_type', 'status']);
            $table->index(['access_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_resources');
    }
};
