<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('admissions', fn (Blueprint $t) => $t->foreignId('mci_student_id')->nullable()->constrained('students')->nullOnDelete()); }
    public function down(): void { Schema::table('admissions', fn (Blueprint $t) => $t->dropConstrainedForeignId('mci_student_id')); }
};
