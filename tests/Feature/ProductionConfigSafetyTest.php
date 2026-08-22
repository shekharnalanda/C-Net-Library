<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionConfigSafetyTest extends TestCase
{
    public function test_application_defaults_to_india_timezone(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
    }

    public function test_production_example_documents_required_runtime_settings(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertIsString($example);
        $this->assertStringContainsString('APP_TIMEZONE=Asia/Kolkata', $example);
        $this->assertStringContainsString('SESSION_DRIVER=database', $example);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $example);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $example);
        $this->assertStringContainsString('SESSION_SAME_SITE=lax', $example);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $example);
        $this->assertStringContainsString('CACHE_STORE=database', $example);
    }

    public function test_session_config_defaults_are_hardened(): void
    {
        $sessionConfig = file_get_contents(config_path('session.php'));

        $this->assertIsString($sessionConfig);
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", $sessionConfig);
        $this->assertTrue((bool) config('session.encrypt'));
        $this->assertTrue((bool) config('session.secure'));
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertContains(strtolower((string) config('session.same_site')), ['lax', 'strict']);
    }
}
