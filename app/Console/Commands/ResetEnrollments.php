<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ResetEnrollments extends Command
{
    protected $signature='maintenance:reset-enrollments {--confirm=} {--backup-directory=}';
    protected $description='Privately back up and reset student enrolments while preserving campuses, halls, seats, fee plans, staff and inventory.';

    public function handle(): int
    {
        if($this->option('confirm')!=='RESET-CNET-LIBRARY-ENROLLMENTS') {
            $this->error('STOPPED: enrollment reset confirmation token is missing.');return self::FAILURE;
        }
        $dir=(string)$this->option('backup-directory');
        try {
            if(!$dir || !str_starts_with($dir,'/') || str_contains($dir,'..')) throw new RuntimeException('An absolute private backup directory is required.');
            if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create backup directory.');
            $dir=realpath($dir);
            if(!$dir || str_starts_with($dir,realpath(base_path()).DIRECTORY_SEPARATOR) || $dir===realpath(base_path())) throw new RuntimeException('Backup must be outside the application and public document root.');
            if(!chmod($dir,0700)) throw new RuntimeException('Cannot protect backup directory.');
            $backupPath=$dir.'/enrollments-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));
            if(!mkdir($backupPath,0700)) throw new RuntimeException('Cannot create unique backup.');
            DB::transaction(function()use($backupPath){
                DB::table('seats')->lockForUpdate()->get(['id']);
                $students=Student::query()->lockForUpdate()->get(['id','user_id']);
                $ids=$students->pluck('id');
                $users=DB::table('users')->whereIn('id',$students->pluck('user_id')->filter())->where('role','student')->whereNotIn('id',DB::table('staff')->whereNotNull('user_id')->select('user_id'))->get(['id','email']);
                $tables=Schema::getTables();$manifest=['created_at'=>now()->toIso8601String(),'tables'=>[]];
                // Full database snapshot also covers live extension/recovery tables and FK side effects.
                foreach($tables as $table) {
                    $name=$table['name'];
                    if(!preg_match('/^[A-Za-z0-9_]+$/',$name)) throw new RuntimeException('Unexpected table name.');
                    $path=$backupPath.'/'.$name.'.json';
                    $handle=fopen($path,'xb');
                    if(!$handle || !chmod($path,0600)) throw new RuntimeException('Cannot protect backup file.');
                    $write=function(string $text)use($handle){if(fwrite($handle,$text)!==strlen($text)) throw new RuntimeException('Backup write incomplete.');};
                    $write('[');$first=true;$count=0;
                    foreach(DB::table($name)->cursor() as $row) {
                        $write(($first?'':',').json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
                        $first=false;$count++;
                    }
                    $write(']');if(!fflush($handle)) throw new RuntimeException('Backup flush failed.');fclose($handle);
                    $saved=file_get_contents($path);$decoded=json_decode($saved,true,512,JSON_THROW_ON_ERROR);
                    if(count($decoded)!==$count) throw new RuntimeException('Backup verification failed.');
                    $manifest['tables'][$name]=['rows'=>$count,'sha256'=>hash('sha256',$saved)];unset($saved,$decoded);
                }
                $manifestText=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
                $manifestPath=$backupPath.'/manifest.json';
                if(file_put_contents($manifestPath,$manifestText,LOCK_EX)!==strlen($manifestText) || !chmod($manifestPath,0600) || file_get_contents($manifestPath)!==$manifestText) throw new RuntimeException('Backup manifest verification failed.');

                $paymentIds=DB::table('payments')->whereIn('student_id',$ids)->pluck('id');
                $bookIssues=DB::table('book_issues')->whereIn('student_id',$ids)->get(['id','book_copy_id','status']);
                $lockerIds=DB::table('locker_allocations')->whereIn('student_id',$ids)->pluck('id');
                $this->delete('payment_adjustments','payment_id',$paymentIds);
                $this->delete('library_charge_payments','book_issue_id',$bookIssues->pluck('id'));
                $this->delete('locker_payments','locker_allocation_id',$lockerIds);
                // Release inventory held by the deleted enrolments; do not change lost/damaged copies.
                DB::table('book_copies')->whereIn('id',$bookIssues->whereIn('status',['issued','overdue'])->pluck('book_copy_id'))
                    ->where('status','issued')->update(['status'=>'available','updated_at'=>now()]);
                DB::table('book_copies')->whereIn('id',DB::table('book_reservations')->whereIn('student_id',$ids)->where('status','active')->pluck('book_copy_id'))
                    ->where('status','reserved')->update(['status'=>'available','updated_at'=>now()]);
                DB::table('seat_allocations')->delete();
                foreach(['saved_jobs','job_clicks','communication_logs','digital_resource_logs','book_reservations','book_issues','locker_allocations','payments','attendances','student_memberships'] as $table) $this->delete($table,'student_id',$ids);
                DB::table('admissions')->delete();
                DB::table('students')->delete();
                $this->delete('sessions','user_id',$users->pluck('id'));
                $this->delete('mobile_api_tokens','user_id',$users->pluck('id'));
                $this->delete('password_reset_tokens','email',$users->pluck('email'));
                $this->delete('users','id',$users->pluck('id'));
                foreach(['seats','study_halls','branches','study_slots','fee_plans','staff','books','book_copies','lockers'] as $table) {
                    if(DB::table($table)->count()!==$manifest['tables'][$table]['rows']) throw new RuntimeException('Preserved structure count changed: '.$table);
                }
                if(DB::table('students')->count() || DB::table('admissions')->count() || DB::table('seat_allocations')->count()) throw new RuntimeException('Reset verification failed.');
            },1);
            $this->info('RESET_COMPLETED | STUDENTS=0 | ADMISSIONS=0 | SEAT_ALLOCATIONS=0');
            $this->line('PRESERVED_SEATS='.DB::table('seats')->count());
            $this->line('PRIVATE_DATABASE_BACKUP='.$backupPath);return self::SUCCESS;
        } catch(\Throwable $e) {
            $this->error('STOPPED: Enrollment reset rolled back. '.$e->getMessage());return self::FAILURE;
        }
    }

    private function delete(string $table,string $column,$ids): void
    {
        if(Schema::hasTable($table) && Schema::hasColumn($table,$column) && $ids->isNotEmpty()) DB::table($table)->whereIn($column,$ids)->delete();
    }
}
