<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxRateRequest;
use App\Models\TaxRate;
use Illuminate\Http\Request;

class TaxRatesController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (!in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

        $rates = TaxRate::query()
            ->when($request->filled('q'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->input('status') === 'active'))
            ->orderBy('rate_percent')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.tax-rates.index', [
            'rates'          => $rates,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
        ]);
    }

    public function create()
    {
        return view('admin.tax-rates.form', [
                 'rate' => new TaxRate(['is_active' => true]),
        'mode' => 'create',
        ]);
    }

    public function store(TaxRateRequest $request)
    {
        $rate = TaxRate::create($request->validated());

        return redirect()->route('admin.tax-rates.index')
            ->with('success', "Tax rate {$rate->name} created.");
    }

    public function edit(TaxRate $rate)
    {
        return view('admin.tax-rates.form', [
            'rate' => $rate,
            'mode' => 'edit',
        ]);
    }

    public function update(TaxRateRequest $request, TaxRate $rate)
    {
        $rate->update($request->validated());

        return redirect()->route('admin.tax-rates.index')
            ->with('success', "Tax rate {$rate->name} updated.");
    }

    public function suspend(TaxRate $rate)
    {
        $rate->update(['is_active' => false]);
        return back()->with('success', "{$rate->name} deactivated.");
    }

    

    public function reactivate(TaxRate $rate)
    {
        $rate->update(['is_active' => true]);
        return back()->with('success', "{$rate->name} reactivated.");
    }

    public function setDefault(TaxRate $rate)
{
    $rate->update(['is_default' => true, 'is_active' => true]);
    return back()->with('success', "{$rate->name} is now the default tax rate.");
}

    public function destroy(TaxRate $rate)
    {
        $name = $rate->name;
        $rate->delete();
        return redirect()->route('admin.tax-rates.index')
            ->with('success', "Tax rate {$name} deleted.");
    }
}