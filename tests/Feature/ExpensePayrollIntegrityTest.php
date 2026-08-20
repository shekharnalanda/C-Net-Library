<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseAdjustment;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePayrollIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_paid_payroll_posts_exactly_one_linked_salary_expense_and_becomes_immutable(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $staff = Staff::create([
            'branch_id' => $branch->id,
            'staff_code' => 'CNL-STF-PAY01',
            'name' => 'Payroll Staff',
            'role' => 'Assistant',
            'monthly_salary' => 12000,
            'status' => 'active',
        ]);

        $payload = [
            'month' => 8,
            'year' => 2026,
            'allowances' => 1000,
            'deductions' => 500,
            'status' => 'paid',
            'payment_mode' => 'bank_transfer',
            'transaction_ref' => 'PAYROLL-2026-08-001',
            'remarks' => 'August salary',
        ];

        $this->actingAs($admin)
            ->post(route('admin.staff.payroll', $staff), $payload)
            ->assertRedirect();

        $payroll = Payroll::query()->where('staff_id', $staff->id)->firstOrFail();
        $this->assertSame('paid', $payroll->status);
        $this->assertSame('12500.00', $payroll->net_salary);

        $expense = Expense::query()->where('payroll_id', $payroll->id)->firstOrFail();
        $this->assertSame('Salary', $expense->category);
        $this->assertSame('12500.00', $expense->amount);
        $this->assertSame($staff->branch_id, $expense->branch_id);
        $this->assertSame(1, Expense::query()->where('payroll_id', $payroll->id)->count());

        $this->actingAs($admin)
            ->post(route('admin.staff.payroll', $staff), [
                ...$payload,
                'allowances' => 2000,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('12500.00', $payroll->fresh()->net_salary);
        $this->assertSame(1, Expense::query()->where('payroll_id', $payroll->id)->count());
    }

    public function test_expense_adjustment_preserves_original_and_reports_net_expense(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $expense = Expense::create([
            'branch_id' => $branch->id,
            'expense_date' => today(),
            'category' => 'Electricity',
            'payee' => 'Utility',
            'amount' => 1000,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.expenses.adjustments.store', $expense), [
                'type' => 'correction',
                'amount' => 250,
                'reason' => 'Duplicate charge correction',
            ])
            ->assertRedirect();

        $this->assertSame('1000.00', $expense->fresh()->amount);
        $this->assertDatabaseHas('expense_adjustments', [
            'expense_id' => $expense->id,
            'type' => 'correction',
            'amount' => 250,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics) {
            return (float) $metrics['gross_expenses'] === 1000.0
                && (float) $metrics['expense_adjustments'] === 250.0
                && (float) $metrics['expenses'] === 750.0;
        });
    }

    public function test_expense_adjustments_cannot_exceed_original_amount(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $expense = Expense::create([
            'branch_id' => $branch->id,
            'expense_date' => today(),
            'category' => 'Internet',
            'amount' => 500,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);
        ExpenseAdjustment::create([
            'expense_id' => $expense->id,
            'type' => 'correction',
            'amount' => 400,
            'reason' => 'First correction',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.expenses.adjustments.store', $expense), [
                'type' => 'reversal',
                'amount' => 150,
                'reason' => 'Too much',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame('400.00', ExpenseAdjustment::query()->where('expense_id', $expense->id)->sum('amount'));
    }
}
