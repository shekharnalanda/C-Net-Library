<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AdmissionApprovalService;
use App\Services\LibraryPortalMailService;
use App\Services\LibraryStudentSessionService;
use App\Services\SettingsService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LibraryPortalFlowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $campus;

    private Seat $seat;

    private StudySlot $slot;

    private FeePlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $this->campus = Branch::factory()->create(['name' => 'C-Net Campus']);
        $hall = StudyHall::factory()->create(['branch_id' => $this->campus->id]);
        $this->seat = Seat::factory()->create(['study_hall_id' => $hall->id, 'seat_no' => '18']);
        $this->slot = StudySlot::factory()->create(['branch_id' => $this->campus->id, 'duration_hours' => 4, 'start_time' => '06:00:00', 'end_time' => '10:00:00', 'is_flexible' => true, 'is_24x7' => false]);
        $this->plan = FeePlan::factory()->create(['branch_id' => $this->campus->id, 'study_slot_id' => $this->slot->id, 'monthly_fee' => 300, 'validity_days' => 30]);
        app(SettingsService::class)->set('seat_monthly_cutoff_day', 10, 'membership', 'integer', null);
    }

    private function student(): Student
    {
        $u = User::factory()->create(['role' => 'student', 'status' => true, 'password' => '9876543210']);

        return Student::factory()->create(['user_id' => $u->id, 'branch_id' => $this->campus->id, 'student_code' => 'CNL-TEST-'.strtoupper(bin2hex(random_bytes(3))), 'status' => 'active', 'mobile' => '9876543210', 'email' => $u->email]);
    }

    private function member(Student $s, string $from = '2026-10-01', string $to = '2026-10-30', int $paid = 300): StudentMembership
    {
        $m = StudentMembership::factory()->create(['student_id' => $s->id, 'study_slot_id' => $this->slot->id, 'fee_plan_id' => $this->plan->id, 'start_date' => $from, 'expiry_date' => $to, 'final_fee' => 300, 'status' => 'active']);
        if ($paid) {
            Payment::factory()->create(['student_id' => $s->id, 'student_membership_id' => $m->id, 'amount' => $paid, 'payment_status' => 'paid']);
        }

        return $m;
    }

    private function payload(): array
    {
        return ['branch_id' => $this->campus->id, 'name' => 'New Student', 'mobile' => '9123456789', 'email' => 'new-student@example.test', 'study_slot_id' => $this->slot->id, 'fee_plan_id' => $this->plan->id, 'preferred_start_date' => '2026-10-05', 'preferred_start_time' => '10:00', 'preferred_end_time' => '14:00', 'preferred_seat_id' => $this->seat->id, 'wants_locker' => 0];
    }

    private function occupy(string $from = '06:00', string $to = '10:00'): void
    {
        $s = $this->student();
        $m = $this->member($s);
        SeatAllocation::create(['student_id' => $s->id, 'student_membership_id' => $m->id, 'seat_id' => $this->seat->id, 'study_slot_id' => $this->slot->id, 'allocated_from' => '2026-10-01', 'allocated_to' => '2026-11-10', 'start_time' => $from, 'end_time' => $to, 'status' => 'active']);
    }

    public function test_partial_portal_migration_can_resume_without_losing_students_or_existing_leases(): void
    {
        $student = $this->student();
        Schema::dropIfExists('library_device_recovery');
        Schema::dropIfExists('library_portal_mail');
        Schema::dropIfExists('library_student_sessions');
        Schema::table('admissions', fn (Blueprint $table) => $table->dropColumn('preferred_start_time'));
        $migration = require database_path('migrations/2026_10_02_083000_add_library_portal_flow.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('admissions', 'preferred_start_time'));
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        app(LibraryStudentSessionService::class)->claim($student, str_repeat('a', 64));
        $migration->up();
        $this->assertDatabaseHas('library_student_sessions', ['student_id' => $student->id, 'token_hash' => hash('sha256', str_repeat('a', 64))]);
    }

    public function test_mysql_portal_schema_uses_explicit_datetime_fields_without_implicit_timestamp_defaults(): void
    {
        $connection = new MySqlConnection(new \PDO('sqlite::memory:'), 'test', '', ['driver' => 'mysql', 'version' => '5.7.44']);
        $connection->useDefaultSchemaGrammar();
        $sql = [];
        $compile = function (string $name, \Closure $callback, bool $create = false) use ($connection, &$sql) {
            $blueprint = new Blueprint($connection, $name);
            if ($create) {
                $blueprint->create();
            }
            $callback($blueprint);
            $sql = array_merge($sql, $blueprint->toSql());
        };
        Schema::shouldReceive('hasColumn')->andReturn(false);
        Schema::shouldReceive('hasTable')->andReturn(false);
        Schema::shouldReceive('table')->andReturnUsing(fn ($name, $callback) => $compile($name, $callback));
        Schema::shouldReceive('create')->andReturnUsing(fn ($name, $callback) => $compile($name, $callback, true));
        $migration = require database_path('migrations/2026_10_02_083000_add_library_portal_flow.php');
        $migration->up();
        $compiled = implode("\n", $sql);
        $this->assertStringContainsString('`last_seen_at` datetime not null', $compiled);
        $this->assertStringContainsString('`expires_at` datetime not null', $compiled);
        $this->assertStringNotContainsString('timestamp not null', $compiled);
        $this->assertStringNotContainsString('0000-00-00', $compiled);
    }

    public function test_existing_recovery_link_and_expired_form_notice_remain_on_both_login_pages(): void
    {
        Route::get('/account-recovery', fn () => 'Existing recovery')->name('mci.recovery');
        Route::getRoutes()->refreshNameLookups();
        $this->withSession(['error' => 'Fresh form required'])->get('/login')
            ->assertOk()->assertSee('/account-recovery')->assertSee('Fresh form required')->assertSee('/student-login');
        $this->withSession(['error' => 'Fresh form required'])->get('/student-login')
            ->assertOk()->assertSee('/account-recovery')->assertSee('Fresh form required');
    }

    public function test_duration_shows_booked_and_free_times_without_contact_details(): void
    {
        $this->occupy();
        $response = $this->getJson('/admission/availability?'.http_build_query(['branch_id' => $this->campus->id, 'study_slot_id' => $this->slot->id, 'fee_plan_id' => $this->plan->id, 'start_date' => '2026-10-05']))->assertOk();
        $windows = collect($response->json('times'))->keyBy('start');
        $this->assertSame(0, $windows['06:00']['available']);
        $this->assertSame(1, $windows['10:00']['available']);
        $this->assertSame('18', $windows['10:00']['seats'][0]['number']);
        $this->assertStringNotContainsString('9876543210', $response->getContent());
        $this->get('/admission')->assertOk()->assertSee('समय अपने-आप चयनित नहीं होगा');
    }

    public function test_selection_is_saved_and_stale_or_foreign_seat_cannot_be_submitted(): void
    {
        $this->post('/admission', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('admissions', ['preferred_seat_id' => $this->seat->id, 'preferred_start_time' => '10:00']);
        $this->occupy('10:00', '14:00');
        $this->post('/admission', array_merge($this->payload(), ['mobile' => '9234567890']))->assertSessionHasErrors('preferred_seat_id');
        $foreign = Seat::factory()->create();
        $this->post('/admission', array_merge($this->payload(), ['preferred_seat_id' => $foreign->id]))->assertSessionHasErrors('preferred_seat_id');
    }

    public function test_available_durations_are_not_fabricated_and_twenty_four_hour_needs_whole_day(): void
    {
        $this->occupy();
        $full = StudySlot::factory()->create(['branch_id' => $this->campus->id, 'is_24x7' => true, 'duration_hours' => 24]);
        $plan = FeePlan::factory()->create(['branch_id' => $this->campus->id, 'study_slot_id' => $full->id]);
        $this->getJson('/admission/availability?'.http_build_query(['branch_id' => $this->campus->id, 'study_slot_id' => $full->id, 'fee_plan_id' => $plan->id, 'start_date' => '2026-10-05']))->assertOk()->assertJsonPath('times.0.available', 0);
    }

    public function test_approval_generates_mobile_login_and_welcome_mail_without_manual_password(): void
    {
        Mail::fake();
        $a = Admission::create(array_merge($this->payload(), ['application_no' => 'APP-NEW', 'status' => 'new']));
        $s = app(AdmissionApprovalService::class)->approve($a, ['fee_plan_id' => $this->plan->id, 'study_slot_id' => $this->slot->id, 'seat_id' => $this->seat->id, 'start_date' => '2026-10-05', 'start_time' => '10:00']);
        $this->assertNotEmpty($s->student_code);
        $this->assertTrue(Hash::check('9123456789', $s->user->password));
        app(LibraryPortalMailService::class)->welcome($s);
        $this->assertDatabaseHas('library_portal_mail', ['student_id' => $s->id, 'status' => 'sent']);
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9123456789'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($s->user, 'library_student');
    }

    public function test_one_active_student_session_blocks_another_and_logout_releases_only_student(): void
    {
        $s = $this->student();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => true]);
        $this->actingAs($admin, 'web');
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9876543210'])->assertRedirect(route('student.dashboard'));
        $this->get('/student/dashboard')->assertOk();
        $this->get('/admin/dashboard')->assertOk();
        $this->get('/login')->assertOk();
        $this->assertAuthenticatedAs($admin, 'web');
        $hash = DB::table('library_student_sessions')->where('student_id', $s->id)->value('token_hash');
        Auth::guard('library_student')->forgetUser();
        $this->flushSession();
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9876543210'])->assertSessionHasErrors('student_code');
        $this->assertSame($hash, DB::table('library_student_sessions')->where('student_id', $s->id)->value('token_hash'));
        app(LibraryStudentSessionService::class)->revoke($s);
        $this->actingAs($admin, 'web');
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9876543210'])->assertRedirect();
        $this->post('/student/logout')->assertRedirect(route('student.login'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertDatabaseMissing('library_student_sessions', ['student_id' => $s->id]);
    }

    public function test_admin_logout_does_not_log_out_student_and_expired_lease_does_not_resume(): void
    {
        $s = $this->student();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => true]);
        $this->actingAs($admin, 'web');
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9876543210']);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertAuthenticatedAs($s->user, 'library_student');
        $this->get('/student/dashboard')->assertOk();
        DB::table('library_student_sessions')->where('student_id', $s->id)->update(['expires_at' => now()->subMinute()]);
        $this->get('/student/dashboard')->assertRedirect(route('student.login'));
    }

    public function test_fee_reminders_are_idempotent_and_skip_paid_next_month(): void
    {
        $s = $this->student();
        $this->member($s);
        $paid = $this->student();
        $this->member($paid);
        $this->member($paid, '2026-11-01', '2026-11-30');
        $this->travelTo(now()->setDate(2026, 10, 25));
        $this->artisan('library:fee-reminders')->assertSuccessful();
        $this->artisan('library:fee-reminders')->assertSuccessful();
        $this->assertSame(1, DB::table('library_portal_mail')->count());
        $this->assertDatabaseHas('library_portal_mail', ['student_id' => $s->id, 'event_key' => 'fee:'.$s->id.':2026-11:25']);
        $this->travelTo(now()->setDate(2026, 10, 28));
        $this->artisan('library:fee-reminders')->assertSuccessful();
        $this->assertSame(2, DB::table('library_portal_mail')->count());
    }

    public function test_report_is_private_contains_full_fee_record_and_exports_excel(): void
    {
        $s = $this->student();
        $this->member($s);
        $this->get('/admin/student-report')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'status' => true]), 'web');
        $this->get('/admin/student-report')->assertOk()->assertSee($s->student_code)->assertSee($s->email)->assertSee('Payment records');
        $r = $this->get('/admin/student-report?format=xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith("PK\x03\x04", $r->getContent());
        $this->assertStringContainsString($s->student_code, $r->getContent());
        $this->get('/admin/student-report?format=pdf')->assertOk()->assertSee('window.print()');
    }

    public function test_email_otp_closes_old_lease_and_cannot_be_replayed(): void
    {
        Mail::fake();
        $s = $this->student();
        app(LibraryStudentSessionService::class)->claim($s, 'old-device');
        $this->post('/student-login/device-otp', ['student_code' => $s->student_code, 'email' => $s->email])->assertRedirect();
        $row = DB::table('library_device_recovery')->first();
        $this->assertNotNull($row);
        DB::table('library_device_recovery')->where('id', $row->id)->update(['otp_hash' => Hash::make('123456')]);
        $this->post('/student-login/device-confirm', ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertDatabaseHas('library_student_sessions', ['student_id' => $s->id]);
        $this->post('/student-login/device-confirm', ['otp' => '123456'])->assertRedirect(route('student.login'));
        $this->assertDatabaseMissing('library_student_sessions', ['student_id' => $s->id]);
        $this->post('/student-login/device-confirm', ['otp' => '123456'])->assertSessionHasErrors('otp');
    }

    public function test_student_api_cannot_bypass_active_browser_session(): void
    {
        $s = $this->student();
        $this->post('/student-login', ['student_code' => $s->student_code, 'password' => '9876543210'])->assertRedirect();
        $this->postJson('/api/mobile/v1/login', ['email' => $s->user->email, 'password' => '9876543210'])->assertStatus(422);
        $this->post('/student/logout')->assertRedirect();
        $this->postJson('/api/mobile/v1/login', ['email' => $s->user->email, 'password' => '9876543210'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_expired_csrf_redirects_to_fresh_form_without_running_action(): void
    {
        $handler = app(ExceptionHandler::class);
        $request = Request::create('/student-login', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $response = $handler->render($request, new HttpException(419, 'Page Expired'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('student.login'), $response->headers->get('Location'));
        $this->assertSame(0, Student::count());
    }

    public function test_welcome_email_failure_preserves_admission_and_is_retryable(): void
    {
        $s = $this->student();
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        app(LibraryPortalMailService::class)->welcome($s);
        $this->assertDatabaseHas('students', ['id' => $s->id, 'status' => 'active']);
        $this->assertDatabaseHas('library_portal_mail', ['student_id' => $s->id, 'status' => 'pending', 'error_code' => 'MAIL_TRANSPORT_FAILED', 'attempts' => 1]);
    }
}
