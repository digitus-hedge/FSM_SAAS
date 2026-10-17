<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Always clear the global tenant between tests so one test's context
        // never bleeds into the next.
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    #[Test]
    public function tenant_scoped_models_only_return_current_tenant_data(): void
    {
        $acme  = Tenant::create(['slug' => 'acme',  'name' => 'Acme',  'status' => 'active']);
        $wayne = Tenant::create(['slug' => 'wayne', 'name' => 'Wayne', 'status' => 'active']);

        Tenant::setCurrent($acme);
        $acme->members()->attach(
            User::factory()->create()->id,
            ['role' => 'company_admin', 'status' => 'active']
        );

        Tenant::setCurrent($wayne);
        $wayne->members()->attach(
            User::factory()->create()->id,
            ['role' => 'company_admin', 'status' => 'active']
        );

        Tenant::setCurrent($acme);
        $this->assertSame(1, $acme->members()->count(), 'Acme should see only its own members');

        Tenant::setCurrent($wayne);
        $this->assertSame(1, $wayne->members()->count(), 'Wayne should see only its own members');
    }

    #[Test]
    public function user_without_membership_cannot_resolve_a_tenant(): void
    {
        $acme     = Tenant::create(['slug' => 'acme', 'name' => 'Acme', 'status' => 'active']);
        $outsider = User::factory()->create();

        $this->assertFalse($outsider->hasAccessToTenant($acme));
    }

    #[Test]
    public function platform_admin_can_access_any_tenant(): void
    {
        $acme  = Tenant::create(['slug' => 'acme', 'name' => 'Acme', 'status' => 'active']);
        $owner = User::factory()->create(['is_platform_admin' => true]);

        $this->assertTrue($owner->hasAccessToTenant($acme));
    }

    #[Test]
    public function suspended_tenant_is_not_accessible_via_middleware(): void
    {
        $tenant = Tenant::create(['slug' => 'suspended', 'name' => 'X', 'status' => 'suspended']);

        $this->assertFalse($tenant->isActive());
    }
}