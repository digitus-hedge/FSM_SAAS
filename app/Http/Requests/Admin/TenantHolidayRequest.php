<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantHolidayRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id ?? null;
        $id       = $this->route('tenant_holiday')?->id;

        return [
            'holiday_date' => [
                'required', 'date', 'date_format:Y-m-d',
                Rule::unique('tenant_holidays', 'holiday_date')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'holiday_date' => 'Holiday Date',
            'name'         => 'Holiday Name',
        ];
    }

    public function messages(): array
    {
        return [
            'holiday_date.required'    => 'Please choose a date for this holiday.',
            'holiday_date.date_format' => 'Date must be in YYYY-MM-DD format.',
            'holiday_date.unique'      => 'A holiday is already defined for this date.',
            'name.required'            => 'Please enter a label for this holiday.',
            'name.max'                 => 'Name can be at most 120 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}