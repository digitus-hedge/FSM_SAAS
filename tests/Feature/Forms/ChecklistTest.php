<?php

namespace Tests\Feature\Forms;

use App\Models\FieldEventLog;
use App\Models\ServiceRequest;
use App\Services\Forms\Checklists;
use App\Services\Forms\FormTemplates;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/** Forms-Approvals-Batch spec 10.4 and 10.5: the checklist, the proof lock and its results (FAB-030 to FAB-042). */
class ChecklistTest extends FormsTestCase
{
    /** A category whose checklist blocks punch-out. */
    private function lockedJob(?array $schema = null, array $proof = []): array
    {
        $tpl = $this->makeTemplate('Inspection', $schema);
        $cat = $this->makeCategory(['active_form_template_id' => $tpl->id, 'require_checklist' => true]);

        return [$this->jobInProgress($cat, $proof), $tpl, $cat];
    }

    #[Test]
    public function fab_030_saving_with_a_required_field_empty_keeps_the_answers_but_the_result_stays_open(): void
    {
        [$sr] = $this->lockedJob();

        $this->save($sr, ['pressure' => 7])->assertRedirect();
        $sr->refresh();

        $this->assertSame(['pressure' => 7], $sr->sr_checklist_payload);
        $this->assertNull($sr->checklist_result);

        $res = $this->punchOut($sr);
        $res->assertSessionHasErrors(['identifier' => 'Complete the checklist before you finish: 1 required answers are missing.']);
        $this->assertSame('in_progress', $sr->fresh()->status);
    }

    #[Test]
    public function fab_031_the_answer_of_a_hidden_field_is_dropped(): void
    {
        $schema = ['sections' => [['key' => 's1', 'title' => '', 'fields' => [
            ['key' => 'works', 'type' => 'yes_no', 'label' => 'Works?', 'required' => true],
            ['key' => 'why', 'type' => 'text', 'label' => 'Why not?', 'required' => true, 'show_if' => ['field' => 'works', 'op' => 'is_no']],
        ]]]];
        [$sr] = $this->lockedJob($schema);

        $this->save($sr, ['works' => 'yes', 'why' => 'should be dropped'])->assertRedirect();
        $sr->refresh();
        $this->assertSame(['works' => 'yes'], $sr->sr_checklist_payload);
        $this->assertSame('ok', $sr->checklist_result, 'a hidden field is never required');

        $this->save($sr, ['works' => 'no'])->assertRedirect();
        $this->assertNull($sr->fresh()->checklist_result, 'visible again, so required');
    }

    #[Test]
    public function fab_032_a_number_outside_its_range_gives_the_result_issues(): void
    {
        [$sr] = $this->lockedJob();
        $this->save($sr, ['works' => 'yes', 'pressure' => 99])->assertRedirect();

        $this->assertSame('issues', $sr->fresh()->checklist_result);
    }

    #[Test]
    public function fab_032b_a_no_on_a_fails_field_gives_the_result_issues(): void
    {
        [$sr] = $this->lockedJob();
        $this->save($sr, ['works' => 'no', 'pressure' => 7])->assertRedirect();

        $this->assertSame('issues', $sr->fresh()->checklist_result);
    }

    #[Test]
    public function fab_033_everything_answered_and_nothing_failing_gives_ok(): void
    {
        [$sr] = $this->lockedJob();
        $this->save($sr, $this->fullAnswers())->assertRedirect();

        $this->assertSame('ok', $sr->fresh()->checklist_result);
        $this->assertNotNull($sr->fresh()->checklist_saved_at);
    }

    #[Test]
    public function the_device_cannot_send_a_result(): void
    {
        [$sr] = $this->lockedJob();
        $this->save($sr, ['works' => 'no', 'pressure' => 7], ['result' => 'ok', 'checklist_result' => 'ok'])->assertRedirect();

        $this->assertSame('issues', $sr->fresh()->checklist_result);
    }

