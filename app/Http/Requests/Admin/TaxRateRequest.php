<?php
namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRateRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        $rateId   = $this->route('rate')?->id;
        $tenantId = Tenant::current()?->id;

        return [
            'name' => [
                'required','string','max:80',
                Rule::unique('tax_rates', 'name')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($rateId),
            ],
            'rate_percent' => 'required|numeric|min:0|max:999.99',
            'is_default'   => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'         => 'tax rate name',
            'rate_percent' => 'rate percent',
            'is_default'   => 'default flag',
            'is_active'    => 'active status',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Please enter a tax rate name.',
            'name.unique'     => 'A tax rate with this name already exists.',
            'rate_percent.required' => 'Please enter a rate.',
            'rate_percent.max'      => 'Rate cannot exceed 999.99%.',
            'rate_percent.min'      => 'Rate cannot be negative.',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => $this->boolean('is_default'),
            'is_active'  => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}