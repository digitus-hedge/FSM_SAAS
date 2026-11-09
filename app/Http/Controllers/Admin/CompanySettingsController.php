<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Http\Request;

class CompanySettingsController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->canDo('settings.manage'), 403);
    }

    public function index()
    {
        $this->guard();

        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $setting = $tenant->setting ?? new TenantSetting(['tenant_id' => $tenant->id]);

        $currencyLocked = Quote::where('tenant_id', $tenant->id)->exists()
            || Invoice::where('tenant_id', $tenant->id)->exists();

        return view('admin.company-settings.index', [
            'tenant'         => $tenant,
            'setting'        => $setting,
            'currencyLocked' => $currencyLocked,
        ]);
    }

    public function update(Request $request)
    {
        $this->guard();

        $tenant = Tenant::current();
        abort_unless($tenant, 404);

        $currencyLocked = Quote::where('tenant_id', $tenant->id)->exists()
            || Invoice::where('tenant_id', $tenant->id)->exists();

        $data = $request->validate([
            'base_currency'             => [$currencyLocked ? 'nullable' : 'required', 'string', 'size:3'],
            'reopen_window_days'        => ['required', 'integer', 'min:0', 'max:365'],
            'feedback_link_days'        => ['nullable', 'integer', 'min:1', 'max:90'],
            'client_sign_in_link_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'require_two_factor'        => ['nullable', 'boolean'],
        ], [
            'base_currency.required'      => 'Choose your company currency.',
            'reopen_window_days.required' => 'Set the reopen window (0 = never allow reopening).',
        ]);

        if (! $currencyLocked && ! empty($data['base_currency'])) {
            $tenant->update(['base_currency' => strtoupper($data['base_currency'])]);
        }

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'reopen_window_days'        => $data['reopen_window_days'],
                'feedback_link_days'        => $data['feedback_link_days'] ?? 14,
                'client_sign_in_link_hours' => $data['client_sign_in_link_hours'] ?? 72,
                'require_two_factor'        => (bool) ($data['require_two_factor'] ?? false),
            ]
        );

        return back()->with('success', 'Company settings saved.');
    }
}