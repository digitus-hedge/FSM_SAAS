<?php
namespace App\Http\Requests\Admin;

use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;
        $tenantId   = Tenant::current()?->id;

        return [
            'code' => [
                'required','string','max:40','alpha_dash',
                Rule::unique('service_categories', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($categoryId),
            ],
            'name'           => 'required|string|min:2|max:120',
            'service_domain' => 'nullable|string|max:80',
            'approval_mode'  => ['required', Rule::in(array_keys(ServiceCategory::APPROVAL_MODES))],

            // Proof rules
            'require_before_photo' => 'sometimes|boolean',
            'require_after_photo'  => 'sometimes|boolean',
            'require_signature'    => 'sometimes|boolean',
            'require_checklist'    => 'sometimes|boolean',
            'invoice_mandatory'    => 'sometimes|boolean',

            // Batch + depot
            'allow_multi_asset_batch'     => 'sometimes|boolean',
            'require_per_asset_checklist' => 'sometimes|boolean',
            'allow_depot_mode'            => 'sometimes|boolean',

            'is_active' => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'code'                        => 'category code',
            'name'                        => 'category name',
            'service_domain'              => 'service domain',
            'approval_mode'               => 'approval mode',
            'require_before_photo'        => 'before photo requirement',
            'require_after_photo'         => 'after photo requirement',
            'require_signature'           => 'signature requirement',
            'require_checklist'           => 'checklist requirement',
            'invoice_mandatory'           => 'invoice-mandatory flag',
            'allow_multi_asset_batch'     => 'multi-asset batch flag',
            'require_per_asset_checklist' => 'per-asset checklist flag',
            'allow_depot_mode'            => 'depot mode flag',
            'is_active'                   => 'active status',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'   => 'A category code is required.',
            'code.alpha_dash' => 'Code can only contain letters, digits, dashes, underscores.',
            'code.unique'     => 'This category code is already used in your workspace.',
            'name.required'   => 'Please enter the category name.',
            'name.min'        => 'Category name is too short (min 2 characters).',
            'approval_mode.required' => 'Please choose an approval mode.',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            // Normalise all booleans
            'require_before_photo'        => $this->boolean('require_before_photo'),
            'require_after_photo'         => $this->boolean('require_after_photo'),
            'require_signature'           => $this->boolean('require_signature'),
            'require_checklist'           => $this->boolean('require_checklist'),
            'invoice_mandatory'           => $this->boolean('invoice_mandatory'),
            'allow_multi_asset_batch'     => $this->boolean('allow_multi_asset_batch'),
            'require_per_asset_checklist' => $this->boolean('require_per_asset_checklist'),
            'allow_depot_mode'            => $this->boolean('allow_depot_mode'),
        ]);
    }
}