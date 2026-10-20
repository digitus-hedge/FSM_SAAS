<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'default_unit_rate',
        'is_billable_default',
        'requires_receipt',
        'is_active',
    ];

    protected $casts = [
        'default_unit_rate'    => 'decimal:2',
        'is_billable_default'  => 'boolean',
        'requires_receipt'     => 'boolean',
        'is_active'            => 'boolean',
    ];

    /* ---------- Scopes ---------- */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;
        return $query->where('name', 'like', "%{$term}%");
    }
}