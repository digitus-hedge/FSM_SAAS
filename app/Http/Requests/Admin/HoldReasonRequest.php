<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $id       = $this->route('hold_reason')?->id;

        return [
            'label' => [
                'required', 'string', 'max:120',
                Rule::unique('hold_reasons', 'label')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                                         ->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'pauses_sla'    => ['nullable', 'boolean'],
            'requires_note' => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'label'         => 'Reason Label',
            'pauses_sla'    => 'Pauses SLA',
            'requires_note' => 'Requires Note',
            'is_active'     => 'Active',
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Please enter a label for this hold reason.',
            'label.unique'   => 'A hold reason with this label already exists.',
            'label.max'      => 'Label can be at most 120 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label'         => trim((string) $this->input('label')),
            'pauses_sla'    => $this->boolean('pauses_sla'),
            'requires_note' => $this->boolean('requires_note'),
            'is_active'     => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}