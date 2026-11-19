<?php

namespace Tests\Feature\Forms;

use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Services\Forms\FormException;
use App\Services\Forms\FormSchema;
use App\Services\Forms\FormTemplates;
use PHPUnit\Framework\Attributes\Test;

/** Forms-Approvals-Batch spec 10.1 to 10.3: templates, versions, the builder and binding (FAB-001 to FAB-023). */
class TemplatesTest extends FormsTestCase
{
    // ───────────── 10.1 templates and versions ─────────────

    #[Test]
    public function fab_001_a_professional_company_cannot_create_an_eleventh_template(): void
    {
        $svc = app(FormTemplates::class);
        for ($i = 1; $i <= 10; $i++) {
            $svc->create($this->t, $this->u['company_admin'], "T{$i}", null);
        }
        try {
            $svc->create($this->t, $this->u['company_admin'], 'T11', null);
            $this->fail('the 11th template was allowed');
        } catch (FormException $e) {
            $this->assertSame('TEMPLATE_LIMIT', $e->errorCode);
            $this->assertSame('Your plan allows 10 checklists. Archive one or upgrade your plan.', $e->getMessage());
        }
        // an archived template makes room again
        $svc->archive(FormTemplate::withoutGlobalScopes()->where('name', 'T1')->first());
        $this->assertSame('T11', $svc->create($this->t, $this->u['company_admin'], 'T11', null)->name);
    }

    #[Test]
    public function fab_002_a_starter_company_gets_the_not_in_your_plan_page(): void
    {
        $starter = $this->makeTenant($this->starterPlan(), slug: 'stf');
        $admin   = $this->makeUser($starter, 'company_admin');

        $this->actingAs($admin)->get(route('admin.checklists.index'))->assertForbidden();
        $this->assertFalse(app(FormTemplates::class)->enabled($starter));
    }

    #[Test]
    public function fab_003_publishing_a_draft_with_no_fields_is_refused(): void
    {
        $tpl = $this->makeTemplate('Empty', FormSchema::blank(), publish: false);
        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Add at least one field.');
        app(FormTemplates::class)->publish($tpl, $this->u['company_admin']);
    }

    #[Test]
    public function fab_004_publishing_version_two_retires_version_one_and_never_leaves_two_published(): void
    {
        $svc = app(FormTemplates::class);
        $tpl = $this->makeTemplate();
        $svc->editAsNewDraft($tpl);
        $svc->publish($tpl, $this->u['company_admin']);

        $versions = FormTemplateVersion::withoutGlobalScopes()->where('form_template_id', $tpl->id)->orderBy('version_no')->get();
        $this->assertSame(['retired', 'published'], $versions->pluck('status')->all());
        $this->assertSame(1, $versions->where('status', 'published')->count());
        $this->assertNotNull($versions[0]->retired_at);
    }

    #[Test]
    public function fab_005_the_content_of_a_published_version_cannot_change(): void
    {
        $v = $this->makeTemplate()->publishedVersion();
        try {
            $v->update(['schema_json' => FormSchema::blank()]);
            $this->fail('a published version was edited');
        } catch (FormException $e) {
            $this->assertSame('TEMPLATE_PUBLISHED_IMMUTABLE', $e->errorCode);
        }
        $this->assertCount(3, $v->fresh()->schema_json['sections'][0]['fields']);
    }

    #[Test]
    public function fab_006_a_template_that_was_published_cannot_be_deleted_only_archived(): void
    {
        $tpl = $this->makeTemplate();
        $this->as('company_admin')->delete(route('admin.checklists.destroy', $tpl))->assertSessionHas('error');
        $this->assertNotNull(FormTemplate::withoutGlobalScopes()->find($tpl->id));

        $this->as('company_admin')->post(route('admin.checklists.archive', $tpl))->assertRedirect();
        $this->assertSame('archived', $tpl->fresh()->status);

        // a draft that was never published can be deleted
        $draft = $this->makeTemplate('Never published', publish: false);
        $this->as('company_admin')->delete(route('admin.checklists.destroy', $draft))->assertRedirect();
        $this->assertNull(FormTemplate::withoutGlobalScopes()->find($draft->id));
    }

