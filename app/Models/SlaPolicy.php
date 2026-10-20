<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sla_policies';

    protected $fillable = [
        'tenant_id', 'service_category_id', 'priority',
        'response_minutes', 'resolution_minutes', 'uses_business_hours',
    ];

    protected $casts = [
        'uses_business_hours' => 'boolean',
        'response_minutes'    => 'integer',
        'resolution_minutes'  => 'integer',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical', 'emergency'];

    public const PRIORITY_LABELS = [
        'low'       => 'Low',
        'medium'    => 'Medium',
        'high'      => 'High',
        'critical'  => 'Critical',
        'emergency' => 'Emergency',
    ];

    public function category() { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }

    /** For sorting rows in a predictable order */
    public function scopeOrderedByPriority($q)
    {
        return $q->orderByRaw("FIELD(priority, 'emergency','critical','high','medium','low')");
    }

    /** Format minutes as "2h 15m" or "3d 4h" */
    public static function humanDuration(int $minutes): string
    {
        if ($minutes === 0) return '0m';
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;
        $parts = [];
        if ($days)  $parts[] = $days.'d';
        if ($hours) $parts[] = $hours.'h';
        if ($mins)  $parts[] = $mins.'m';
        return implode(' ', $parts);
    }

    public function getResponseHumanAttribute(): string
    {
        return self::humanDuration($this->response_minutes);
    }

    public function getResolutionHumanAttribute(): string
    {
        return self::humanDuration($this->resolution_minutes);
    }
}