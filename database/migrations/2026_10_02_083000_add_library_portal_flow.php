<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $t) {
            $t->unsignedBigInteger('preferred_seat_id')->nullable();
            $t->date('preferred_start_date')->nullable();
            $t->time('preferred_start_time')->nullable();
            $t->time('preferred_end_time')->nullable();
        });
        Schema::create('library_student_sessions', function (Blueprint $t) {
            $t->unsignedBigInteger('student_id')->primary();
            $t->string('token_hash', 64);
            $t->timestamp('expires_at');
            $t->timestamp('last_seen_at');
            $t->string('practice_session_hash', 64)->nullable();
            $t->timestamp('practice_expires_at')->nullable();
            $t->timestamps();
            $t->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
        Schema::create('library_portal_mail', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('student_id')->nullable();
            $t->string('event_key')->unique();
            $t->string('recipient');
            $t->string('subject');
            $t->text('body');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamps();
        });
        Schema::create('library_device_recovery', function (Blueprint $t) {
            $t->id();
            $t->string('challenge_hash', 64)->unique();
            $t->unsignedBigInteger('student_id');
            $t->string('otp_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_device_recovery');
        Schema::dropIfExists('library_portal_mail');
        Schema::dropIfExists('library_student_sessions');
        Schema::table('admissions', fn (Blueprint $t) => $t->dropColumn(['preferred_seat_id', 'preferred_start_date', 'preferred_start_time', 'preferred_end_time']));
    }
};
