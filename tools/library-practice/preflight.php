<?php

declare(strict_types=1);

[$script,$root,$site]=$argv;
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$host=parse_url((string)config('app.url'),PHP_URL_HOST);
$expected=$site==='library'?'cnetlibrary.mciedu.com':'test.mciedu.com';
if($host!==$expected){throw new RuntimeException('Application URL does not match the reviewed site.');}
if(PHP_VERSION_ID<80300){throw new RuntimeException('PHP 8.3 or newer is required.');}
$requirements=$site==='library'?[
 'users'=>['id','role','status'],'students'=>['id','user_id','student_code','name','branch_id','status'],
 'student_memberships'=>['id','student_id','start_date','expiry_date','final_fee','status'],
 'payments'=>['id','student_membership_id','amount','payment_status'],'payment_adjustments'=>['id','payment_id','amount'],
]:[
 'users'=>['id','role','is_active'],'student_profiles'=>['id','user_id','student_code','status'],
 'tests'=>['id','exam_id','available_from','available_until','is_active','answer_visibility','answers_available_at'],
 'questions'=>['id','is_active','is_published'],'question_test'=>['test_id','question_id'],
 'test_attempts'=>['id','student_profile_id','test_id','status'],'student_enrollments'=>['id','student_profile_id'],
];
foreach($requirements as $table=>$columns){
 if(!Schema::hasTable($table)){throw new RuntimeException('Required table is missing: '.$table);}
 foreach($columns as $column){if(!Schema::hasColumn($table,$column)){throw new RuntimeException('Required column is missing: '.$table.'.'.$column);}}
}
if($site==='library'){
 $connection=DB::connection()->getConfig();
 if(!in_array($connection['driver'],['mysql','sqlite'],true)){throw new RuntimeException('Library database driver requires review.');}
 if(DB::table('branches')->count()<2){throw new RuntimeException('The two library campuses were not found.');}
 echo "LIBRARY_PREFLIGHT_OK\n";
}else{
 $count=DB::table('tests as t')->where('t.is_active',true)
  ->where(fn($q)=>$q->whereNull('t.available_from')->orWhere('t.available_from','<=',now()))
  ->where(fn($q)=>$q->whereNull('t.available_until')->orWhere('t.available_until','>=',now()))
  ->whereExists(function($q){$q->selectRaw('1')->from('question_test as qt')->join('questions as q','q.id','=','qt.question_id')->whereColumn('qt.test_id','t.id')->where('q.is_active',true)->where('q.is_published',true);})->count();
 if($count<10){throw new RuntimeException('Fewer than 10 published tests are currently available.');}
 echo 'TEST_SERIES_PREFLIGHT_OK | AVAILABLE_TESTS='.$count."\n";
}
