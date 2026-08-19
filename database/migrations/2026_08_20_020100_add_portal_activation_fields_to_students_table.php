<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('portal_activation_token', 64)->nullable()->unique()->after('qr_token');
            $table->timestamp('portal_activation_expires_at')->nullable()->after('portal_activation_token');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_activation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['portal_activation_token']);
            $table->dropColumn([
                'portal_activation_token',
                'portal_activation_expires_at',
                'portal_activated_at',
            ]);
        });
    }
};
