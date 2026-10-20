<?php
namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectSiteRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        $siteId   = $this->route('site')?->id;
        $tenantId = Tenant::current()?->id;

        return [
            'site_code' => [
                'required','string','max:40','alpha_dash',
                Rule::unique('project_sites', 'site_code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($siteId),
            ],
            'name'      => 'required|string|min:2|max:180',
            'site_type' => 'required|in:client_site,tenant_workshop',
            'client_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('site_type') === 'client_site'),
                'exists:clients,id',
            ],
            'address'                => 'nullable|string|max:1000',
            'city'                   => 'nullable|string|max:100',
            'lat'                    => 'nullable|numeric|between:-90,90',
            'lng'                    => 'nullable|numeric|between:-180,180',
            'geofence_radius_meters' => 'nullable|integer|min:0|max:10000',
            'contact_name'           => 'nullable|string|max:150',
            'contact_phone'          => 'nullable|string|max:40',
            'access_instructions'    => 'nullable|string|max:2000',
            'is_active'              => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_code'              => 'site code',
            'name'                   => 'site name',
            'site_type'              => 'site type',
            'client_id'              => 'client',
            'lat'                    => 'latitude',
            'lng'                    => 'longitude',
            'geofence_radius_meters' => 'geofence radius',
            'contact_name'           => 'on-site contact',
            'contact_phone'          => 'on-site phone',
            'access_instructions'    => 'access instructions',
            'is_active'              => 'active status',
        ];
    }

    public function messages(): array
    {
        return [
            'site_code.required'   => 'A site code is required.',
            'site_code.alpha_dash' => 'Site code can only contain letters, digits, dashes, underscores.',
            'site_code.unique'     => 'This site code is already used in your workspace.',
            'name.required'        => 'Please enter the site name.',
            'name.min'             => 'Site name is too short (min 2 characters).',
            'client_id.required'   => 'A client site needs a client — pick one, or change the type to a Tenant workshop.',
            'client_id.exists'     => 'Selected client no longer exists.',
            'lat.between'          => 'Latitude must be between -90 and 90.',
            'lng.between'          => 'Longitude must be between -180 and 180.',
            'geofence_radius_meters.max' => 'Geofence radius cannot exceed 10,000 meters.',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'site_code' => strtoupper(trim((string) $this->site_code)),
            // If type is tenant_workshop, force client_id to null (workshops have no client)
            'client_id' => $this->input('site_type') === 'tenant_workshop'
                ? null
                : $this->input('client_id'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }
}