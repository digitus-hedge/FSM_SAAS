<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_code',
        'name',
        'contact_person',
        'email',
        'phone',
        'billing_address',
        'tax_id',
        'payment_terms_days',
        'asset_visibility_enabled',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'asset_visibility_enabled' => 'boolean',
        'is_active'                => 'boolean',
    ];

    /* -----------------------------------------------------------------
     | Relationships
     | ----------------------------------------------------------------- */

    public function contacts()
    {
        return $this->hasMany(ClientContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function sites()
    {
        return $this->hasMany(ProjectSite::class);
    }

     public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /* -----------------------------------------------------------------
     | Scopes
     | ----------------------------------------------------------------- */

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

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('client_code', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}