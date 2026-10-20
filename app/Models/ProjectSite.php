<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectSite extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id','client_id','site_code','name','site_type',
        'address','city','lat','lng','geofence_radius_meters',
        'contact_name','contact_phone','access_instructions','is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lat'       => 'decimal:7',
        'lng'       => 'decimal:7',
    ];

    public function client() { return $this->belongsTo(Client::class); }

    public function scopeActive($q)        { return $q->where('is_active', true); }
    public function scopeWorkshops($q)     { return $q->where('site_type', 'tenant_workshop'); }
    public function scopeClientSites($q)   { return $q->where('site_type', 'client_site'); }

    public function getIsWorkshopAttribute(): bool
    {
        return $this->site_type === 'tenant_workshop';
    }
}