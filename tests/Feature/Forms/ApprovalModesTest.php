<?php

namespace Tests\Feature\Forms;

use App\Models\Contract;
use App\Models\Plan;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\UserNotification;
use PHPUnit\Framework\Attributes\Test;

/** Forms-Approvals-Batch spec 10.7: approval modes and the rules page (FAB-070 to FAB-079). */
class ApprovalModesTest extends FormsTestCase
{
    private function enterprise(): void
    {
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['approval_finance' => true])])->save();
        $this->t = $this->t->fresh();
    }

    private function withContracts(): void
    {
        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['contracts' => true])])->save();
    }

    private function catWith(string $mode): ServiceCategory
    {
        return $this->makeCategory(['approval_mode' => $mode]);
    }

    private function coveringContract(): Contract
    {
        $c = new Contract();
        $c->forceFill([
            'tenant_id' => $this->t->id, 'contract_number' => 'CT-1', 'client_id' => $this->client->id, 'contract_type' => 'amc',
            'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'status' => 'active',
        ])->save();

        return $c;
    }

    #[Test]
    public function fab_070_a_plan_without_the_mode_cannot_save_it(): void
    {
        $this->t->plan->forceFill(['features' => ['forms' => true, 'approval_auto' => false, 'approval_finance' => false]])->save();
        $cat = $this->makeCategory();

        foreach (['always_auto', 'auto_if_in_contract', 'finance_and_manager'] as $mode) {
            $this->as('company_admin')->post(route('admin.approval-rules.update', $cat), ['mode' => $mode])->assertForbidden();
        }
        $this->assertSame('manual', $cat->fresh()->approval_mode);

        // Professional has the two automatic modes but not Finance and manager
        $this->t->plan->forceFill(['features' => ['forms' => true, 'approval_auto' => true, 'approval_finance' => false]])->save();
        $this->as('company_admin')->post(route('admin.approval-rules.update', $cat), ['mode' => 'always_auto'])->assertRedirect();
        $this->as('company_admin')->post(route('admin.approval-rules.update', $cat), ['mode' => 'finance_and_manager'])->assertForbidden();
        $this->assertSame('always_auto', $cat->fresh()->approval_mode);
    }

    #[Test]
    public function fab_070b_the_category_form_refuses_a_mode_outside_the_plan_and_the_mode_dropdown_marks_it(): void
    {
        $cat = $this->makeCategory();
        $this->as('company_admin')->put(route('admin.service-categories.update', $cat), ['name' => 'Renamed', 'approval_mode' => 'finance_and_manager'])->assertForbidden();
        $this->as('company_admin')->get(route('admin.approval-rules.index'))->assertOk()->assertSee('Not in your plan');
    }

    #[Test]
    public function fab_071_auto_if_in_contract_approves_at_once_when_a_contract_covers_the_request(): void
    {
        $this->withContracts();
        $this->coveringContract();
        $cat = $this->catWith('auto_if_in_contract');

        $sr = $this->raiseOnHost($cat);

        $this->assertSame('approved', $sr->status);
        $this->assertSame('in_contract', $sr->coverage_type);
        $this->assertSame('auto_if_in_contract', $sr->auto_approved_mode);
        $this->assertNull($sr->approved_by_user_id);
        $this->assertSame(1, Contract::withoutGlobalScopes()->first()->calls_used_current_period);
        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertSee('Approved automatically (auto_if_in_contract).');
    }

    #[Test]
    public function fab_072_auto_if_in_contract_leaves_an_uncovered_request_pending(): void
    {
        $this->withContracts();
        $sr = $this->raiseOnHost($this->catWith('auto_if_in_contract'));

        $this->assertSame('pending', $sr->status);
        $this->assertNull($sr->auto_approved_mode);
    }

    #[Test]
    public function fab_073_always_auto_approves_every_request_and_audits_it(): void
    {
        $sr = $this->raise($this->catWith('always_auto'));

        $this->assertSame('approved', $sr->status);
        $this->assertSame('always_auto', $sr->auto_approved_mode);
        $this->assertSame('system', \App\Models\SrStatusHistory::where('service_request_id', $sr->id)->where('to_status', 'approved')->value('actor_type'));
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'request.auto_approved')->where('item_id', $sr->id)->exists());
        $this->assertGreaterThan(0, UserNotification::where('event_key', 'sr.auto_approved')->count());
    }

    #[Test]
    public function fab_074_finance_and_manager_waits_for_the_second_approval(): void
    {
        $this->enterprise();
        $sr = $this->raise($this->catWith('finance_and_manager'));
        $this->assertSame('awaiting_both', $sr->approval_state);

        $this->as('operations_manager')->post(route('service-requests.approve', $sr), ['coverage' => 'goodwill', 'note' => 'ok'])->assertRedirect();
        $sr->refresh();
        $this->assertSame('pending', $sr->status);
        $this->assertSame('awaiting_finance', $sr->approval_state);
        $this->assertSame($this->u['operations_manager']->id, $sr->approved_by_ops_user_id);

        $this->as('finance')->post(route('service-requests.approve-finance', $sr))->assertRedirect();
        $sr->refresh();
        $this->assertSame('approved', $sr->status);
        $this->assertSame('goodwill', $sr->coverage_type);
        $this->assertSame('done', $sr->approval_state);
    }

    #[Test]
    public function finance_can_approve_first_and_the_operations_manager_second(): void
    {
        $this->enterprise();
        $sr = $this->raise($this->catWith('finance_and_manager'));

        $this->as('finance')->post(route('service-requests.approve-finance', $sr))->assertRedirect();
        $this->assertSame('awaiting_ops', $sr->fresh()->approval_state);
        $this->as('operations_manager')->post(route('service-requests.approve', $sr), ['coverage' => 'goodwill', 'note' => 'x'])->assertRedirect();

        $this->assertSame('approved', $sr->fresh()->status);
    }

    #[Test]
    public function fab_075_the_same_person_cannot_give_both_approvals(): void
    {
        $this->enterprise();
        $sr = $this->raise($this->catWith('finance_and_manager'));

        $this->as('company_admin')->post(route('service-requests.approve', $sr), ['coverage' => 'goodwill', 'note' => 'x'])->assertRedirect();
        $this->as('company_admin')->post(route('service-requests.approve-finance', $sr))->assertSessionHas('error', 'A second person must give the other approval.');

        $this->assertSame('pending', $sr->fresh()->status);
        $this->assertNull($sr->fresh()->approved_by_finance_user_id);
    }

    #[Test]
    public function the_finance_side_needs_expense_approve_and_the_request_to_be_waiting(): void
    {
        $this->enterprise();
        $sr = $this->raise($this->catWith('finance_and_manager'));

        $this->as('front_desk')->post(route('service-requests.approve-finance', $sr))->assertForbidden();
        $plain = $this->raise($this->makeCategory());
        $this->as('finance')->post(route('service-requests.approve-finance', $plain))->assertStatus(422);
    }

    #[Test]
    public function fab_076_a_rejection_by_finance_sends_the_request_back_to_the_requester_with_the_reason(): void
    {
        $this->enterprise();
        $sr = $this->raise($this->catWith('finance_and_manager'));
        $this->as('operations_manager')->post(route('service-requests.approve', $sr), ['coverage' => 'goodwill', 'note' => 'x']);

        $this->as('finance')->post(route('service-requests.reject-finance', $sr), ['reason' => 'No budget'])->assertRedirect();
        $sr->refresh();

        $this->assertSame('pending', $sr->status);
        $this->assertNull($sr->approval_state);
        $this->assertNull($sr->approved_by_ops_user_id);
        $n = UserNotification::where('event_key', 'sr.approval_rejected')->where('user_id', $this->u['front_desk']->id)->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString('No budget', $n->body);
    }

    #[Test]
    public function fab_077_a_company_that_drops_to_a_plan_without_the_mode_runs_manual_and_keeps_the_stored_value(): void
    {
        $cat = $this->catWith('always_auto');
        $this->t->plan->forceFill(['features' => ['forms' => true, 'approval_auto' => false]])->save();

        $sr = $this->raise($cat);

        $this->assertSame('pending', $sr->status);
        $this->assertSame('always_auto', $cat->fresh()->approval_mode);
        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertOk()
            ->assertSee('does not include Always auto-approve, so this runs as manual');
    }

    #[Test]
    public function fab_079_a_batch_under_an_automatic_mode_gets_one_approval(): void
    {
        $cat = $this->makeCategory(['approval_mode' => 'always_auto']);
        $ids = [$this->makeAsset($cat)->id, $this->makeAsset($cat)->id, $this->makeAsset($cat)->id];

        $this->as('front_desk')->post(route('service-requests.store'), $this->body($cat, ['asset_ids' => $ids]))->assertSessionHasNoErrors();

        $this->assertSame(1, ServiceRequest::withoutGlobalScopes()->count());
        $this->assertSame('approved', ServiceRequest::withoutGlobalScopes()->first()->status);
    }

    #[Test]
    public function the_second_approver_named_on_the_rule_is_the_only_finance_approver(): void
    {
        $this->enterprise();
        $cat = $this->catWith('finance_and_manager');
        $other = $this->makeUser($this->t, 'finance', 'finance2@fab.test');
        $this->as('company_admin')->post(route('admin.approval-rules.update', $cat), [
            'mode' => 'finance_and_manager', 'second_approver_user_id' => $other->id,
        ])->assertRedirect();
        $sr = $this->raise($cat);

        $this->as('finance')->post(route('service-requests.approve-finance', $sr))->assertForbidden();
        $this->actingAs($other)->post(route('service-requests.approve-finance', $sr))->assertRedirect();
        $this->assertSame($other->id, $sr->fresh()->approved_by_finance_user_id);
    }

    // ───────────── rules page and permissions ─────────────

    #[Test]
    public function fab_091_the_operations_manager_sees_the_rules_page_but_cannot_save(): void
    {
        $cat = $this->makeCategory();

        $this->as('operations_manager')->get(route('admin.approval-rules.index'))->assertOk()->assertSee('You can look at the rules but not change them.');
        $this->as('operations_manager')->post(route('admin.approval-rules.update', $cat), ['mode' => 'always_auto'])->assertForbidden();
        $this->as('company_admin')->post(route('admin.approval-rules.update', $cat), ['mode' => 'always_auto'])->assertRedirect();
        $this->assertSame('always_auto', $cat->fresh()->approval_mode);
        $this->assertTrue(\App\Models\ActivityLog::where('action', 'approval_mode.changed')->exists());
    }

    #[Test]
    public function the_category_form_keeps_the_mode_when_the_user_may_not_change_it(): void
    {
        $cat = $this->makeCategory(['approval_mode' => 'always_auto']);

        $this->as('operations_manager')->put(route('admin.service-categories.update', $cat), ['name' => 'Renamed', 'approval_mode' => 'manual'])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $cat->fresh()->name);
        $this->assertSame('always_auto', $cat->fresh()->approval_mode);
    }

    #[Test]
    public function fab_093_the_new_routes_are_behind_sign_in(): void
    {
        $tpl = $this->makeTemplate();
        foreach ([route('admin.checklists.index'), route('admin.checklists.show', $tpl), route('admin.approval-rules.index')] as $url) {
            $this->get($url)->assertRedirect();   // to the sign-in page
        }
        $sr = $this->raise($this->makeCategory());
        \Illuminate\Support\Facades\Auth::logout();
        $this->post(route('service-requests.approve-finance', $sr))->assertRedirect();
    }
}
