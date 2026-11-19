<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'reopen_window_days', 'feedback_link_days',
        'client_sign_in_link_hours', 'require_two_factor',
        'overdue_reminders_enabled', 'overdue_first_after_days', 'overdue_repeat_days', 'overdue_max_reminders',
    ];

    protected $casts = ['require_two_factor' => 'boolean', 'overdue_reminders_enabled' => 'boolean'];

    /** The company's settings row, created with the defaults when it does not exist yet. */
    public static function forTenant(int $tenantId): self
    {
        $setting = static::firstOrCreate(['tenant_id' => $tenantId]);

        // A row just created only holds the attributes we gave it. Reload so the column defaults are filled in.
        return $setting->wasRecentlyCreated ? $setting->fresh() : $setting;
    }
}