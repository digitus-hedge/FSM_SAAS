<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HoldReason extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'label',
        'pauses_sla',
        'requires_note',
        'is_active',
    ];

    protected $casts = [
        'pauses_sla'    => 'boolean',
        'requires_note' => 'boolean',
        'is_active'     => 'boolean',
    ];

    /* ---------- Scopes ---------- */

    public function scopeActive($q)   { return $q->where('is_active', true); }
    public function scopeInactive($q) { return $q->where('is_active', false); }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where('label', 'like', "%{$term}%");
    }
}