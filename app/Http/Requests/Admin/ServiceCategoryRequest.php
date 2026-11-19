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
            'nullable','string','max:40','alpha_dash',   // ← nullable
            Rule::unique('service_categories', 'code')
                ->where('tenant_id', $tenantId)
                ->ignore($categoryId),
        ],
            'name'           => 'required|string|min:2|max:120',
            'service_domain' => 'nullable|string|max:80',
            // FRM-07: the mode is checked against the plan in the controller (APPROVAL_MODE_NOT_IN_PLAN).
            'approval_mode'  => ['required', Rule::in(array_keys(ServiceCategory::APPROVAL_MODES))],
            // DEP-03: the intake and return checklists of a workshop job
            'intake_form_template_id' => ['nullable', 'integer', Rule::exists('form_templates', 'id')->where('tenant_id', $tenantId ?? auth()->user()?->tenant_id)->where('status', 'active')],
            'return_form_template_id' => ['nullable', 'integer', Rule::exists('form_templates', 'id')->where('tenant_id', $tenantId ?? auth()->user()?->tenant_id)->where('status', 'active')],
            // FRM-04: the category's checklist template (an active one of this company)
            'active_form_template_id' => [
                'nullable', 'integer',
                Rule::exists('form_templates', 'id')->where('tenant_id', $tenantId ?? auth()->user()?->tenant_id)->where('status', 'active'),
            ],


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

        $code = trim((string) $this->code);

        $this->merge([
            // 'code' => strtoupper(trim((string) $this->code)),
             'code'          => $code === '' ? null : strtoupper($code),
            // Only a holder of workflow.manage (full) changes the mode; everyone else keeps the saved one (manual for a new category).
            'approval_mode' => \App\Services\Permissions\PermissionLevel::canChange($this->user(), 'workflow.manage') && $this->filled('approval_mode')
                ? $this->input('approval_mode')
                : ($this->route('category')?->approval_mode ?? 'manual'),
            'active_form_template_id' => $this->filled('active_form_template_id') ? $this->input('active_form_template_id') : null,
            'intake_form_template_id' => $this->filled('intake_form_template_id') ? $this->input('intake_form_template_id') : null,
            'return_form_template_id' => $this->filled('return_form_template_id') ? $this->input('return_form_template_id') : null,

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