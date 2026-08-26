<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->foreignId('study_hall_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('study_halls')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->index(['branch_id', 'study_hall_id', 'status'], 'lockers_branch_hall_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lockers', function (Blueprint $table) {
            $table->dropIndex('lockers_branch_hall_status_idx');
            $table->dropConstrainedForeignId('study_hall_id');
        });
    }
};
