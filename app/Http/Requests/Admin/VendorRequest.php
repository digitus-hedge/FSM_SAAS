<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $id       = $this->route('vendor')?->id;

        return [
            'name' => [
                'required', 'string', 'max:180',
                Rule::unique('vendors', 'name')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)
                                         ->whereNull('deleted_at'))
                    ->ignore($id),
            ],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email'        => ['nullable', 'email:rfc', 'max:190'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'tax_id'       => ['nullable', 'string', 'max:60'],
            'status'       => ['required', Rule::in(['active', 'suspended'])],
            'notes'        => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'         => 'Vendor Name',
            'contact_name' => 'Contact Person',
            'email'        => 'Email',
            'phone'        => 'Phone',
            'tax_id'       => 'Tax ID / GSTIN',
            'status'       => 'Status',
            'notes'        => 'Notes',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the vendor name.',
            'name.unique'   => 'A vendor with this name already exists.',
            'email.email'   => 'Please enter a valid email address.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'         => trim((string) $this->input('name')),
            'contact_name' => $this->input('contact_name') ? trim($this->input('contact_name')) : null,
            'email'        => $this->input('email') ? strtolower(trim($this->input('email'))) : null,
            'phone'        => $this->input('phone') ? trim($this->input('phone')) : null,
            'tax_id'       => $this->input('tax_id') ? strtoupper(trim($this->input('tax_id'))) : null,
            'notes'        => $this->input('notes') ? trim($this->input('notes')) : null,
            'status'       => $this->input('status') ?: 'active',
        ]);
    }
}