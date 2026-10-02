<?php

use App\Services\LibraryPortalMailService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

[$script, $root, $output, $recipient] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (parse_url((string) config('app.url'), PHP_URL_HOST) !== 'cnetlibrary.mciedu.com' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Mail probe identity requires review.');
}
$result = ['accepted' => false, 'code' => 'MAIL_CONFIGURATION_INVALID'];
$service = app(LibraryPortalMailService::class);
try {
    $service->configureTransport();
    if (isset($argv[4])) {
        $sender = $argv[4];
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || ! in_array(strtolower(substr(strrchr($sender, '@'), 1)), ['mciedu.com', 'mciedu.in'], true)) {
            throw new RuntimeException('MAIL_PRIVATE_SENDER_INVALID');
        }
        config(['mail.default' => 'library_portal_probe', 'mail.mailers.library_portal_probe' => ['transport' => 'sendmail', 'path' => '/usr/sbin/sendmail -bs -i'], 'mail.from.address' => $sender, 'mail.from.name' => 'C-Net Library · MCI Educational Group']);
    }
    if (! in_array(config('mail.default'), ['smtp', 'sendmail', 'library_portal_hosted', 'library_portal_probe'], true)) {
        throw new RuntimeException('MAIL_PRIVATE_TRANSPORT_INVALID');
    }
    Mail::raw("C-Net Library email delivery check.\nयह एडमिशन वेलकम ईमेल सेवा की जाँच है। कोई विद्यार्थी पासवर्ड बदला नहीं गया है।\nयदि यह मेल मिला है तो इनबॉक्स डिलीवरी की जाँच सफल है।", fn ($mail) => $mail->to($recipient)->subject('C-Net Library · Email delivery check'));
    $result = ['accepted' => true, 'code' => 'TRANSPORT_ACCEPTED', 'driver' => config('mail.default')];
} catch (Throwable $error) {
    $result['code'] = $service->failureCode($error);
}
file_put_contents($output, json_encode($result, JSON_THROW_ON_ERROR));
chmod($output, 0600);
echo 'MAIL_PROBE | '.$result['code']."\n";
