<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeSupportTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_runtime_support_tables_exist_after_migrations(): void
    {
        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected runtime table [{$table}] to exist.");
        }
    }
}
