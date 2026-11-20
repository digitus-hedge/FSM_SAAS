<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'w_skill', 'w_availability', 'w_workload', 'w_proximity', 'w_first_fix', 'job_minutes',
        'workload_green_below', 'workload_amber_up_to',
    ];

    public const DEFAULTS = [
        'w_skill' => 35, 'w_availability' => 25, 'w_workload' => 20,
        'w_proximity' => 10, 'w_first_fix' => 10, 'job_minutes' => 120,
        // DSP-06: green below 60 percent of the week's capacity, amber up to 90, red above
        'workload_green_below' => 60, 'workload_amber_up_to' => 90,
    ];

    /** The company's settings row; created with the blueprint defaults the first time it is needed. */
    public static function forTenant(int $tenantId): self
    {
        $row = static::where('tenant_id', $tenantId)->first();
        if (! $row) {
            $row = static::create(['tenant_id' => $tenantId] + self::DEFAULTS);
        }
        return $row;
    }

    public function weights(): array
    {
        return [
            'skill'        => (int) $this->w_skill,
            'availability' => (int) $this->w_availability,
            'workload'     => (int) $this->w_workload,
            'proximity'    => (int) $this->w_proximity,
            'first_fix'    => (int) $this->w_first_fix,
        ];
    }
}
