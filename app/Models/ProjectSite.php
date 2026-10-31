<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProjectSite extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'site_code',
        'name',
        'site_type',
        'address',
        'city',
        'lat',
        'lng',
        'geofence_radius_meters',
        'always_enforce_geofence',   // ← added
        'contact_name',
        'contact_phone',
        'access_instructions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'always_enforce_geofence' => 'boolean',   // ← added
        'lat'       => 'decimal:7',
        'lng'       => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProjectSite $site) {
            if (blank($site->site_code)) {
                $tenantId = $site->tenant_id
                    ?? Tenant::current()?->id;

                $site->site_code = DB::transaction(function () use ($tenantId) {
                    // Lock the tenant row to avoid concurrent code generation.
                    $tenant = Tenant::query()
                        ->whereKey($tenantId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lastNumber = (int) ProjectSite::withTrashed()
                        ->where('tenant_id', $tenant->id)
                        ->where('site_code', 'like', 'ST-%')
                        ->orderByRaw(
                            "CAST(SUBSTRING(site_code, 4) AS UNSIGNED) DESC"
                        )
                        ->value(DB::raw('SUBSTRING(site_code, 4)'));

                    return 'ST-' . str_pad(
                        $lastNumber + 1,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );
                });
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeWorkshops($q)
    {
        return $q->where('site_type', 'tenant_workshop');
    }

    public function scopeClientSites($q)
    {
        return $q->where('site_type', 'client_site');
    }

    public function getIsWorkshopAttribute(): bool
    {
        return $this->site_type === 'tenant_workshop';
    }
}
