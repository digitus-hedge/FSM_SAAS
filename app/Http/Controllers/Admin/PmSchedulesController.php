<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Contract;
use App\Models\FormTemplate;
use App\Models\PmSchedule;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Forms\FormException;
use App\Services\Forms\FormTemplates;
use App\Services\Maintenance\Frequency;
use App\Services\Maintenance\PmScheduleAccess;
use App\Services\Maintenance\PmSchedules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Maintenance schedules and the calendar (Preventive-Maintenance spec PMC-01, PMC-06). Reading routes carry level:pm.view,
 * changing routes level:pm.manage, all behind the `pm_time` plan gate.
 */
class PmSchedulesController extends Controller
{
    private function tenant(): Tenant
    {
        return Tenant::findOrFail(auth()->user()->tenant_id);
    }

    /** The company's own date, without a time zone attached, so it compares with the date columns. */
    private function today(Tenant $tenant): Carbon
    {
        return Carbon::parse(Carbon::now($tenant->timezone ?: config('app.timezone'))->toDateString());
    }

    public function index(Request $request)
    {
        $tenant = $this->tenant();
        $query  = PmScheduleAccess::narrow(PmSchedule::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user())
            ->with(['site:id,name', 'category:id,name', 'technician:id,name'])->withCount('assetRows')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('site'), fn ($q) => $q->where('project_site_id', $request->input('site')))
            ->when($request->filled('category'), fn ($q) => $q->where('service_category_id', $request->input('category')))
            ->when($request->filled('technician'), fn ($q) => $q->where('default_technician_user_id', $request->input('technician')))
            ->orderBy('next_due_date')->orderBy('name');

