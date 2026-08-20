<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CommunicationLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPrivacyHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_service_redacts_sensitive_values_recursively(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app(AuditService::class)->log('privacy.test', null, [], [
            'password' => 'secret-password',
            'nested' => [
                'portal_activation_token' => 'secret-token',
                'safe' => 'visible-value',
            ],
        ]);

        $log = AuditLog::query()->where('action', 'privacy.test')->firstOrFail();

        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['nested']['portal_activation_token']);
        $this->assertSame('visible-value', $log->new_values['nested']['safe']);
    }

    public function test_communication_log_masks_recipient_in_admin_view(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'status' => true,
        ]);

        $permission = Permission::create([
            'name' => 'Communications Manage',
            'slug' => 'communications.manage',
            'group' => 'Communications',
        ]);
        $role = Role::create([
            'name' => 'Privacy Admin',
            'slug' => 'privacy-admin',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $admin->roles()->attach($role->id);

        CommunicationLog::factory()->create([
            'branch_id' => $branch->id,
            'recipient' => 'student@example.com',
            'channel' => 'email',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.communications.index'));

        $response->assertOk();
        $response->assertDontSee('student@example.com');
        $response->assertSee('s******@example.com');
    }
}
