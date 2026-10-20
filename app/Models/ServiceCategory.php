<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'service_domain',
        'active_form_template_id', 'custom_workflow_json', 'approval_mode',
        'require_before_photo', 'require_after_photo', 'require_signature',
        'require_checklist', 'invoice_mandatory',
        'allow_multi_asset_batch', 'require_per_asset_checklist', 'allow_depot_mode',
        'is_active',
    ];

    protected $casts = [
        'custom_workflow_json'        => 'array',
        'require_before_photo'        => 'boolean',
        'require_after_photo'         => 'boolean',
        'require_signature'           => 'boolean',
        'require_checklist'           => 'boolean',
        'invoice_mandatory'           => 'boolean',
        'allow_multi_asset_batch'     => 'boolean',
        'require_per_asset_checklist' => 'boolean',
        'allow_depot_mode'            => 'boolean',
        'is_active'                   => 'boolean',
    ];

    public const APPROVAL_MODES = [
        'manual'               => 'Manual approval by Ops Manager',
        'auto_if_in_contract'  => 'Auto if covered by contract',
        'always_auto'          => 'Always auto-approve',
        'finance_and_manager'  => 'Requires Finance + Manager',
    ];

    public function scopeActive($q)   { return $q->where('is_active', true); }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        return $q->where(function ($w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('service_domain', 'like', "%{$term}%");
        });
    }
}