        return view('admin.maintenance.index', [
            'schedules'  => $query->paginate(25)->withQueryString(),
            'sites'      => ProjectSite::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'technicians' => $this->technicians($tenant),
            'canManage'  => PmScheduleAccess::mayManage($request->user()),
        ]);
    }

    public function create()
    {
        return view('admin.maintenance.form', $this->formData($this->tenant(), new PmSchedule([
            'frequency' => 'monthly', 'lead_days' => 7, 'priority' => 'medium', 'skip_if_open' => true, 'first_due_date' => now(),
        ])));
    }

    public function store(Request $request)
    {
        $tenant = $this->tenant();
        $data   = $this->validated($request, $tenant);
        if (Carbon::parse($data['first_due_date'])->lt($this->today($tenant)->subDay())) {
            return back()->withInput()->withErrors(['first_due_date' => 'The first due date cannot be more than a day in the past.']);
        }

        $schedule = app(PmSchedules::class)->create($tenant, $request->user(), $data, $data['asset_ids']);

        return redirect()->route('admin.maintenance.show', $schedule)->with('success', "Schedule {$schedule->name} created.");
    }

    public function show(Request $request, PmSchedule $schedule)
    {
        abort_unless(PmScheduleAccess::mayView($request->user(), $schedule), 404);
        $schedule->load(['site', 'category', 'technician', 'contract', 'template', 'assetRows.asset', 'runs.serviceRequest']);

        return view('admin.maintenance.show', [
            'schedule' => $schedule, 'canManage' => PmScheduleAccess::mayManage($request->user()),
            'requests' => ServiceRequest::withoutGlobalScopes()->where('pm_schedule_id', $schedule->id)->orderByDesc('pm_due_date')->limit(20)->get(),
            'upcoming' => $schedule->status === 'active'
                ? Frequency::between($schedule->first_due_date, $schedule->frequency, $schedule->interval_value, $schedule->next_due_date, $schedule->next_due_date->copy()->addYear())
                : [],
        ]);
    }

    public function edit(PmSchedule $schedule)
    {
        if ($schedule->status === 'ended') {
            throw new FormException('PM_SCHEDULE_ENDED', 'This schedule has ended and cannot be changed.');
        }

        return view('admin.maintenance.form', $this->formData($this->tenant(), $schedule->load('assetRows')));
    }

    public function update(Request $request, PmSchedule $schedule)
    {
        $tenant = $this->tenant();
        $data   = $this->validated($request, $tenant);
        app(PmSchedules::class)->update($schedule, $data, $data['asset_ids'], $this->today($tenant));

        return redirect()->route('admin.maintenance.show', $schedule)->with('success', 'Schedule saved.');
    }

    public function pause(PmSchedule $schedule)
    {
        app(PmSchedules::class)->pause($schedule);

        return back()->with('success', 'Schedule paused.');
    }

    public function resume(PmSchedule $schedule)
    {
        app(PmSchedules::class)->resume($schedule, $this->today($this->tenant()));

        return back()->with('success', 'Schedule resumed.');
    }

    public function end(PmSchedule $schedule)
    {
        app(PmSchedules::class)->end($schedule, $this->today($this->tenant()));

        return back()->with('success', 'Schedule ended.');
    }

    public function destroy(PmSchedule $schedule)
    {
        app(PmSchedules::class)->delete($schedule);

        return redirect()->route('admin.maintenance.index')->with('success', 'Schedule deleted.');
    }

    /** AJAX: the assets a schedule may take at a site, of one category. */
    public function assets(Request $request)
    {
        $tid = auth()->user()->tenant_id;

        return response()->json(Asset::withoutGlobalScopes()->where('tenant_id', $tid)->whereNull('deleted_at')
            ->where('project_site_id', (int) $request->input('site_id'))->where('service_category_id', (int) $request->input('category_id'))
            ->where('status', '!=', 'decommissioned')->orderBy('asset_code')->get(['id', 'asset_code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'label' => $a->asset_code . ' — ' . $a->name]));
    }

    /** The calendar (PMC-06, D16): made requests and the due dates still to come, for up to 6 months. */
    public function calendar(Request $request)
    {
        $tenant = $this->tenant();
        $from   = Carbon::parse($request->input('from', $this->today($tenant)->startOfMonth()->toDateString()));
        $to     = Carbon::parse($request->input('to', $from->copy()->endOfMonth()->toDateString()));
        if ($to->lt($from) || $from->diffInMonths($to) > 6) {
            return back()->with('error', 'Choose a range of up to 6 months.');
        }

        $schedules = PmScheduleAccess::narrow(PmSchedule::withoutGlobalScopes()->where('tenant_id', $tenant->id), $request->user())
            ->when($request->filled('site'), fn ($q) => $q->where('project_site_id', $request->input('site')))
            ->when($request->filled('category'), fn ($q) => $q->where('service_category_id', $request->input('category')))
            ->when($request->filled('technician'), fn ($q) => $q->where('default_technician_user_id', $request->input('technician')))
            ->with(['site:id,name', 'category:id,name'])->get();

        $made = ServiceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('pm_schedule_id', $schedules->pluck('id'))
            ->whereBetween('pm_due_date', [$from->toDateString(), $to->toDateString()])->get();

        $items = [];
        foreach ($made as $sr) {
            $items[] = ['date' => Carbon::parse($sr->pm_due_date)->toDateString(), 'kind' => 'request', 'status' => $sr->status,
                'label' => $sr->sr_number . ' ' . $sr->title, 'url' => route('service-requests.show', $sr), 'schedule_id' => $sr->pm_schedule_id];
        }
        foreach ($schedules->where('status', 'active') as $s) {
            foreach (Frequency::between($s->first_due_date, $s->frequency, $s->interval_value, max($from, $s->next_due_date), $to) as $d) {
                if ($s->end_date && $d->gt($s->end_date)) {
                    break;
                }
                if ($made->contains(fn ($sr) => (int) $sr->pm_schedule_id === (int) $s->id && Carbon::parse($sr->pm_due_date)->isSameDay($d))) {
                    continue;
                }
                $items[] = ['date' => $d->toDateString(), 'kind' => 'upcoming', 'status' => null, 'label' => $s->name . ' (' . ($s->site?->name ?? '') . ')',
                    'url' => route('admin.maintenance.show', $s), 'schedule_id' => $s->id];
            }
        }
        usort($items, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return view('admin.maintenance.calendar', [
            'items' => collect($items)->groupBy('date'), 'from' => $from, 'to' => $to,
            'sites' => ProjectSite::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'technicians' => $this->technicians($tenant),
        ]);
    }

    // ───────────── helpers ─────────────

    private function technicians(Tenant $tenant)
    {
        return User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deactivated_at')
            ->whereHas('role', fn ($q) => $q->whereIn('code', ['field_technician', 'team_lead']))->orderBy('name')->get(['id', 'name']);
    }

    private function formData(Tenant $tenant, PmSchedule $schedule): array
    {
        return [
            'schedule' => $schedule, 'sites' => ProjectSite::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'technicians' => $this->technicians($tenant),
            'templates' => app(FormTemplates::class)->enabled($tenant)
                ? FormTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']) : collect(),
            'contracts' => $tenant->hasFeature('contracts')
                ? Contract::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'expiring_soon'])->orderBy('contract_number')->get(['id', 'contract_number']) : collect(),
            'frequencies' => PmSchedule::FREQUENCIES, 'priorities' => ServiceRequest::PRIORITIES,
            'selectedAssets' => $schedule->exists ? $schedule->assetRows->pluck('asset_id')->all() : [],
        ];
    }

    private function validated(Request $request, Tenant $tenant): array
    {
        $tid = $tenant->id;

        return $request->validate([
            'name'                       => ['required', 'string', 'max:180'],
            'project_site_id'            => ['required', Rule::exists('project_sites', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'service_category_id'        => ['required', Rule::exists('service_categories', 'id')->where('tenant_id', $tid)->where('is_active', 1)->whereNull('deleted_at')],
            'asset_ids'                  => ['required', 'array', 'min:1', 'max:200'],
            'asset_ids.*'                => ['integer'],
            'frequency'                  => ['required', Rule::in(array_keys(PmSchedule::FREQUENCIES))],
            'interval_value'             => ['nullable', 'integer'],
            'first_due_date'             => ['required', 'date'],
            'next_due_date'              => ['nullable', 'date'],
            'lead_days'                  => ['required', 'integer', 'min:0', 'max:60'],
            'priority'                   => ['required', Rule::in(array_keys(ServiceRequest::PRIORITIES))],
            'form_template_id'           => ['nullable', Rule::exists('form_templates', 'id')->where('tenant_id', $tid)->where('status', 'active')],
            'default_technician_user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tid)],
            'contract_id'                => ['nullable', Rule::exists('contracts', 'id')->where('tenant_id', $tid)],
            'skip_if_open'               => ['nullable', 'boolean'],
            'end_date'                   => ['nullable', 'date', 'after_or_equal:first_due_date'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
