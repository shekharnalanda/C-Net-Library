<?php

declare(strict_types=1);

[$script,$root,$path]=$argv;
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory=dirname($path);
if($path!=='/home4/mcied45x/mci-library-test-bridge/config.json' || is_link($directory)){
 throw new RuntimeException('Use the reviewed private bridge directory.');
}
if(!is_dir($directory) && !mkdir($directory,0700,true)){throw new RuntimeException('Cannot create private bridge directory.');}
chmod($directory,0700);
$tickets=$directory.'/tickets';
if(is_link($tickets) || (!is_dir($tickets) && !mkdir($tickets,0700))){throw new RuntimeException('Cannot create private ticket directory.');}
chmod($tickets,0700);
$connection=Illuminate\Support\Facades\DB::connection()->getConfig();
$connection['url']=null;
if(!in_array($connection['driver'],['mysql','sqlite'],true)){throw new RuntimeException('Library database driver requires review.');}
if(file_exists($path)){
 if(is_link($path) || (fileperms($path)&0077)){throw new RuntimeException('Private bridge configuration permissions require review.');}
 $old=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if(($old['library_connection']['database']??null)!==$connection['database']
  || !preg_match('/^[a-f0-9]{32}$/',$old['bridge_id']??'') || !preg_match('/^[a-f0-9]{64}$/',$old['secret']??'')){throw new RuntimeException('Existing bridge identity requires review.');}
}else{$old=['bridge_id'=>bin2hex(random_bytes(16)),'secret'=>bin2hex(random_bytes(32))];}
$data=json_encode(['bridge_id'=>$old['bridge_id'],'secret'=>$old['secret'],'test_url'=>'https://test.mciedu.com','library_connection'=>$connection],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
$temp=$path.'.'.bin2hex(random_bytes(5)).'.tmp';
if(file_put_contents($temp,$data,LOCK_EX)!==strlen($data) || !chmod($temp,0600) || !rename($temp,$path)){throw new RuntimeException('Cannot protect bridge configuration.');}
echo "PRIVATE_BRIDGE_CONFIGURED\n";
