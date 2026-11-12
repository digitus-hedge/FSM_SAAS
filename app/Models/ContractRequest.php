<?php

namespace App\Http\Requests\Admin;

use App\Models\Contract;
use App\Models\ProjectSite;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    private bool $lockedFieldChanged = false;

    public function authorize(): bool
    {
        return (bool) $this->user()?->canDo('contract.manage');
    }

    protected function prepareForValidation(): void
    {
        $merge = ['auto_renew' => $this->boolean('auto_renew')];

        /** @var Contract|null $self */
        $self = $this->route('contract');

        // D14: client and type are locked after activation. Disabled inputs are not sent,
        // so fill them from the saved contract. A different value sent by hand is refused.
        if ($self && $self->isLocked()) {
            foreach (['client_id', 'contract_type'] as $f) {
                if ($this->filled($f) && (string) $this->input($f) !== (string) $self->{$f}) {
                    $this->lockedFieldChanged = true;
                }
                $merge[$f] = $self->{$f};
            }
        }

        // Users without access to money never send it. Keep the saved values untouched.
        if (! Contract::canSeeMoney($this->user())) {
            $this->request->remove('annual_value');
            $this->request->remove('payment_terms');
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $tid = Tenant::current()?->id;
        $own = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tid)->whereNull('deleted_at');

        return [
            'action'                 => ['nullable', Rule::in(['save_draft', 'save', 'activate'])],
            'client_id'              => ['required', $own('clients')],
            'contract_type'          => ['required', Rule::in(array_keys(Contract::TYPES))],
            'start_date'             => ['required', 'date'],
            'end_date'               => ['required', 'date', 'after_or_equal:start_date'],
            'response_sla_minutes'   => ['nullable', 'integer', 'min:1', 'max:525600'],
            'resolution_sla_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'max_calls'              => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'call_period'            => ['required', Rule::in(array_keys(Contract::CALL_PERIODS))],
            'annual_value'           => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'payment_terms'          => ['nullable', 'string', 'max:120'],
            'auto_renew'             => ['boolean'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'site_ids'               => ['nullable', 'array'],
            'site_ids.*'             => ['integer', $own('project_sites')],
            'category_ids'           => ['nullable', 'array'],
            'category_ids.*'         => ['integer', $own('service_categories')],
            'documents'              => ['nullable', 'array', 'max:' . Contract::MAX_FILES],
            'documents.*'            => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:' . Contract::MAX_FILE_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required'            => 'Choose a client.',
            'contract_type.required'        => 'Choose a type.',
            'start_date.required'           => 'Enter a start date.',
            'end_date.required'             => 'Enter an end date.',
            'end_date.after_or_equal'       => 'The end date must be on or after the start date.',
            'response_sla_minutes.*'        => 'Enter a whole number of minutes from 1 to 525,600.',
            'resolution_sla_minutes.*'      => 'Enter a whole number of minutes from 1 to 525,600.',
            'max_calls.*'                   => 'Enter a whole number of calls of 1 or more, or leave it empty for no cap.',
            'annual_value.*'                => 'Enter an amount of 0 or more.',
            'documents.max'                 => 'A contract can have up to 5 files.',
            'documents.*.max'               => 'Files can be up to 10 MB.',
            'documents.*.mimes'             => 'Attach a PDF, JPG or PNG file.',
            'documents.*.uploaded'          => 'Files can be up to 10 MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'client', 'contract_type' => 'type', 'start_date' => 'start date',
            'end_date' => 'end date', 'call_period' => 'call period', 'annual_value' => 'annual value',
            'payment_terms' => 'payment terms', 'auto_renew' => 'auto-renew',
            'site_ids' => 'sites', 'category_ids' => 'categories',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->lockedFieldChanged) {
                $v->errors()->add('client_id', 'The client and the type cannot be changed after the contract is active.');
            }
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            // Every chosen site must be a site of the chosen client.
            $siteIds = array_filter((array) $this->input('site_ids', []));
            if ($siteIds) {
                $valid = ProjectSite::whereIn('id', $siteIds)
                    ->where('client_id', $this->input('client_id'))
                    ->where('site_type', 'client_site')
                    ->count();
                if ($valid !== count(array_unique($siteIds))) {
                    $v->errors()->add('site_ids', 'Choose only sites that belong to the selected client.');
                }
            }

            // At most 5 files on a contract, counting the ones already attached.
            $self = $this->route('contract');
            if ($self && $this->hasFile('documents')) {
                if ($self->documents()->count() + count($this->file('documents')) > Contract::MAX_FILES) {
                    $v->errors()->add('documents', 'A contract can have up to 5 files.');
                }
            }
        });
    }
}
