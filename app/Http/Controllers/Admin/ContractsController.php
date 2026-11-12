<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContractRequest;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractsController extends Controller
{
    protected array $perPageOptions = [10, 25, 50, 100];

    public function __construct(private AuditLogger $audit) {}

    protected function canView(): void   { abort_unless(auth()->user()?->canDo('contract.view'), 403); }
    protected function canManage(): void { abort_unless(auth()->user()?->canDo('contract.manage'), 403); }

    /* ---------------------------------------------------------------- CNT-01 */

    public function index(Request $request)
    {
        $this->canView();

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, $this->perPageOptions, true)) $perPage = 10;

        $expiry = $request->input('expiry');
        $q      = trim((string) $request->input('q', ''));

        $contracts = Contract::query()
            ->with('client:id,name')
            ->withCount(['sites', 'categories'])
            ->when($q !== '', fn ($w) => $w->where(function ($x) use ($q) {
                $x->where('contract_number', 'like', "%{$q}%")
                  ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            }))
            ->when($request->filled('client_id'), fn ($w) => $w->where('client_id', $request->input('client_id')))
            ->when($request->filled('contract_type'), fn ($w) => $w->where('contract_type', $request->input('contract_type')))
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->input('status')))
            ->when($request->boolean('has_cap'), fn ($w) => $w->whereNotNull('max_calls'))
            ->when(in_array($expiry, ['30', '60', '90'], true), fn ($w) => $w
                ->whereDate('end_date', '>=', today())
                ->whereDate('end_date', '<=', today()->addDays((int) $expiry)))
            ->when($expiry === 'expired', fn ($w) => $w->whereDate('end_date', '<', today()))
            ->orderBy('end_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.contracts.index', [
            'contracts'      => $contracts,
            'perPage'        => $perPage,
            'perPageOptions' => $this->perPageOptions,
            'clients'        => Client::orderBy('name')->get(['id', 'name']),
            'showMoney'      => Contract::canSeeMoney(),
        ] + $this->labels());
    }

    /* ---------------------------------------------------------------- CNT-02 */

    public function create()
    {
        $this->canManage();

        return view('admin.contracts.form', $this->formData(new Contract([
            'status' => 'draft', 'contract_type' => 'amc', 'call_period' => 'contract_term',
            'start_date' => today(), 'end_date' => today()->addYear()->subDay(),
        ]), 'create'));
    }

    public function store(ContractRequest $request)
    {
        $data        = $request->validated();
        $activate    = ($data['action'] ?? null) === 'activate';
        $siteIds     = $data['site_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['action'], $data['site_ids'], $data['category_ids'], $data['documents']);
        $data['status'] = 'draft';

        $contract = DB::transaction(function () use ($data, $siteIds, $categoryIds, $request, $activate) {
            $contract = Contract::create($data);
            $contract->syncCoverage($siteIds, $categoryIds);
            $this->storeDocuments($contract, $request->file('documents', []));
            if ($activate) $contract->activate();
            return $contract;
        });

        $this->audit->log('contract.created', [
            'item_type' => Contract::class,
            'item_id'   => $contract->id,
            'new'       => $this->snapshot($contract) + ['site_ids' => array_map('intval', $siteIds), 'category_ids' => array_map('intval', $categoryIds)],
        ]);
        if ($activate) {
            $this->audit->log('contract.activated', ['item_type' => Contract::class, 'item_id' => $contract->id, 'new' => ['status' => $contract->status, 'contract_number' => $contract->contract_number]]);
        }

        return redirect()->route('admin.contracts.show', $contract)
            ->with('success', $activate ? 'Contract activated.' : 'Contract saved.');
    }

    public function edit(Contract $contract)
    {
        $this->canManage();
        if (in_array($contract->status, ['cancelled', 'renewed'], true)) {
            return redirect()->route('admin.contracts.show', $contract)
                ->with('error', 'A ' . strtolower($contract->status_label) . ' contract cannot be edited.');
        }
        return view('admin.contracts.form', $this->formData($contract, 'edit'));
    }

    public function update(ContractRequest $request, Contract $contract)
    {
        if (in_array($contract->status, ['cancelled', 'renewed'], true)) {
            return redirect()->route('admin.contracts.show', $contract)
                ->with('error', 'A ' . strtolower($contract->status_label) . ' contract cannot be edited.');
        }

        $data        = $request->validated();
        $activate    = ($data['action'] ?? null) === 'activate' && $contract->status === 'draft';
        $siteIds     = array_map('intval', $data['site_ids'] ?? []);
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        unset($data['action'], $data['site_ids'], $data['category_ids'], $data['documents']);

        $oldSites = $contract->sites()->pluck('project_sites.id')->map(fn ($i) => (int) $i)->sort()->values()->all();
        $oldCats  = $contract->categories()->pluck('service_categories.id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $contract->fill($data);
        $old = []; $new = [];
        foreach ($contract->getDirty() as $key => $value) {
            $old[$key] = $this->plain($contract->getOriginal($key));
            $new[$key] = $this->plain($contract->getAttribute($key));
        }

        DB::transaction(function () use ($contract, $siteIds, $categoryIds, $request, $activate) {
            $contract->save();
            $contract->syncCoverage($siteIds, $categoryIds);
            $this->storeDocuments($contract, $request->file('documents', []));
            if ($activate) $contract->activate();
        });

        sort($siteIds); sort($categoryIds);
        if ($oldSites !== $siteIds)    { $old['site_ids'] = $oldSites;     $new['site_ids'] = $siteIds; }
        if ($oldCats !== $categoryIds) { $old['category_ids'] = $oldCats; $new['category_ids'] = $categoryIds; }

        if ($new) {
            $this->audit->log('contract.updated', [
                'item_type' => Contract::class,
                'item_id'   => $contract->id,
                'old'       => $old,
                'new'       => $new + ['contract_number' => $contract->contract_number],
            ]);
        }
        if ($activate) {
            $this->audit->log('contract.activated', ['item_type' => Contract::class, 'item_id' => $contract->id, 'new' => ['status' => $contract->status, 'contract_number' => $contract->contract_number]]);
        }

        return redirect()->route('admin.contracts.show', $contract)
            ->with('success', $activate ? 'Contract activated.' : 'Contract saved.');
    }

    /* ----------------------------------------------------------- detail page */

    public function show(Contract $contract)
    {
        $this->canView();

        $contract->load('client:id,name');
        $money = Contract::canSeeMoney();

        $sites = $contract->sites()->orderBy('name')->get(['project_sites.id', 'project_sites.name', 'project_sites.site_code']);
        $cats  = $contract->categories()->orderBy('name')->get(['service_categories.id', 'service_categories.name']);

        $calls = DB::table('contract_calls as cc')
            ->leftJoin('service_requests as sr', 'sr.id', '=', 'cc.service_request_id')
            ->leftJoin('users as u', 'u.id', '=', 'cc.user_id')
            ->where('cc.contract_id', $contract->id)
            ->orderByDesc('cc.id')->limit(200)
            ->get(['cc.action', 'cc.over_cap', 'cc.created_at', 'sr.id as sr_id', 'sr.sr_number', 'u.name as user_name']);

        $requests = DB::table('service_requests')
            ->where('contract_id', $contract->id)
            ->orderByDesc('id')->limit(100)
            ->get(['id', 'sr_number', 'status', 'coverage_type', 'created_at']);

        $history = ActivityLog::query()
            ->where('item_type', Contract::class)->where('item_id', $contract->id)
            ->orderByDesc('id')->limit(100)->get()
            ->map(function ($row) use ($money) {
                $hide = $money ? [] : ['annual_value', 'payment_terms'];
                foreach (['old_values', 'new_values'] as $col) {
                    $v = $row->{$col};
                    if (is_string($v)) $v = json_decode($v, true);
                    $row->{$col} = is_array($v) ? array_diff_key($v, array_flip($hide)) : [];
                }
                return $row;
            });

        return view('admin.contracts.show', [
            'contract'  => $contract,
            'sites'     => $sites,
            'cats'      => $cats,
            'calls'     => $calls,
            'requests'  => $requests,
            'history'   => $history,
            'documents' => $money ? $contract->documents()->orderByDesc('id')->get() : collect(),
            'showMoney' => $money,
            'canManage' => (bool) auth()->user()?->canDo('contract.manage'),
            'tab'       => in_array(request('tab'), ['details', 'calls', 'requests', 'documents', 'history'], true) ? request('tab') : 'details',
        ] + $this->labels());
    }

    /* --------------------------------------------------------------- actions */

    public function activate(Contract $contract)
    {
        $this->canManage();
        $contract->activate();

        $this->audit->log('contract.activated', ['item_type' => Contract::class, 'item_id' => $contract->id, 'new' => ['status' => $contract->status, 'contract_number' => $contract->contract_number]]);
        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Contract activated.');
    }

    public function renew(Contract $contract)
    {
        $this->canManage();
        abort_unless($contract->isRenewable(), 409, 'This contract cannot be renewed.');

        $copy = $contract->renewAsDraft();

        $this->audit->log('contract.renewed', [
            'item_type' => Contract::class, 'item_id' => $contract->id,
            'new' => ['contract_number' => $contract->contract_number, 'new_contract_id' => $copy->id, 'new_contract_number' => $copy->contract_number],
        ]);

        return redirect()->route('admin.contracts.edit', $copy)
            ->with('success', "Draft {$copy->contract_number} created. Check the dates and terms, then activate it.");
    }

    public function cancel(Request $request, Contract $contract)
    {
        $this->canManage();
        $data = $request->validate(
            ['cancel_reason' => ['required', 'string', 'min:3', 'max:255']],
            ['cancel_reason.*' => 'Enter a reason of 3 to 255 characters.']
        );

        $old = $contract->status;
        $contract->cancel(trim($data['cancel_reason']));

        $this->audit->log('contract.cancelled', [
            'item_type' => Contract::class, 'item_id' => $contract->id,
            'old' => ['status' => $old],
            'new' => ['status' => 'cancelled', 'cancel_reason' => $contract->cancel_reason, 'contract_number' => $contract->contract_number],
        ]);

        return redirect()->route('admin.contracts.show', $contract)->with('success', 'Contract cancelled.');
    }

    public function destroy(Contract $contract)
    {
        $this->canManage();
        if (! $contract->isDeletable()) {
            return redirect()->route('admin.contracts.show', $contract)
                ->with('error', 'Only a draft with no requests can be deleted.');
        }

        $number = $contract->contract_number;
        DB::transaction(function () use ($contract) {
            foreach ($contract->documents()->get() as $doc) {
                Storage::disk('local')->delete($doc->storage_path);
                $doc->delete();
            }
            $contract->delete();   // soft delete
        });

        $this->audit->log('contract.deleted', ['item_type' => Contract::class, 'item_id' => $contract->id, 'old' => ['contract_number' => $number]]);
        return redirect()->route('admin.contracts.index')->with('success', "{$number} deleted.");
    }

    /* ------------------------------------------------------------- documents */

    public function documentDownload(Contract $contract, int $document)
    {
        $this->canView();
        abort_unless(Contract::canSeeMoney(), 403);          // files show prices and terms (A1, option 2)

        $doc = $contract->documents()->findOrFail($document);
        abort_unless(Storage::disk('local')->exists($doc->storage_path), 404);

        return Storage::disk('local')->download($doc->storage_path, $doc->original_name ?: 'contract-file');
    }

    public function documentDestroy(Contract $contract, int $document)
    {
        $this->canManage();
        $doc = $contract->documents()->findOrFail($document);

        Storage::disk('local')->delete($doc->storage_path);
        $name = $doc->original_name;
        $doc->delete();

        $this->audit->log('contract.updated', ['item_type' => Contract::class, 'item_id' => $contract->id, 'old' => ['document' => $name], 'new' => ['document' => null, 'contract_number' => $contract->contract_number]]);
        return redirect()->route('admin.contracts.show', [$contract, 'tab' => 'documents'])->with('success', 'File removed.');
    }

    protected function storeDocuments(Contract $contract, array $files): void
    {
        foreach ($files as $file) {
            /** @var UploadedFile $file */
            if (! $file || ! $file->isValid()) continue;

            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $name = Str::uuid() . '__' . $safe;
            $path = $file->storeAs("contracts/{$contract->id}", $name, 'local');   // private disk, served only by documentDownload

            Attachment::create([
                'tenant_id'           => $contract->tenant_id,
                'attachable_type'     => Contract::class,
                'attachable_id'       => $contract->id,
                'kind'                => 'document',
                'storage_path'        => $path,
                'original_name'       => $file->getClientOriginalName(),
                'mime_type'           => $file->getClientMimeType(),
                'size_bytes'          => $file->getSize(),
                'sha256'              => hash_file('sha256', $file->getRealPath()),
                'uploaded_by_user_id' => auth()->id(),
                'client_event_uuid'   => (string) Str::uuid(),
            ]);

            $this->audit->log('contract.document_added', [
                'item_type' => Contract::class, 'item_id' => $contract->id,
                'new' => ['file' => $file->getClientOriginalName(), 'contract_number' => $contract->contract_number],
            ]);
        }
    }

    /* --------------------------------------------------------------- helpers */

    protected function formData(Contract $contract, string $mode): array
    {
        $coveredSites = old('site_ids', $contract->exists ? $contract->sites()->pluck('project_sites.id')->all() : []);
        $coveredCats  = old('category_ids', $contract->exists ? $contract->categories()->pluck('service_categories.id')->all() : []);

        return [
            'contract' => $contract,
            'mode'     => $mode,
            'clients'  => Client::where(fn ($q) => $q->where('is_active', true)
                    ->when($contract->client_id, fn ($q2) => $q2->orWhere('id', $contract->client_id)))
                ->orderBy('name')->get(['id', 'name']),
            'sites'    => ProjectSite::clientSites()->where('is_active', true)->orderBy('name')->get(['id', 'client_id', 'name', 'site_code']),
            'categories' => ServiceCategory::where(fn ($q) => $q->where('is_active', true)
                    ->when($contract->exists, fn ($q2) => $q2->orWhereIn('id', $contract->categories()->pluck('service_categories.id'))))
                ->orderBy('name')->get(['id', 'name']),
            'coveredSites' => array_map('intval', (array) $coveredSites),
            'coveredCats'  => array_map('intval', (array) $coveredCats),
            'showMoney'    => Contract::canSeeMoney(),
            'documents'    => $contract->exists ? $contract->documents()->orderByDesc('id')->get() : collect(),
        ] + $this->labels();
    }

    protected function snapshot(Contract $c): array
    {
        return collect($c->only([
            'contract_number', 'client_id', 'contract_type', 'status', 'start_date', 'end_date',
            'response_sla_minutes', 'resolution_sla_minutes', 'max_calls', 'call_period',
            'annual_value', 'payment_terms', 'auto_renew',
        ]))->map(fn ($v) => $this->plain($v))->all();
    }

    protected function plain($v)
    {
        return $v instanceof Carbon ? $v->toDateString() : $v;
    }

    protected function labels(): array
    {
        $row = DB::table('tenant_terminology')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('term_key', 'contract')->first();

        return [
            'label'  => ucwords(data_get($row, 'singular') ?: 'contract'),
            'plural' => ucwords(data_get($row, 'plural') ?: 'contracts'),
        ];
    }
}
