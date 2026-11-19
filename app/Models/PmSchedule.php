<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A calendar maintenance schedule (Preventive-Maintenance spec PMC-01). */
class PmSchedule extends Model
{
    use BelongsToTenant;

    public const FREQUENCIES = [
        'weekly' => 'Every week', 'monthly' => 'Every month', 'quarterly' => 'Every 3 months', 'half_yearly' => 'Every 6 months',
        'yearly' => 'Every year', 'every_n_days' => 'Every N days', 'every_n_months' => 'Every N months',
    ];

    protected $fillable = [
        'tenant_id', 'name', 'project_site_id', 'service_category_id', 'frequency', 'interval_value', 'first_due_date', 'next_due_date',
        'lead_days', 'priority', 'form_template_id', 'default_technician_user_id', 'contract_id', 'skip_if_open', 'status', 'end_date',
        'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'first_due_date' => 'date', 'next_due_date' => 'date', 'end_date' => 'date', 'skip_if_open' => 'boolean',
    ];

    public function site()       { return $this->belongsTo(ProjectSite::class, 'project_site_id'); }
    public function category()   { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
    public function technician() { return $this->belongsTo(User::class, 'default_technician_user_id'); }
    public function contract()   { return $this->belongsTo(Contract::class); }
    public function template()   { return $this->belongsTo(FormTemplate::class, 'form_template_id'); }
    public function runs()       { return $this->hasMany(PmScheduleRun::class)->orderByDesc('due_date'); }
    public function requests()   { return $this->hasMany(ServiceRequest::class, 'pm_schedule_id'); }

    public function assetRows()  { return $this->hasMany(PmScheduleAsset::class); }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'pm_schedule_assets', 'pm_schedule_id', 'asset_id');
    }

    public function getFrequencyLabelAttribute(): string
    {
        $n = $this->interval_value;

        return match ($this->frequency) {
            'every_n_days'   => "Every {$n} days",
            'every_n_months' => "Every {$n} months",
            default          => self::FREQUENCIES[$this->frequency] ?? $this->frequency,
        };
    }
}
