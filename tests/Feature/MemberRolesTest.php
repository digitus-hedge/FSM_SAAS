<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MemberRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Tenant::setCurrent(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    #[Test]
    public function role_scope_is_isolated_between_tenants(): void
    {
        $acme  = Tenant::create(['slug' => 'acme',  'name' => 'Acme',  'status' => 'active']);
        $wayne = Tenant::create(['slug' => 'wayne', 'name' => 'Wayne', 'status' => 'active']);

        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        // Admin in Acme, Finance in Wayne — same user, different roles per tenant.
        app(PermissionRegistrar::class)->setPermissionsTeamId($acme->id);
        $user->assignRole('company_admin');

        app(PermissionRegistrar::class)->setPermissionsTeamId($wayne->id);
        $user->assignRole('finance');

        // Check from Acme's context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($acme->id);
        $this->assertTrue($user->hasRole('company_admin'));
        $this->assertFalse($user->hasRole('finance'));

        // Check from Wayne's context.
        app(PermissionRegistrar::class)->setPermissionsTeamId($wayne->id);
        $this->assertTrue($user->hasRole('finance'));
        $this->assertFalse($user->hasRole('company_admin'));
    }

    #[Test]
    public function technician_cannot_approve_service_requests(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'name' => 'Acme', 'status' => 'active']);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole('technician');

        $this->assertTrue($user->hasPermissionTo('tech.punch_in'));
        $this->assertFalse($user->hasPermissionTo('sr.approve'));
    }

    #[Test]
    public function company_admin_has_every_permission(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'name' => 'Acme', 'status' => 'active']);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole('company_admin');

        foreach (PermissionSeeder::KEYS as $key) {
            $this->assertTrue($user->hasPermissionTo($key), "Admin should have {$key}");
        }
    }
}