<?php

namespace App\Http\Requests\Admin;

use App\Models\Client;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'client_id' => [
                'required', 'integer',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'project_site_id' => [
                'required', 'integer',
                Rule::exists('project_sites', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'service_category_id' => [
                'required', 'integer',
                Rule::exists('service_categories', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at'),
            ],
            'priority' => ['required', Rule::in(array_keys(\App\Models\ServiceRequest::PRIORITIES))],
            'title'    => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'client_po_reference' => ['nullable', 'string', 'max:100'],
            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $tenantId = auth()->user()->tenant_id;
            $clientId = $this->input('client_id');
            $siteId   = $this->input('project_site_id');

            // Site must belong to the chosen client (or be a workshop with client_id null)
            if ($clientId && $siteId) {
                $site = ProjectSite::where('id', $siteId)->where('tenant_id', $tenantId)->first();
                if ($site && $site->client_id && (int) $site->client_id !== (int) $clientId) {
                    $v->errors()->add('project_site_id', 'This site does not belong to the chosen client.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'client_id'           => 'Client',
            'project_site_id'     => 'Site',
            'service_category_id' => 'Category',
            'priority'            => 'Priority',
            'title'               => 'Title',
            'description'         => 'Description',
            'client_po_reference' => 'PO reference',
            'attachments.*'       => 'Attachment',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'            => 'Please choose a client.',
            'project_site_id.required'      => 'Please choose a site.',
            'service_category_id.required'  => 'Please choose a service category.',
            'priority.required'             => 'Please choose a priority.',
            'title.required'                => 'Please enter a short title.',
            'attachments.max'               => 'You can upload up to 10 files.',
            'attachments.*.max'             => 'Each attachment must be 10 MB or smaller.',
            'attachments.*.mimes'           => 'Attachments must be JPG, PNG or PDF.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title'               => trim((string) $this->input('title')),
            'description'         => $this->input('description') ? trim($this->input('description')) : null,
            'client_po_reference' => $this->input('client_po_reference') ? trim($this->input('client_po_reference')) : null,
        ]);
    }
}