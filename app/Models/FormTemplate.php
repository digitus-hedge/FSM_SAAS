<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A checklist template (Forms-Approvals-Batch spec FRM-01). Its content lives in versions. */
class FormTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description', 'status', 'created_by_user_id'];

    public function versions()
    {
        return $this->hasMany(FormTemplateVersion::class)->orderByDesc('version_no');
    }

    public function publishedVersion(): ?FormTemplateVersion
    {
        return $this->versions()->where('status', 'published')->first();
    }

    public function draftVersion(): ?FormTemplateVersion
    {
        return $this->versions()->where('status', 'draft')->first();
    }

    /** True once any version was published: such a template can only be archived (D22). */
    public function everPublished(): bool
    {
        return $this->versions()->whereIn('status', ['published', 'retired'])->exists();
    }
}
