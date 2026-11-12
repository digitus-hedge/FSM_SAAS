<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Contract extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    public const TYPES        = ['amc' => 'AMC', 'warranty' => 'Warranty', 'adhoc' => 'Ad-hoc', 'dlp' => 'DLP'];
    public const STATUSES     = ['draft' => 'Draft', 'active' => 'Active', 'expiring_soon' => 'Expiring soon', 'expired' => 'Expired', 'renewed' => 'Renewed', 'cancelled' => 'Cancelled'];
    public const CALL_PERIODS = ['contract_term' => 'Whole contract term', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
    /** Base roles that may see contract money and contract files (decision A1, option 2: not Front Desk). */
    public const MONEY_ROLES = ['company_admin', 'ops_manager', 'finance'];
    public const MAX_FILES   = 5;
    public const MAX_FILE_KB = 10240;

    protected $fillable = [
        'tenant_id', 'contract_number', 'client_id', 'contract_type', 'status',
        'start_date', 'end_date', 'response_sla_minutes', 'resolution_sla_minutes',
        'max_calls', 'call_period', 'calls_used_current_period', 'period_started_on',
        'annual_value', 'payment_terms', 'auto_renew', 'renewed_from_contract_id', 'notes',
        'activated_at', 'cancelled_at', 'cancel_reason', 'auto_renewed_at',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'period_started_on' => 'date',
        'annual_value'      => 'decimal:3',
        'auto_renew'        => 'boolean',
        'activated_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
        'auto_renewed_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contract $c) {
            $c->tenant_id = $c->tenant_id ?? Tenant::current()?->id;

            if (blank($c->contract_number)) {
                $c->contract_number = static::nextNumber((int) $c->tenant_id);
            }
            if (! $c->period_started_on) {
                $c->period_started_on = $c->start_date;
            }
        });

        // Blueprint 11: a foreign key between two tenant tables must belong to the same tenant.
        static::saving(function (Contract $c) {
            if (! Client::query()->whereKey($c->client_id)->exists()) {   // tenant scope applies
                throw ValidationException::withMessages(['client_id' => 'Choose a client from your own company.']);
            }
        });
    }

    /**
     * Next number from tenant_sequences, with a row lock.
     * The table has no key column: the contract sequence is the row whose prefix is CT
     * (to switch to CON later, change that row's prefix and pattern). Pattern example: CT-YYYY-000001,
     * where YYYY becomes the year and the trailing digits set the width of the counter.
     */
    protected const SEQUENCE_PREFIX = 'CT';

    protected static function nextNumber(int $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $now  = now();
            $year = (int) $now->format('Y');
            $p    = static::SEQUENCE_PREFIX;

            $row = DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)->where('prefix', $p)
                ->lockForUpdate()->first();

            if (! $row) {
                $id = DB::table('tenant_sequences')->insertGetId([
                    'tenant_id' => $tenantId, 'prefix' => $p, 'pattern' => $p . '-YYYY-000001',
                    'reset_yearly' => 1, 'current_year' => $year, 'next_number' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $row = DB::table('tenant_sequences')->where('id', $id)->lockForUpdate()->first();
            }

            $seq = (int) $row->next_number;
            if ($row->reset_yearly && (int) $row->current_year !== $year) {
                $seq = 1;
            }

            DB::table('tenant_sequences')->where('id', $row->id)->update([
                'next_number' => $seq + 1, 'current_year' => $year, 'updated_at' => $now,
            ]);

            $pattern = (string) $row->pattern;
            $width   = preg_match('/(\d+)$/', $pattern, $m) ? strlen($m[1]) : 6;
            $head    = preg_replace('/\d+$/', '', $pattern);

            return str_replace('YYYY', (string) $year, $head) . str_pad((string) $seq, $width, '0', STR_PAD_LEFT);
        });
    }

    public function client()     { return $this->belongsTo(Client::class); }
    public function sites()      { return $this->belongsToMany(ProjectSite::class, 'contract_sites', 'contract_id', 'project_site_id'); }
    public function categories() { return $this->belongsToMany(ServiceCategory::class, 'contract_categories', 'contract_id', 'service_category_id'); }

    /**
     * Replace the covered sites and categories. An empty list means "all"
     * (all of the client's sites, all categories), per blueprint 7.4.
     */
    public function syncCoverage(array $siteIds, array $categoryIds): void
    {
        $now = now();
        $tenantId = $this->tenant_id;

        DB::table('contract_sites')->where('contract_id', $this->id)->delete();
        DB::table('contract_categories')->where('contract_id', $this->id)->delete();

        foreach (array_unique($siteIds) as $id) {
            DB::table('contract_sites')->insert(['tenant_id' => $tenantId, 'contract_id' => $this->id, 'project_site_id' => (int) $id, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (array_unique($categoryIds) as $id) {
            DB::table('contract_categories')->insert(['tenant_id' => $tenantId, 'contract_id' => $this->id, 'service_category_id' => (int) $id, 'created_at' => $now, 'updated_at' => $now]);
        }
    }


    public function documents() { return $this->morphMany(Attachment::class, 'attachable')->where('kind', 'document'); }

    /* ---------------------------------------------------------------- state */

    /** D14: after activation the client and the type cannot change. */
    public function isLocked(): bool { return $this->status !== 'draft'; }

    public function isCancellable(): bool { return in_array($this->status, ['draft', 'active', 'expiring_soon'], true); }

    public function isRenewable(): bool
    {
        return in_array($this->status, ['active', 'expiring_soon', 'expired'], true)
            && ! static::withoutGlobalScopes()->where('renewed_from_contract_id', $this->id)->exists();
    }

    /** Only a Draft with no requests can be deleted. */
    public function isDeletable(): bool
    {
        return $this->status === 'draft'
            && ! DB::table('service_requests')->where('contract_id', $this->id)->exists();
    }

    /** Status after activation: Expiring soon when the end is within 90 days. */
    protected function statusForToday(): string
    {
        return $this->end_date && $this->end_date->lte(today()->addDays(90)) ? 'expiring_soon' : 'active';
    }

    public function activate(): void
    {
        if ($this->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft can be activated.']);
        }
        if ($this->end_date->lt(today())) {
            throw ValidationException::withMessages(['end_date' => 'The end date has already passed. Change the dates before you activate.']);
        }

        DB::transaction(function () {
            $this->forceFill(['status' => $this->statusForToday(), 'activated_at' => now()])->save();

            // Activating a renewal makes the old contract Renewed (6.11).
            if ($this->renewed_from_contract_id) {
                static::whereKey($this->renewed_from_contract_id)
                    ->whereIn('status', ['active', 'expiring_soon', 'expired'])
                    ->update(['status' => 'renewed']);
            }
        });
    }

    public function cancel(string $reason): void
    {
        if (! $this->isCancellable()) {
            throw ValidationException::withMessages(['cancel_reason' => 'This contract cannot be cancelled.']);
        }
        $this->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
    }

    /**
     * Renew by hand (6.11): a Draft copy that starts the day after this one ends.
     * Same terms, sites, categories, SLA, cap, value and payment terms; calls used 0.
     */
    public function renewAsDraft(): Contract
    {
        $start = $this->start_date->copy();
        $end   = $this->end_date->copy();

        // Whole months/years: the end is the day before an anniversary of the start.
        $months = (int) floor($start->diffInMonths($end->copy()->addDay()));
        $wholeMonths = $months > 0 && $start->copy()->addMonthsNoOverflow($months)->equalTo($end->copy()->addDay());

        $newStart = $end->copy()->addDay();
        $newEnd   = $wholeMonths
            ? $newStart->copy()->addMonthsNoOverflow($months)->subDay()
            : $newStart->copy()->addDays((int) $start->diffInDays($end));

        return DB::transaction(function () use ($newStart, $newEnd) {
            $copy = static::create([
                'client_id'               => $this->client_id,
                'contract_type'           => $this->contract_type,
                'status'                  => 'draft',
                'start_date'              => $newStart,
                'end_date'                => $newEnd,
                'response_sla_minutes'    => $this->response_sla_minutes,
                'resolution_sla_minutes'  => $this->resolution_sla_minutes,
                'max_calls'               => $this->max_calls,
                'call_period'             => $this->call_period,
                'calls_used_current_period' => 0,
                'annual_value'            => $this->annual_value,
                'payment_terms'           => $this->payment_terms,
                'auto_renew'              => $this->auto_renew,
                'renewed_from_contract_id' => $this->id,
                'notes'                   => $this->notes,
            ]);
            $copy->syncCoverage(
                $this->sites()->pluck('project_sites.id')->all(),
                $this->categories()->pluck('service_categories.id')->all()
            );
            return $copy;
        });
    }

    /* --------------------------------------------------------------- access */

    public static function baseRoleOf($user): ?string
    {
        if (! $user) return null;
        try {
            return DB::table('roles')->where('id', $user->role_id)->value('base_role');
        } catch (\Throwable $e) {
            return null;   // unknown role: treat as no access to money
        }
    }

    /** Value, payment terms and files: Company Admin, Operations Manager and Finance. */
    public static function canSeeMoney($user = null): bool
    {
        return in_array(static::baseRoleOf($user ?? auth()->user()), self::MONEY_ROLES, true);
    }

    /** The cap is set and every call is used. */
    public function capReached(): bool
    {
        return $this->max_calls !== null && $this->calls_used_current_period >= $this->max_calls;
    }

    public function scopeWithCap($q) { return $q->whereNotNull('max_calls'); }

    public function getTypeLabelAttribute(): string   { return self::TYPES[$this->contract_type] ?? $this->contract_type; }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
