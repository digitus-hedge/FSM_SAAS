<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DemoBillingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes what DemoBillingSeeder added for the "skyline" company, and nothing else. Everything is found through the
 * client with code DEMO-B7 in that company, so other clients, other companies and Skyline's own settings are never touched.
 * Development only: it refuses to run when APP_ENV is production.
 *
 * Kept on purpose: the audit log (append only), and Skyline's currencies, rates and reminder settings.
 */
class RemoveDemoBilling extends Command
{
    protected $signature = 'demo:remove-billing {--force : Do not ask for confirmation}';

    protected $description = 'Delete the demo data added by DemoBillingSeeder (client DEMO-B7 of the skyline company). Development only.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('demo:remove-billing refuses to run when APP_ENV is production.');

            return self::FAILURE;
        }

        $tenant = Tenant::where('slug', DemoBillingSeeder::SLUG)->first();
        if (! $tenant) {
            $this->error('Company "' . DemoBillingSeeder::SLUG . '" not found. Nothing was removed.');

            return self::FAILURE;
        }
        $tid = (int) $tenant->id;

        $client = DB::table('clients')->where('tenant_id', $tid)->where('client_code', DemoBillingSeeder::CLIENT_CODE)->first();
        if (! $client) {
            $this->info('Nothing to remove: no client ' . DemoBillingSeeder::CLIENT_CODE . ' in ' . $tenant->name . '.');

            return self::SUCCESS;
        }

        $ids = $this->collect($tid, (int) $client->id);

        if ($blockers = $this->blockers($tid, $ids)) {
            $this->error('Not removed. Other records depend on the demo data:');
            foreach ($blockers as $line) {
                $this->line('  - ' . $line);
            }
            $this->line('Remove or move those records first, then run this command again. Nothing was deleted.');

            return self::FAILURE;
        }

        $this->info('Demo data of ' . $tenant->name . ' (client ' . DemoBillingSeeder::CLIENT_CODE . '):');
        $this->table(['Table', 'Rows'], collect($this->counts($tid, $ids))->map(fn ($n, $t) => [$t, $n])->values()->all());

        if (! $this->option('force') && ! $this->confirm('Delete these rows?', false)) {
            $this->line('Nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = DB::transaction(fn () => $this->delete($tid, $ids));

        $this->info('Removed: ' . collect($deleted)->filter()->map(fn ($n, $t) => "{$t} {$n}")->implode(', ') . '.');
        $this->line('Kept: the audit log, and ' . $tenant->name . "'s currencies, rates and reminder settings.");

        return self::SUCCESS;
    }

    /** Ids of everything that belongs to the demo client. @return array<string, array<int, int>|int|null> */
    private function collect(int $tid, int $clientId): array
    {
        $sr = DB::table('service_requests')->where('tenant_id', $tid)->where('client_id', $clientId)->pluck('id')->all();

        return [
            'client'   => $clientId,
            'sr'       => $sr,
            'invoice'  => DB::table('invoices')->where('tenant_id', $tid)->where('client_id', $clientId)->pluck('id')->all(),
            'site'     => DB::table('project_sites')->where('tenant_id', $tid)->where('client_id', $clientId)->pluck('id')->all(),
            'contact'  => DB::table('client_contacts')->where('tenant_id', $tid)->where('client_id', $clientId)->pluck('id')->all(),
            'quote'    => DB::table('quotes')->where('tenant_id', $tid)->where(fn ($q) => $q->where('client_id', $clientId)->orWhereIn('service_request_id', $sr ?: [0]))->pluck('id')->all(),
            'category' => DB::table('service_categories')->where('tenant_id', $tid)->where('code', DemoBillingSeeder::CLIENT_CODE)->value('id'),
        ];
    }

    /** Records outside the demo set that would be orphaned or block the delete. @return string[] */
    private function blockers(int $tid, array $ids): array
    {
        $out = [];
        $add = function (string $label, int $n) use (&$out) {
            if ($n > 0) {
                $out[] = "{$label}: {$n}";
            }
        };
        $sr   = $ids['sr'] ?: [0];
        $site = $ids['site'] ?: [0];

        $add('service requests of other clients on the demo site', DB::table('service_requests')->where('tenant_id', $tid)->whereIn('project_site_id', $site)->where('client_id', '!=', $ids['client'])->count());
        $add('invoices of other clients on demo requests', DB::table('invoices')->whereIn('service_request_id', $sr)->where('client_id', '!=', $ids['client'])->count());
        $add('assets of the demo client or site', DB::table('assets')->where('tenant_id', $tid)->where(fn ($q) => $q->where('client_id', $ids['client'])->orWhereIn('project_site_id', $site))->count());
        $add('contracts of the demo client', DB::table('contracts')->where('tenant_id', $tid)->where('client_id', $ids['client'])->count());
        $add('contracts covering the demo site', DB::table('contract_sites')->whereIn('project_site_id', $site)->count());
        foreach (['contract_calls', 'service_request_expenses', 'service_request_punches', 'service_request_assets', 'signature_waiver_otps'] as $table) {
            $add("{$table} on demo requests", DB::table($table)->whereIn('service_request_id', $sr)->count());
        }

        return $out;
    }

    /** @return array<string, int> rows each table would lose */
    private function counts(int $tid, array $ids): array
    {
        $inv = $ids['invoice'] ?: [0];
        $sr  = $ids['sr'] ?: [0];
        $qt  = $ids['quote'] ?: [0];

        return [
            'payments'                    => DB::table('payments')->whereIn('invoice_id', $inv)->count(),
            'invoice_items'               => DB::table('invoice_items')->whereIn('invoice_id', $inv)->count(),
            'invoice_events'              => DB::table('invoice_events')->whereIn('invoice_id', $inv)->count(),
            'invoice_tokens'              => DB::table('invoice_tokens')->whereIn('invoice_id', $inv)->count(),
            'user_notifications'          => $this->notifications($tid, $ids['invoice'])->count(),
            'invoices'                    => count($ids['invoice']),
            'quotes (with lines, events, links)' => count($ids['quote']),
            'sr_status_history'           => DB::table('sr_status_history')->whereIn('service_request_id', $sr)->count(),
            'sr_events'                   => DB::table('sr_events')->whereIn('service_request_id', $sr)->count(),
            'sr_notes'                    => DB::table('sr_notes')->whereIn('service_request_id', $sr)->count(),
            'service_requests'            => count($ids['sr']),
            'project_sites'               => count($ids['site']),
            'client_contacts'             => count($ids['contact']),
            'clients'                     => 1,
            'service_categories'          => $ids['category'] ? 1 : 0,
        ];
    }

    /** Reminder notices for demo invoices. They would link to invoices that no longer exist. */
    private function notifications(int $tid, array $invoiceIds)
    {
        return DB::table('user_notifications')->where('tenant_id', $tid)->where('event_key', 'invoice.overdue')
            ->whereIn('data->invoice_id', $invoiceIds ?: [0]);
    }

    /** @return array<string, int> */
    private function delete(int $tid, array $ids): array
    {
        $inv = $ids['invoice'] ?: [0];
        $sr  = $ids['sr'] ?: [0];
        $qt  = $ids['quote'] ?: [0];
        $d   = [];

        $d['payments']           = DB::table('payments')->whereIn('invoice_id', $inv)->delete();
        $d['invoice_items']      = DB::table('invoice_items')->whereIn('invoice_id', $inv)->delete();
        $d['invoice_events']     = DB::table('invoice_events')->whereIn('invoice_id', $inv)->delete();
        $d['invoice_tokens']     = DB::table('invoice_tokens')->whereIn('invoice_id', $inv)->delete();
        $d['user_notifications'] = $this->notifications($tid, $ids['invoice'])->delete();
        $d['invoices']           = DB::table('invoices')->whereIn('id', $inv)->delete();

        foreach (['quote_events', 'quote_items', 'quote_tokens'] as $t) {
            DB::table($t)->whereIn('quote_id', $qt)->delete();
        }
        $d['quotes'] = DB::table('quotes')->whereIn('id', $qt)->delete();

        $d['sr_status_history'] = DB::table('sr_status_history')->whereIn('service_request_id', $sr)->delete();
        $d['sr_events']         = DB::table('sr_events')->whereIn('service_request_id', $sr)->delete();
        $d['sr_notes']          = DB::table('sr_notes')->whereIn('service_request_id', $sr)->delete();
        DB::table('sr_reschedules')->whereIn('service_request_id', $sr)->delete();
        DB::table('geofence_override_requests')->whereIn('service_request_id', $sr)->delete();
        $d['service_requests']  = DB::table('service_requests')->whereIn('id', $sr)->delete();

        $d['project_sites']   = DB::table('project_sites')->whereIn('id', $ids['site'] ?: [0])->delete();
        $d['client_contacts'] = DB::table('client_contacts')->whereIn('id', $ids['contact'] ?: [0])->delete();
        $d['clients']         = DB::table('clients')->where('id', $ids['client'])->where('tenant_id', $tid)->delete();

        // The demo category goes only when nothing else still uses it.
        $cat = $ids['category'];
        $d['service_categories'] = 0;
        if ($cat
            && ! DB::table('service_requests')->where('service_category_id', $cat)->exists()
            && ! DB::table('assets')->where('service_category_id', $cat)->exists()
            && ! DB::table('contract_categories')->where('service_category_id', $cat)->exists()) {
            $d['service_categories'] = DB::table('service_categories')->where('id', $cat)->where('tenant_id', $tid)->delete();
        }

        return $d;
    }
}
