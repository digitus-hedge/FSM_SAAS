<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantTerminology extends Model
{
    use BelongsToTenant;

    protected $table   = 'tenant_terminology';
    protected $fillable = ['tenant_id', 'term_key', 'singular', 'plural'];

    /** Default labels — the catalogue of terms you let tenants rename */
    public const DEFAULTS = [
        'service_request' => ['Service request', 'Service requests'],
        'technician'      => ['Technician',      'Technicians'],
        'team_lead'       => ['Team lead',       'Team leads'],
        'client'          => ['Client',          'Clients'],
        'site'            => ['Site',            'Sites'],
        'asset'           => ['Asset',           'Assets'],
        'contract'        => ['Contract',        'Contracts'],
        'quote'           => ['Quote',           'Quotes'],
        'invoice'         => ['Invoice',         'Invoices'],
        'qc_review'       => ['QC review',       'QC reviews'],
    ];

    /** Fetch the current tenant's label — falls back to the default */
    public static function label(string $key, bool $plural = false): string
    {
        $tenantId = Tenant::current()?->id;
        $row = $tenantId
            ? static::where('tenant_id', $tenantId)->where('term_key', $key)->first()
            : null;

        if ($row) return $plural ? $row->plural : $row->singular;

        $d = self::DEFAULTS[$key] ?? [$key, $key];
        return $plural ? $d[1] : $d[0];
    }
}