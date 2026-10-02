<?php

use App\Services\LibraryPracticeBridge;
use App\Services\SettingsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

[$script,$root,$site] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expected = $site === 'library' ? 'cnetlibrary.mciedu.com' : 'test.mciedu.com';
if (parse_url((string) config('app.url'), PHP_URL_HOST) !== $expected || PHP_VERSION_ID < 80300) {
    throw new RuntimeException('Site identity or PHP version requires review.');
}
$requirements = $site === 'library' ? [
    'admissions' => ['id', 'branch_id', 'mobile', 'email', 'study_slot_id', 'fee_plan_id'],
    'students' => ['id', 'user_id', 'student_code', 'mobile', 'email', 'status'],
    'student_memberships' => ['id', 'student_id', 'final_fee', 'start_date', 'expiry_date'],
    'seat_allocations' => ['id', 'seat_id', 'start_time', 'end_time', 'allocated_from', 'allocated_to', 'status'],
    'study_slots' => ['id', 'duration_hours', 'branch_id', 'is_24x7'],
    'fee_plans' => ['id', 'branch_id', 'study_slot_id', 'monthly_fee', 'validity_days'],
    'mobile_api_tokens' => ['id', 'user_id', 'token_hash'],
    'payments' => ['id', 'student_membership_id', 'amount', 'payment_status'],
    'payment_adjustments' => ['id', 'payment_id', 'amount'],
] : ['library_practice_accounts' => ['id', 'library_student_id', 'library_user_id'], 'library_practice_months' => ['id', 'period'], 'library_practice_selections' => ['id', 'test_attempt_id']];
foreach ($requirements as $table => $columns) {
    if (! Schema::hasTable($table)) {
        throw new RuntimeException('Required table missing: '.$table);
    }foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            throw new RuntimeException('Required column missing: '.$table.'.'.$column);
        }
    }
}
if ($site === 'library') {
    if (DB::table('branches')->count() < 2) {
        throw new RuntimeException('Two library campuses were not found.');
    }
    if (! in_array(config('mail.default'), ['smtp', 'sendmail', 'mail'], true) || ! filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A working email sender must be configured; log-only mail cannot deliver student messages.');
    }
    if ((int) app(SettingsService::class)->get('seat_monthly_cutoff_day', 0) !== 10) {
        throw new RuntimeException('Existing fee cutoff requires review.');
    }
}
app(LibraryPracticeBridge::class)->configuration();
echo "PORTAL_PREFLIGHT_OK | {$site}\n";
