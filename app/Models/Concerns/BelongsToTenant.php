<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Any Eloquent model with this trait is automatically:
 *   1. Scoped on read  — queries return ONLY rows for the current tenant.
 *   2. Scoped on write — new rows get the current tenant_id auto-filled.
 *
 * To bypass (e.g. a platform-admin console), use:
 *   SomeModel::withoutGlobalScope(TenantScope::class)->get();
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (! $model->tenant_id && $tenant = Tenant::current()) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($tenant = Tenant::current()) {
            $builder->where($model->getTable() . '.tenant_id', $tenant->id);
        }
        // No current tenant = no filter applied. Platform admins and
        // CLI jobs that explicitly scope themselves still work.
    }
}