    #[Test]
    public function fab_007_duplicating_a_template_makes_a_draft_copy_that_counts_against_the_limit(): void
    {
        $tpl = $this->makeTemplate();
        $this->as('company_admin')->post(route('admin.checklists.duplicate', $tpl))->assertRedirect();

        $copy = FormTemplate::withoutGlobalScopes()->where('name', 'Inspection (copy)')->firstOrFail();
        $this->assertNull($copy->publishedVersion());
        $this->assertSame('draft', $copy->draftVersion()->status);
        $this->assertSame(2, FormTemplate::withoutGlobalScopes()->where('tenant_id', $this->t->id)->where('status', 'active')->count());
    }

    // ───────────── 10.2 builder ─────────────

    private function problemsFor(array $field): array
    {
        return FormSchema::problems(['sections' => [['key' => 's1', 'title' => 'x', 'fields' => [array_merge(['key' => 'f1', 'type' => 'text', 'label' => 'L'], $field)]]]]);
    }

    #[Test]
    public function fab_010_a_number_field_with_minimum_above_maximum_is_invalid(): void
    {
        $this->assertStringContainsString('minimum is above the maximum', $this->problemsFor(['type' => 'number', 'label' => 'Pressure', 'min' => 5, 'max' => 1])[0]);
        $this->assertSame([], $this->problemsFor(['type' => 'number', 'min' => 1, 'max' => 5]));
    }

    #[Test]
    public function fab_011_a_show_if_that_points_to_a_later_field_is_invalid(): void
    {
        $bad = FormSchema::problems(['sections' => [['key' => 's1', 'title' => '', 'fields' => [
            ['key' => 'a', 'type' => 'text', 'label' => 'A', 'show_if' => ['field' => 'b', 'op' => 'equals', 'value' => 'x']],
            ['key' => 'b', 'type' => 'text', 'label' => 'B'],
        ]]]]);
        $this->assertNotEmpty($bad);
        $this->assertStringContainsString('earlier field', $bad[0]);

        $good = FormSchema::problems(['sections' => [['key' => 's1', 'title' => '', 'fields' => [
            ['key' => 'b', 'type' => 'text', 'label' => 'B'],
            ['key' => 'a', 'type' => 'text', 'label' => 'A', 'show_if' => ['field' => 'b', 'op' => 'equals', 'value' => 'x']],
        ]]]]);
        $this->assertSame([], $good);
    }

    #[Test]
    public function fab_012_dropdown_options_and_label_length_are_checked(): void
    {
        $this->assertNotEmpty($this->problemsFor(['type' => 'dropdown', 'options' => ['only one']]));
        $this->assertNotEmpty($this->problemsFor(['type' => 'dropdown', 'options' => array_map(fn ($i) => "o{$i}", range(1, 21))]));
        $this->assertSame([], $this->problemsFor(['type' => 'dropdown', 'options' => ['a', 'b']]));
        $this->assertNotEmpty($this->problemsFor(['label' => str_repeat('x', 121)]));
    }

    // ───────────── 10.3 binding ─────────────

    #[Test]
    public function fab_020_the_asset_template_wins_over_the_category_template(): void
    {
        $catTpl   = $this->makeTemplate('Category list');
        $assetTpl = $this->makeTemplate('Asset list');
        $cat      = $this->makeCategory(['active_form_template_id' => $catTpl->id]);
        $asset    = $this->makeAsset($cat, ['form_template_id' => $assetTpl->id]);

        $sr = $this->raise($cat, ['primary_asset_id' => $asset->id]);
        $this->assertSame($assetTpl->publishedVersion()->id, $sr->form_template_version_id);

        $plain = $this->raise($cat);   // no asset: the category's template
        $this->assertSame($catTpl->publishedVersion()->id, $plain->form_template_version_id);
    }

