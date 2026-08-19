<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_public_admission_and_enquiry_pages_load(): void
    {
        $this->get('/admission')->assertOk();
        $this->get('/enquiry')->assertOk();
    }

    public function test_public_jobs_and_digital_library_pages_load(): void
    {
        $this->get('/jobs')->assertOk();
        $this->get('/digital-library')->assertOk();
    }
}
