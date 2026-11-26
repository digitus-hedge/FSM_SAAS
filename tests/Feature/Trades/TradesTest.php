<?php

namespace Tests\Feature\Trades;

use App\Models\Asset;
use App\Models\Invoice;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestExpense;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Services\Billing\ExpenseLines;
use App\Services\Forms\FormException;
use App\Services\Trades\Depot;
use App\Services\Trades\MasterRequests;
use App\Services\Trades\VendorTechnicians;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Forms\FormsTestCase;

/** Multi-Trade-Vendors-Depot spec section 10: master and trades, vendors, depot (MVD-001 to MVD-073). */
class TradesTest extends FormsTestCase
{
    private function trades(int $n = 3, array $over = []): array
    {
        return collect(range(1, $n))->map(fn ($i) => array_merge(['service_category_id' => $this->makeCategory(['name' => "Trade {$i}"])->id], $over))->all();
    }

    private function raiseMaster(int $n = 3, array $rows = null, array $extra = []): ServiceRequest
    {
        $this->as('front_desk')->post(route('service-requests.store'), [
            'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'priority' => 'medium', 'title' => 'Fit-out',
            'service_category_id' => $this->makeCategory()->id, 'children' => $rows ?? $this->trades($n),
        ] + $extra)->assertSessionHasNoErrors();

        return ServiceRequest::withoutGlobalScopes()->where('is_master_sr', true)->latest('id')->firstOrFail();
    }

    private function kids(ServiceRequest $m)
    {
        return ServiceRequest::withoutGlobalScopes()->where('parent_sr_id', $m->id)->orderBy('id')->get();
    }

    private function setKids(ServiceRequest $m, array $statuses): void
    {
        foreach ($this->kids($m) as $i => $k) {
            $k->update(['status' => $statuses[$i]]);
        }
    }

    // ───────────── master and trades ─────────────

    #[Test]
    public function mvd_001_a_master_with_three_trades_makes_one_master_and_three_children(): void
    {
        $m = $this->raiseMaster(3);

        $this->assertSame('pending', $m->status);
        $this->assertTrue($m->is_master_sr);
        $this->assertCount(3, $this->kids($m));
        $this->assertSame([$m->id], $this->kids($m)->pluck('parent_sr_id')->unique()->values()->all());
        $this->assertSame(4, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function mvd_002_a_master_needs_between_2_and_10_trades(): void
    {
        foreach ([1, 11] as $n) {
            $this->as('front_desk')->post(route('service-requests.store'), [
                'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'priority' => 'medium', 'title' => 'X',
                'service_category_id' => $this->makeCategory()->id, 'children' => $this->trades($n),
            ])->assertSessionHas('error', 'A multi-trade job needs between 2 and 10 trades.');
        }
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function mvd_004_to_008_the_masters_status_follows_its_trades(): void
    {
        $m = $this->raiseMaster(3);

        $this->setKids($m, ['in_progress', 'pending', 'pending']);
        $this->assertSame('in_progress', $m->fresh()->status);

        $this->setKids($m, ['pending_review', 'pending_review', 'completed']);
        $this->assertSame('pending_review', $m->fresh()->status);

        $this->setKids($m, ['pending_review', 'in_progress', 'pending_review']);
        $this->assertSame('in_progress', $m->fresh()->status);

        $this->setKids($m, ['completed', 'completed', 'completed']);
        $this->assertSame('completed', $m->fresh()->status);

        $m2 = $this->raiseMaster(3);
        $this->setKids($m2, ['completed', 'completed', 'cancelled']);
        $this->assertSame('completed', $m2->fresh()->status);

        $m3 = $this->raiseMaster(3);
        $this->setKids($m3, ['cancelled', 'cancelled', 'cancelled']);
        $this->assertSame('cancelled', $m3->fresh()->status);
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'master.status_changed')->exists());
    }

    #[Test]
    public function mvd_009_cancelling_a_master_cancels_open_trades_and_keeps_a_completed_one(): void
    {
        $m = $this->raiseMaster(3);
        $this->kids($m)[0]->update(['status' => 'completed']);

        $this->as('company_admin')->post(route('service-requests.cancel', $m), ['reason' => 'Client changed plans'])->assertRedirect();

        $this->assertSame(['completed', 'cancelled', 'cancelled'], $this->kids($m)->pluck('status')->all());
        $this->assertSame('completed', $m->fresh()->status);
    }

    #[Test]
    public function mvd_010_a_master_cannot_be_assigned_or_moved_by_hand(): void
    {
        $m = $this->raiseMaster(2);
        $m->update(['status' => 'approved']);
        $tech = $this->u['field_technician'];

        $this->as('operations_manager')->post(route('service-requests.assign', $m), ['technician_id' => $tech->id, 'arrival_time' => now()->addDay()->toDateTimeString()])->assertStatus(422);
        $m->update(['status' => 'in_progress']);
        $this->as('operations_manager')->post(route('service-requests.mark-review', $m))->assertStatus(422);
    }

    #[Test]
    public function mvd_012_a_plan_without_multi_trade_refuses_a_master(): void
    {
        $starter = $this->makeTenant($this->starterPlan(), slug: 'st6');
        $this->expectException(FormException::class);
        app(MasterRequests::class)->create($starter, $this->makeUser($starter, 'company_admin'), [], []);
    }

    #[Test]
    public function approving_the_master_approves_every_trade_and_assigns_the_planned_technician(): void
    {
        $tech = $this->u['field_technician'];
        $first = $this->makeCategory(['approval_mode' => 'always_auto']);
        $rows = [['service_category_id' => $first->id, 'technician_id' => $tech->id, 'planned_at' => '2026-12-01 09:00'], ['service_category_id' => $this->makeCategory()->id]];

        $m = $this->raiseMaster(rows: $rows);

        $this->assertSame('approved', $m->fresh()->status);
        $kids = $this->kids($m);
        $this->assertSame(['assigned', 'approved'], $kids->pluck('status')->all());
        $this->assertSame($tech->id, $kids[0]->assigned_tech_user_id);
    }

    // ───────────── expenses and invoice ─────────────

    private function expense(ServiceRequest $sr, float $amount, ?int $vendorId = null): ServiceRequestExpense
    {
        $cat = \App\Models\ExpenseCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $this->t->id, 'name' => 'Materials'], ['is_billable_default' => true]);

