<?php
// Local CLI checks only. No web entry point or credentials are printed.
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
$root=$argv[1]??'';$mode=$argv[2]??'preflight';
try {
    if(PHP_VERSION_ID<80200) throw new RuntimeException('PHP 8.2+ is required.');
    require $root.'/vendor/autoload.php';
    $app=require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $host=parse_url((string)config('app.url'),PHP_URL_HOST);
    if(!in_array(strtolower((string)$host),['cnetlibrary.mciedu.com','www.cnetlibrary.mciedu.com'],true)) throw new RuntimeException('Library application URL does not match.');
    if(config('app.timezone')!=='Asia/Kolkata') throw new RuntimeException('Library timezone must be Asia/Kolkata.');
    $required=[
        'students'=>['id','user_id','branch_id'], 'admissions'=>['id'], 'users'=>['id','role','email'],
        'branches'=>['id','name'], 'study_halls'=>['id','branch_id','status'],
        'seats'=>['id','study_hall_id','seat_no','status'],
        'study_slots'=>['id','duration_hours','start_time','end_time','is_24x7','is_flexible'],
        'student_memberships'=>['id','student_id','fee_plan_id','study_slot_id','start_date','expiry_date','final_fee','status'],
        'seat_allocations'=>['id','student_membership_id','seat_id','allocated_from','allocated_to','start_time','end_time','status'],
        'payments'=>['id','student_id','student_membership_id','payment_status','amount'],
        'payment_adjustments'=>['id','payment_id','amount'],
        'book_issues'=>['id','student_id','book_copy_id','status'],
        'book_copies'=>['id','status'], 'book_reservations'=>['student_id','book_copy_id','status'],
        'locker_allocations'=>['id','student_id'], 'fee_plans'=>['id','study_slot_id','monthly_fee'],
        'staff'=>['id'],'books'=>['id'],'lockers'=>['id'],'settings'=>['key','value','type'],'audit_logs'=>['id'],
    ];
    foreach($required as $table=>$columns) foreach($columns as $column) {
        if(!Illuminate\Support\Facades\Schema::hasColumn($table,$column)) throw new RuntimeException('Schema mismatch: '.$table.'.'.$column);
    }
    $counts=[];foreach(['students','admissions','seat_allocations','seats','branches','study_halls','study_slots','fee_plans'] as $table) $counts[$table]=Illuminate\Support\Facades\DB::table($table)->count();
    if(!$counts['seats'] || $counts['branches']<2) throw new RuntimeException('Expected both campuses and existing seats.');
    if($mode==='installed') {
        foreach(['seat-report','admin.seat-report','admin.seat-allocations.destroy'] as $name) if(!Illuminate\Support\Facades\Route::has($name)) throw new RuntimeException('Required route missing: '.$name);
        foreach(['maintenance:reset-enrollments','seats:configure-monthly-cutoff','memberships:release-unpaid-seats'] as $name) if(!isset(Illuminate\Support\Facades\Artisan::all()[$name])) throw new RuntimeException('Required command missing: '.$name);
        // Actual controller/view dispatch; no student names or contact information are output.
        $request=Illuminate\Http\Request::create('https://cnetlibrary.mciedu.com/seat-report','GET');
        $request->setRouteResolver(fn()=>Illuminate\Support\Facades\Route::getRoutes()->getByName('seat-report'));
        $response=$app->make(App\Http\Controllers\SeatScheduleController::class)->index($request,$app->make(App\Services\SeatScheduleService::class));
        if($response->getStatusCode()!==200 || !str_contains($response->getContent(),'Seat &amp; Time Report') && !str_contains($response->getContent(),'Seat & Time Report')) throw new RuntimeException('Seat report render failed.');
    }
    echo json_encode(['status'=>'OK','counts'=>$counts],JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {fwrite(STDERR,'AUDIT_FAILED: '.$e->getMessage().PHP_EOL);exit(1);}
