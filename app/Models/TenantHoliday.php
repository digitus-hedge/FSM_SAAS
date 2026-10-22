<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantHoliday extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'holiday_date',
        'name',
    ];

    protected $casts = [
        'holiday_date' => 'date',
    ];

    /* ---------- Scopes ---------- */

    public function scopeUpcoming($q)
    {
        return $q->whereDate('holiday_date', '>=', now()->toDateString());
    }

    public function scopePast($q)
    {
        return $q->whereDate('holiday_date', '<', now()->toDateString());
    }

    public function scopeInYear($q, int $year)
    {
        return $q->whereYear('holiday_date', $year);
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where('name', 'like', "%{$term}%");
    }
}