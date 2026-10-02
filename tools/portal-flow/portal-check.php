<?php

use App\Models\Test;
use App\Services\LibraryPracticeBridge;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $root, $site, $output] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = $site === 'library' ? 'cnetlibrary.mciedu.com' : 'test.mciedu.com';
if (parse_url((string) config('app.url'), PHP_URL_HOST) !== $expected) {
    throw new RuntimeException('Diagnostic site identity mismatch.');
}
$started = hrtime(true);
app(LibraryPracticeBridge::class)->identity(0, 'DIAGNOSTIC-NONEXISTENT', 0);
$result = ['bridge_database_ms' => round((hrtime(true) - $started) / 1e6, 1)];
if ($site === 'library') {
    $result['mail_status_counts'] = DB::table('library_portal_mail')->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
    $result['mail_error_counts'] = DB::table('library_portal_mail')->where('status', 'pending')->whereNotNull('error_code')->select('error_code', DB::raw('count(*) as total'))->groupBy('error_code')->pluck('total', 'error_code')->all();
} else {
    $started = hrtime(true);
    Test::where('is_active', true)
        ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
        ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
        ->whereHas('questions', fn ($q) => $q->where('questions.is_active', true)->where('questions.is_published', true))
        ->latest('id')->limit(20)->get(['id', 'exam_id']);
    $result['catalog_database_ms'] = round((hrtime(true) - $started) / 1e6, 1);
}
file_put_contents($output, json_encode($result, JSON_THROW_ON_ERROR));
chmod($output, 0600);
echo "PORTAL_NONDESTRUCTIVE_CHECK_OK\n";
