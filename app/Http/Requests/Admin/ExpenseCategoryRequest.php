<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $id       = $this->route('expense_category')?->id;

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                                        ->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'default_unit_rate'   => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_billable_default' => ['nullable', 'boolean'],
            'requires_receipt'    => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                => 'Category Name',
            'default_unit_rate'   => 'Default Unit Rate',
            'is_billable_default' => 'Billable by Default',
            'requires_receipt'    => 'Requires Receipt',
            'is_active'           => 'Active',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Please enter the expense category name.',
            'name.unique'             => 'This category name is already in use.',
            'default_unit_rate.numeric' => 'Default rate must be a valid number.',
            'default_unit_rate.min'   => 'Default rate cannot be negative.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'                => trim((string) $this->input('name')),
            'default_unit_rate'   => $this->input('default_unit_rate') === '' ? null : $this->input('default_unit_rate'),
            'is_billable_default' => $this->boolean('is_billable_default'),
            'requires_receipt'    => $this->boolean('requires_receipt'),
            'is_active'           => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}