    #[Test]
    public function fab_021_a_later_version_never_changes_a_request_that_already_exists(): void
    {
        $tpl = $this->makeTemplate();
        $cat = $this->makeCategory(['active_form_template_id' => $tpl->id]);
        $sr  = $this->raise($cat);
        $v1  = $tpl->publishedVersion()->id;
        $this->assertSame($v1, $sr->form_template_version_id);

        $svc = app(FormTemplates::class);
        $svc->editAsNewDraft($tpl);
        $svc->publish($tpl, $this->u['company_admin']);

        $this->assertSame($v1, $sr->fresh()->form_template_version_id);
        $this->assertNotSame($v1, $this->raise($cat)->form_template_version_id);
    }

    #[Test]
    public function fab_022_a_template_without_a_published_version_gives_no_checklist(): void
    {
        $tpl = $this->makeTemplate('Draft only', publish: false);
        $cat = $this->makeCategory(['active_form_template_id' => $tpl->id]);

        $this->assertNull($this->raise($cat)->form_template_version_id);
    }

    #[Test]
    public function fab_023_an_archived_template_cannot_be_chosen_on_a_category(): void
    {
        $tpl = $this->makeTemplate();
        app(FormTemplates::class)->archive($tpl);
        $cat = $this->makeCategory();

        $this->as('company_admin')->put(route('admin.service-categories.update', $cat), [
            'name' => 'Renamed', 'approval_mode' => 'manual', 'active_form_template_id' => $tpl->id,
        ])->assertSessionHasErrors('active_form_template_id');
        $this->assertNull($cat->fresh()->active_form_template_id);
    }

    #[Test]
    public function the_builder_saves_a_draft_through_the_page_and_publishes_it(): void
    {
        $tpl = app(FormTemplates::class)->create($this->t, $this->u['company_admin'], 'Built by hand', null);
        $schema = ['sections' => [['key' => 's1', 'title' => 'One', 'fields' => [['type' => 'text', 'label' => 'Name plate', 'required' => '1']]]]];

        $this->as('company_admin')->post(route('admin.checklists.draft.save', $tpl), ['schema' => json_encode($schema)])->assertRedirect();
        $field = $tpl->draftVersion()->schema_json['sections'][0]['fields'][0];
        $this->assertTrue($field['required']);
        $this->assertNotSame('', $field['key']);

        $this->as('company_admin')->post(route('admin.checklists.publish', $tpl))->assertRedirect();
        $this->assertSame('published', $tpl->fresh()->publishedVersion()->status);
        $this->as('company_admin')->get(route('admin.checklists.show', $tpl))->assertOk()->assertSee('Edit as new draft');
    }

    // ───────────── 10.8 permissions and isolation ─────────────

    #[Test]
    public function fab_090_only_the_company_admin_and_operations_manager_open_the_checklists_page(): void
    {
        foreach (['front_desk', 'finance'] as $who) {
            $this->as($who)->get(route('admin.checklists.index'))->assertForbidden();
        }
        foreach (['company_admin', 'operations_manager'] as $who) {
            $this->as($who)->get(route('admin.checklists.index'))->assertOk();
        }
    }

    #[Test]
    public function fab_092_another_companys_template_is_a_404(): void
    {
        $other = $this->makeTenant(slug: 'oth');
        $foreign = FormTemplate::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'Theirs', 'status' => 'active']);

        $this->as('company_admin')->get(route('admin.checklists.show', $foreign))->assertNotFound();
        $this->as('company_admin')->post(route('admin.checklists.archive', $foreign))->assertNotFound();
    }

    #[Test]
    public function fab_094_template_changes_write_audit_rows_without_answers(): void
    {
        $tpl = $this->makeTemplate();
        app(FormTemplates::class)->archive($tpl);

        $actions = \App\Models\ActivityLog::where('tenant_id', $this->t->id)->pluck('action')->all();
        foreach (['form_template.created', 'form_template.published', 'form_template.archived'] as $a) {
            $this->assertContains($a, $actions);
        }
    }
}
