<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TenantBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'hours'                 => ['required', 'array', 'size:7'],
            'hours.*'               => ['required', 'array'],
            'hours.*.weekday'       => ['required', 'integer', 'between:0,6'],
            'hours.*.is_open'       => ['nullable', 'boolean'],
            'hours.*.opens_at'      => ['nullable', 'required_if:hours.*.is_open,1', 'date_format:H:i'],
            'hours.*.closes_at'     => ['nullable', 'required_if:hours.*.is_open,1', 'date_format:H:i', 'after:hours.*.opens_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'hours.*.opens_at.required_if'    => 'Please set an opening time for days marked as open.',
            'hours.*.closes_at.required_if'   => 'Please set a closing time for days marked as open.',
            'hours.*.closes_at.after'         => 'Closing time must be after opening time.',
            'hours.*.opens_at.date_format'    => 'Opening time must be in HH:MM format.',
            'hours.*.closes_at.date_format'   => 'Closing time must be in HH:MM format.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $hours = $this->input('hours', []);

        foreach ($hours as $i => $row) {
            $isOpen = filter_var($row['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $hours[$i]['is_open']   = $isOpen;
            $hours[$i]['weekday']   = (int) ($row['weekday'] ?? 0);
            $hours[$i]['opens_at']  = $isOpen ? ($row['opens_at']  ?: null) : null;
            $hours[$i]['closes_at'] = $isOpen ? ($row['closes_at'] ?: null) : null;
        }

        $this->merge(['hours' => $hours]);
    }
}