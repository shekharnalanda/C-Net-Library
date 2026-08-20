<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashbookFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function adminWithRole(string $legacyRole, string $pivotRole): User
    {
        $user = User::create([
            'name' => ucfirst($legacyRole).' User',
            'email' => $legacyRole.'-'.Str::random(6).'@example.com',
            'password' => 'SecurePass123!',
            'role' => $legacyRole,
            'status' => true,
        ]);

        $role = Role::query()->where('slug', $pivotRole)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    public function test_accountant_can_record_append_only_expense_with_audit_log(): void
    {
        $accountant = $this->adminWithRole('accountant', 'accountant');
        $branch = Branch::query()->where('status', true)->first();

        $response = $this->actingAs($accountant)->post(route('admin.expenses.store'), [
            'branch_id' => $branch?->id,
            'expense_date' => today()->toDateString(),
            'category' => 'Electricity',
            'payee' => 'Power Company',
            'amount' => 1250.50,
            'payment_mode' => 'upi',
            'transaction_ref' => 'EXP-TEST-001',
            'description' => 'Monthly bill',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('expenses', [
            'category' => 'Electricity',
            'transaction_ref' => 'EXP-TEST-001',
            'created_by' => $accountant->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $accountant->id,
            'action' => 'expense.created',
        ]);
    }

    public function test_duplicate_expense_transaction_reference_is_rejected(): void
    {
        $accountant = $this->adminWithRole('accountant', 'accountant');

        Expense::create([
            'expense_date' => today(),
            'category' => 'Internet',
            'amount' => 500,
            'payment_mode' => 'upi',
            'transaction_ref' => 'EXP-DUP-001',
            'created_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)->post(route('admin.expenses.store'), [
            'expense_date' => today()->toDateString(),
            'category' => 'Internet',
            'amount' => 500,
            'payment_mode' => 'upi',
            'transaction_ref' => 'EXP-DUP-001',
        ])->assertSessionHasErrors('transaction_ref');

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_reception_user_cannot_access_cashbook(): void
    {
        $reception = $this->adminWithRole('reception', 'reception');

        $this->actingAs($reception)
            ->get(route('admin.expenses.index'))
            ->assertForbidden();
    }

    public function test_reports_show_closing_cash_position_after_expenses(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $student = Student::query()->where('branch_id', $branch->id)->first();

        if (! $student) {
            $student = Student::create([
                'branch_id' => $branch->id,
                'student_code' => 'CNL-CASH-001',
                'qr_token' => (string) Str::uuid(),
                'name' => 'Cash Test Student',
                'mobile' => '9000000001',
                'joining_date' => today(),
                'status' => 'active',
            ]);
        }

        $membership = StudentMembership::query()->where('student_id', $student->id)->first();
        if (! $membership) {
            $this->markTestSkipped('Seeded finance fixture does not include a membership for cashbook reporting test.');
        }

        Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-'.now()->format('Y').'-CASH01',
            'amount' => 2000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        Expense::create([
            'branch_id' => $branch->id,
            'expense_date' => today(),
            'category' => 'Cleaning',
            'amount' => 750,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Operating Expenses');
        $response->assertSee('Closing Cash Position');
        $response->assertSee('1,250.00');
    }
}
