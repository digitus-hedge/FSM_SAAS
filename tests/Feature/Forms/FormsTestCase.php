<?php

namespace Tests\Feature\Forms;

use App\Models\Asset;
use App\Models\Client;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Forms\FormTemplates;
use Carbon\Carbon;
use Tests\Feature\Currency\BillingTestCase;

/**
 * Shared set-up for the Forms, approvals and batch tests (docs/specs/Forms-Approvals-Batch.md, section 10).
 * MAIL_MAILER must be "log" so no real email is sent (phpunit.xml sets it for tests).
 */
abstract class FormsTestCase extends BillingTestCase
{
    protected Tenant $t;
    protected Client $client;
    protected ProjectSite $site;
    protected array $u = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        config(['mail.default' => 'log']);
        $this->t = $this->makeTenant(slug: 'fab');
        $this->client = $this->makeClient($this->t);
        $this->site = ProjectSite::withoutGlobalScopes()->create(['tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'name' => 'Main site']);
        foreach (['company_admin', 'operations_manager', 'finance', 'front_desk', 'field_technician', 'team_lead'] as $code) {
            $this->u[$code] = $this->makeUser($this->t, $code);
        }
    }

    protected function as(string $who)
    {
        return $this->actingAs($this->u[$who]);
    }

    /** The three fields most tests use: a required yes/no that fails on No, a required number with a range, an optional text. */
    protected function schema(): array
    {
        return ['sections' => [['key' => 's1', 'title' => 'Checks', 'fields' => [
            ['key' => 'works', 'type' => 'yes_no', 'label' => 'Works?', 'required' => true, 'fails_on_no' => true],
            ['key' => 'pressure', 'type' => 'number', 'label' => 'Pressure', 'required' => true, 'min' => 5, 'max' => 10, 'unit' => 'bar'],
            ['key' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => false],
        ]]]];
    }

    protected function makeTemplate(string $name = 'Inspection', ?array $schema = null, bool $publish = true): FormTemplate
    {
        $svc = app(FormTemplates::class);
        $tpl = $svc->create($this->t, $this->u['company_admin'], $name, null);
        $svc->saveDraft($tpl, $schema ?? $this->schema());
        if ($publish) {
            $svc->publish($tpl, $this->u['company_admin']);
        }

        return $tpl->fresh();
    }

    protected function makeCategory(array $attrs = []): ServiceCategory
    {
        static $n = 0;
        $n++;

        return ServiceCategory::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->t->id, 'code' => 'CAT-' . $n, 'name' => 'Category ' . $n, 'approval_mode' => 'manual',
            'require_before_photo' => false, 'require_after_photo' => false, 'require_signature' => false,
            'allow_multi_asset_batch' => true, 'is_active' => true,
        ], $attrs));
    }

    protected function makeAsset(?ServiceCategory $cat = null, array $attrs = []): Asset
    {
        static $n = 0;
        $n++;

        return Asset::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $this->t->id, 'client_id' => $this->client->id, 'project_site_id' => $this->site->id,
            'service_category_id' => ($cat ?? $this->makeCategory())->id, 'name' => 'Extinguisher ' . $n, 'status' => 'active',
        ], $attrs));
    }

    /** A request body that passes ServiceRequestRequest. */
    protected function body(ServiceCategory $cat, array $extra = []): array
    {
        return array_merge([
            'client_id' => $this->client->id, 'project_site_id' => $this->site->id, 'service_category_id' => $cat->id,
            'priority' => 'medium', 'title' => 'Routine check',
        ], $extra);
    }

    /** A request created through the form, by the Front Desk. */
    protected function raise(ServiceCategory $cat, array $extra = []): ServiceRequest
    {
        $this->as('front_desk')->post(route('service-requests.store'), $this->body($cat, $extra))->assertSessionHasNoErrors();

        return ServiceRequest::withoutGlobalScopes()->where('tenant_id', $this->t->id)->latest('id')->firstOrFail();
    }

    /** A request in progress for the technician, bound to the category's published checklist. */
    protected function jobInProgress(ServiceCategory $cat, array $proof = []): ServiceRequest
    {
        $sr = $this->raise($cat);
        $sr->forceFill([
            'status' => 'in_progress', 'assigned_tech_user_id' => $this->u['field_technician']->id,
            'proof_rules_snapshot' => array_merge(['require_checklist' => (bool) $cat->require_checklist, 'require_per_asset_checklist' => (bool) $cat->require_per_asset_checklist], $proof),
            'first_punch_in_at' => now()->subHour(), 'tech_accepted_at' => now()->subHours(2),
        ])->save();

        return $sr->fresh();
    }

    /** A request created the way a person does it on the company's own address (the company is then "current", as contracts need). */
    protected function raiseOnHost(ServiceCategory $cat, array $extra = []): ServiceRequest
    {
        $this->as('front_desk')->post($this->onHost($this->t, '/service-requests'), $this->body($cat, $extra))->assertSessionHasNoErrors();

        return ServiceRequest::withoutGlobalScopes()->where('tenant_id', $this->t->id)->latest('id')->firstOrFail();
    }

    protected function save(ServiceRequest $sr, array $answers, array $extra = [])
    {
        return $this->as('field_technician')->post(route('technician.jobs.checklist.save', $sr), array_merge([
            'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(), 'answers' => $answers,
        ], $extra));
    }

    protected function punchOut(ServiceRequest $sr)
    {
        return $this->as('field_technician')->post(route('technician.jobs.punch-out', $sr), ['client_event_uuid' => (string) \Illuminate\Support\Str::uuid()]);
    }

    protected function fullAnswers(): array
    {
        return ['works' => 'yes', 'pressure' => 7];
    }

    protected function publishedVersion(FormTemplate $tpl): FormTemplateVersion
    {
        return $tpl->publishedVersion();
    }
}
