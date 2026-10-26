<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const STATUSES = [
        'pending'          => 'Pending',
        'quoted'           => 'Quoted',
        'approved'         => 'Approved',
        'assigned'         => 'Assigned',
        'in_progress'      => 'In Progress',
        'pending_review'   => 'Pending Review',
        'awaiting_invoice' => 'Awaiting Invoice',
        'rework'           => 'Rework',
        'completed'        => 'Completed',
        'cancelled'        => 'Cancelled',
    ];

    public const PRIORITIES = [
        'low'       => 'Low',
        'medium'    => 'Medium',
        'high'      => 'High',
        'critical'  => 'Critical',
        'emergency' => 'Emergency',
    ];

    public const PRIORITY_COLORS = [
        'low'       => 'gray',
        'medium'    => 'amber',
        'high'      => 'orange',
        'critical'  => 'red',
        'emergency' => 'red',
    ];

    protected $fillable = [
        'tenant_id',
        'sr_number',
        'parent_sr_id',
        'is_master_sr',
        'client_id',
        'project_site_id',
        'service_category_id',
        'primary_asset_id',
        'contract_id',
        'pm_schedule_id',
        'vendor_id',
        'service_mode',
        'source',
        'coverage_type',
        'coverage_override_by_user_id',
        'coverage_override_reason',
        'contract_choice_pending',
        'quote_requested_at',
        'follow_up_of_sr_id',
        'invoice_mandatory',
        'enforce_geofence',
        'allow_signature_waiver',
        'priority',
        'status',
        'custom_stage_code',
        'is_on_hold',
        'current_hold_reason_id',
        'billing_status',
        'title',
        'description',
        'client_po_reference',
        'created_by_user_id',
        'approved_by_user_id',
        'team_lead_user_id',
        'assigned_tech_user_id',
        'daily_route_sequence',
        'form_template_version_id',
        'sla_clock_started_at',
        'sla_paused_at',
        'sla_paused_minutes',
        'sla_response_due_at',
        'sla_resolution_due_at',
        'sla_response_warned_at',
        'sla_resolution_warned_at',
        'sla_response_breached',
        'sla_resolution_breached',
        'scheduled_eta_at',
        'tech_accepted_at',
        'first_punch_in_at',
        'last_punch_out_at',
        'total_work_minutes',
        'total_hold_minutes',
        'rework_count',
        'reopened_count',
        'proof_rules_snapshot',
        'sr_checklist_payload',
        'customer_signer_name',
        'customer_signer_phone',
        'customer_signature_attachment_id',
        'signature_waiver_otp_id',
        'signature_gps_lat',
        'signature_gps_lng',
        'signed_at',
        'qc_reviewed_by_user_id',
        'qc_reviewed_at',
        'qc_rejection_reason',
        'cancellation_reason',
        'completed_at',
        'client_star_rating',
        'client_nps_score',
        'client_feedback_comment',
        'hold_started_at'
    ];

    protected $casts = [
        'is_master_sr'            => 'boolean',
        'contract_choice_pending' => 'boolean',
        'invoice_mandatory'       => 'boolean',
        'enforce_geofence'        => 'boolean',
        'allow_signature_waiver'  => 'boolean',
        'is_on_hold'              => 'boolean',
        'sla_response_breached'   => 'boolean',
        'sla_resolution_breached' => 'boolean',
        'proof_rules_snapshot'    => 'array',
        'sr_checklist_payload'    => 'array',
        'quote_requested_at'      => 'datetime',
        'sla_clock_started_at'    => 'datetime',
        'sla_paused_at'           => 'datetime',
        'sla_response_due_at'     => 'datetime',
        'sla_resolution_due_at'   => 'datetime',
        'sla_response_warned_at'  => 'datetime',
        'sla_resolution_warned_at' => 'datetime',
        'scheduled_eta_at'        => 'datetime',
        'tech_accepted_at'        => 'datetime',
        'first_punch_in_at'       => 'datetime',
        'last_punch_out_at'       => 'datetime',
        'qc_reviewed_at'          => 'datetime',
        'completed_at'            => 'datetime',
        'signed_at'               => 'datetime',
        'signature_gps_lat'       => 'decimal:7',
        'signature_gps_lng'       => 'decimal:7',
            'hold_started_at' => 'datetime',
    ];

    /* ---------- Relationships ---------- */

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function site()
    {
        return $this->belongsTo(ProjectSite::class, 'project_site_id');
    }
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
    public function assignedTech()
    {
        return $this->belongsTo(User::class, 'assigned_tech_user_id');
    }
    public function teamLead()
    {
        return $this->belongsTo(User::class, 'team_lead_user_id');
    }
    public function holdReason()
    {
        return $this->belongsTo(HoldReason::class, 'current_hold_reason_id');
    }

    
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_sr_id');
    }
    public function children()
    {
        return $this->hasMany(self::class, 'parent_sr_id');
    }

    /* ---------- Scopes ---------- */

    public function scopeStatus($q, ?string $s)
    {
        return $s ? $q->where('status', $s)      : $q;
    }
    public function scopePriority($q, ?string $p)
    {
        return $p ? $q->where('priority', $p)    : $q;
    }
    public function scopeForClient($q, $id)
    {
        return $id ? $q->where('client_id', $id) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($qq) use ($term) {
            $qq->where('sr_number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('client_po_reference', 'like', "%{$term}%");
        });
    }

    /* ---------- Helpers ---------- */

    public static function nextSrNumber(int $tenantId, ?int $year = null): string
    {
        $year ??= (int) now()->year;

        // Find the highest sequence for this tenant & year
        $last = static::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('sr_number', 'like', "SR-{$year}-%")
            ->orderByDesc('id')
            ->value('sr_number');

        $n = 1;
        if ($last && preg_match('/SR-\d{4}-(\d+)$/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return sprintf('SR-%d-%06d', $year, $n);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }
    public function priorityColor(): string
    {
        return self::PRIORITY_COLORS[$this->priority] ?? 'gray';
    }

    /**
     * Find the SLA policy that applies to this SR.
     * Priority: category + priority match, then tenant-default (null category) + priority match.
     */

    // public function resolveSlaPolicy(): ?SlaPolicy
    // {
    //     return SlaPolicy::where('tenant_id', $this->tenant_id)
    //         ->where('priority', $this->priority)
    //         ->orderByRaw('CASE WHEN service_category_id = ? THEN 0 ELSE 1 END', [$this->service_category_id])
    //         ->whereIn('service_category_id', [$this->service_category_id, null])
    //         ->first();
    // }


    public function resolveSlaPolicy(): ?SlaPolicy
    {
        // 1. Exact match: tenant + category + priority
        $exact = SlaPolicy::where('tenant_id', $this->tenant_id)
            ->where('priority', $this->priority)
            ->where('service_category_id', $this->service_category_id)
            ->first();
        if ($exact) return $exact;

        // 2. Tenant default: same priority, no category
        $tenantDefault = SlaPolicy::where('tenant_id', $this->tenant_id)
            ->where('priority', $this->priority)
            ->whereNull('service_category_id')
            ->first();
        if ($tenantDefault) return $tenantDefault;

        // 3. Loose fallback: ANY policy for this category (any priority), closest priority first
        $byCategory = SlaPolicy::where('tenant_id', $this->tenant_id)
            ->where('service_category_id', $this->service_category_id)
            ->orderByRaw("FIELD(priority, ?, 'low', 'medium', 'high', 'critical', 'emergency')", [$this->priority])
            ->first();
        if ($byCategory) return $byCategory;

        // 4. Last resort: ANY policy for this tenant
        return SlaPolicy::where('tenant_id', $this->tenant_id)
            ->orderByRaw("FIELD(priority, ?, 'low', 'medium', 'high', 'critical', 'emergency')", [$this->priority])
            ->first();
    }
    


    /**
 * Return the list of attachments stored on disk for this SR.
 * Each item: ['filename' => stored name, 'display' => original name, 'url' => public URL, 'size' => bytes, 'type' => mime group]
 */
public function attachmentsRel()
{
    return $this->morphMany(Attachment::class, 'attachable')->orderBy('id', 'desc');
}

/**
 * Backward-compatible helper — returns the array format the Blade views already use.
 */

public function attachments(): array
{
    return $this->attachmentsRel
        ->filter(fn ($a) => !empty($a->storage_path))
        ->map(fn ($a) => [
            'id'       => $a->id,
            'filename' => basename($a->storage_path),
            'display'  => $a->original_name ?? basename($a->storage_path),
            'url'      => $a->url,
            'size'     => $a->size_bytes,
            'type'     => $a->is_image ? 'image' : 'file',
            'ext'      => $a->ext,
        ])
        ->values()
        ->toArray();
}



    /**
     * Live SLA state as of now().
     * Returns one of: not_started | on_track | at_risk | response_breached | resolution_breached.
     */
    public function slaState(): string
    {
        if (!$this->sla_clock_started_at) return 'not_started';

        $now = now();
        if ($this->sla_resolution_due_at && $now->greaterThan($this->sla_resolution_due_at)) {
            return 'resolution_breached';
        }
        if ($this->sla_response_due_at && !$this->tech_accepted_at && $now->greaterThan($this->sla_response_due_at)) {
            return 'response_breached';
        }
        // "At risk" = within 20% of resolution window
        if ($this->sla_resolution_due_at) {
            $window = $this->sla_clock_started_at->diffInMinutes($this->sla_resolution_due_at);
            $left   = $now->diffInMinutes($this->sla_resolution_due_at, false);
            if ($window > 0 && $left >= 0 && $left < max(15, $window * 0.2)) return 'at_risk';
        }
        return 'on_track';
    }

    public function slaStateLabel(): string
    {
        return match ($this->slaState()) {
            'not_started'         => 'Not started',
            'on_track'            => 'On track',
            'at_risk'             => 'At risk',
            'response_breached'   => 'Response breached',
            'resolution_breached' => 'Resolution breached',
        };
    }

    public function slaStateColor(): string
    {
        return match ($this->slaState()) {
            'on_track'  => 'green',
            'at_risk'   => 'amber',
            default     => $this->sla_clock_started_at ? 'red' : 'gray',
        };
    }
}
