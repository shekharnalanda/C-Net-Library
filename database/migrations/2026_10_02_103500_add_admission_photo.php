<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admissions', 'photo')) {
            Schema::table('admissions', fn (Blueprint $table) => $table->string('photo')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('admissions', fn (Blueprint $table) => $table->dropColumn('photo'));
    }
};
