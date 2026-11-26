<?php
// app/Rules/CompanyAddress.php — enforces TEN-D2 exactly
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CompanyAddress implements ValidationRule
{
    // The reserved list lives in config/tenancy.php (go-live list, TEN-D2 extras and every first path segment outside the company group).
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[a-z][a-z0-9-]{2,39}$/', $value)) {
            $fail(__('Use 3 to 40 lowercase letters, numbers or hyphens, starting with a letter.'));
            return;
        }
        if (str_contains($value, '--')) {
            $fail(__('Use 3 to 40 lowercase letters, numbers or hyphens, starting with a letter.'));
            return;
        }
        if (\App\Support\Tenancy::isReserved($value)) {
            $fail(__('That address is reserved.'));
            return;
        }
        if (\App\Models\Tenant::whereRaw('LOWER(slug) = ?', [strtolower($value)])->exists()) {
            $fail(__('That address is already taken.'));
        }
    }
}