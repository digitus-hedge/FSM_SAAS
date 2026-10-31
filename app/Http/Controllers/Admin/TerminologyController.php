<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantTerminology;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TerminologyController extends Controller
{
    public function index()
    {
        $tenantId = Tenant::current()?->id;

        $saved = TenantTerminology::where('tenant_id', $tenantId)
            ->get()->keyBy('term_key');

        // Build the editable rows — one per default term, with saved overrides merged in
        $rows = collect(TenantTerminology::DEFAULTS)->map(function ($labels, $key) use ($saved) {
            return [
                'key'              => $key,
                'default_singular' => $labels[0],
                'default_plural'   => $labels[1],
                'singular'         => $saved[$key]->singular ?? $labels[0],
                'plural'           => $saved[$key]->plural   ?? $labels[1],
            ];
        })->values();

        return view('admin.terminology.index', compact('rows'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'terms'              => 'required|array',
            'terms.*.singular'   => 'required|string|max:80',
            'terms.*.plural'     => 'required|string|max:80',
        ]);

        $tenantId = Tenant::current()?->id;
        abort_unless($tenantId, 403);

        foreach ($data['terms'] as $key => $vals) {
            if (! array_key_exists($key, TenantTerminology::DEFAULTS)) continue;

            $default = TenantTerminology::DEFAULTS[$key];
            $singular = trim($vals['singular']);
            $plural   = trim($vals['plural']);

            // If the user reset to the default, delete the override row
            if ($singular === $default[0] && $plural === $default[1]) {
                TenantTerminology::where('tenant_id', $tenantId)
                    ->where('term_key', $key)->delete();
                $this->clearTermCache($tenantId, $key);
                continue;
            }

            TenantTerminology::updateOrCreate(
                ['tenant_id' => $tenantId, 'term_key' => $key],
                ['singular' => $singular, 'plural' => $plural],
            );

            // Bust the cache so the term() helper picks up the new value next request
            $this->clearTermCache($tenantId, $key);
        }

        return back()->with('success', 'Terminology saved.');
    }

    public function resetOne(string $key)
    {
        abort_unless(array_key_exists($key, TenantTerminology::DEFAULTS), 404);

        $tenantId = Tenant::current()?->id;

        TenantTerminology::where('tenant_id', $tenantId)
            ->where('term_key', $key)->delete();

        $this->clearTermCache($tenantId, $key);

        return response()->json(['ok' => true]);
    }

    /**
     * Clear both singular and plural cache keys for a given term.
     */
    protected function clearTermCache(?int $tenantId, string $key): void
    {
        if (! $tenantId) return;

        Cache::forget("term:{$tenantId}:{$key}:singular");
        Cache::forget("term:{$tenantId}:{$key}:plural");
    }
}