<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantUsageCounter extends Model
{
    protected $fillable = [
        'tenant_id', 'year', 'month',
        'requests_count', 'storage_bytes', 'api_requests_count',
    ];
}