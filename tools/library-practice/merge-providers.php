<?php

declare(strict_types=1);

[$script,$root,$output]=$argv;
$path=$root.'/bootstrap/providers.php';
if(is_link($path)){throw new RuntimeException('Provider file is a symlink.');}
$providers=is_file($path)?require $path:[];
if(!is_array($providers)){throw new RuntimeException('Provider list requires review.');}
foreach($providers as $provider){if(!is_string($provider)){throw new RuntimeException('Provider list contains an unsupported entry.');}}
$providers[]='App\\Providers\\LibraryPracticeServiceProvider';
$providers=array_values(array_unique($providers));
$text="<?php\n\nreturn ".var_export($providers,true).";\n";
if(file_put_contents($output,$text,LOCK_EX)!==strlen($text)){throw new RuntimeException('Cannot stage provider merge.');}
echo "PROVIDER_MERGE_OK\n";
