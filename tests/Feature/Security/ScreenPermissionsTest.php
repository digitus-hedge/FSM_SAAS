<?php

namespace Tests\Feature\Security;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * The office screens that used to have no permission check (or a role-name check), now gated by the signed matrix
 * (docs: blueprint section 4, database/seeders/data/permission_matrix.csv):
 *   reading a page needs view level, changing something needs change level, and a GET that shows a create or edit form needs change level.
 * Plus scoped route binding: another company's id is a 404.
 */
class ScreenPermissionsTest extends BillingTestCase
{
    private const VIEW   = ['full', 'grantable', 'record_level', 'view_only'];
    private const CHANGE = ['full', 'grantable', 'record_level'];
    private const ROLES  = ['company_admin', 'operations_manager', 'front_desk', 'finance'];

    private Tenant $t;
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = $this->makeTenant(slug: 'sp');
        foreach (self::ROLES as $code) {
            $this->users[$code] = $this->makeUser($this->t, $code);
        }
    }

    private function level(string $key, string $role): string
    {
        static $m = null;
        if ($m === null) {
            $rows = array_map('str_getcsv', file(database_path('seeders/data/permission_matrix.csv')));
            $head = array_shift($rows);
            foreach ($rows as $row) {
                if (count(array_filter($row)) === 0) {
                    continue;
                }
                $r = array_combine($head, $row);
                $m[$r['permission_key']] = $r;
            }
        }

        return $m[$key][$role];
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> url, permission key, kind (page or form) */
    private function screens(): array
    {
        return [
            ['/admin/clients', 'client.manage', 'page'], ['/admin/clients/create', 'client.manage', 'form'],
            ['/admin/sites', 'site.manage', 'page'], ['/admin/sites/create', 'site.manage', 'form'],
            ['/admin/service-categories', 'category.manage', 'page'], ['/admin/service-categories/create', 'category.manage', 'form'],
            ['/admin/team-lead-categories', 'category.manage', 'page'],
            ['/admin/sla-policies', 'settings.manage', 'page'],
            ['/admin/tax-rates', 'settings.manage', 'page'], ['/admin/tax-rates/create', 'settings.manage', 'form'],
            ['/admin/hold-reasons', 'settings.manage', 'page'], ['/admin/hold-reasons/create', 'settings.manage', 'form'],
            ['/admin/expense-categories', 'settings.manage', 'page'], ['/admin/expense-categories/create', 'settings.manage', 'form'],
            ['/admin/tenant-holidays', 'settings.manage', 'page'], ['/admin/tenant-holidays/create', 'settings.manage', 'form'],
            ['/admin/business-hours', 'settings.manage', 'page'],
            ['/admin/branding', 'settings.manage', 'page'],
            ['/admin/usage', 'settings.manage', 'page'],
            ['/service-requests', 'sr.view', 'page'], ['/service-requests/export', 'sr.export', 'page'],
            ['/admin/vendors', 'vendor.manage', 'page'], ['/admin/vendors/create', 'vendor.manage', 'form'],
            ['/admin/terminology', 'vocabulary.manage', 'page'],
            ['/manage/users', 'user.manage', 'page'], ['/manage/users/create', 'user.manage', 'form'],
            ['/manage/roles', 'role.manage', 'page'], ['/manage/roles/create', 'role.manage', 'form'],
            ['/manage/audit', 'audit.view', 'page'],
            ['/admin/operations-queue', 'sr.qc', 'page'],
            ['/admin/checklists', 'form.manage', 'page'], ['/admin/checklists/create', 'form.manage', 'form'],   // B3
            ['/admin/approval-rules', 'workflow.manage', 'page'],                                              // B3
            ['/admin/reports', 'report.view', 'page'], ['/admin/summary-settings', 'notification.manage', 'page'],   // B8
            ['/admin/maintenance', 'pm.view', 'page'], ['/admin/maintenance/create', 'pm.manage', 'form'],     // B4
        ];
    }

    #[Test]
    public function every_gated_screen_follows_the_signed_matrix_for_each_role(): void
    {
        $wrong = [];
        foreach ($this->screens() as [$url, $key, $kind]) {
            foreach (self::ROLES as $role) {
                $allowed = in_array($this->level($key, $role), $kind === 'form' ? self::CHANGE : self::VIEW, true);
                $status  = $this->actingAs($this->users[$role])->get($this->onHost($this->t, $url))->getStatusCode();
                Tenant::setCurrent(null);

                if ($allowed && ($status === 403 || $status >= 500)) {
                    $wrong[] = "{$role} should open GET {$url} ({$key} = {$this->level($key, $role)}) but got {$status}";
                }
                if (! $allowed && $status !== 403) {
                    $wrong[] = "{$role} should be refused GET {$url} ({$key} = {$this->level($key, $role)}) but got {$status}";
                }
            }
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    #[Test]
    public function a_view_only_holder_opens_the_page_but_not_the_form_and_cannot_post(): void
    {
        // Finance holds client.manage at view_only
        $this->assertSame('view_only', $this->level('client.manage', 'finance'));
        $finance = $this->users['finance'];

        $this->actingAs($finance)->get($this->onHost($this->t, '/admin/clients'))->assertOk();
        $this->actingAs($finance)->get($this->onHost($this->t, '/admin/clients/create'))->assertForbidden();
        $this->actingAs($finance)->post($this->onHost($this->t, '/admin/clients'), ['name' => 'Sneaky'])->assertForbidden();
        $this->assertSame(0, \App\Models\Client::withoutGlobalScopes()->where('name', 'Sneaky')->count());
    }

    #[Test]
    public function the_writes_of_a_gated_screen_are_refused_to_roles_without_the_permission(): void
    {
        $desk = $this->actingAs($this->users['front_desk']); // no settings.manage, no vendor.manage

        $desk->post($this->onHost($this->t, '/admin/hold-reasons'), ['name' => 'X'])->assertForbidden();
        $desk->post($this->onHost($this->t, '/admin/vendors'), ['name' => 'X'])->assertForbidden();
        $desk->put($this->onHost($this->t, '/admin/business-hours'), [])->assertForbidden();
        $desk->post($this->onHost($this->t, '/manage/roles'), ['name' => 'X'])->assertForbidden();
        $rate = \App\Models\TaxRate::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'name' => 'VAT', 'rate_percent' => 5, 'is_active' => true]);
        $desk->delete($this->onHost($this->t, '/admin/tax-rates/' . $rate->id))->assertForbidden();
        $this->assertNotNull(\App\Models\TaxRate::withoutGlobalScopes()->find($rate->id));
    }

    #[Test]
    public function the_front_desk_keeps_what_the_matrix_gives_it(): void
    {
        $desk = $this->users['front_desk'];

        $this->actingAs($desk)->get($this->onHost($this->t, '/admin/clients/create'))->assertOk();      // client.manage full
        $this->actingAs($desk)->get($this->onHost($this->t, '/admin/sites/create'))->assertOk();        // site.manage full
        $this->actingAs($desk)->get($this->onHost($this->t, '/manage/users'))->assertOk();              // user.manage record_level (client users)
    }

    // ───────────── invoices, payments, expenses: permissions, not role names ─────────────

    private function pendingInvoice(): Invoice
    {
        return $this->makeInvoice($this->t, $this->makeClient($this->t, 'Inv Client'), ['status' => 'pending_approval', 'submitted_by_user_id' => $this->users['finance']->id]);
    }

    #[Test]
    public function approve_and_send_back_need_invoice_approve_the_operations_manager_and_the_admin_not_finance(): void
    {
        $this->assertSame('none', $this->level('invoice.approve', 'finance'));
        $inv = $this->pendingInvoice();

        $this->actingAs($this->users['finance'])->post(route('admin.invoices.approve', $inv))->assertForbidden();
        $this->actingAs($this->users['finance'])->post(route('admin.invoices.send-back', $inv), ['reason' => 'Check it'])->assertForbidden();
        $this->assertSame('pending_approval', $inv->fresh()->status);

        $this->actingAs($this->users['operations_manager'])->post(route('admin.invoices.send-back', $inv), ['reason' => 'Check it'])->assertRedirect();
        $this->assertSame('draft', $inv->fresh()->status);

        Invoice::whereKey($inv->id)->update(['status' => 'pending_approval']); // the copy in memory still says pending_approval, so save through the query
        $this->actingAs($this->users['operations_manager'])->post(route('admin.invoices.approve', $inv))->assertRedirect();
        $this->assertSame('approved', $inv->fresh()->status);
    }

    #[Test]
    public function recording_and_reversing_payments_need_payment_record_and_undoing_a_write_off_is_for_the_admin_only(): void
    {
        $inv = $this->makeInvoice($this->t, $this->makeClient($this->t, 'Pay Client'), ['status' => 'sent', 'total' => 100, 'balance' => 100]);
        $pay = ['amount' => 10, 'received_at' => '2026-11-19', 'method' => 'cash'];

        $this->actingAs($this->users['operations_manager'])->post(route('admin.invoices.payment', $inv), $pay)->assertForbidden();
        $this->actingAs($this->users['front_desk'])->post(route('admin.invoices.payment', $inv), $pay)->assertForbidden();
        $this->actingAs($this->users['finance'])->post(route('admin.invoices.payment', $inv), $pay)->assertRedirect();

        // Finance writes the balance off, but only the Company Admin can undo it
        $this->actingAs($this->users['finance'])->post(route('admin.invoices.write-off', $inv), ['reason' => 'Small balance'])->assertRedirect();
        $this->assertSame('written_off', $inv->fresh()->status);
        $this->actingAs($this->users['finance'])->post(route('admin.invoices.undo-write-off', $inv))->assertForbidden();
        $this->actingAs($this->users['operations_manager'])->post(route('admin.invoices.undo-write-off', $inv))->assertForbidden();
        $this->actingAs($this->users['company_admin'])->post(route('admin.invoices.undo-write-off', $inv))->assertRedirect();
        $this->assertNotSame('written_off', $inv->fresh()->status);
    }

    #[Test]
    public function the_operations_manager_can_open_an_invoice_but_not_change_it_and_front_desk_cannot_open_it(): void
    {
        $inv = $this->makeInvoice($this->t, $this->makeClient($this->t, 'View Client'), ['status' => 'draft']);

        $this->actingAs($this->users['operations_manager'])->get(route('admin.invoices.show', $inv))->assertOk();
        $this->actingAs($this->users['operations_manager'])->get(route('admin.invoices.edit', $inv))->assertForbidden();
        $this->actingAs($this->users['operations_manager'])->post(route('admin.invoices.void', $inv), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->users['front_desk'])->get(route('admin.invoices.show', $inv))->assertForbidden();
    }

    private function expenseOf(Tenant $t): int
    {
        return DB::table('service_request_expenses')->insertGetId([
            'tenant_id' => $t->id, 'service_request_id' => 1, 'logged_by_user_id' => 1, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'expense_category_id' => 1, 'description' => 'Part', 'quantity' => 1, 'unit_rate' => 10, 'total_amount' => 10,
            'is_billable_to_client' => 1, 'approval_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function expense_approval_needs_expense_approve_admin_operations_manager_and_finance(): void
    {
        foreach (['company_admin' => true, 'operations_manager' => true, 'finance' => true, 'front_desk' => false] as $role => $may) {
            $id = $this->expenseOf($this->t);
            $res = $this->actingAs($this->users[$role])->post(route('admin.expenses-review.approve', $id), ['is_billable' => 1]);

            $may ? $this->assertNotSame(403, $res->getStatusCode(), $role) : $res->assertForbidden();
        }
        $this->actingAs($this->users['front_desk'])->get(route('admin.expenses-review.index'))->assertForbidden();
        $this->actingAs($this->users['finance'])->get(route('admin.expenses-review.index'))->assertOk();
    }

    // ───────────── scoped route binding ─────────────

    #[Test]
    public function another_companys_expense_user_and_role_are_not_found(): void
    {
        $b = $this->makeTenant(slug: 'otherco');
        $expenseB = $this->expenseOf($b);
        $userB = $this->makeUser($b, 'finance');
        $roleB = \App\Models\Role::where('tenant_id', $b->id)->where('code', 'finance')->first();
        $admin = $this->users['company_admin'];

        $this->actingAs($admin)->post(route('admin.expenses-review.approve', $expenseB), ['is_billable' => 1])->assertNotFound();
        $this->actingAs($admin)->post(route('admin.expenses-review.reject', $expenseB), ['reason' => 'x'])->assertNotFound();
        $this->assertSame('pending', DB::table('service_request_expenses')->where('id', $expenseB)->value('approval_status'));

        $this->actingAs($admin)->get(route('tenant.users.edit', $userB))->assertNotFound();
        $this->actingAs($admin)->put(route('tenant.users.update', $userB), ['name' => 'Hijack'])->assertNotFound();
        $this->actingAs($admin)->post(route('tenant.users.deactivate', $userB))->assertNotFound();
        $this->actingAs($admin)->get(route('tenant.roles.edit', $roleB))->assertNotFound();
        $this->actingAs($admin)->delete(route('tenant.roles.destroy', $roleB))->assertNotFound();
        $this->assertNull($userB->fresh()->deactivated_at);
        $this->assertNotNull(\App\Models\Role::find($roleB->id));
    }

    #[Test]
    public function a_team_lead_cannot_decide_another_companys_geofence_override_or_conflict(): void
    {
        $b = $this->makeTenant(slug: 'otherco2');
        $lead = $this->makeUser($this->t, 'team_lead');
        $overrideB = DB::table('geofence_override_requests')->insertGetId([
            'tenant_id' => $b->id, 'service_request_id' => 1, 'technician_user_id' => 1, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reason' => 'x', 'requested_at_device' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $logB = DB::table('field_event_log')->insertGetId([
            'tenant_id' => $b->id, 'user_id' => 1, 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'event_type' => 'punch_in',
            'recorded_at_device' => now(), 'result' => 'conflict_rejected', 'payload_hash' => str_repeat('a', 64), 'payload_json' => '{}',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($lead)->post(route('technician.team.overrides.approve', $overrideB))->assertNotFound();
        $this->actingAs($lead)->post(route('technician.team.overrides.reject', $overrideB), ['decision_note' => 'x'])->assertNotFound();
        $this->actingAs($lead)->post(route('technician.team.conflicts.reviewed', $logB))->assertNotFound();
        $this->assertSame('pending', DB::table('geofence_override_requests')->where('id', $overrideB)->value('status'));
    }

    #[Test]
    public function a_technician_from_another_company_cannot_be_assigned_to_a_job(): void
    {
        $b = $this->makeTenant(slug: 'otherco3');
        $techB = $this->makeUser($b, 'field_technician');
        $sr = $this->makeServiceRequest($this->t, $this->makeClient($this->t, 'Job Client'), 'approved');

        $this->actingAs($this->users['company_admin'])
            ->post(route('service-requests.assign', $sr), ['technician_id' => $techB->id, 'arrival_time' => now()->addDay()->toDateTimeString()])
            ->assertSessionHasErrors('technician_id');
        $this->assertNull(\App\Models\ServiceRequest::withoutGlobalScopes()->find($sr->id)->assigned_tech_user_id);
    }

    #[Test]
    public function the_scoped_models_hide_other_companies_rows(): void
    {
        $b = $this->makeTenant(slug: 'otherco4');
        $mine = $this->expenseOf($this->t);
        $theirs = $this->expenseOf($b);

        Tenant::setCurrent($this->t);
        $ids = \App\Models\ServiceRequestExpense::pluck('id')->all();
        Tenant::setCurrent(null);

        $this->assertContains($mine, $ids);
        $this->assertNotContains($theirs, $ids);
    }
}
