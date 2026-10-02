<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL can persist even when a later table creation fails.
        foreach (['preferred_seat_id' => 'unsignedBigInteger', 'preferred_start_date' => 'date', 'preferred_start_time' => 'time', 'preferred_end_time' => 'time'] as $column => $type) {
            if (! Schema::hasColumn('admissions', $column)) {
                Schema::table('admissions', fn (Blueprint $t) => $t->{$type}($column)->nullable());
            }
        }
        if (! Schema::hasTable('library_student_sessions')) {
            Schema::create('library_student_sessions', function (Blueprint $t) {
                $t->unsignedBigInteger('student_id')->primary();
                $t->string('token_hash', 64);
                $t->dateTime('expires_at');
                $t->dateTime('last_seen_at');
                $t->string('practice_session_hash', 64)->nullable();
                $t->dateTime('practice_expires_at')->nullable();
                $t->timestamps();
                $t->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('library_portal_mail')) {
            Schema::create('library_portal_mail', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('student_id')->nullable();
                $t->string('event_key')->unique();
                $t->string('recipient');
                $t->string('subject');
                $t->text('body');
                $t->string('status')->default('pending');
                $t->unsignedInteger('attempts')->default(0);
                $t->dateTime('next_attempt_at')->nullable();
                $t->dateTime('sent_at')->nullable();
                $t->string('error_code')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('library_device_recovery')) {
            Schema::create('library_device_recovery', function (Blueprint $t) {
                $t->id();
                $t->string('challenge_hash', 64)->unique();
                $t->unsignedBigInteger('student_id');
                $t->string('otp_hash');
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->dateTime('expires_at');
                $t->dateTime('consumed_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('library_device_recovery');
        Schema::dropIfExists('library_portal_mail');
        Schema::dropIfExists('library_student_sessions');
        Schema::table('admissions', fn (Blueprint $t) => $t->dropColumn(['preferred_seat_id', 'preferred_start_date', 'preferred_start_time', 'preferred_end_time']));
    }
};
