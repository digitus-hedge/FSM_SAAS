<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $tenantId = auth()->user()->tenant_id;

                if ($tenantId !== null) {
                    $builder->where(
                        $builder->getModel()->qualifyColumn('tenant_id'),
                        $tenantId
                    );
                }
            }
        });

        static::creating(function (Model $model) {
            if (
                auth()->check() &&
                $model->getAttribute('tenant_id') === null
            ) {
                $model->setAttribute(
                    'tenant_id',
                    auth()->user()->tenant_id
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Tenant::class,
            'tenant_id'
        );
    }
}