    #[Test]
    public function fab_034_an_older_event_arriving_last_does_not_overwrite_the_newer_answers(): void
    {
        [$sr] = $this->lockedJob();
        $new = Carbon::parse('2026-11-19 09:30:00', 'UTC')->toIso8601String();
        $old = Carbon::parse('2026-11-19 09:00:00', 'UTC')->toIso8601String();

        $this->save($sr, ['works' => 'yes', 'pressure' => 8], ['recorded_at' => $new])->assertRedirect();
        $this->save($sr, ['works' => 'no', 'pressure' => 1], ['recorded_at' => $old])->assertRedirect();

        $this->assertSame(['works' => 'yes', 'pressure' => 8], $sr->fresh()->sr_checklist_payload);
        $this->assertSame(1, FieldEventLog::withoutGlobalScopes()->where('service_request_id', $sr->id)->where('result', 'conflict_rejected')->count());
    }

    #[Test]
    public function the_same_event_twice_changes_nothing_the_second_time(): void
    {
        [$sr] = $this->lockedJob();
        $uuid = (string) \Illuminate\Support\Str::uuid();

        $this->save($sr, ['works' => 'yes', 'pressure' => 8], ['client_event_uuid' => $uuid]);
        $this->save($sr, ['works' => 'no', 'pressure' => 1], ['client_event_uuid' => $uuid]);

        $this->assertSame(['works' => 'yes', 'pressure' => 8], $sr->fresh()->sr_checklist_payload);
        $this->assertSame(1, FieldEventLog::withoutGlobalScopes()->where('client_event_uuid', $uuid)->count());
    }

    #[Test]
    public function fab_035_an_event_for_another_version_than_the_bound_one_is_refused(): void
    {
        [$sr, $tpl] = $this->lockedJob();
        $svc = app(FormTemplates::class);
        $svc->editAsNewDraft($tpl);
        $svc->publish($tpl, $this->u['company_admin']);
        $newVersion = $tpl->fresh()->publishedVersion()->id;

        $this->as('field_technician')->postJson(route('technician.jobs.checklist.save', $sr), [
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'version_id' => $newVersion, 'answers' => $this->fullAnswers(),
        ])->assertStatus(409)->assertJsonPath('error.code', 'CHECKLIST_VERSION_MISMATCH')
            ->assertJsonPath('error.message', 'This checklist changed. Reload the job to continue.');
        $this->assertNull($sr->fresh()->sr_checklist_payload);
    }

