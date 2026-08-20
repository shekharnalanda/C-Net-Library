<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseSmokeCommandTest extends TestCase
{
    public function test_release_smoke_command_is_registered_and_checks_critical_deployment_contracts(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ReleaseSmokeCheck.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("protected \$signature = 'release:smoke'", $source);
        $this->assertStringContainsString("Route::has(\$routeName)", $source);
        $this->assertStringContainsString("Artisan::call('schedule:list')", $source);
        $this->assertStringContainsString('memberships:expire-due', $source);
        $this->assertStringContainsString('memberships:activate-scheduled', $source);
        $this->assertStringContainsString("public_path('storage')", $source);
        $this->assertStringContainsString("storage_path('app/public')", $source);
        $this->assertStringContainsString("realpath(\$publicStorage)", $source);
        $this->assertStringContainsString("DB::select('SELECT 1')", $source);
    }
}
