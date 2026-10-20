<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'is_active',
        'user_limit', 'requests_per_month_limit', 'storage_gb_limit',
        'api_requests_per_minute', 'features',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'features'  => 'array',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function hasFeature(string $key): bool
    {
        return (bool) data_get($this->features, $key, false);
    }
}