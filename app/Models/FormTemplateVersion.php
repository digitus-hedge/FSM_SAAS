<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One version of a checklist template. A published or retired version never changes (D3). */
class FormTemplateVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'form_template_id', 'version_no', 'status', 'schema_json',
        'published_at', 'published_by_user_id', 'retired_at',
    ];

    protected $casts = [
        'schema_json'  => 'array',
        'published_at' => 'datetime',
        'retired_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        // TEMPLATE_PUBLISHED_IMMUTABLE: the content of a published or retired version cannot change, through any code path.
        static::updating(function (self $v) {
            if ($v->isDirty('schema_json') && in_array($v->getOriginal('status'), ['published', 'retired'], true)) {
                throw new \App\Services\Forms\FormException('TEMPLATE_PUBLISHED_IMMUTABLE', 'This version is published and cannot be changed. Create a new draft to edit it.');
            }
        });
    }

    public function template()
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }
}
