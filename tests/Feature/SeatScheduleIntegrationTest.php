<?php

namespace Tests\Feature;

use App\Models\{Branch,StudyHall,StudySlot,Seat,SeatAllocation,Student,StudentMembership,FeePlan,Payment,PaymentAdjustment,User,Admission};
use App\Services\{DailySeatWindow,SeatAllocationService,SeatFeeReleaseService,SettingsService,AdmissionApprovalService,MembershipRenewalService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,File};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SeatScheduleIntegrationTest extends TestCase
{
    use RefreshDatabase;
    private Branch $campus;
    private Seat $seat;
    private StudySlot $slot;
    private FeePlan $plan;
    private User $admin;
    protected function setUp(): void
    {
        parent::setUp();$this->travelTo(now()->setDate(2026,10,5)->startOfDay());
        $this->campus=Branch::factory()->create(['name'=>'C-Net Campus']);
        $hall=StudyHall::factory()->create(['branch_id'=>$this->campus->id]);
        $this->seat=Seat::factory()->create(['study_hall_id'=>$hall->id,'seat_no'=>'10']);
        $this->slot=StudySlot::factory()->create(['branch_id'=>$this->campus->id,'duration_hours'=>4,'start_time'=>'06:00:00','end_time'=>'10:00:00','is_flexible'=>true]);
        $this->plan=FeePlan::factory()->create(['branch_id'=>$this->campus->id,'study_slot_id'=>$this->slot->id,'monthly_fee'=>300,'validity_days'=>30]);
        $this->admin=User::factory()->create(['role'=>'super_admin']);
        app(SettingsService::class)->set('seat_monthly_cutoff_day',10,'membership','integer',null);
    }
    private function branchAdmin(int $branchId): User
    {
        $user=User::factory()->create(['role'=>'admin','branch_id'=>$branchId]);
        $role=\App\Models\Role::firstOrCreate(['slug'=>'branch-admin'],['name'=>'Branch admin','is_system'=>true]);
        $permission=\App\Models\Permission::firstOrCreate(['slug'=>'students.manage'],['name'=>'Students','group'=>'students']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);$user->roles()->attach($role);
        return $user;
    }
    private function member(string $name='Amit Kumar', string $start='2026-10-01', string $expiry='2026-10-30',int $paid=300): StudentMembership
    {
        $s=Student::factory()->create(['branch_id'=>$this->campus->id,'name'=>$name]);
        $m=StudentMembership::factory()->create(['student_id'=>$s->id,'fee_plan_id'=>$this->plan->id,'study_slot_id'=>$this->slot->id,'start_date'=>$start,'expiry_date'=>$expiry,'base_fee'=>300,'discount'=>0,'final_fee'=>300]);
        if($paid) Payment::factory()->create(['student_id'=>$s->id,'student_membership_id'=>$m->id,'amount'=>$paid,'payment_status'=>$paid<300?'partial':'paid']);
        return $m;
    }
    private function allocation(StudentMembership $m,string $start='06:00',string $end='10:00',?string $from=null,?string $to=null): SeatAllocation
    {
        return SeatAllocation::create(['student_id'=>$m->student_id,'student_membership_id'=>$m->id,'seat_id'=>$this->seat->id,'study_slot_id'=>$this->slot->id,'allocated_from'=>$from??$m->start_date->toDateString(),'allocated_to'=>$to??app(SeatFeeReleaseService::class)->holdUntil($m->expiry_date->toDateString()),'start_time'=>$start,'end_time'=>$end,'status'=>'active']);
    }
    public function test_same_seat_accepts_four_adjacent_windows_and_rejects_overlap(): void
    {
        foreach([['Amit',6,10],['Ramesh',10,14],['Priya',14,18],['Neha',18,22]] as [$name,$start,$end]) {
            $m=$this->member($name);
            $this->actingAs($this->admin)->post('/admin/study-space/allocations',['student_id'=>$m->student_id,'seat_id'=>$this->seat->id,'study_slot_id'=>$this->slot->id,'allocated_from'=>'2026-10-01','start_time'=>sprintf('%02d:00',$start),'end_time'=>sprintf('%02d:00',$end),'status'=>'active'])->assertSessionHasNoErrors();
        }
        $this->assertSame(4,SeatAllocation::count());
        $m=$this->member('Overlap');
        $this->actingAs($this->admin)->post('/admin/study-space/allocations',['student_id'=>$m->student_id,'seat_id'=>$this->seat->id,'study_slot_id'=>$this->slot->id,'allocated_from'=>'2026-10-01','start_time'=>'09:00','end_time'=>'13:00','status'=>'active'])->assertSessionHasErrors('seat_id');
        $this->assertSame(4,SeatAllocation::count());
    }
    public function test_availability_json_and_public_report_agree_and_hide_contact_details(): void
    {
        $m=$this->member();$this->allocation($m);
        $params=['branch_id'=>$this->campus->id,'study_slot_id'=>$this->slot->id,'allocated_from'=>'2026-10-05','allocated_to'=>'2026-10-30','start_time'=>'10:00','end_time'=>'14:00'];
        $this->actingAs($this->admin)->getJson('/admin/available-seats?'.http_build_query($params))->assertOk()->assertJsonFragment(['id'=>$this->seat->id]);
        $params['start_time']='09:00';$params['end_time']='13:00';
        $this->getJson('/admin/available-seats?'.http_build_query($params))->assertOk()->assertExactJson([]);
        auth()->logout();
        $this->get('/seat-report?date=2026-10-05')->assertOk()->assertSee('Amit Kumar')->assertSee('06:00–10:00')->assertSee('10:00–24:00')->assertDontSee($m->student->mobile)->assertDontSee($m->student->email)->assertHeader('X-Robots-Tag','noindex, nofollow, noarchive');
        $csv=$this->get('/seat-report?date=2026-10-05&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Amit Kumar',$csv);$this->assertStringNotContainsString($m->student->email,$csv);
        $this->get('/')->assertOk()->assertSee('Seat & Time Report',false);
    }
    public function test_overnight_open_ended_and_24_hour_conflicts(): void
    {
        $this->assertFalse(DailySeatWindow::overlaps('2026-10-01',null,'06:00','10:00','2027-01-01',null,'10:00','14:00'));
        $this->assertTrue(DailySeatWindow::overlaps('2026-10-01',null,'06:00','10:00','2027-01-01',null,'09:00','13:00'));
        $this->assertTrue(DailySeatWindow::overlaps('2026-10-01','2026-10-01','22:00','02:00','2026-10-02','2026-10-02','01:00','05:00'));
        $this->assertFalse(DailySeatWindow::overlaps('2026-10-01','2026-10-01','22:00','02:00','2026-10-02','2026-10-02','02:00','06:00'));
        $this->assertTrue(DailySeatWindow::overlaps('2026-10-01','2026-10-01',null,null,'2026-10-01','2026-10-01','06:00','10:00'));
        $m=$this->member('Night');$this->allocation($m,'22:00','02:00','2026-10-01','2026-10-01');
        $this->get('/seat-report?date=2026-10-02')->assertOk()->assertSee('Night')->assertSee('00:00–02:00');
    }
    public function test_unpaid_release_on_eleventh_preserves_history_and_paid_membership(): void
    {
        $unpaid=$this->member('Unpaid',paid:100);$a=$this->allocation($unpaid);
        $this->travelTo(now()->setDate(2026,10,10)->endOfDay());
        $this->assertSame(0,app(SeatFeeReleaseService::class)->releaseDue());
        $this->travelTo(now()->setDate(2026,10,11)->startOfDay());
        $this->assertSame(1,app(SeatFeeReleaseService::class)->releaseDue());
        $this->assertSame('released',$a->fresh()->status);$this->assertSame('expired',$unpaid->fresh()->status);
        $this->assertDatabaseHas('students',['id'=>$unpaid->student_id]);$this->assertSame(1,$unpaid->payments()->count());
        $paid=$this->member('Paid');$pa=$this->allocation($paid,'10:00','14:00');
        $this->assertSame(0,app(SeatFeeReleaseService::class)->releaseDue());$this->assertSame('active',$pa->fresh()->status);
        $payment=$paid->payments()->first();PaymentAdjustment::create(['payment_id'=>$payment->id,'type'=>'refund','amount'=>1,'reason'=>'Test refund','created_by'=>$this->admin->id]);
        $this->assertSame(1,app(SeatFeeReleaseService::class)->releaseDue());
    }
    public function test_paid_september_holds_to_october_tenth_then_releases(): void
    {
        $m=$this->member('September','2026-09-01','2026-09-30');$a=$this->allocation($m);
        $this->assertSame('2026-10-10',$a->allocated_to->toDateString());
        $this->travelTo(now()->setDate(2026,10,10));$this->assertSame(0,app(SeatFeeReleaseService::class)->releaseDue());
        $this->travelTo(now()->setDate(2026,10,11));$this->assertSame(1,app(SeatFeeReleaseService::class)->releaseDue());
        $this->assertSame('2026-11-10',DailySeatWindow::renewalDeadline('2026-10-14'));
        $this->assertSame('2026-10-18',DailySeatWindow::unpaidDeadline('2026-10-18'));
    }
    public function test_admin_can_change_seat_and_time_delete_allocation_and_cannot_cross_campus(): void
    {
        $m=$this->member();$a=$this->allocation($m);
        $seat=Seat::factory()->create(['study_hall_id'=>$this->seat->study_hall_id]);
        $this->actingAs($this->admin)->patch('/admin/study-space/allocations/'.$a->id,['seat_id'=>$seat->id,'start_time'=>'10:00','end_time'=>'14:00','status'=>'active'])->assertSessionHasNoErrors();
        $this->assertSame($seat->id,$a->fresh()->seat_id);
        $foreign=Seat::factory()->create();
        $this->patch('/admin/study-space/allocations/'.$a->id,['seat_id'=>$foreign->id,'status'=>'active'])->assertStatus(422);
        $branchAdmin=$this->branchAdmin($foreign->studyHall->branch_id);
        $this->actingAs($branchAdmin)->delete('/admin/seat-allocations/'.$a->id)->assertForbidden();
        $this->actingAs($this->admin)->delete('/admin/seat-allocations/'.$a->id)->assertRedirect();
        $this->assertDatabaseMissing('seat_allocations',['id'=>$a->id]);$this->assertDatabaseHas('students',['id'=>$m->student_id]);
    }
    public function test_cannot_reactivate_overdue_seat_or_change_paid_duration(): void
    {
        $m=$this->member(paid:0);$a=$this->allocation($m);
        $this->travelTo(now()->setDate(2026,10,11));
        $this->actingAs($this->admin)->patch('/admin/study-space/allocations/'.$a->id,['start_time'=>'10:00','end_time'=>'14:00','status'=>'active'])->assertSessionHasErrors('student_id');
        $this->assertSame(1,app(SeatFeeReleaseService::class)->releaseDue());
        $this->patch('/admin/study-space/allocations/'.$a->id,['status'=>'active'])->assertSessionHasErrors('student_id');
    }
    public function test_renewal_same_seat_truncates_grace_without_conflict_and_activates(): void
    {
        $m=$this->member('Renewal','2026-09-01','2026-09-30');$a=$this->allocation($m);
        $new=app(MembershipRenewalService::class)->renew($m->student,['fee_plan_id'=>$this->plan->id,'study_slot_id'=>$this->slot->id,'seat_id'=>$this->seat->id,'start_date'=>'2026-10-05','start_time'=>'06:00']);
        $this->assertSame('2026-10-04',$a->fresh()->allocated_to->toDateString());$this->assertSame('released',$a->fresh()->status);
        $this->assertSame('2026-11-10',$new->seatAllocations()->first()->allocated_to->toDateString());
    }
    public function test_reset_private_backup_preserves_seats_and_admins_and_removes_all_enrollments(): void
    {
        $m=$this->member();$this->allocation($m);$portal=User::factory()->create(['role'=>'student']);$m->student->update(['user_id'=>$portal->id]);
        Admission::create(['branch_id'=>$this->campus->id,'application_no'=>'TEST-ADMISSION','name'=>'Applicant','mobile'=>'9876543210','status'=>'new']);
        $dir=sys_get_temp_dir().'/cnet-reset-test-'.bin2hex(random_bytes(6));
        $this->artisan('maintenance:reset-enrollments',['--confirm'=>'RESET-CNET-LIBRARY-ENROLLMENTS','--backup-directory'=>$dir])->assertSuccessful();
        $this->assertSame(0,Student::count());$this->assertSame(0,SeatAllocation::count());$this->assertSame(0,Admission::count());
        $this->assertDatabaseHas('seats',['id'=>$this->seat->id]);$this->assertDatabaseHas('users',['id'=>$this->admin->id]);$this->assertDatabaseMissing('users',['id'=>$portal->id]);
        $manifest=glob($dir.'/*/manifest.json')[0];$data=json_decode(file_get_contents($manifest),true);
        $this->assertSame(1,$data['tables']['students']['rows']);$this->assertSame(0600,fileperms($manifest)&0777);
        $studentBackup=dirname($manifest).'/students.json';$this->assertSame($data['tables']['students']['sha256'],hash_file('sha256',$studentBackup));
        File::deleteDirectory($dir);
    }
    public function test_reset_refuses_public_backup_path_and_preserves_data(): void
    {
        $m=$this->member();$this->allocation($m);
        $this->artisan('maintenance:reset-enrollments',['--confirm'=>'RESET-CNET-LIBRARY-ENROLLMENTS','--backup-directory'=>public_path()])->assertFailed();
        $this->assertSame(1,Student::count());$this->assertSame(1,SeatAllocation::count());
    }
    public function test_admission_uses_actual_hours_and_correct_fee_plan(): void
    {
        $admission=Admission::create(['branch_id'=>$this->campus->id,'application_no'=>'TEST-ADMISSION','name'=>'Applicant','mobile'=>'9876543210','status'=>'new']);
        $student=app(AdmissionApprovalService::class)->approve($admission,['fee_plan_id'=>$this->plan->id,'study_slot_id'=>$this->slot->id,'seat_id'=>$this->seat->id,'start_date'=>'2026-10-05','start_time'=>'10:00']);
        $a=$student->seatAllocations()->first();$this->assertSame('10:00:00',$a->start_time);$this->assertSame('14:00:00',$a->end_time);
        $this->actingAs($this->admin)->get('/admin/study-space')->assertOk()->assertSee('Actual daily start');
    }
    public function test_all_requested_durations_and_full_day_are_resolved_without_all_day_flexible_block(): void
    {
        foreach([3,4,5,6,8,10,12] as $hours) {
            $slot=StudySlot::create(['name'=>'Flexible '.$hours,'status'=>true,'branch_id'=>$this->campus->id,'duration_hours'=>$hours,'start_time'=>null,'end_time'=>null,'is_flexible'=>true]);
            [$start,$end]=app(SeatAllocationService::class)->resolveTimes($slot,['start_time'=>'18:00']);
            $this->assertSame('18:00:00',$start);$this->assertSame(sprintf('%02d:00:00',(18+$hours)%24),$end);
        }
        $full=StudySlot::create(['name'=>'24x7','status'=>true,'branch_id'=>$this->campus->id,'duration_hours'=>24,'is_24x7'=>true]);
        $this->assertSame([null,null],app(SeatAllocationService::class)->resolveTimes($full));
        $this->expectException(ValidationException::class);
        app(SeatAllocationService::class)->resolveTimes($slot);
    }

    public function test_reset_rolls_back_all_changes_if_an_unreviewed_dependency_blocks_deletion(): void
    {
        $m=$this->member();$a=$this->allocation($m);$payment=$m->payments()->first();
        \Illuminate\Support\Facades\Schema::create('test_unreviewed_ledger',function($table){$table->id();$table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();});
        DB::table('test_unreviewed_ledger')->insert(['payment_id'=>$payment->id]);
        $dir=sys_get_temp_dir().'/cnet-rollback-test-'.bin2hex(random_bytes(6));
        $this->artisan('maintenance:reset-enrollments',['--confirm'=>'RESET-CNET-LIBRARY-ENROLLMENTS','--backup-directory'=>$dir])->assertFailed();
        $this->assertDatabaseHas('seat_allocations',['id'=>$a->id]);$this->assertDatabaseHas('students',['id'=>$m->student_id]);$this->assertDatabaseHas('payments',['id'=>$payment->id]);
        $this->assertNotEmpty(glob($dir.'/*/manifest.json'));File::deleteDirectory($dir);
    }

    public function test_admin_report_filter_stays_inside_campus_and_public_can_view_both(): void
    {
        $foreign=Seat::factory()->create();$foreign->studyHall->branch->update(['name'=>'MCI Campus']);
        $this->get('/seat-report')->assertOk()->assertSee('C-Net Campus')->assertSee('MCI Campus');
        $branchAdmin=$this->branchAdmin($this->campus->id);
        $this->actingAs($branchAdmin)->get('/admin/seat-report')->assertOk()->assertSee('C-Net Campus')->assertDontSee('MCI Campus');
        $this->get('/admin/seat-report?branch_id='.$foreign->studyHall->branch_id)->assertForbidden();
    }

    public function test_csv_formula_names_are_escaped_and_fee_plan_mismatch_is_rejected(): void
    {
        $m=$this->member('=1+1');$this->allocation($m);
        $csv=$this->get('/seat-report?format=csv')->assertOk()->streamedContent();$this->assertStringContainsString("'=1+1",$csv);
        $other=StudySlot::factory()->create(['branch_id'=>$this->campus->id]);
        $this->expectException(ValidationException::class);
        app(MembershipRenewalService::class)->renew($m->student,['fee_plan_id'=>$this->plan->id,'study_slot_id'=>$other->id]);
    }

}
