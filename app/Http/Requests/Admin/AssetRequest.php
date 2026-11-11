<?php

namespace App\Http\Requests\Admin;

use App\Models\Asset;
use App\Models\ProjectSite;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canDo('asset.manage');
    }

    public function rules(): array
    {
        $tid = Tenant::current()?->id;
        $own = fn (string $table) => Rule::exists($table, 'id')
            ->where('tenant_id', $tid)->whereNull('deleted_at');

        return [
            'client_id'            => ['required', $own('clients')],
            'project_site_id'      => ['required', $own('project_sites')],
            'service_category_id'  => ['required', $own('service_categories')],
            'parent_asset_id'      => ['nullable', $own('assets')],
            'name'                 => ['required', 'string', 'max:180'],
            'manufacturer'         => ['nullable', 'string', 'max:120'],
            'model'                => ['nullable', 'string', 'max:120'],
            'serial_number'        => ['nullable', 'string', 'max:120'],
            'location_within_site' => ['nullable', 'string', 'max:255'],
            'installation_date'    => ['nullable', 'date'],
            'warranty_expiry_date' => ['nullable', 'date', 'after_or_equal:installation_date'],
            'status'               => ['required', Rule::in(['active', 'under_repair', 'inactive'])],
            'notes'                => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id'            => 'client',
            'project_site_id'      => 'site',
            'service_category_id'  => 'service category',
            'parent_asset_id'      => 'parent asset',
            'location_within_site' => 'location within site',
            'installation_date'    => 'installation date',
            'warranty_expiry_date' => 'warranty expiry',
            'serial_number'        => 'serial number',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Assets sit at client sites, and the site must belong to the chosen client.
            $site = ProjectSite::find($this->input('project_site_id'));
            if (! $site || $site->site_type !== 'client_site'
                || (int) $site->client_id !== (int) $this->input('client_id')) {
                $v->errors()->add('project_site_id', 'Choose a site that belongs to the selected client.');
                return;
            }

            /** @var Asset|null $self */
            $self = $this->route('asset');

            // A parent with children cannot move to another site (blueprint 7.2).
            if ($self && (int) $self->project_site_id !== (int) $site->id && $self->children()->exists()) {
                $v->errors()->add('project_site_id', 'This asset has child assets. Move or detach them first.');
                return;
            }

            $parentId = $this->input('parent_asset_id');
            if (! $parentId) {
                return;
            }

            $parent = Asset::find($parentId);
            if (! $parent || (int) $parent->project_site_id !== (int) $site->id) {
                $v->errors()->add('parent_asset_id', 'The parent asset must belong to the same site.');
                return;
            }

            // No loops: walk up from the proposed parent and make sure we never reach this asset.
            if ($self) {
                $cur = $parent;
                for ($i = 0; $cur && $i < 50; $i++) {
                    if ((int) $cur->id === (int) $self->id) {
                        $v->errors()->add('parent_asset_id', 'An asset cannot sit under itself or one of its children.');
                        return;
                    }
                    $cur = $cur->parent_asset_id ? Asset::find($cur->parent_asset_id) : null;
                }
            }
        });
    }
}