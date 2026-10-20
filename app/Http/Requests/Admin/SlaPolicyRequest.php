<?php
namespace App\Http\Requests\Admin;

use App\Models\SlaPolicy;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SlaPolicyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        $policyId = $this->route('policy')?->id;
        $tenantId = Tenant::current()?->id;

        return [
            'service_category_id' => 'nullable|exists:service_categories,id',
            'priority' => [
                'required',
                Rule::in(SlaPolicy::PRIORITIES),
                // Only one row per (tenant, category, priority)
                Rule::unique('sla_policies', 'priority')
                    ->where('tenant_id', $tenantId)
                    ->where('service_category_id', $this->input('service_category_id') ?: null)
                    ->ignore($policyId),
            ],
            'response_minutes'    => 'required|integer|min:1|max:525600',   // max 1 year
            'resolution_minutes'  => 'required|integer|min:1|max:525600|gte:response_minutes',
            'uses_business_hours' => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'service_category_id' => 'service category',
            'priority'            => 'priority',
            'response_minutes'    => 'response time',
            'resolution_minutes'  => 'resolution time',
            'uses_business_hours' => 'business-hours flag',
        ];
    }

    public function messages(): array
    {
        return [
            'priority.unique'         => 'A policy for this category + priority already exists. Edit it instead.',
            'resolution_minutes.gte'  => 'Resolution time must be at least the response time.',
            'response_minutes.max'    => 'Response time cannot exceed 1 year (525,600 minutes).',
            'resolution_minutes.max'  => 'Resolution time cannot exceed 1 year.',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'service_category_id' => $this->input('service_category_id') ?: null,
            'uses_business_hours' => $this->boolean('uses_business_hours'),
        ]);
    }
}