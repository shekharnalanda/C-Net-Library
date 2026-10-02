<?php

[$script,$root,$directory] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (is_link($directory) || str_starts_with($directory, $root.'/public')) {
    throw new RuntimeException('Database backup must be private.');
}
if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create private database backup.');
}chmod($directory, 0700);
$tables = ['users', 'students', 'admissions', 'student_memberships', 'seat_allocations', 'payments', 'payment_adjustments', 'fee_plans', 'study_slots', 'branches', 'study_halls', 'seats', 'mobile_api_tokens', 'library_student_sessions', 'library_portal_mail', 'library_device_recovery'];
$manifest = [];
DB::transaction(function () use ($directory, $tables, &$manifest) {
    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            continue;
        }
        $path = $directory.'/'.$table.'.jsonl';
        $f = fopen($path, 'xb');
        if (! $f) {
            throw new RuntimeException('Cannot create private backup.');
        }chmod($path, 0600);
        $count = 0;
        $hash = hash_init('sha256');
        try {
            foreach (DB::table($table)->cursor() as $row) {
                $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                if (fwrite($f, $line) !== strlen($line)) {
                    throw new RuntimeException('Incomplete backup write.');
                }hash_update($hash, $line);
                $count++;
            }if (! fflush($f)) {
                throw new RuntimeException('Cannot flush backup.');
            }
        } finally {
            fclose($f);
        }
        $digest = hash_final($hash);
        if (! hash_equals($digest, hash_file('sha256', $path))) {
            throw new RuntimeException('Private backup checksum mismatch.');
        }
        $manifest[$table] = ['rows' => $count, 'sha256' => $digest, 'columns' => Schema::getColumnListing($table)];
    }
}, 1);
file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
chmod($directory.'/manifest.json', 0600);
echo "PRIVATE_REVIEWED_TABLE_BACKUP_VERIFIED\n";
