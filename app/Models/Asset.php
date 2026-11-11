<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Asset extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    public const STATUSES = ['active', 'under_repair', 'inactive', 'decommissioned'];

    protected $fillable = [
        'tenant_id', 'client_id', 'project_site_id', 'parent_asset_id', 'service_category_id',
        'asset_code', 'qr_hash', 'name', 'manufacturer', 'model', 'serial_number',
        'location_within_site', 'installation_date', 'warranty_expiry_date',
        'default_contract_id', 'status', 'tracks_meter', 'meter_unit',
        'current_meter_reading', 'last_meter_recorded_at', 'photo_attachment_id',
        'specifications_json', 'notes',
    ];

    protected $casts = [
        'installation_date'      => 'date',
        'warranty_expiry_date'   => 'date',
        'tracks_meter'           => 'boolean',
        'current_meter_reading'  => 'decimal:2',
        'last_meter_recorded_at' => 'datetime',
        'specifications_json'    => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (blank($asset->qr_hash)) {
                $asset->qr_hash = bin2hex(random_bytes(20));   // 40 chars, not guessable
            }

            if (blank($asset->asset_code)) {
                $tenantId = $asset->tenant_id ?? Tenant::current()?->id;

                $asset->asset_code = DB::transaction(function () use ($tenantId) {
                    $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

                    $last = (int) Asset::withTrashed()
                        ->where('tenant_id', $tenant->id)
                        ->where('asset_code', 'like', 'AS-%')
                        ->orderByRaw('CAST(SUBSTRING(asset_code, 4) AS UNSIGNED) DESC')
                        ->value(DB::raw('SUBSTRING(asset_code, 4)'));

                    return 'AS-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
                });
            }
        });

        // Blueprint 7.2: a parent and its children must belong to the same site.
        static::saving(function (Asset $asset) {
            if (! $asset->parent_asset_id) {
                return;
            }

            if ($asset->exists && (int) $asset->parent_asset_id === (int) $asset->id) {
                throw ValidationException::withMessages([
                    'parent_asset_id' => 'An asset cannot be its own parent.',
                ]);
            }

            $parent = Asset::withTrashed()->find($asset->parent_asset_id);

            if (! $parent || (int) $parent->project_site_id !== (int) $asset->project_site_id) {
                throw ValidationException::withMessages([
                    'parent_asset_id' => 'The parent asset must belong to the same site.',
                ]);
            }
        });
    }

    public function client()   { return $this->belongsTo(Client::class); }
    public function site()     { return $this->belongsTo(ProjectSite::class, 'project_site_id'); }
    public function category() { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
    public function parent()   { return $this->belongsTo(Asset::class, 'parent_asset_id'); }
    public function children() { return $this->hasMany(Asset::class, 'parent_asset_id'); }

    /** Decommissioned assets cannot be chosen for new service requests. */
    public function scopeSelectable($q)
    {
        return $q->where('status', '!=', 'decommissioned');
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (! $this->warranty_expiry_date) {
            return 'none';
        }
        return $this->warranty_expiry_date->isFuture() || $this->warranty_expiry_date->isToday()
            ? 'in_warranty'
            : 'expired';
    }

    public function getIsDecommissionedAttribute(): bool
    {
        return $this->status === 'decommissioned';
    }
}