<?php

declare(strict_types=1);

[$script,$root,$site]=$argv;
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$bridge=$app->make(App\Services\LibraryPracticeBridge::class);
$config=$bridge->configuration();
if($site==='library'){
 foreach(['student.library-practice','student.library-practice.launch'] as $name){
  if(!Illuminate\Support\Facades\Route::has($name)){throw new RuntimeException('Library practice route is missing.');}
 }
 $student=(object)['name'=>'Verification','id'=>0];
 $view=view('student.library-practice',['student'=>$student,'eligible'=>false,'notice'=>null])->render();
 if(!str_contains($view,'10 पूरे टेस्ट सेट')){throw new RuntimeException('Library practice page did not render.');}
 echo "LIBRARY_PRACTICE_ROUTES_AND_PAGE_OK\n";
}else{
 foreach(['library-practice.index','library-practice.select','library-practice.start','library-practice.attempts.result','admin.library-practice'] as $name){
  if(!Illuminate\Support\Facades\Route::has($name)){throw new RuntimeException('Test practice route is missing.');}
 }
 foreach(['library_practice_accounts','library_practice_months','library_practice_selections'] as $table){
  if(!Illuminate\Support\Facades\Schema::hasTable($table)){throw new RuntimeException('Benefit table is missing.');}
 }
 $bridge->identity(0,'VERIFY-NONEXISTENT',0);
 $rows=new Illuminate\Pagination\LengthAwarePaginator([],0,50);
 $view=view('admin.library-practice',['rows'=>$rows,'period'=>'2026-10'])->render();
 if(!str_contains($view,'Library Free Practice Report')){throw new RuntimeException('Report did not render.');}
 echo "TEST_SERIES_PRACTICE_ROUTES_DATABASE_AND_REPORT_OK\n";
}
