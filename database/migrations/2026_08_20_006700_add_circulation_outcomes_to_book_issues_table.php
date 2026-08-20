<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            $table->enum('return_condition', ['good', 'fair', 'damaged'])->nullable()->after('returned_at');
            $table->decimal('loss_charge', 10, 2)->default(0)->after('fine_paid');
            $table->index(['book_copy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            $table->dropIndex(['book_copy_id', 'status']);
            $table->dropColumn(['return_condition', 'loss_charge']);
        });
    }
};