    #[Test]
    public function fab_036_punch_out_with_a_required_checklist_and_no_answers_is_blocked_then_allowed_when_done(): void
    {
        [$sr] = $this->lockedJob();

        $this->punchOut($sr)->assertSessionHasErrors('identifier');
        $this->assertSame('in_progress', $sr->fresh()->status);

        $this->save($sr, $this->fullAnswers());
        $this->punchOut($sr)->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $sr->fresh()->status);
    }

    #[Test]
    public function a_category_that_does_not_require_the_checklist_never_blocks_punch_out(): void
    {
        $tpl = $this->makeTemplate();
        $cat = $this->makeCategory(['active_form_template_id' => $tpl->id, 'require_checklist' => false]);
        $sr  = $this->jobInProgress($cat);

        $this->punchOut($sr)->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $sr->fresh()->status);
    }

    #[Test]
    public function a_lost_plan_pauses_the_checklist_rule_instead_of_blocking_work(): void
    {
        [$sr] = $this->lockedJob();
        $this->t->plan->forceFill(['features' => ['forms' => false]])->save();

        $this->assertSame([], app(Checklists::class)->blockers($sr->fresh()));
    }

    #[Test]
    public function fab_037_the_office_submit_for_review_works_without_a_checklist_and_says_so(): void
    {
        [$sr] = $this->lockedJob();

        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertOk()->assertSee('Checklist not completed');
        $this->as('operations_manager')->post(route('service-requests.mark-review', $sr))->assertRedirect();
        $this->assertSame('pending_review', $sr->fresh()->status);
    }

    #[Test]
    public function fab_038_another_technician_cannot_save_the_checklist_of_this_job(): void
    {
        [$sr] = $this->lockedJob();
        $other = $this->makeUser($this->t, 'field_technician', 'other@fab.test');

        $this->actingAs($other)->post(route('technician.jobs.checklist.save', $sr), [
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $this->fullAnswers(),
        ])->assertForbidden();
        $this->assertNull($sr->fresh()->sr_checklist_payload);
    }

    #[Test]
    public function the_checklist_can_only_be_saved_while_the_job_is_in_progress(): void
    {
        [$sr] = $this->lockedJob();
        $sr->forceFill(['status' => 'assigned'])->save();

        $this->save($sr, $this->fullAnswers())->assertStatus(422);
    }

    #[Test]
    public function the_field_screen_lists_the_checklist_and_shows_what_blocks_punch_out(): void
    {
        [$sr] = $this->lockedJob();

        $this->as('field_technician')->get(route('technician.jobs.show', $sr))->assertOk()
            ->assertSee(route('technician.jobs.checklist', $sr, false), false)
            ->assertSee('Complete the checklist before you finish');
        $this->as('field_technician')->get(route('technician.jobs.checklist', $sr))->assertOk()->assertSee('Works?')->assertSee('Pressure');
    }

    #[Test]
    public function an_answer_for_a_field_the_version_does_not_have_is_ignored(): void
    {
        [$sr] = $this->lockedJob();
        $this->save($sr, $this->fullAnswers() + ['ghost' => 'x'])->assertRedirect();

        $this->assertArrayNotHasKey('ghost', $sr->fresh()->sr_checklist_payload);
    }

    #[Test]
    public function an_invalid_answer_is_refused_and_saves_nothing(): void
    {
        [$sr] = $this->lockedJob();
        $this->as('field_technician')->postJson(route('technician.jobs.checklist.save', $sr), [
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => ['works' => 'maybe', 'pressure' => 'abc'],
        ])->assertStatus(422);

        $this->assertNull($sr->fresh()->sr_checklist_payload);
    }

    // ───────────── 10.5 results ─────────────

    #[Test]
    public function fab_040_the_request_page_shows_the_version_the_result_and_the_answers(): void
    {
        [$sr, $tpl] = $this->lockedJob();
        $this->save($sr, $this->fullAnswers() + ['note' => 'Gauge replaced']);

        $this->as('operations_manager')->get(route('service-requests.show', $sr))->assertOk()
            ->assertSee('Inspection, version 1')->assertSee('All good')->assertSee('Gauge replaced')->assertSee('7 bar');
    }

    #[Test]
    public function fab_041_the_asset_360_lists_the_checklist_with_its_date_and_result(): void
    {
        $tpl   = $this->makeTemplate();
        $cat   = $this->makeCategory(['active_form_template_id' => $tpl->id, 'require_checklist' => true]);
        $asset = $this->makeAsset($cat);
        $sr    = $this->raise($cat, ['primary_asset_id' => $asset->id]);
        $sr->forceFill(['status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id])->save();
        $this->save($sr, ['works' => 'no', 'pressure' => 7]);

        $this->t->plan->forceFill(['features' => array_merge($this->t->plan->features, ['assets_full' => true, 'assets_basic' => true])])->save();
        $this->actingAs($this->u['company_admin'])->get($this->onHost($this->t, "/admin/assets/{$asset->id}"))
            ->assertOk()->assertSee('Inspection, version 1')->assertSee('Issues found')->assertSee($sr->sr_number);
    }

    #[Test]
    public function fab_042_a_client_sees_only_the_fields_marked_visible_to_the_client(): void
    {
        $schema = ['sections' => [['key' => 's1', 'title' => '', 'fields' => [
            ['key' => 'works', 'type' => 'yes_no', 'label' => 'Works?', 'required' => true, 'visible_to_client' => true],
            ['key' => 'internal', 'type' => 'text', 'label' => 'Internal remark', 'required' => false],
        ]]]];
        [$sr] = $this->lockedJob($schema);
        $this->save($sr, ['works' => 'yes', 'internal' => 'secret staff words']);

        \App\Models\Role::firstOrCreate(['tenant_id' => $this->t->id, 'code' => 'client_admin'], ['name' => 'Client Admin', 'is_standard' => false]);
        $clientUser = $this->makeUser($this->t, 'client_admin', 'ca@fab.test');
        $clientUser->forceFill(['client_id' => $this->client->id])->save();
        $this->t->plan->forceFill(['code' => 'professional'])->save();

        $res = $this->actingAs($clientUser)->get($this->onHost($this->t, "/portal/requests/{$sr->id}"));
        $res->assertOk()->assertSee('All good')->assertSee('Works?')->assertDontSee('Internal remark')->assertDontSee('secret staff words');
    }

}
