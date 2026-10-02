<?php

use App\Services\LibraryPortalMailService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $root] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (parse_url((string) config('app.url'), PHP_URL_HOST) !== 'cnetlibrary.mciedu.com') {
    throw new RuntimeException('Mail retry site identity mismatch.');
}
$keys = DB::table('library_portal_mail')->where('status', 'pending')->where('event_key', 'like', 'welcome:%')->orderBy('id')->limit(20)->pluck('event_key');
$accepted = 0;
foreach ($keys as $key) {
    DB::table('library_portal_mail')->where('event_key', $key)->where('status', 'pending')->update(['attempts' => 0, 'next_attempt_at' => null, 'updated_at' => now()]);
    $accepted += app(LibraryPortalMailService::class)->deliver($key);
}
echo 'WELCOME_RETRY | queued_before='.$keys->count().' | transport_accepted='.$accepted."\n";