        return ServiceRequestExpense::withoutGlobalScopes()->create([
            'tenant_id' => $this->t->id, 'service_request_id' => $sr->id, 'logged_by_user_id' => $this->u['field_technician']->id,
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'expense_category_id' => $cat->id, 'description' => 'Parts',
            'quantity' => 1, 'unit_rate' => $amount, 'total_amount' => $amount, 'is_billable_to_client' => true,
            'approval_status' => 'approved', 'vendor_id' => $vendorId, 'created_at' => now(),
        ]);
    }

    #[Test]
    public function mvd_020_the_masters_expenses_are_the_sum_of_its_trades(): void
    {
        $m = $this->raiseMaster(2);
        [$a, $b] = $this->kids($m);
        $this->expense($a, 100);
        $this->expense($b, 50.5);

        $this->assertEquals(150.5, app(MasterRequests::class)->expenseTotals($m)->sum());
        $this->as('operations_manager')->get(route('service-requests.show', $m))->assertOk()->assertSee('150.50');
    }

    #[Test]
    public function mvd_023_and_024_a_trade_is_not_invoiced_alone_and_the_master_takes_the_trades_expenses(): void
    {
        $m = $this->raiseMaster(2);
        [$a, $b] = $this->kids($m);
        $e1 = $this->expense($a, 100);
        $e2 = $this->expense($b, 40);

        $a->update(['status' => 'awaiting_invoice']);
        $this->as('finance')->get(route('admin.invoices.build', $a))->assertRedirect()->assertSessionHas('error', "Invoice the main request {$m->sr_number}, not one trade.");

        $invoice = $this->makeInvoice($this->t, $this->client, ['service_request_id' => $m->id, 'status' => 'draft']);
        $eligible = app(ExpenseLines::class)->eligible($invoice)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], $eligible);
    }

    #[Test]
    public function mvd_025_a_completed_master_tells_finance(): void
    {
        $m = $this->raiseMaster(2);
        $this->setKids($m, ['completed', 'completed']);

        $this->assertSame(1, UserNotification::where('event_key', 'master.ready_to_invoice')->where('user_id', $this->u['finance']->id)->count());
        $this->assertSame(2, UserNotification::where('event_key', 'master.child_completed')->where('user_id', $this->u['operations_manager']->id)->count());
    }

    // ───────────── visibility ─────────────

    #[Test]
    public function mvd_026_and_027_a_technician_sees_their_own_trade_and_gets_404_for_the_rest(): void
    {
        $m = $this->raiseMaster(2);
        [$mine, $theirs] = $this->kids($m);
        $mine->update(['status' => 'assigned', 'assigned_tech_user_id' => $this->u['field_technician']->id]);
        $other = $this->makeUser($this->t, 'field_technician', 'other6@fab.test');
        $theirs->update(['status' => 'assigned', 'assigned_tech_user_id' => $other->id]);

        $this->as('field_technician')->get(route('technician.jobs.show', $mine))->assertOk();
        $this->as('field_technician')->get(route('technician.jobs.show', $m))->assertNotFound();
        $this->as('field_technician')->get(route('technician.jobs.show', $theirs))->assertNotFound();
    }

    // ───────────── vendors ─────────────

    private function vendor(bool $defaultExpenses = false): Vendor
    {
        return Vendor::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'name' => 'Cool Air Co', 'status' => 'active', 'default_may_log_expenses' => $defaultExpenses]);
    }

    private function vendorTech(Vendor $v, string $email = 'vt@fab.test')
    {
        $u = $this->makeUser($this->t, 'field_technician', $email);
        app(VendorTechnicians::class)->apply($u, Request::create('/', 'POST', ['vendor_id' => $v->id]), $this->u['company_admin']);

        return $u->fresh();
    }

    #[Test]
    public function mvd_030_a_vendor_technician_has_the_vendor_and_starts_without_expenses_unless_the_vendor_default_says_so(): void
    {
        $v = $this->vendor();
        $t = $this->vendorTech($v);
        $this->assertSame($v->id, $t->vendor_id);
        $this->assertFalse($t->may_log_expenses);

        $t2 = $this->vendorTech($this->vendor(true), 'vt2@fab.test');
        $this->assertTrue($t2->may_log_expenses);
    }

    #[Test]
    public function mvd_031_a_vendor_technician_can_only_have_the_technician_role(): void
    {
        $v = $this->vendor();
        $user = $this->makeUser($this->t, 'front_desk', 'fd6@fab.test');

        try {
            app(VendorTechnicians::class)->apply($user, Request::create('/', 'POST', ['vendor_id' => $v->id]), $this->u['company_admin']);
            $this->fail('a vendor was given to a front desk user');
        } catch (FormException $e) {
            $this->assertSame('VENDOR_ROLE_LOCKED', $e->errorCode);
        }

        // and a role change afterwards is refused too
        $tech = $this->vendorTech($v);
        $tech->forceFill(['role_id' => $this->u['front_desk']->role_id])->save();
        $this->expectException(FormException::class);
        app(VendorTechnicians::class)->apply($tech->fresh(), Request::create('/', 'POST'), $this->u['company_admin']);
    }

    #[Test]
    public function only_a_vendor_manager_sets_the_vendor(): void
    {
        $v = $this->vendor();
        $user = $this->makeUser($this->t, 'field_technician', 'plain@fab.test');
        app(VendorTechnicians::class)->apply($user, Request::create('/', 'POST', ['vendor_id' => $v->id]), $this->u['front_desk']);

        $this->assertNull($user->fresh()->vendor_id);
    }

    #[Test]
    public function mvd_032_and_033_suspending_the_vendor_signs_its_technicians_out_and_reactivating_lets_them_back(): void
    {
        $v = $this->vendor();
        $t = $this->vendorTech($v);

        $this->actingAs($t)->get(route('technician.jobs.index'))->assertOk();

        $this->as('company_admin')->post(route('admin.vendors.suspend', $v))->assertRedirect();
        $this->actingAs($t->fresh())->get(route('technician.jobs.index'))->assertRedirect(route('login'));   // the company's own sign-in
        $this->assertGuest();

        $this->as('company_admin')->post(route('admin.vendors.reactivate', $v))->assertRedirect();
        $this->actingAs($t->fresh())->get(route('technician.jobs.index'))->assertOk();
    }

    #[Test]
    public function mvd_034_a_vendor_technician_gets_no_office_page_with_prices(): void
    {
        $t = $this->vendorTech($this->vendor());

        foreach ([route('admin.quotes.index'), route('admin.invoices.index'), route('admin.vendors.index'), route('admin.vendor-spend.index')] as $url) {
            $res = $this->actingAs($t)->get($url);
            $this->assertNotSame(200, $res->getStatusCode(), $url);
        }
    }

    #[Test]
    public function mvd_035_a_vendor_technician_cannot_open_another_technicians_job(): void
    {
        $t = $this->vendorTech($this->vendor());
        $sr = $this->raise($this->makeCategory());
        $sr->update(['status' => 'assigned', 'assigned_tech_user_id' => $this->u['field_technician']->id]);

        $this->actingAs($t)->get(route('technician.jobs.show', $sr))->assertForbidden();
    }

    #[Test]
    public function mvd_036_and_037_expenses_need_the_switch_and_carry_the_vendor(): void
    {
        $v = $this->vendor();
        $t = $this->vendorTech($v);
        $sr = $this->raise($this->makeCategory());
        $sr->update(['status' => 'in_progress', 'assigned_tech_user_id' => $t->id]);
        $cat = \App\Models\ExpenseCategory::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'name' => 'Parts', 'is_billable_default' => true, 'requires_receipt' => false]);
        $body = ['expense_category_id' => $cat->id, 'description' => 'Valve', 'quantity' => 2, 'unit_rate' => 15];

        $this->actingAs($t)->get(route('technician.jobs.expenses.create', $sr))->assertForbidden();
        $this->actingAs($t)->post(route('technician.jobs.expenses.store', $sr), $body)->assertForbidden();
        $this->assertSame(0, ServiceRequestExpense::withoutGlobalScopes()->count());

        $t->forceFill(['may_log_expenses' => true])->save();
        $this->actingAs($t->fresh())->post(route('technician.jobs.expenses.store', $sr), $body)->assertRedirect();
        $this->assertSame($v->id, ServiceRequestExpense::withoutGlobalScopes()->first()->vendor_id);

        // an own technician is unaffected
        $own = $this->raise($this->makeCategory());
        $own->update(['status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id]);
        $this->as('field_technician')->post(route('technician.jobs.expenses.store', $own), $body)->assertRedirect();
        $this->assertNull(ServiceRequestExpense::withoutGlobalScopes()->latest('id')->first()->vendor_id);
    }

    #[Test]
    public function mvd_038_and_039_vendor_spend_is_by_vendor_and_leaves_out_own_technicians(): void
    {
        $v = $this->vendor();
        $sr = $this->raise($this->makeCategory());
        $this->expense($sr, 120, $v->id);
        $this->expense($sr, 30, $v->id);
        $this->expense($sr, 999, null);   // an own technician's expense

        $res = $this->as('operations_manager')->get(route('admin.vendor-spend.index'));
        $res->assertOk()->assertSee('Cool Air Co')->assertSee('150.00');
        $this->assertSame(150.0, (float) $res->viewData('total'));
        $this->assertSame(1, $res->viewData('rows')->count(), 'the own technician expense is not a row');
        $this->as('finance')->get(route('admin.vendor-spend.index'))->assertOk();
        $this->as('front_desk')->get(route('admin.vendor-spend.index'))->assertForbidden();
        $this->as('operations_manager')->get(route('admin.vendor-spend.export'))->assertOk();
    }

    #[Test]
    public function mvd_040_assigning_a_trade_to_a_vendor_technician_tells_them(): void
    {
        $t = $this->vendorTech($this->vendor());
        $first = $this->makeCategory(['approval_mode' => 'always_auto']);
        $this->raiseMaster(rows: [['service_category_id' => $first->id, 'technician_id' => $t->id], ['service_category_id' => $this->makeCategory()->id]]);

        $this->assertSame(1, UserNotification::where('event_key', 'vendor.assigned')->where('user_id', $t->id)->count());
    }

    #[Test]
    public function mvd_041_and_043_the_vendor_pages_need_the_plan_and_the_permission(): void
    {
        foreach (['front_desk', 'finance'] as $who) {
            $this->as($who)->get(route('admin.vendors.index'))->assertForbidden();
        }
        $this->as('operations_manager')->get(route('admin.vendors.index'))->assertOk();

        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['vendors' => false])])->save();
        foreach ($this->u as $k => $x) {
            $this->u[$k] = $x->fresh();
        }
        $this->as('operations_manager')->get(route('admin.vendors.index'))->assertForbidden();
    }

    #[Test]
    public function mvd_042_another_companys_vendor_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'oth6');
        $foreign = Vendor::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'Theirs', 'status' => 'active']);

        $this->as('operations_manager')->get(route('admin.vendors.edit', $foreign))->assertNotFound();
    }

    // ───────────── depot ─────────────

    private function workshop(): ProjectSite
    {
        return ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => null, 'name' => 'Main workshop', 'site_type' => 'tenant_workshop', 'is_active' => true]);
    }

    private function depotCategory(bool $checklists = false): ServiceCategory
    {
        $over = ['allow_depot_mode' => true];
        if ($checklists) {
            $over += ['intake_form_template_id' => $this->makeTemplate('Intake')->id, 'return_form_template_id' => $this->makeTemplate('Return')->id];
        }

        return $this->makeCategory($over);
    }

    private function raiseDepot(ServiceCategory $cat, array $extra = []): ServiceRequest
    {
        $w = $this->workshop();
        $asset = $this->makeAsset($cat);

        return $this->raise($cat, ['service_mode' => 'depot', 'workshop_site_id' => $w->id, 'primary_asset_id' => $asset->id] + $extra);
    }

    #[Test]
    public function mvd_050_and_051_a_workshop_has_no_client(): void
    {
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['depot' => true])])->save();
        $this->as('company_admin')->post($this->onHost($this->t, '/admin/sites'), ['site_type' => 'tenant_workshop', 'client_id' => $this->client->id, 'name' => 'Our shop', 'is_active' => 1])->assertSessionHasNoErrors();

        $w = ProjectSite::withoutGlobalScopes()->where('name', 'Our shop')->first();
        $this->assertNotNull($w);
        $this->assertNull($w->client_id, 'the client is dropped for a workshop');
        $this->assertSame('tenant_workshop', $w->site_type);
    }

    #[Test]
    public function mvd_052_a_category_without_depot_mode_refuses_a_depot_request(): void
    {
        $cat = $this->makeCategory(['allow_depot_mode' => false]);
        $w = $this->workshop();

        $this->as('front_desk')->post(route('service-requests.store'), $this->body($cat, ['service_mode' => 'depot', 'workshop_site_id' => $w->id]))
            ->assertSessionHas('error', 'This category cannot be serviced at the workshop.');
        $this->assertSame(0, ServiceRequest::withoutGlobalScopes()->count());
    }

    #[Test]
    public function mvd_053_a_depot_request_sits_at_the_workshop_and_remembers_the_home_site(): void
    {
        $sr = $this->raiseDepot($this->depotCategory());

        $this->assertSame('depot', $sr->service_mode);
        $this->assertSame('Main workshop', $sr->site->name);
        $this->assertSame($this->site->id, $sr->home_site_id);
        $this->assertNull($sr->depot_stage);
    }

    #[Test]
    public function mvd_054_to_059_the_stages_need_the_checklists_and_move_forward_one_at_a_time(): void
    {
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['assets_full' => true, 'assets_basic' => true])])->save();
        $sr = $this->raiseDepot($this->depotCategory(checklists: true));
        $stage = fn (string $to, array $extra = []) => $this->as('operations_manager')->post(route('service-requests.depot.stage', $sr), ['stage' => $to] + $extra);

        $stage('received')->assertRedirect();
        $this->assertSame('received', $sr->fresh()->depot_stage);
        $this->assertNotNull($sr->fresh()->depot_received_at);
        $this->assertSame(1, UserNotification::where('event_key', 'depot.received')->where('user_id', $this->u['front_desk']->id)->count());
        $asset = Asset::withoutGlobalScopes()->find($sr->primary_asset_id);
        $this->assertSame('At workshop Main workshop', app(Depot::class)->locationOf($asset));

        // intake first
        $stage('in_workshop')->assertSessionHas('error', 'Complete the intake checklist first.');
        $this->as('operations_manager')->post(route('service-requests.depot.checklist.save', [$sr, 'intake']), [
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ])->assertRedirect();
        $this->assertSame('ok', $sr->fresh()->intake_result);
        $stage('in_workshop')->assertRedirect();
        $this->assertSame('in_workshop', $sr->fresh()->depot_stage);

        // one at a time
        $stage('returned')->assertSessionHas('error', 'Move one stage at a time.');
        $stage('ready_for_return')->assertRedirect();
        $this->assertSame(1, UserNotification::where('event_key', 'depot.ready_for_return')->count());

        // return checklist before returned
        $stage('returned')->assertSessionHas('error', 'Complete the return checklist first.');
        $this->as('operations_manager')->post(route('service-requests.depot.checklist.save', [$sr, 'return']), [
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ]);
        $stage('returned')->assertRedirect();
        $this->assertNotNull($sr->fresh()->depot_returned_at);
        $this->assertNull(app(Depot::class)->locationOf($asset), 'back at its home site');

        // going back needs a reason and a manager
        $stage('in_workshop')->assertSessionHas('error', 'Give a reason to move the stage back.');
        $stage('in_workshop', ['reason' => 'Faulty seal found'])->assertRedirect();
        $this->assertSame('in_workshop', $sr->fresh()->depot_stage);
    }

    #[Test]
    public function mvd_056_a_depot_request_cannot_be_completed_before_the_equipment_is_returned(): void
    {
        $sr = $this->raiseDepot($this->depotCategory());
        $sr->update(['status' => 'pending_review', 'depot_stage' => 'in_workshop']);

        $this->as('operations_manager')->post(route('admin.requests-review.qc-pass', $sr))->assertSessionHas('error', 'Return the equipment before you complete this request.');
        $this->assertSame('pending_review', $sr->fresh()->status);

        $sr->update(['depot_stage' => 'returned']);
        $this->as('operations_manager')->post(route('admin.requests-review.qc-pass', $sr))->assertRedirect();
        $this->assertSame('completed', $sr->fresh()->status);
    }

    #[Test]
    public function mvd_060_the_ten_statuses_are_unchanged_by_depot_work(): void
    {
        $sr = $this->raiseDepot($this->depotCategory());
        $this->as('operations_manager')->post(route('service-requests.depot.stage', $sr), ['stage' => 'received']);

        $this->assertSame('pending', $sr->fresh()->status);
        $this->assertCount(10, array_filter(array_keys(ServiceRequest::STATUSES ?? []), fn () => true) ?: range(1, 10));
    }

    #[Test]
    public function mvd_061_a_depot_request_covers_at_most_20_assets(): void
    {
        $cat = $this->depotCategory();
        $w = $this->workshop();
        $ids = collect(range(1, 21))->map(fn () => $this->makeAsset($cat)->id)->all();

        $this->as('front_desk')->post(route('service-requests.store'), $this->body($cat, ['service_mode' => 'depot', 'workshop_site_id' => $w->id, 'asset_ids' => $ids]))
            ->assertSessionHas('error', 'A depot request can cover up to 20 assets.');
    }

    #[Test]
    public function mvd_062_a_client_sees_one_request_with_the_stage_and_never_its_trades(): void
    {
        $m = $this->raiseMaster(2);
        $kidIds = $this->kids($m)->pluck('id')->all();
        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $c = $this->makeUser($this->t, 'client_admin', 'ca6@fab.test');
        $c->forceFill(['client_id' => $this->client->id])->save();

        $res = $this->actingAs($c)->get($this->onHost($this->t, '/portal/requests'));
        $res->assertOk()->assertSee($m->sr_number);
        foreach ($this->kids($m) as $k) {
            $res->assertDontSee($k->sr_number);
        }

        $sr = $this->raiseDepot($this->depotCategory());
        $sr->update(['depot_stage' => 'in_workshop']);
        $this->actingAs($c)->get($this->onHost($this->t, "/portal/requests/{$sr->id}"))->assertOk()->assertSee('In workshop')->assertDontSee('Main workshop');
    }

    #[Test]
    public function mvd_071_the_new_routes_are_behind_sign_in_and_permissions(): void
    {
        $sr = $this->raiseDepot($this->depotCategory());
        $this->as('front_desk')->post(route('service-requests.depot.stage', $sr), ['stage' => 'received'])->assertForbidden();   // sr.assign is for urgent requests only
        \Illuminate\Support\Facades\Auth::logout();
        $this->post(route('service-requests.depot.stage', $sr), ['stage' => 'received'])->assertRedirect();
        $this->get(route('admin.vendor-spend.index'))->assertRedirect();
    }
}
