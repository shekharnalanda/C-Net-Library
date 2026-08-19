<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_resource_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('digital_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['view','download']);
            $table->timestamp('accessed_at');
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index(['digital_resource_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_resource_logs');
    }
};
