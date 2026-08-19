<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('accession_no')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('rack_no')->nullable();
            $table->enum('condition', ['new', 'good', 'fair', 'damaged'])->default('good');
            $table->enum('status', ['available', 'issued', 'reserved', 'lost', 'damaged'])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
