<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationAndConstraintHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function adminFor(Branch $branch, string $permissionSlug): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'status' => true,
        ]);

        $permission = Permission::firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug, 'group' => 'Test']
        );
        $role = Role::create([
            'name' => 'Test Role '.uniqid(),
            'slug' => 'test-role-'.uniqid(),
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_duplicate_expense_transaction_reference_returns_validation_error(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->adminFor($branch, 'payments.manage');

        Expense::create([
            'branch_id' => $branch->id,
            'expense_date' => today(),
            'category' => 'Internet',
            'amount' => 500,
            'payment_mode' => 'upi',
            'transaction_ref' => 'EXP-DUP-001',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.expenses.store'), [
            'branch_id' => $branch->id,
            'expense_date' => today()->toDateString(),
            'category' => 'Electricity',
            'amount' => 750,
            'payment_mode' => 'upi',
            'transaction_ref' => 'EXP-DUP-001',
        ])->assertSessionHasErrors('transaction_ref');

        $this->assertSame(1, Expense::query()->where('transaction_ref', 'EXP-DUP-001')->count());
    }

    public function test_staff_attendance_rejects_shift_from_another_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->adminFor($branchA, 'staff.manage');

        $staff = Staff::create([
            'branch_id' => $branchA->id,
            'staff_code' => 'STF-A-001',
            'name' => 'Branch A Staff',
            'role' => 'staff',
            'monthly_salary' => 10000,
            'status' => 'active',
        ]);

        $shift = StaffShift::create([
            'branch_id' => $branchB->id,
            'name' => 'Branch B Shift',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'status' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.staff.attendance', $staff), [
            'staff_shift_id' => $shift->id,
            'status' => 'present',
        ])->assertSessionHasErrors('staff_shift_id');
    }
}
