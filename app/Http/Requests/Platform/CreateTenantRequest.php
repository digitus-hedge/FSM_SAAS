<?php
// app/Http/Requests/Platform/CreateTenantRequest.php
namespace App\Http\Requests\Platform;

use App\Rules\CompanyAddress;  // enforces D2 fully
use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform')?->platform_role === 'owner';
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'min:2', 'max:160'],
            'slug'              => ['required', 'string', new CompanyAddress()], // D2 rules
            'plan_id'           => ['required', 'exists:plans,id'],
            'country'           => ['required', 'string', 'size:2'],
            'timezone'          => ['required', 'timezone'],
            'base_currency'     => ['required', 'string', 'size:3'],
            'start_as'          => ['required', 'in:trial,active'],
            'trial_ends_on'     => ['nullable', 'date', 'after:today', 'required_if:start_as,trial'],
            'first_admin_name'  => ['required', 'string', 'min:2', 'max:150'],
            'first_admin_email' => ['required', 'email', 'max:190'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => __('Enter the company name (2 to 160 characters).'),
            'slug.required'  => __('Use 3 to 40 lowercase letters, numbers or hyphens, starting with a letter.'),
        ];
    }
}