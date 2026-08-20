<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureBranchScope;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Staff;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BranchMiddlewareNestedModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_adjustment_from_another_branch_is_rejected(): void
    {
        [$branchA, $branchB] = [Branch::factory()->create(), Branch::factory()->create()];
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branchA->id, 'status' => true]);
        $student = Student::factory()->create(['branch_id' => $branchB->id]);
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'start_date' => today(),
            'expiry_date' => today()->addMonth(),
            'base_fee' => 500,
            'discount' => 0,
            'final_fee' => 500,
            'status' => 'active',
        ]);
        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'MW-'.Str::upper(Str::random(12)),
            'amount' => 500,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
        ]);
        $adjustment = PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 50,
            'reason' => 'Test',
        ]);

        $this->assertNestedModelIsForbidden($admin, 'adjustment', $adjustment);
    }

    public function test_staff_leave_from_another_branch_is_rejected(): void
    {
        [$branchA, $branchB] = [Branch::factory()->create(), Branch::factory()->create()];
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branchA->id, 'status' => true]);
        $staff = Staff::create([
            'branch_id' => $branchB->id,
            'staff_code' => 'STF-'.Str::upper(Str::random(8)),
            'name' => 'Other Branch Staff',
            'role' => 'Operator',
            'monthly_salary' => 0,
            'status' => 'active',
        ]);
        $leave = StaffLeave::create([
            'staff_id' => $staff->id,
            'from_date' => today(),
            'to_date' => today()->addDay(),
            'leave_type' => 'casual',
            'reason' => 'Test leave',
            'status' => 'pending',
        ]);

        $this->assertNestedModelIsForbidden($admin, 'staffLeave', $leave);
    }

    private function assertNestedModelIsForbidden(User $admin, string $parameterName, object $model): void
    {
        $request = Request::create('/admin/test', 'GET');
        $request->setUserResolver(fn () => $admin);
        $route = new Route(['GET'], '/admin/test', fn () => null);
        $route->setParameter($parameterName, $model);
        $request->setRouteResolver(fn () => $route);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);

        app(EnsureBranchScope::class)->handle($request, fn () => response('ok'));
    }
}
