<?php

namespace Tests\Feature\Currency;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceCategory;
use Carbon\Carbon;
use Database\Seeders\DemoBillingSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/** demo:remove-billing deletes what DemoBillingSeeder added, and only that. */
class RemoveDemoBillingTest extends BillingTestCase
{
    private function skyline()
    {
        $t = $this->makeTenant(base: 'INR', tz: 'Asia/Kolkata', slug: 'skyline');
        $this->makeUser($t, 'company_admin');

        return $t;
    }

    private function demoCounts($tid): array
    {
        $demo = Client::withoutGlobalScopes()->where('tenant_id', $tid)->where('client_code', 'DEMO-B7')->pluck('id');

        return [
            'clients'  => $demo->count(),
            'invoices' => Invoice::where('tenant_id', $tid)->whereIn('client_id', $demo)->count(),
            'payments' => Payment::where('tenant_id', $tid)->count(),
            'srs'      => DB::table('service_requests')->where('tenant_id', $tid)->whereIn('client_id', $demo)->count(),
            'sites'    => DB::table('project_sites')->where('tenant_id', $tid)->whereIn('client_id', $demo)->count(),
            'contacts' => DB::table('client_contacts')->where('tenant_id', $tid)->whereIn('client_id', $demo)->count(),
            'category' => ServiceCategory::withoutGlobalScopes()->where('tenant_id', $tid)->where('code', 'DEMO-B7')->count(),
        ];
    }

    #[Test]
    public function it_removes_all_the_demo_data_including_reminder_rows_and_notices(): void
    {
        Mail::fake();
        $t = $this->skyline();
        $this->makeUser($t, 'finance'); // so the overdue job also creates Finance notices for the demo invoices
        $this->seed(DemoBillingSeeder::class);
        Carbon::setTestNow(Carbon::now('Asia/Kolkata')->setTime(9, 0));
        $this->artisan('invoices:overdue-daily', ['--force' => true])->assertSuccessful();
        $this->assertGreaterThan(0, DB::table('user_notifications')->where('event_key', 'invoice.overdue')->count());
        $this->assertGreaterThan(0, DB::table('invoice_events')->where('type', 'reminder_sent')->count());
        $auditBefore = ActivityLog::count();

        $this->artisan('demo:remove-billing', ['--force' => true])->assertSuccessful();

        $this->assertSame(array_fill_keys(['clients', 'invoices', 'payments', 'srs', 'sites', 'contacts', 'category'], 0), $this->demoCounts($t->id));
        foreach (['invoice_items', 'invoice_events', 'invoice_tokens', 'user_notifications'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertGreaterThanOrEqual($auditBefore, ActivityLog::count()); // the audit log is never trimmed
    }

    #[Test]
    public function it_leaves_other_clients_and_other_companies_untouched(): void
    {
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);

        $other = $this->makeClient($t, 'Real Client');
        $realInvoice = $this->makeInvoice($t, $other, ['total' => 777, 'balance' => 777]);
        $this->sendTo($realInvoice, 'real@client.test');

        $b = $this->makeTenant(slug: 'othertenant');
        $bClient = Client::withoutGlobalScopes()->create(['tenant_id' => $b->id, 'client_code' => 'DEMO-B7', 'name' => 'Same code, other company']);
        $bInvoice = $this->makeInvoice($b, $bClient);

        $this->artisan('demo:remove-billing', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, $this->demoCounts($t->id)['clients']);
        $this->assertTrue(Invoice::whereKey($realInvoice->id)->exists());
        $this->assertSame(1, DB::table('invoice_tokens')->where('invoice_id', $realInvoice->id)->count());
        $this->assertTrue(Client::withoutGlobalScopes()->whereKey($other->id)->exists());
        $this->assertTrue(Client::withoutGlobalScopes()->whereKey($bClient->id)->exists()); // same client code, different company
        $this->assertTrue(Invoice::whereKey($bInvoice->id)->exists());
    }

    #[Test]
    public function it_asks_first_and_deletes_nothing_when_the_answer_is_no(): void
    {
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);

        $this->artisan('demo:remove-billing')->expectsConfirmation('Delete these rows?', 'no')->assertSuccessful();

        $this->assertSame(1, $this->demoCounts($t->id)['clients']);
        $this->assertSame(3, $this->demoCounts($t->id)['invoices']);

        $this->artisan('demo:remove-billing')->expectsConfirmation('Delete these rows?', 'yes')->assertSuccessful();
        $this->assertSame(0, $this->demoCounts($t->id)['clients']);
    }

    #[Test]
    public function it_refuses_in_production_and_deletes_nothing(): void
    {
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);
        $this->app['env'] = 'production';

        try {
            $this->artisan('demo:remove-billing', ['--force' => true])->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(1, $this->demoCounts($t->id)['clients']);
    }

    #[Test]
    public function it_does_nothing_when_there_is_no_demo_data(): void
    {
        $t = $this->skyline();

        $this->artisan('demo:remove-billing', ['--force' => true])->expectsOutputToContain('Nothing to remove')->assertSuccessful();
    }

    #[Test]
    public function it_is_blocked_and_deletes_nothing_when_another_record_depends_on_the_demo_client(): void
    {
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);
        $client = Client::withoutGlobalScopes()->where('tenant_id', $t->id)->where('client_code', 'DEMO-B7')->first();
        DB::table('contracts')->insert([
            'tenant_id' => $t->id, 'contract_number' => 'CON-DEMO-1', 'client_id' => $client->id, 'contract_type' => 'amc',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('demo:remove-billing', ['--force' => true])->expectsOutputToContain('contracts of the demo client: 1')->assertFailed();

        $c = $this->demoCounts($t->id);
        $this->assertSame([1, 3, 1], [$c['clients'], $c['invoices'], $c['payments']]);
    }

    #[Test]
    public function the_demo_category_stays_when_something_else_still_uses_it(): void
    {
        $t = $this->skyline();
        $this->seed(DemoBillingSeeder::class);
        $cat = ServiceCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('code', 'DEMO-B7')->first();
        $other = $this->makeClient($t, 'Real Client');
        $site = DB::table('project_sites')->insertGetId(['tenant_id' => $t->id, 'client_id' => $other->id, 'name' => 'Real site', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('service_requests')->insert([
            'tenant_id' => $t->id, 'sr_number' => 'SR-REAL-1', 'client_id' => $other->id, 'project_site_id' => $site,
            'service_category_id' => $cat->id, 'title' => 'Real job', 'proof_rules_snapshot' => '[]', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('demo:remove-billing', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, $this->demoCounts($t->id)['clients']);
        $this->assertSame(1, $this->demoCounts($t->id)['category']);
    }
}
