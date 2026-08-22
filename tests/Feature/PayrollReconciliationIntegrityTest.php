<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseAdjustment;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollReconciliationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payroll_transaction_reference_is_rejected(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'super_admin',
            'status' => true,
        ]);
        $staffA = Staff::create([
            'branch_id' => $branch->id,
            'staff_code' => 'STF-'.Str::upper(Str::random(8)),
            'name' => 'Staff A',
            'role' => 'Staff',
            'monthly_salary' => 10000,
            'status' => 'active',
        ]);
        $staffB = Staff::create([
            'branch_id' => $branch->id,
            'staff_code' => 'STF-'.Str::upper(Str::random(8)),
            'name' => 'Staff B',
            'role' => 'Staff',
            'monthly_salary' => 12000,
            'status' => 'active',
        ]);

        Payroll::create([
            'staff_id' => $staffA->id,
            'month' => 7,
            'year' => 2026,
            'basic_salary' => 10000,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 10000,
            'status' => 'paid',
            'paid_on' => today(),
            'payment_mode' => 'bank_transfer',
            'transaction_ref' => 'PAYROLL-REF-001',
            'processed_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.staff.payroll', $staffB), [
            'month' => 7,
            'year' => 2026,
            'allowances' => 0,
            'deductions' => 0,
            'status' => 'paid',
            'payment_mode' => 'bank_transfer',
            'transaction_ref' => 'PAYROLL-REF-001',
        ]);

        $response->assertSessionHasErrors('transaction_ref');
        $this->assertDatabaseMissing('payrolls', [
            'staff_id' => $staffB->id,
            'transaction_ref' => 'PAYROLL-REF-001',
        ]);
    }

    public function test_reconciliation_command_reports_paid_payroll_without_linked_expense(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $staff = Staff::create([
            'branch_id' => $branch->id,
            'staff_code' => 'STF-'.Str::upper(Str::random(8)),
            'name' => 'Historical Staff',
            'role' => 'Staff',
            'monthly_salary' => 15000,
            'status' => 'active',
        ]);

        Payroll::create([
            'staff_id' => $staff->id,
            'month' => 6,
            'year' => 2026,
            'basic_salary' => 15000,
            'allowances' => 0,
            'deductions' => 0,
            'net_salary' => 15000,
            'status' => 'paid',
            'paid_on' => today()->subMonth(),
            'payment_mode' => 'cash',
        ]);

        $this->artisan('payroll:audit-reconciliation')
            ->expectsOutputToContain('has no linked cashbook expense')
            ->assertFailed();
    }

    public function test_dashboard_uses_net_expense_after_adjustment(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'super_admin',
            'status' => true,
        ]);
        $expense = Expense::create([
            'branch_id' => $branch->id,
            'expense_date' => today(),
            'category' => 'Electricity',
            'amount' => 1000,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);
        ExpenseAdjustment::create([
            'expense_id' => $expense->id,
            'type' => 'correction',
            'amount' => 250,
            'reason' => 'Duplicate component corrected',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('data', function (array $data) {
            return (float) $data['today_gross_expenses'] === 1000.0
                && (float) $data['today_expense_adjustments'] === 250.0
                && (float) $data['today_expenses'] === 750.0
                && (float) $data['today_cash_position'] === -750.0;
        });
    }
}
