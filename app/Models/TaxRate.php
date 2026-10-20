<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'rate_percent', 'is_default', 'is_active',
    ];

    protected $casts = [
        'rate_percent' => 'decimal:2',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function scopeActive($q)  { return $q->where('is_active', true); }
    public function scopeDefault($q) { return $q->where('is_default', true); }

    /** Enforce only one default per tenant */
    protected static function booted(): void
    {
        static::saving(function (self $taxRate) {
            if ($taxRate->is_default && $taxRate->tenant_id) {
                static::query()
                    ->where('tenant_id', $taxRate->tenant_id)
                    ->where('id', '!=', $taxRate->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }
}