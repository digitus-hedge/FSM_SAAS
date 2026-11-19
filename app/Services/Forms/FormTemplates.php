<?php

namespace App\Services\Forms;

use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Template and version rules of the Forms-Approvals-Batch spec (FRM-01 to FRM-04, D2, D3, D22 to D24). */
class FormTemplates
{
    public function enabled(?Tenant $tenant): bool
    {
        return (bool) $tenant?->hasFeature('forms');
    }

    /** The number of active templates the plan allows, null when unlimited (D2). */
    public function limit(Tenant $tenant): ?int
    {
        $limit = data_get($tenant->plan?->features, 'form_templates_limit');

        return $limit === null ? null : (int) $limit;
    }

    public function create(Tenant $tenant, User $user, string $name, ?string $description): FormTemplate
    {
        $this->assertRoom($tenant);

        $template = FormTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'description' => $description,
            'status' => 'active', 'created_by_user_id' => $user->id,
        ]);
        $this->newDraft($template, FormSchema::blank());
        $this->audit('form_template.created', $template, ['name' => $name]);

        return $template;
    }

    public function saveDraft(FormTemplate $template, array $schema): FormTemplateVersion
    {
        $schema = FormSchema::normalise($schema);
        $draft  = $template->draftVersion();
        if (! $draft) {
            return $this->newDraft($template, $schema);
        }
        $draft->update(['schema_json' => $schema]);

        return $draft;
    }

    /** "Edit" on a published version: a new draft copy (D3). */
    public function editAsNewDraft(FormTemplate $template): FormTemplateVersion
    {
        if ($draft = $template->draftVersion()) {
            return $draft;
        }
        $latest = $template->versions()->first();

        return $this->newDraft($template, $latest?->schema_json ?? FormSchema::blank());
    }

    public function publish(FormTemplate $template, User $user): FormTemplateVersion
    {
        $draft = $template->draftVersion();
        if (! $draft) {
            throw new FormException('TEMPLATE_INVALID', 'There is no draft to publish.');
        }
        $problems = FormSchema::problems($draft->schema_json ?? []);
        if ($problems) {
            throw new FormException('TEMPLATE_INVALID', $problems[0]);
        }
        if ($template->status === 'archived') {
            throw new FormException('TEMPLATE_INVALID', 'An archived checklist cannot be published.');
        }

        DB::transaction(function () use ($template, $draft, $user) {
            // The two never overlap: the old version retires in the same transaction.
            $template->versions()->where('status', 'published')->get()->each(
                fn (FormTemplateVersion $v) => $v->update(['status' => 'retired', 'retired_at' => now()])
            );
            $draft->update(['status' => 'published', 'published_at' => now(), 'published_by_user_id' => $user->id]);
        });
        $this->audit('form_template.published', $template, ['version' => $draft->version_no]);

        return $draft->fresh();
    }

    public function discardDraft(FormTemplate $template): void
    {
        $template->versions()->where('status', 'draft')->delete();
    }

    public function archive(FormTemplate $template): void
    {
        $template->update(['status' => 'archived']);
        $this->audit('form_template.archived', $template, []);
    }

    public function delete(FormTemplate $template): void
    {
        if ($template->everPublished()) {
            throw new FormException('TEMPLATE_PUBLISHED_IMMUTABLE', 'A checklist that was published cannot be deleted. Archive it instead.');
        }
        $this->audit('form_template.deleted', $template, ['name' => $template->name]);
        $template->versions()->delete();
        $template->delete();
    }

    public function duplicate(Tenant $tenant, User $user, FormTemplate $template): FormTemplate
    {
        $this->assertRoom($tenant);
        $name = $this->freeName($tenant, $template->name . ' (copy)');
        $copy = FormTemplate::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'description' => $template->description,
            'status' => 'active', 'created_by_user_id' => $user->id,
        ]);
        $this->newDraft($copy, $template->versions()->first()?->schema_json ?? FormSchema::blank());
        $this->audit('form_template.created', $copy, ['name' => $name, 'copy_of' => $template->id]);

        return $copy;
    }

    private function assertRoom(Tenant $tenant): void
    {
        $limit = $this->limit($tenant);
        if ($limit !== null && FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')->count() >= $limit) {
            throw new FormException('TEMPLATE_LIMIT', "Your plan allows {$limit} checklists. Archive one or upgrade your plan.");
        }
    }

    private function freeName(Tenant $tenant, string $name): string
    {
        $try = $name;
        $i = 2;
        while (FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $try)->exists()) {
            $try = $name . ' ' . $i++;
        }

        return $try;
    }

    private function newDraft(FormTemplate $template, array $schema): FormTemplateVersion
    {
        $next = (int) FormTemplateVersion::withoutGlobalScopes()->where('form_template_id', $template->id)->max('version_no') + 1;

        return FormTemplateVersion::withoutGlobalScopes()->create([
            'tenant_id' => $template->tenant_id, 'form_template_id' => $template->id,
            'version_no' => $next, 'status' => 'draft', 'schema_json' => $schema,
        ]);
    }

    private function audit(string $action, FormTemplate $template, array $new): void
    {
        app(AuditLogger::class)->log($action, [
            'tenant_id' => $template->tenant_id, 'item_type' => FormTemplate::class, 'item_id' => $template->id, 'new' => $new,
        ]);
    }
}
