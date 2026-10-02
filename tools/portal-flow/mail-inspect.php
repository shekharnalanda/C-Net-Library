<?php

use App\Services\LibraryPortalMailService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

[$script, $root, $output] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$host = parse_url((string) config('app.url'), PHP_URL_HOST);
if ($host === 'cnetlibrary.mciedu.com') {
    app(LibraryPortalMailService::class)->configureTransport();
}
if (! in_array($host, ['cnetlibrary.mciedu.com', 'mciedu.com', 'www.mciedu.com'], true) && ! (basename($root) === 'Salary-Book' && preg_match('/\.(mciedu\.com|mciedu\.in)$/i', (string) $host))) {
    throw new RuntimeException('Mail source identity requires review.');
}
$recipient = null;
if (in_array($host, ['mciedu.com', 'www.mciedu.com'], true) && Schema::hasColumn('users', 'is_admin')) {
    $owners = DB::table('users')->where('is_admin', true)->whereRaw('LOWER(name) LIKE ?', ['%sujit%'])->pluck('email');
    if ($owners->count() === 1 && filter_var($owners->first(), FILTER_VALIDATE_EMAIL)) {
        $recipient = $owners->first();
    }
}
file_put_contents($output, json_encode(['sender' => config('mail.from.address'), 'driver' => config('mail.default'), 'recipient' => $recipient], JSON_THROW_ON_ERROR));
chmod($output, 0600);
echo "MAIL_CONFIGURATION_READ_PRIVATELY\n";
