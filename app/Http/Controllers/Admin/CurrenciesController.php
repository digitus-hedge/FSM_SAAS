<?php

namespace App\Http\Controllers\Admin;

use App\Services\Permissions\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Services\Currency\CurrencyException;
use App\Services\Currency\CurrencyService;
use Illuminate\Http\Request;

/** Currencies and rates (CUR-02, spec 5.1 and 6.2). Plan: multi_currency. Needs settings.manage or invoice.manage. */
class CurrenciesController extends Controller
{
    private function tenant()
    {
        $user = auth()->user();
        abort_unless($user && $user->tenant_id, 404);
        abort_unless(\App\Services\Permissions\PermissionLevel::canView($user, 'settings.manage', 'invoice.manage'), 404); // CP-018 (not found for roles with no access at all)
        // Seeing the page is not changing it: a view_only holder may look but not turn currencies on or off or add rates.
        abort_unless(request()->isMethodSafe() || PermissionLevel::canChange($user, 'settings.manage', 'invoice.manage'), 403);

        return $user->tenant;
    }

    public function index(CurrencyService $currencies)
    {
        $tenant = $this->tenant();

        $active = $currencies->active($tenant)->map(function ($c) use ($currencies, $tenant) {
            $c->latest = $currencies->latestRate($tenant, $c->currency_code);
            $c->in_use = $currencies->inUse($tenant, $c->currency_code);

            return $c;
        });

        $addable = collect($currencies->supported())
            ->reject(fn ($code) => $code === strtoupper($tenant->base_currency) || $active->contains('currency_code', $code))
            ->values();

        return view('admin.currencies.index', [
            'tenant'      => $tenant,
            'active'      => $active,
            'addable'     => $addable,
            'attribution' => config('rates.open_er_api.attribution'),
            'showAttribution' => config('rates.provider') === 'open_er_api',
        ]);
    }

    public function store(Request $request, CurrencyService $currencies)
    {
        $tenant = $this->tenant();
        $request->validate(['currency_code' => 'required|string|size:3'], ['currency_code.*' => 'Choose a currency.']);

        try {
            $currencies->enable($tenant, $request->input('currency_code'), $request->user());
        } catch (CurrencyException $e) {
            return back()->withErrors(['currency_code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.currencies.index')->with('success', strtoupper($request->input('currency_code')) . ' turned on.');
    }

    public function destroy(Request $request, CurrencyService $currencies, string $code)
    {
        $tenant = $this->tenant();

        try {
            $currencies->disable($tenant, $code, $request->user());
        } catch (CurrencyException $e) {
            return back()->withErrors(['currency_code' => $e->getMessage()]);
        }

        return redirect()->route('admin.currencies.index')->with('success', strtoupper($code) . ' turned off. Its rate history is kept.');
    }

    public function history(CurrencyService $currencies, string $code)
    {
        $tenant = $this->tenant();
        abort_unless($currencies->active($tenant)->contains('currency_code', strtoupper($code))
            || \App\Models\TenantCurrency::query()->where('tenant_id', $tenant->id)->where('currency_code', strtoupper($code))->exists(), 404);

        return view('admin.currencies.history', [
            'tenant'  => $tenant,
            'code'    => strtoupper($code),
            'history' => $currencies->history($tenant, $code),
            'today'   => now($tenant->timezone ?: config('app.timezone'))->toDateString(),
        ]);
    }

    public function addRate(Request $request, CurrencyService $currencies, string $code)
    {
        $tenant = $this->tenant();
        $data   = $request->validate(['rate_date' => 'required|date', 'rate' => 'required']);

        try {
            $currencies->addManualRate($tenant, $code, $data['rate_date'], $data['rate'], $request->user());
        } catch (CurrencyException $e) {
            $field = str_contains($e->getMessage(), 'date') ? 'rate_date' : 'rate';

            return back()->withErrors([$field => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.currencies.history', $code)->with('success', 'Rate saved.');
    }
}
