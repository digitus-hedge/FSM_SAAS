<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const STATUSES = ['active', 'suspended'];

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'tax_id',
        'status',
        'notes',
    ];

    /* ---------- Scopes ---------- */

    public function scopeActive($q)    { return $q->where('status', 'active'); }
    public function scopeSuspended($q) { return $q->where('status', 'suspended'); }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($qq) use ($term) {
            $qq->where('name',         'like', "%{$term}%")
               ->orWhere('contact_name','like', "%{$term}%")
               ->orWhere('email',       'like', "%{$term}%")
               ->orWhere('phone',       'like', "%{$term}%")
               ->orWhere('tax_id',      'like', "%{$term}%");
        });
    }

    /* ---------- Accessors ---------- */

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}