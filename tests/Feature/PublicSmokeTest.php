<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads_and_exposes_core_navigation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('Admin Login')
            ->assertSee('Student Login')
            ->assertSee('Online Admission');
    }

    public function test_login_page_loads_for_student_admin_and_staff_portal(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Portal Login')
            ->assertSee('Student')
            ->assertSee('Admin');
    }

    public function test_public_admission_and_enquiry_pages_load(): void
    {
        $this->get('/admission')->assertOk()->assertSee('Admission Application')->assertSee('Locker');
        $this->get('/enquiry')->assertOk();
    }

    public function test_public_jobs_and_digital_library_pages_load(): void
    {
        $this->get('/jobs')->assertOk();
        $this->get('/digital-library')->assertOk();
    }
}
