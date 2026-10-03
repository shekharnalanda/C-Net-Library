<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mci_pay_orders', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('branch_id')->nullable()->index();
            $t->string('principal_type',20);
            $t->string('principal_id',100);
            $t->string('active_key',150)->nullable()->unique();
            $t->string('student_reference',100);
            $t->string('payer_name',190);
            $t->string('payer_phone',20)->nullable();
            $t->string('purpose',250);
            $t->string('billing_month',7)->nullable();
            $t->unsignedBigInteger('amount_paise');
            $t->string('status',30)->default('created')->index();
            $t->uuid('central_id')->nullable()->unique();
            $t->text('checkout_url')->nullable();
            $t->unsignedInteger('revision')->default(0);
            $t->string('reference',22)->nullable()->index();
            $t->date('payment_date')->nullable();
            $t->string('central_receipt',100)->nullable();
            $t->string('local_receipt',100)->nullable();
            $t->text('review_note')->nullable();
            $t->text('sync_error')->nullable();
            $t->json('metadata');
            $t->timestamps();
            $t->index(['principal_type','principal_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('mci_pay_orders'); }
};

