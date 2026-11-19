<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * Who may add, edit, turn off or reset which users (blueprint section 4):
 * "Only the Company Admin adds staff and technicians. A Client Admin adds users for their own company, and front desk adds
 *  client users when a client has no Client Admin. Operations managers do not add users."
 * Plus: nobody but a Company Admin creates or edits a staff user or gives anyone the Company Admin role, and nobody changes their own role.
 */
class UserAdminScopeTest extends BillingTestCase
{
    private Tenant $t;
    private Client $acme;
    private Client $beta;
    private array $u = [];
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->t    = $this->makeTenant(slug: 'ua');
        $this->acme = $this->makeClient($this->t, 'Acme Co');
        $this->beta = $this->makeClient($this->t, 'Beta Co');

        foreach (['company_admin', 'operations_manager', 'finance', 'front_desk'] as $code) {
            $this->u[$code] = $this->makeUser($this->t, $code);
        }
        // A Client Admin is a client role with the code client_admin; it holds the same user.manage right as client_user.
        $clientUserRole = Role::where('tenant_id', $this->t->id)->where('code', 'client_user')->first();
        $this->clientAdminRole = Role::create(['tenant_id' => $this->t->id, 'code' => 'client_admin', 'name' => 'Client Admin', 'is_standard' => false]);
        foreach (RolePermission::where('role_id', $clientUserRole->id)->get() as $rp) {
            RolePermission::create(['tenant_id' => $this->t->id, 'role_id' => $this->clientAdminRole->id, 'permission_id' => $rp->permission_id, 'level' => $rp->level]);
        }
        $this->u['client_admin'] = $this->clientUser($this->acme, $this->clientAdminRole->id, 'ca@acme.test');
    }

    private Role $clientAdminRole;

    private function role(string $code): Role
    {
        return Role::where('tenant_id', $this->t->id)->where('code', $code)->firstOrFail();
    }

    private function clientUser(Client $client, int $roleId, string $email): User
    {
        return User::create(['tenant_id' => $this->t->id, 'role_id' => $roleId, 'client_id' => $client->id, 'name' => 'Client ' . $email,
            'email' => $email, 'phone' => '+97150' . (++$this->seq) . '000', 'password' => 'password']);
    }

    private function payload(string $roleCode, array $extra = []): array
    {
        $n = ++$this->seq;

        return array_merge(['name' => "New Person {$n}", 'email' => "new{$n}@ua.test", 'phone' => '+97155' . $n . '999', 'role_id' => $this->role($roleCode)->id], $extra);
    }

    private function url(string $path = ''): string
    {
        return $this->onHost($this->t, '/manage/users' . $path);
    }

    private function as(string $who)
    {
        return $this->actingAs($this->u[$who]);
    }

    // ───────────── the rule: only a Company Admin creates or edits staff, or gives the Company Admin role ─────────────

    #[Test]
    public function only_a_company_admin_can_create_a_staff_user(): void
    {
        foreach (['operations_manager', 'finance', 'front_desk', 'client_admin'] as $who) {
            foreach (['finance', 'operations_manager', 'front_desk', 'field_technician', 'team_lead'] as $staffRole) {
                $this->as($who)->post($this->url(), $this->payload($staffRole))->assertForbidden();
            }
        }
        $this->assertSame(0, User::where('email', 'like', 'new%@ua.test')->count());

        $this->as('company_admin')->post($this->url(), $this->payload('finance'))->assertRedirect();
        $this->assertSame(1, User::where('email', 'like', 'new%@ua.test')->count());
    }

    #[Test]
    public function nobody_but_a_company_admin_can_give_anyone_the_company_admin_role(): void
    {
        foreach (['operations_manager', 'finance', 'front_desk', 'client_admin'] as $who) {
            $this->as($who)->post($this->url(), $this->payload('company_admin'))->assertForbidden();
            // nor by promoting a user they can manage
            $victim = $this->clientUser($this->beta, $this->role('client_user')->id, "v{$who}@beta.test");
            $this->as($who)->put($this->url('/' . $victim->id), $this->payload('company_admin', ['client_id' => $this->beta->id]))->assertStatus(403);
            $this->assertNotSame($this->role('company_admin')->id, $victim->fresh()->role_id);
        }

        $this->as('company_admin')->post($this->url(), $this->payload('company_admin'))->assertRedirect();
        $this->assertSame(2, User::where('role_id', $this->role('company_admin')->id)->count());
    }

    #[Test]
    public function only_a_company_admin_can_edit_turn_off_or_reset_a_staff_user(): void
    {
        $staff = $this->u['finance'];

        foreach (['operations_manager', 'front_desk', 'client_admin'] as $who) {
            $this->as($who)->get($this->url("/{$staff->id}/edit"))->assertForbidden();
            $this->as($who)->put($this->url("/{$staff->id}"), $this->payload('finance'))->assertForbidden();
            $this->as($who)->post($this->url("/{$staff->id}/deactivate"))->assertForbidden();
            $this->as($who)->post($this->url("/{$staff->id}/temporary-password"))->assertForbidden();
            $this->as($who)->post($this->url("/{$staff->id}/unlock"))->assertForbidden();
            $this->as($who)->get($this->url("/{$staff->id}/devices"))->assertForbidden();
        }
        $this->assertNull($staff->fresh()->deactivated_at);

        $this->as('company_admin')->get($this->url("/{$staff->id}/edit"))->assertOk();
        $this->as('company_admin')->post($this->url("/{$staff->id}/deactivate"))->assertRedirect();
        $this->assertNotNull($staff->fresh()->deactivated_at);
    }

    #[Test]
    public function nobody_can_change_their_own_role_not_even_a_company_admin(): void
    {
        $other = $this->makeUser($this->t, 'company_admin', 'second.admin@ua.test'); // so the last-admin rule is not what stops it
        $admin = $this->u['company_admin'];

        $this->as('company_admin')->put($this->url("/{$admin->id}"), array_merge($this->payload('finance'), ['name' => $admin->name, 'email' => $admin->email, 'phone' => $admin->phone ?? '+971500000001']))
            ->assertSessionHasErrors(['role_id' => 'You cannot change your own role.']);
        $this->assertSame($this->role('company_admin')->id, $admin->fresh()->role_id);

        // a Client Admin cannot move themselves to another client role either
        $ca = $this->u['client_admin'];
        $this->as('client_admin')->put($this->url("/{$ca->id}"), $this->payload('client_user', ['name' => $ca->name, 'email' => $ca->email, 'phone' => $ca->phone, 'client_id' => $this->acme->id]))
            ->assertSessionHasErrors(['role_id' => 'You cannot change your own role.']);
        $this->assertSame($this->clientAdminRole->id, $ca->fresh()->role_id);

        // saving yourself with the same role is fine
        $this->as('company_admin')->put($this->url("/{$admin->id}"), ['name' => 'Renamed Admin', 'email' => $admin->email, 'phone' => $admin->phone ?? '+971500000001', 'role_id' => $admin->role_id])
            ->assertRedirect();
        $this->assertSame('Renamed Admin', $admin->fresh()->name);
    }

    // ───────────── the Front Desk ─────────────

    #[Test]
    public function the_front_desk_adds_client_users_only_and_only_for_a_client_without_a_client_admin(): void
    {
        // Acme has a Client Admin (ca@acme.test); Beta has none
        $this->as('front_desk')->post($this->url(), $this->payload('client_user', ['client_id' => $this->acme->id]))->assertForbidden();
        $this->as('front_desk')->post($this->url(), $this->payload('client_user', ['client_id' => $this->beta->id]))->assertRedirect();
        $this->as('front_desk')->post($this->url(), $this->payload('client_admin', ['client_id' => $this->beta->id]))->assertRedirect(); // the first admin of a client

        $this->assertSame(2, User::where('client_id', $this->beta->id)->count());
        $this->assertSame(1, User::where('client_id', $this->acme->id)->count()); // only the Client Admin that was already there
    }

    #[Test]
    public function the_front_desk_sees_and_edits_only_client_users_of_clients_without_a_client_admin(): void
    {
        $betaUser = $this->clientUser($this->beta, $this->role('client_user')->id, 'bu@beta.test');
        $acmeUser = $this->clientUser($this->acme, $this->role('client_user')->id, 'au@acme.test');

        $list = $this->as('front_desk')->get($this->url())->assertOk();
        $list->assertSee('bu@beta.test')->assertDontSee('au@acme.test')->assertDontSee($this->u['finance']->email)->assertDontSee($this->u['company_admin']->email);

        $this->as('front_desk')->get($this->url("/{$betaUser->id}/edit"))->assertOk();
        $this->as('front_desk')->post($this->url("/{$betaUser->id}/temporary-password"))->assertRedirect();
        $this->as('front_desk')->get($this->url("/{$acmeUser->id}/edit"))->assertForbidden();      // Acme has a Client Admin now
        $this->as('front_desk')->post($this->url("/{$acmeUser->id}/deactivate"))->assertForbidden();
        $this->as('front_desk')->get($this->url("/{$this->u['company_admin']->id}/edit"))->assertForbidden();
    }

    #[Test]
    public function the_front_desks_form_offers_only_client_roles_and_the_import_is_closed_to_them(): void
    {
        $form = $this->as('front_desk')->get($this->url('/create'))->assertOk();
        $form->assertSee('Client User')->assertDontSee('Company Admin')->assertDontSee('Operations Manager')->assertDontSee('Finance');

        $this->as('front_desk')->get($this->url('/import'))->assertForbidden();
        $this->as('front_desk')->get($this->url())->assertDontSee(route('tenant.users.import.show', [], false));
        $this->as('company_admin')->get($this->url('/import'))->assertOk();
    }

    // ───────────── a Client Admin ─────────────

    #[Test]
    public function a_client_admin_manages_only_the_client_users_of_their_own_company(): void
    {
        $mine  = $this->clientUser($this->acme, $this->role('client_user')->id, 'mine@acme.test');
        $other = $this->clientUser($this->beta, $this->role('client_user')->id, 'other@beta.test');

        $this->as('client_admin')->post($this->url(), $this->payload('client_user', ['client_id' => $this->acme->id]))->assertRedirect();
        $this->as('client_admin')->post($this->url(), $this->payload('client_user', ['client_id' => $this->beta->id]))->assertForbidden();
        $this->as('client_admin')->post($this->url(), $this->payload('finance'))->assertForbidden();

        $this->as('client_admin')->get($this->url())->assertOk()->assertSee('mine@acme.test')->assertDontSee('other@beta.test')->assertDontSee($this->u['finance']->email);
        $this->as('client_admin')->get($this->url("/{$mine->id}/edit"))->assertOk();
        $this->as('client_admin')->get($this->url("/{$other->id}/edit"))->assertForbidden();
        $this->as('client_admin')->post($this->url("/{$other->id}/deactivate"))->assertForbidden();

        // moving a user of their own company to another client is refused
        $this->as('client_admin')->put($this->url("/{$mine->id}"), $this->payload('client_user', ['name' => $mine->name, 'email' => $mine->email, 'phone' => $mine->phone, 'client_id' => $this->beta->id]))->assertForbidden();
        $this->assertSame($this->acme->id, $mine->fresh()->client_id);
    }

    #[Test]
    public function a_plain_client_user_gets_nothing(): void
    {
        $plain = $this->clientUser($this->acme, $this->role('client_user')->id, 'plain@acme.test');

        $this->actingAs($plain)->get($this->url())->assertForbidden();
        $this->actingAs($plain)->post($this->url(), $this->payload('client_user', ['client_id' => $this->acme->id]))->assertForbidden();
    }

    #[Test]
    public function the_operations_manager_and_finance_never_reach_user_management(): void
    {
        foreach (['operations_manager', 'finance'] as $who) {
            $this->as($who)->get($this->url())->assertForbidden();
            $this->as($who)->get($this->url('/create'))->assertForbidden();
        }
    }

    #[Test]
    public function personal_extra_permissions_are_the_company_admins_to_give(): void
    {
        $beta = $this->clientUser($this->beta, $this->role('client_user')->id, 'extra@beta.test');
        $before = \App\Models\UserPermissionOverride::count();

        // a Front Desk user tries to tick personal extra permissions while saving a client user
        $this->as('front_desk')->put($this->url("/{$beta->id}"), $this->payload('client_user', [
            'name' => $beta->name, 'email' => $beta->email, 'phone' => $beta->phone, 'client_id' => $this->beta->id, 'extra_perms' => ['sr.approve'],
        ]))->assertRedirect();

        $this->assertSame($before, \App\Models\UserPermissionOverride::count());
    }
}
