<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantBusinessHour extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'weekday',
        'opens_at',
        'closes_at',
        'is_open',
    ];

    protected $casts = [
        'weekday'   => 'integer',
        'is_open'   => 'boolean',
    ];

    /* ---------- Weekday reference ---------- */

    public const WEEKDAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        0 => 'Sunday',
    ];

    public const SHORT_LABELS = [
        1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
        5 => 'Fri', 6 => 'Sat', 0 => 'Sun',
    ];

    public function getWeekdayNameAttribute(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '—';
    }

    /* ---------- Static helpers ---------- */

    /**
     * Return the tenant's 7 rows, creating any missing ones with sensible defaults.
     * Order: Mon–Fri 09:00-18:00 open, Sat 09:00-13:00 open, Sun closed.
     */
    public static function ensureForTenant(int $tenantId): \Illuminate\Support\Collection
    {
        $defaults = [
            1 => ['09:00:00', '18:00:00', true],
            2 => ['09:00:00', '18:00:00', true],
            3 => ['09:00:00', '18:00:00', true],
            4 => ['09:00:00', '18:00:00', true],
            5 => ['09:00:00', '18:00:00', true],
            6 => ['09:00:00', '13:00:00', true],
            0 => [null,       null,       false],
        ];

        foreach ($defaults as $wd => [$open, $close, $isOpen]) {
            static::firstOrCreate(
                ['tenant_id' => $tenantId, 'weekday' => $wd],
                ['opens_at' => $open, 'closes_at' => $close, 'is_open' => $isOpen]
            );
        }

        return static::where('tenant_id', $tenantId)
            ->orderByRaw('CASE weekday WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3 WHEN 4 THEN 4 WHEN 5 THEN 5 WHEN 6 THEN 6 WHEN 0 THEN 7 END')
            ->get()
            ->keyBy('weekday');
    }
}