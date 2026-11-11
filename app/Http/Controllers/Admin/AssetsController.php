<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssetRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetsController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

    public function __construct(private AuditLogger $audit) {}

    protected function canView(): void   { abort_unless(auth()->user()?->canDo('asset.view'), 403); }
    protected function canManage(): void { abort_unless(auth()->user()?->canDo('asset.manage'), 403); }

    public function index(Request $request)
    {
        $this->canView();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

        $assets = Asset::query()
            ->with(['client:id,name', 'site:id,name,site_code', 'category:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = $request->input('q');
                $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")
                    ->orWhere('asset_code', 'like', "%{$t}%")
                    ->orWhere('serial_number', 'like', "%{$t}%")
                    ->orWhere('manufacturer', 'like', "%{$t}%")
                    ->orWhere('model', 'like', "%{$t}%"));
            })
            ->when($request->filled('site_id'), fn ($q) => $q->where('project_site_id', $request->input('site_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('service_category_id', $request->input('category_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->input('warranty') === 'in_warranty', fn ($q) => $q->whereDate('warranty_expiry_date', '>=', today()))
            ->when($request->input('warranty') === 'expired', fn ($q) => $q->whereDate('warranty_expiry_date', '<', today()))
            ->when($request->input('warranty') === 'none', fn ($q) => $q->whereNull('warranty_expiry_date'))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.assets.index', [
            'assets'         => $assets,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'sites'          => ProjectSite::clientSites()->orderBy('name')->get(['id', 'name', 'site_code']),
            'categories'     => ServiceCategory::orderBy('name')->get(['id', 'name']),
        ] + $this->labels());
    }

    public function create()
    {
        $this->canManage();
        return view('admin.assets.form', $this->formData(new Asset(['status' => 'active']), 'create'));
    }

    public function store(AssetRequest $request)
    {
        $asset = $this->createWithRetry($request->validated());

        return redirect()->route('admin.assets.index')
            ->with('success', "{$asset->asset_code} created.");
    }

    public function edit(Asset $asset)
    {
        $this->canManage();
        return view('admin.assets.form', $this->formData($asset, 'edit'));
    }

    public function update(AssetRequest $request, Asset $asset)
    {
        if ($asset->is_decommissioned) {
            return back()->with('error', 'A decommissioned asset is read-only.');
        }

        $asset->update($request->validated());   // asset_code and qr_hash never change

        return redirect()->route('admin.assets.index')
            ->with('success', "{$asset->asset_code} updated.");
    }

    public function decommission(Asset $asset)
    {
        $this->canManage();

        if ($asset->is_decommissioned) {
            return back()->with('error', 'This asset is already decommissioned.');
        }

        $previousStatus = $asset->status;

        $asset->update(['status' => 'decommissioned']);

        $this->audit->log('asset.decommissioned', [
            'item_type' => Asset::class,
            'item_id'   => $asset->id,
            'old'       => ['status' => $previousStatus],
            'new'       => ['status' => 'decommissioned', 'asset_code' => $asset->asset_code],
        ]);

        return redirect()->route('admin.assets.index')
            ->with('success', "{$asset->asset_code} decommissioned. Its history is kept.");
    }

    protected function createWithRetry(array $data, int $attempts = 3): Asset
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return Asset::create($data);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000' || $i === $attempts - 1) {
                    throw $e;   // not a duplicate code, or out of retries
                }
            }
        }
        throw new \RuntimeException('Could not generate a unique asset code.');
    }

    protected function formData(Asset $asset, string $mode): array
    {
        return [
            'asset'  => $asset,
            'mode'   => $mode,
            'clients' => Client::where(fn ($q) => $q->where('is_active', true)
                    ->when($asset->client_id, fn ($q2) => $q2->orWhere('id', $asset->client_id)))
                ->orderBy('name')->get(['id', 'name']),
            'sites'  => ProjectSite::clientSites()
                ->where(fn ($q) => $q->where('is_active', true)
                    ->when($asset->project_site_id, fn ($q2) => $q2->orWhere('id', $asset->project_site_id)))
                ->orderBy('name')->get(['id', 'client_id', 'name', 'site_code']),
            'categories' => ServiceCategory::where(fn ($q) => $q->where('is_active', true)
                    ->when($asset->service_category_id, fn ($q2) => $q2->orWhere('id', $asset->service_category_id)))
                ->orderBy('name')->get(['id', 'name']),
            'parentOptions' => Asset::selectable()
                ->when($asset->exists, fn ($q) => $q->where('id', '!=', $asset->id))
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'project_site_id']),
        ] + $this->labels();
    }

        protected function labels(): array
    {
        $row = DB::table('tenant_terminology')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('term_key', 'asset')->first();

        return [
            'label'  => ucwords(data_get($row, 'singular') ?: 'asset'),
            'plural' => ucwords(data_get($row, 'plural') ?: 'assets'),
        ];
    }
}