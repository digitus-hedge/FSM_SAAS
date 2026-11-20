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

    protected function canView(): void   { abort_unless(\App\Services\Permissions\PermissionLevel::canView(auth()->user(), 'asset.view'), 403); }
    protected function canManage(): void { abort_unless(\App\Services\Permissions\PermissionLevel::canChange(auth()->user(), 'asset.manage'), 403); }

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
        $asset = $this->createWithRetry($this->payload($request));
        $this->applyPhoto($asset, $request);

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

        $asset->update($this->payload($request, $asset));   // asset_code and qr_hash never change
        $this->applyPhoto($asset, $request);

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

    /**
     * The columns to save (AST-02). The photo and the specification rows are not columns:
     * the photo is stored by applyPhoto(), the rows become specifications_json (a list of name and value, empty names dropped).
     * The meter fields count only while "Track a meter reading" is on: switched off, what is stored stays as it is.
     */
    protected function payload(AssetRequest $request, ?Asset $asset = null): array
    {
        $data  = $request->validated();
        $specs = (array) ($data['specs'] ?? []);
        unset($data['photo'], $data['remove_photo'], $data['specs']);

        $tracks = (bool) ($data['tracks_meter'] ?? false);
        $data['tracks_meter'] = $tracks;
        if (! $tracks) {
            unset($data['meter_unit'], $data['current_meter_reading']);
        } elseif (($data['current_meter_reading'] ?? null) === null) {
            unset($data['current_meter_reading']);
        } elseif (! $asset || ! $asset->tracks_meter || (float) $asset->current_meter_reading !== (float) $data['current_meter_reading']) {
            $data['last_meter_recorded_at'] = now();
        }

        $rows = collect($specs)
            ->map(fn ($r) => ['name' => trim((string) ($r['name'] ?? '')), 'value' => trim((string) ($r['value'] ?? ''))])
            ->filter(fn ($r) => $r['name'] !== '')->values()->all();
        $data['specifications_json'] = $rows ?: null;

        return $data;
    }

    /** A new photo replaces the old one (file and row); "Remove photo" clears it. The photo is an attachment of kind asset_photo on the public disk. */
    protected function applyPhoto(Asset $asset, AssetRequest $request): void
    {
        $file   = $request->file('photo');
        $remove = $request->boolean('remove_photo');
        if (! $file && ! $remove) {
            return;
        }

        $old = $asset->photo_attachment_id ? \App\Models\Attachment::find($asset->photo_attachment_id) : null;

        if ($file) {
            $path = $file->storeAs("assets/{$asset->id}", \Illuminate\Support\Str::uuid() . '.' . $file->extension(), 'public');
            $att  = \App\Models\Attachment::create([
                'tenant_id' => $asset->tenant_id, 'attachable_type' => Asset::class, 'attachable_id' => $asset->id, 'kind' => 'asset_photo',
                'storage_path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by_user_id' => auth()->id(), 'client_event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]);
            $asset->update(['photo_attachment_id' => $att->id]);
        } else {
            $asset->update(['photo_attachment_id' => null]);
        }

        $old?->deleteWithFile();
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