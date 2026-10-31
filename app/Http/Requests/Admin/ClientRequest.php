<?php
namespace App\Http\Requests\Admin;   // or Admin — match your folder

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
{
    $clientId = $this->route('client')?->id;
    $tenantId = Tenant::current()?->id;

    return [
        'client_code' => [
            'nullable',
            'string',
            'min:2',
            'max:40',
            'regex:/^[A-Za-z0-9_\-]+$/',
            Rule::unique('clients', 'client_code')
                ->where('tenant_id', $tenantId)
                ->ignore($clientId),
        ],
        'name' => [
            'required',
            'string',
            'min:2',
            'max:180',
            // letters, digits, spaces, and common business-name punctuation only
            'regex:/^[\p{L}\p{N}\s.,&\'\-\/()]+$/u',
        ],
        'contact_person' => [
            'nullable',
            'string',
            'max:150',
            'regex:/^[\p{L}\s.,\'\-]+$/u', // letters/spaces/punctuation, no digits
        ],
        'email' => [
            'nullable',
            'email:rfc,dns',
            'max:190',
            Rule::unique('clients', 'email')
                ->where('tenant_id', $tenantId)
                ->ignore($clientId),
        ],
        'phone' => [
            'nullable',
            'string',
            'min:7',   // added: block single/few-digit junk entries
            'max:40',
            'regex:/^[0-9+\-\s()]+$/',
            Rule::unique('clients', 'phone')
                ->where('tenant_id', $tenantId)
                ->ignore($clientId),
        ],
        'tax_id' => [
            'nullable',
            'string',
            'min:5',    // added: block 1-4 char junk entries
            'max:60',
            'regex:/^[A-Za-z0-9\-]+$/', // added: alphanumeric + dash only, no spaces/symbols
        ],
        'payment_terms_days' => [
            'required',
            'integer',
            'min:0',
            'max:365',
        ],
        'asset_visibility_enabled' => [
            'sometimes',
            'boolean',
        ],
        'is_active' => [
            'sometimes',
            'boolean',
        ],
        'billing_address' => [
            'nullable',
            'string',
            'max:1000',
        ],
        'notes' => [
            'nullable',
            'string',
            'max:2000',
        ],
    ];
}
    /** Friendly field names in error messages */
    public function attributes(): array
    {
        return [
            'client_code'              => 'client code',
            'name'                     => 'name',
            'contact_person'           => 'contact person',
            'email'                    => 'email',
            'phone'                    => 'phone',
            'tax_id'                   => 'tax ID',
            'payment_terms_days'       => 'payment terms',
            'asset_visibility_enabled' => 'asset visibility',
            'is_active'                => 'active status',
            'billing_address'          => 'billing address',
            'notes'                    => 'internal notes',
        ];
    }

    /** Custom per-rule messages */
    public function messages(): array
    {
        return [
       
            'client_code.regex'               => 'Client code can only contain letters, digits, dashes and underscores.',
            'client_code.unique'              => 'This client code is already used in your workspace. Pick another.',
            'client_code.min'                 => 'Client code must be at least 2 characters.',
            'client_code.max'                 => 'Client code is too long (max 40 characters).',

            'name.required'                   => 'Please enter the client name.',
            'name.min'                        => 'Client name is too short (min 2 characters).',

            'email.email'                     => 'That is not a valid email address.',
            'email.max'                       => 'Email is too long (max 190 characters).',

            'phone.regex'                     => 'Phone can contain digits, +, -, spaces and brackets only.',

            'payment_terms_days.required'     => 'Payment terms are required (use 30 if unsure).',
            'payment_terms_days.integer'      => 'Payment terms must be a whole number of days.',
            'payment_terms_days.min'          => 'Payment terms cannot be negative.',
            'payment_terms_days.max'          => 'Payment terms cannot exceed 365 days.',

            'billing_address.max'             => 'Billing address is too long (max 1000 characters).',
            'notes.max'                       => 'Notes are too long (max 2000 characters).',

            'name.regex'                      => 'Client name contains characters that are not allowed.',
            'contact_person.regex'            => 'Contact person can only contain letters, spaces and punctuation.',
            'email.unique'                    => 'Another client in your workspace already uses this email.',
            'phone.min'                       => 'Phone number looks too short.',
            'phone.unique'                    => 'Another client in your workspace already uses this phone number.',
            'tax_id.min'                      => 'Tax ID looks too short.',
            'tax_id.regex'                    => 'Tax ID can only contain letters, digits and dashes.',
        ];
    }

    public function prepareForValidation(): void
{
    // Normalise code: trim + uppercase, OR null when empty so the "nullable" rule kicks in
    $code = trim((string) $this->client_code);

    $this->merge([
        'client_code' => $code === '' ? null : strtoupper($code),
        'name'        => trim((string) $this->name),
        'email'       => trim((string) $this->email) ?: null,
        'phone'       => trim((string) $this->phone) ?: null,
        'tax_id'      => trim((string) $this->tax_id) ?: null,
        'asset_visibility_enabled' => $this->boolean('asset_visibility_enabled'),
        'is_active'   => $this->has('is_active') ? $this->boolean('is_active') : true,
    ]);
}
}