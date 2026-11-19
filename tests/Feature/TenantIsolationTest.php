<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\PlatformUser;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * Tenant isolation as the product works today: users belong to one company (users.tenant_id), company models carry the
 * tenant scope, and the platform owner is a separate table and guard. (The old version of this file used Tenant::members()
 * and an is_platform_admin flag that no longer exist.)
 */
class TenantIsolationTest extends BillingTestCase
{
    #[Test]
    public function tenant_scoped_models_only_return_current_tenant_data(): void
    {
        $acme  = $this->makeTenant(slug: 'acme');
        $wayne = $this->makeTenant(slug: 'wayne');
        $ca = $this->makeClient($acme, 'Acme client');
        $cw = $this->makeClient($wayne, 'Wayne client');
        $this->makeInvoice($acme, $ca);
        $this->makeInvoice($wayne, $cw);
        $this->makeInvoice($wayne, $cw);

        Tenant::setCurrent($acme);
        $this->assertSame(['Acme client'], Client::pluck('name')->all());
        $this->assertSame(1, Invoice::count());

        Tenant::setCurrent($wayne);
        $this->assertSame(['Wayne client'], Client::pluck('name')->all());
        $this->assertSame(2, Invoice::count());

        Tenant::setCurrent(null); // jobs and commands have no company and see every row
        $this->assertSame(2, Client::count());
        $this->assertSame(3, Invoice::count());
    }

    #[Test]
    public function a_user_of_another_company_cannot_resolve_a_tenant(): void
    {
        $acme     = $this->makeTenant(slug: 'acme');
        $wayne    = $this->makeTenant(slug: 'wayne');
        $outsider = $this->makeUser($wayne, 'company_admin');

        $this->assertFalse($outsider->hasAccessToTenant($acme));
        $this->assertTrue($outsider->hasAccessToTenant($wayne));

        $this->actingAs($outsider)->get($this->onHost($acme, '/dashboard'))->assertForbidden();
    }

    #[Test]
    public function a_deactivated_user_has_no_access_even_to_their_own_company(): void
    {
        $acme = $this->makeTenant(slug: 'acme');
        $user = $this->makeUser($acme, 'finance');
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->assertFalse($user->fresh()->hasAccessToTenant($acme));
    }

    #[Test]
    public function the_platform_owner_is_not_a_company_user_and_the_console_is_closed_on_a_company_address(): void
    {
        $acme  = $this->makeTenant(slug: 'acme');
        $owner = PlatformUser::create(['name' => 'Owner', 'email' => 'owner@aiywah.test', 'password' => 'password', 'platform_role' => 'owner']);

        // on a company address the owner console does not exist...
        $this->actingAs($owner, 'platform')->get($this->onHost($acme, '/admin'))->assertNotFound();
        Tenant::setCurrent(null);

        // ...and the owner is not a company user at all: a different table and model, with no company
        $this->assertFalse($owner instanceof \App\Models\User);
        $this->assertNull($owner->getAttribute('tenant_id'));

        // a visitor with no company session is sent to sign in on a company address
        \Illuminate\Support\Facades\Auth::guard('platform')->logout();
        \Illuminate\Support\Facades\Auth::shouldUse('web');
        $this->get($this->onHost($acme, '/dashboard'))->assertRedirect();
    }

    #[Test]
    public function a_suspended_company_is_not_reachable(): void
    {
        $tenant = $this->makeTenant(slug: 'suspended');
        $tenant->forceFill(['status' => 'suspended'])->save();

        $this->assertFalse($tenant->fresh()->isActive());
        $this->get($this->onHost($tenant, '/login'))->assertNotFound();
    }
}
