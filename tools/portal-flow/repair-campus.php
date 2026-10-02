<?php

use App\Http\Controllers\Public\AdmissionController;
use App\Services\LibraryCampusSlotSetup;
use Illuminate\Contracts\Console\Kernel;

[$script, $root, $backup] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (parse_url((string) config('app.url'), PHP_URL_HOST) !== 'cnetlibrary.mciedu.com') {
    throw new RuntimeException('Library identity does not match.');
}
$manifest = json_decode(file_get_contents($backup.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['branches', 'study_slots', 'fee_plans'] as $table) {
    $path = $backup.'/'.$table.'.jsonl';
    if (! isset($manifest[$table]['sha256']) || ! is_file($path) || ! hash_equals($manifest[$table]['sha256'], hash_file('sha256', $path))) {
        throw new RuntimeException('Verified campus/slot/fee backup is required.');
    }
}
$result = app(LibraryCampusSlotSetup::class)->repair();
$data = app(AdmissionController::class)->create()->getData();
foreach ([$result['source_id'], $result['target_id']] as $id) {
    if ($data['studySlots']->where('branch_id', $id)->isEmpty() || $data['feePlans']->where('branch_id', $id)->isEmpty()) {
        throw new RuntimeException('Both campus catalogs must appear in the public admission form.');
    }
}

echo 'CAMPUS_SETUP | '.json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
