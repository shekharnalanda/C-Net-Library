<?php

namespace Tests\Feature;

use Tests\TestCase;

class MigrationOrderSafetyTest extends TestCase
{
    public function test_high_risk_migrations_remain_dependency_ordered(): void
    {
        $migrations = [
            'payments' => '2026_08_20_000800_create_payments_table.php',
            'payrolls' => '2026_08_20_002400_create_payrolls_table.php',
            'book_copies' => '2026_08_20_003820_create_book_copies_table.php',
            'book_issues' => '2026_08_20_003830_create_book_issues_table.php',
            'expenses' => '2026_08_20_005000_create_expenses_table.php',
            'expense_unique' => '2026_08_20_005100_add_unique_transaction_ref_to_expenses_table.php',
            'payment_unique' => '2026_08_20_005200_add_unique_transaction_ref_to_payments_table.php',
            'runtime' => '2026_08_20_006000_create_runtime_support_tables.php',
            'expense_payroll_link' => '2026_08_20_006300_add_expense_adjustments_and_payroll_link.php',
            'payroll_unique' => '2026_08_20_006500_add_unique_transaction_ref_to_payrolls_table.php',
            'library_ledgers' => '2026_08_20_006700_create_library_reservations_and_charge_payments_tables.php',
            'receipt_integrity' => '2026_08_20_008000_add_receipt_integrity_fields.php',
        ];

        foreach ($migrations as $filename) {
            $this->assertFileExists(database_path('migrations/'.$filename));
        }

        $this->assertTrue(strcmp($migrations['expenses'], $migrations['expense_unique']) < 0);
        $this->assertTrue(strcmp($migrations['payments'], $migrations['payment_unique']) < 0);
        $this->assertTrue(strcmp($migrations['payrolls'], $migrations['expense_payroll_link']) < 0);
        $this->assertTrue(strcmp($migrations['expenses'], $migrations['expense_payroll_link']) < 0);
        $this->assertTrue(strcmp($migrations['book_copies'], $migrations['library_ledgers']) < 0);
        $this->assertTrue(strcmp($migrations['book_issues'], $migrations['library_ledgers']) < 0);
        $this->assertTrue(strcmp($migrations['payments'], $migrations['receipt_integrity']) < 0);
    }

    public function test_runtime_support_rollback_is_intentionally_non_destructive(): void
    {
        $path = database_path('migrations/2026_08_20_006000_create_runtime_support_tables.php');
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString('intentionally non-destructive', $contents);
        $this->assertStringNotContainsString("Schema::dropIfExists('sessions')", $contents);
        $this->assertStringNotContainsString("Schema::dropIfExists('jobs')", $contents);
    }
}
