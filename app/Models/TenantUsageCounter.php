<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantUsageCounter extends Model
{
    protected $fillable = [
        'tenant_id', 'year', 'month',
        'requests_count', 'storage_bytes', 'api_requests_count',
        'user_count', 'whatsapp_message_count', 'email_message_count',
        'warnings_json',
    ];

    protected $casts = [
        'warnings_json' => 'array',
    ];

    /**
     * Percent used of a given limit. Returns null when the plan has no limit set
     * for this metric (blueprint: "A plan with no number shows 'No limit'").
     */
    public function percentOf(?int $limit, int $used): ?float
    {
        if ($limit === null || $limit <= 0) {
            return null;
        }

        return round(($used / $limit) * 100, 1);
    }

    /**
     * Has an 80% or 100% warning already been shown for this limit this month?
     * $limitKey is one of 'users', 'requests', 'storage'. $threshold is 80 or 100.
     */
    public function hasWarned(string $limitKey, int $threshold): bool
    {
        return (bool) data_get($this->warnings_json, "{$limitKey}.{$threshold}", false);
    }

    public function markWarned(string $limitKey, int $threshold): void
    {
        $warnings = $this->warnings_json ?? [];
        $warnings[$limitKey][$threshold] = true;
        $this->warnings_json = $warnings;
        $this->save();
    }
}