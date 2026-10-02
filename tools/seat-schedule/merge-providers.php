<?php
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
$file=$argv[1];$output=$argv[2];
try {
    $providers=is_file($file)?require $file:[];
    if(!is_array($providers)) throw new RuntimeException('Existing providers must return an array.');
    foreach($providers as $provider) if(!is_string($provider)) throw new RuntimeException('Unrecognized provider entry.');
    $providers[]=App\Providers\SeatScheduleServiceProvider::class;
    $text="<?php\n\n// Existing provider registrations are preserved.\nreturn ".var_export(array_values(array_unique($providers)),true).";\n";
    if(file_put_contents($output,$text)!==strlen($text)) throw new RuntimeException('Provider merge write failed.');
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
