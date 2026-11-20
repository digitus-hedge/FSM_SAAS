<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ONE-TIME correction of the arrival time of maintenance requests made before the time zone rule (Models\Concerns\AppTimezoneDates).
 *
 * What was wrong: PmGenerator saved `scheduled_eta_at` as a Carbon made in the COMPANY's zone (09:00 on the due date). Eloquent wrote its wall-clock
 * time (09:00) and every reader takes the column in the APPLICATION zone, so the request showed 09:00 in the application zone instead of 09:00 in the
 * company's zone. The error is the difference between the two zones (for example 1.5 hours for a company in Dubai when the application zone is Asia/Kolkata).
 * Nothing else in the code saved a Carbon in another zone: see the list in the project notes (CLAUDE.md, "Gotchas").
 *
 * Which rows: requests made by a maintenance schedule (`pm_schedule_id` set) whose arrival time is still exactly the generated slot (the due date
 * at 09:00:00) and that are not finished (Pending, Approved or Assigned). A request someone moved or assigned to another time is already right and is left alone.
 *
 * SLA-safe: only `scheduled_eta_at` changes. The statement is a plain query update, so no model event fires (no notification, no master status
 * recalculation, no SLA clock change), `updated_at` is not touched and no SLA column (clock start, due times, pause, warned and breached flags) is read or written.
 * The SLA due times do not depend on the arrival time.
 *
 * Safety: it refuses to change anything unless --confirm is given (without it, it only lists what it would do and exits with an error).
 * With --confirm it first writes a backup file (storage/app/time-fixes/...json) and an audit row for each company. --revert=<file> --confirm puts the old values back
 * for the rows still holding the corrected value.
 */
class FixPmEtaTimes extends Command
{
    protected $signature = 'times:fix-pm-eta {--confirm : Really change the rows} {--tenant= : Only this company id} {--revert= : Put back the values of a backup file}';

    protected $description = 'One-time: correct the arrival time of maintenance requests saved in the wrong time zone (refuses to run without --confirm).';

    public function handle(AuditLogger $audit): int
    {
        if ($this->option('revert')) {
            return $this->revert((string) $this->option('revert'), $audit);
        }

        $plan = $this->plan();
        $total = array_sum(array_map('count', $plan));

        $this->line('Application time zone: ' . config('app.timezone'));
        foreach ($plan as $tenantId => $rows) {
            $this->line(sprintf('Company %d: %d request(s)%s', $tenantId, count($rows), $rows ? ', for example ' . $rows[0]['sr_number'] . ' ' . $rows[0]['old'] . ' -> ' . $rows[0]['new'] : ''));
        }
        $this->line("Total: {$total} request(s) would change (column scheduled_eta_at only).");

        if (! $this->option('confirm')) {
            $this->error('Nothing was changed. Run again with --confirm to apply it.');

            return self::FAILURE;
        }
        if ($total === 0) {
            $this->info('Nothing to correct.');

            return self::SUCCESS;
        }

        $file = 'time-fixes/pm-eta-' . now()->format('Ymd-His') . '.json';
        Storage::disk('local')->put($file, json_encode($plan, JSON_PRETTY_PRINT));
        $this->line('Backup written to storage/app/private/' . $file . ' (or storage/app/' . $file . ' on older disks).');

        foreach ($plan as $tenantId => $rows) {
            if (! $rows) {
                continue;
            }
            DB::transaction(function () use ($rows, $tenantId, $audit, $file) {
                foreach ($rows as $r) {
                    // only while the row still holds the old value: a request changed in the meantime is not touched
                    DB::table('service_requests')->where('id', $r['id'])->where('scheduled_eta_at', $r['old'])->update(['scheduled_eta_at' => $r['new']]);
                }
                $audit->log('data.time_corrected', [
                    'tenant_id' => $tenantId, 'item_type' => 'service_requests',
                    'new' => ['column' => 'scheduled_eta_at', 'rows' => count($rows), 'backup' => $file, 'reason' => 'maintenance requests saved in the company zone instead of the application zone'],
                ]);
            });
        }
        $this->info("Corrected {$total} request(s).");

        return self::SUCCESS;
    }

    /** @return array<int,array<int,array{id:int,sr_number:string,old:string,new:string}>> by company id */
    private function plan(): array
    {
        $app  = config('app.timezone');
        $plan = [];

        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->get();
        foreach ($tenants as $tenant) {
            $tz = $tenant->timezone ?: $app;
            $plan[$tenant->id] = [];

            $rows = DB::table('service_requests')
                ->where('tenant_id', $tenant->id)->whereNotNull('pm_schedule_id')->whereNull('deleted_at')
                ->whereIn('status', ['pending', 'approved', 'assigned'])
                ->whereRaw("TIME(scheduled_eta_at) = '09:00:00'")->whereRaw('DATE(scheduled_eta_at) = pm_due_date')
                ->orderBy('id')->get(['id', 'sr_number', 'scheduled_eta_at']);

            foreach ($rows as $r) {
                $old = (string) $r->scheduled_eta_at;
                $new = Carbon::parse($old, $tz)->setTimezone($app)->format('Y-m-d H:i:s');   // the same wall-clock time, read in the company's zone
                if ($new !== $old) {
                    $plan[$tenant->id][] = ['id' => (int) $r->id, 'sr_number' => (string) $r->sr_number, 'old' => $old, 'new' => $new];
                }
            }
        }

        return $plan;
    }

    private function revert(string $file, AuditLogger $audit): int
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($file)) {
            $this->error("Backup file not found on the local disk: {$file}");

            return self::FAILURE;
        }
        $plan = json_decode($disk->get($file), true) ?: [];
        $total = array_sum(array_map('count', $plan));
        $this->line("The backup holds {$total} request(s).");

        if (! $this->option('confirm')) {
            $this->error('Nothing was changed. Run again with --confirm to put the old values back.');

            return self::FAILURE;
        }

        $back = 0;
        foreach ($plan as $tenantId => $rows) {
            foreach ($rows as $r) {
                $back += DB::table('service_requests')->where('id', $r['id'])->where('scheduled_eta_at', $r['new'])->update(['scheduled_eta_at' => $r['old']]);
            }
            if ($rows) {
                $audit->log('data.time_correction_reverted', ['tenant_id' => $tenantId, 'item_type' => 'service_requests', 'new' => ['column' => 'scheduled_eta_at', 'rows' => count($rows), 'backup' => $file]]);
            }
        }
        $this->info("Put back {$back} request(s).");

        return self::SUCCESS;
    }
}
