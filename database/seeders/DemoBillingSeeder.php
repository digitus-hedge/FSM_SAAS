<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceToken;
use App\Models\Payment;
use App\Models\ProjectSite;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for checking B7 Part 1 by hand (currencies, payments ledger, aging, overdue reminders).
 * NOT part of DatabaseSeeder. Run it by hand, on a development database only:
 *
 *     php artisan db:seed --class=DemoBillingSeeder
 *
 * It refuses to run when APP_ENV is production. It adds to the "skyline" company: one client with a contact, one site,
 * three service requests and three invoices (not due, 10 days overdue, USD 40 days overdue) and one payment.
 * Running it twice does nothing the second time (the client code DEMO-B7 marks the data).
 *
 * To remove the demo data again: php artisan demo:remove-billing (App\Console\Commands\RemoveDemoBilling).
 */
class DemoBillingSeeder extends Seeder
{
    public const SLUG        = 'skyline';
    public const CLIENT_CODE = 'DEMO-B7';
    private const USD_RATE    = '88.500000'; // INR for one USD, fixed on the demo invoice

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DemoBillingSeeder refuses to run when APP_ENV is production.');
        }

        $tenant = Tenant::where('slug', self::SLUG)->first();
        if (! $tenant) {
            throw new \RuntimeException('Company "' . self::SLUG . '" not found. Nothing was added.');
        }

        if (Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('client_code', self::CLIENT_CODE)->exists()) {
            $this->say('Demo data is already there (client ' . self::CLIENT_CODE . '). Nothing was added.');
            return;
        }

        $admin = User::where('tenant_id', $tenant->id)->whereHas('role', fn ($q) => $q->where('code', 'company_admin'))->first();
        $today = Carbon::now($tenant->timezone ?: config('app.timezone'))->startOfDay();

        DB::transaction(function () use ($tenant, $admin, $today) {
            $client = Client::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'client_code' => self::CLIENT_CODE, 'name' => 'Demo Client B7',
                'payment_terms_days' => 30, 'is_active' => true,
            ]);

            $contact = ClientContact::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Demo Contact',
                'email' => 'demo.contact@example.test', 'is_primary' => true, 'is_active' => true,
            ]);

            $site = ProjectSite::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id, 'name' => 'Demo Site B7',
            ]);

            $category = ServiceCategory::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'DEMO-B7'],
                ['name' => 'Demo service', 'is_active' => true],
            );

            // [description, currency, rate, amount, due date, payment]
            $rows = [
                ['Demo: not yet due',        $tenant->base_currency, '1',            5000, $today->copy()->addDays(10), null],
                ['Demo: 10 days overdue',    $tenant->base_currency, '1',            8000, $today->copy()->subDays(10), null],
                ['Demo: USD, 40 days overdue', 'USD',                self::USD_RATE, 1000, $today->copy()->subDays(40), 400],
            ];

            foreach ($rows as [$title, $currency, $rate, $amount, $due, $paid]) {
                $sr = ServiceRequest::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id, 'sr_number' => ServiceRequest::nextSrNumber($tenant->id),
                    'client_id' => $client->id, 'project_site_id' => $site->id, 'service_category_id' => $category->id,
                    'title' => $title, 'status' => 'completed', 'billing_status' => 'invoiced',
                    'coverage_type' => 'out_of_coverage', 'proof_rules_snapshot' => [],
                    'created_by_user_id' => $admin?->id,
                ]);

                $invoice = Invoice::create([
                    'tenant_id' => $tenant->id, 'client_id' => $client->id, 'service_request_id' => $sr->id,
                    'invoice_number' => Invoice::nextNumber($tenant->id), 'status' => 'sent',
                    'currency_code' => $currency, 'exchange_rate' => $rate, 'rate_source' => $currency === $tenant->base_currency ? null : 'manual',
                    'subtotal' => $amount, 'total' => $amount, 'balance' => $amount,
                    'base_total' => \App\Services\Currency\Money::toBase($amount, $rate, $tenant->base_currency),
                    'due_date' => $due->toDateString(), 'issued_at' => $due->copy()->subDays(30), 'sent_at' => $due->copy()->subDays(30),
                    'approved_at' => $due->copy()->subDays(30), 'created_by_user_id' => $admin?->id,
                    'notes' => 'Demo data for checking B7 Part 1.',
                ]);

                $invoice->items()->create([
                    'line_no' => 1, 'description' => $title, 'quantity' => 1, 'unit_rate' => $amount, 'line_total' => $amount,
                ]);

                // The contact the invoice was "sent to": reminders go to the contacts on the invoice's secure links.
                InvoiceToken::create([
                    'tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'client_contact_id' => $contact->id,
                    'recipient_name' => $contact->name, 'token' => InvoiceToken::generate(), 'sent_via' => 'email', 'sent_at' => $invoice->sent_at,
                ]);
                InvoiceEvent::create([
                    'tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'user_id' => $admin?->id,
                    'type' => 'sent', 'label' => 'Sent to ' . $contact->email . ' (demo)', 'occurred_at' => $invoice->sent_at,
                ]);

                if ($paid) {
                    // currency_code and base_amount are filled by the Payment model, like any payment recorded in the app.
                    Payment::create([
                        'tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'payment_number' => Payment::nextNumber($tenant->id),
                        'amount' => $paid, 'received_at' => now(), 'method' => 'bank_transfer', 'reference' => 'DEMO-REF-1',
                        'received_by_user_id' => $admin?->id,
                    ]);
                    $invoice->recomputeBalance();
                }
            }
        });

        $this->say('Demo data added to ' . $tenant->name . ': 1 client, 1 site, 3 service requests, 3 invoices, 1 payment.');
    }

    private function say(string $message): void
    {
        $this->command ? $this->command->info($message) : null;
    }
}
