<?php

namespace Tests\Feature\Currency;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceToken;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Currency\FakeRateProvider;
use App\Services\Tenants\SeedStandardRoles;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared set-up for the Currency and payments tests (docs/specs/Currency-and-Payments.md, section 10).
 *
 * These tests need MySQL: the existing migrations do not run on SQLite. They refuse to run on any database whose
 * name does not end in "_test", because RefreshDatabase drops every table. Run them with:
 *   DB_CONNECTION=mysql DB_DATABASE=fsm_saas_test php artisan test tests/Feature/Currency
 */
abstract class BillingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_ends_with((string) config('database.connections.' . config('database.default') . '.database'), '_test')) {
            $this->markTestSkipped('Run with DB_CONNECTION=mysql DB_DATABASE=fsm_saas_test (the existing migrations do not run on SQLite).');
        }

        // Invoices here point at service requests we do not need to build.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        config(['rates.provider' => 'fake']);
        FakeRateProvider::reset();
        $this->seed(PermissionCatalogSeeder::class);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        Tenant::setCurrent(null);
        parent::tearDown();
    }

    protected function plan(string $code, array $features): Plan
    {
        return Plan::firstOrCreate(['code' => $code], ['name' => ucfirst($code), 'is_active' => true, 'features' => $features]);
    }

    protected function proPlan(): Plan
    {
        return $this->plan('professional', [
            'multi_currency' => true, 'billing_full' => true,
            'forms' => true, 'form_templates_limit' => 10, 'approval_auto' => true, 'approval_finance' => false, 'pm_time' => true,
            'multi_trade' => true, 'vendors' => true, 'depot' => true,
            'reports_full' => true, 'brand_pdf' => true,
        ]);
    }

    protected function starterPlan(): Plan
    {
        return $this->plan('starter', ['multi_currency' => false, 'billing_full' => false]);
    }

    protected function makeTenant(?Plan $plan = null, string $base = 'AED', string $tz = 'Asia/Dubai', string $slug = null): Tenant
    {
        $slug ??= 't' . (Tenant::count() + 1);
        $tenant = Tenant::create([
            'slug' => $slug, 'name' => strtoupper($slug) . ' Ltd', 'status' => 'active', 'source' => 'platform_admin',
            'plan_id' => ($plan ?? $this->proPlan())->id, 'base_currency' => $base, 'timezone' => $tz,
        ]);
        app(SeedStandardRoles::class)->handle($tenant);

        // The first company of a test is the one route() builds URLs for until someone signs in (path mode, CLAUDE.md Tenancy)
        if (! \App\Support\Tenancy::hasCompanyUrls()) {
            $this->forTenant($tenant);
        }

        return $tenant->fresh();
    }

    protected function makeUser(Tenant $tenant, string $roleCode, ?string $email = null): User
    {
        $role = \App\Models\Role::where('tenant_id', $tenant->id)->where('code', $roleCode)->firstOrFail();

        return User::create([
            'tenant_id' => $tenant->id, 'role_id' => $role->id, 'name' => ucfirst($roleCode) . ' ' . $tenant->slug,
            'email' => $email ?? ($roleCode . '@' . $tenant->slug . '.test'), 'password' => 'password',
        ]);
    }

    protected function makeClient(Tenant $tenant, string $name = 'Acme Client'): Client
    {
        return Client::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'client_code' => 'C' . (Client::withoutGlobalScopes()->count() + 1), 'name' => $name,
        ]);
    }

    protected function makeInvoice(Tenant $tenant, Client $client, array $attrs = []): Invoice
    {
        static $n = 0;
        $n++;
        $total = $attrs['total'] ?? 1000;

        return Invoice::create(array_merge([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'service_request_id' => 1,
            'invoice_number' => 'INV-T-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'status' => 'sent', 'currency_code' => $tenant->base_currency, 'exchange_rate' => 1,
            'total' => $total, 'balance' => $attrs['balance'] ?? $total, 'base_total' => $total,
            'due_date' => now()->addDays(10)->toDateString(),
        ], $attrs));
    }

    /** A contact on the client with an email, and a secure link of the invoice sent to it. */
    protected function sendTo(Invoice $invoice, string $email): InvoiceToken
    {
        $contact = ClientContact::withoutGlobalScopes()->create([
            'tenant_id' => $invoice->tenant_id, 'client_id' => $invoice->client_id, 'name' => 'Contact ' . $email, 'email' => $email, 'is_active' => true,
        ]);

        return InvoiceToken::create([
            'tenant_id' => $invoice->tenant_id, 'invoice_id' => $invoice->id, 'client_contact_id' => $contact->id,
            'recipient_name' => $contact->name, 'token' => InvoiceToken::generate(), 'sent_via' => 'email', 'sent_at' => now(),
        ]);
    }

    /** A service request (the older tables have no factory). Status defaults to the one an invoice can be built from. */
    protected function makeServiceRequest(Tenant $tenant, Client $client, string $status = 'awaiting_invoice'): \App\Models\ServiceRequest
    {
        static $n = 0;
        $n++;

        return \App\Models\ServiceRequest::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'sr_number' => 'SR-T-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'client_id' => $client->id, 'project_site_id' => 1, 'service_category_id' => 1,
            'title' => 'Test job ' . $n, 'status' => $status, 'coverage_type' => 'out_of_coverage', 'proof_rules_snapshot' => [],
        ]);
    }

    /** A quote with one line of 1,000 in the given currency (total 1,000 when there is no tax). */
    protected function makeQuote(Tenant $tenant, Client $client, \App\Models\ServiceRequest $sr, array $attrs = []): \App\Models\Quote
    {
        static $n = 0;
        $n++;
        $total = $attrs['total'] ?? 1000;
        $rate  = $attrs['exchange_rate'] ?? 1;

        $quote = \App\Models\Quote::create(array_merge([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'service_request_id' => $sr->id,
            'quote_number' => 'QT-T-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT), 'source' => 'builder', 'status' => 'draft',
            'currency_code' => $tenant->base_currency, 'exchange_rate' => $rate, 'subtotal' => $total, 'total' => $total,
            'base_total' => round($total * $rate, 3), 'valid_until' => now()->addDays(10)->toDateString(),
            'created_by_user_id' => 1,
        ], $attrs));
        \App\Models\QuoteItem::create(['quote_id' => $quote->id, 'line_no' => 1, 'description' => 'Work', 'quantity' => 1, 'unit_rate' => $total, 'line_total' => $total]);

        return $quote;
    }

    /** The form fields of a builder save: one line of 1,000 unless told otherwise. */
    protected function builderPayload(array $extra = [], string $dateField = 'valid_until'): array
    {
        return array_merge([
            $dateField => now()->addDays(10)->toDateString(), 'discount_type' => 'none', 'discount_value' => 0,
            'lines' => [['description' => 'Work done', 'quantity' => 1, 'unit_rate' => 1000, 'tax_rate_id' => null]],
        ], $extra);
    }

    /** A request on the company's own address, the way a person uses the product (the tenant scope is then active). */
    protected function onHost(Tenant $tenant, string $path = ''): string
    {
        return \App\Support\TenantUrl::for($tenant, $path);   // path mode /{slug}{path}, subdomain mode {slug}.host{path}
    }

    protected function enableCurrency(Tenant $tenant, string $code, ?string $rate = '3.67250000', ?string $date = null): void
    {
        \App\Models\TenantCurrency::create(['tenant_id' => $tenant->id, 'currency_code' => $code, 'is_active' => true]);
        if ($rate !== null) {
            $this->setRate($tenant, $code, $date ?? now($tenant->timezone)->toDateString(), $rate);
        }
    }

    /** Change the rate already stored for today (there is one row for each company, pair and date). */
    protected function changeRate(Tenant $tenant, string $from, string $rate, string $source = 'provider'): void
    {
        \App\Models\ExchangeRate::where('tenant_id', $tenant->id)->where('from_currency', $from)->update(['rate' => $rate, 'source' => $source]);
    }

    protected function setRate(Tenant $tenant, string $from, string $date, string $rate, string $source = 'provider'): void
    {
        \App\Models\ExchangeRate::create([
            'tenant_id' => $tenant->id, 'from_currency' => $from, 'to_currency' => $tenant->base_currency,
            'rate' => $rate, 'rate_date' => $date, 'source' => $source,
        ]);
    }
}
