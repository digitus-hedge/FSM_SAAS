<?php

namespace Tests\Feature\Currency;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * CP-043 and CP-106: the four B7 migrations run forward and back, and fill existing payments.
 * Changing a table commits the open transaction in MySQL, so this test cleans up after itself.
 */
class MigrationsTest extends BillingTestCase
{
    private const FILES = [
        '2026_11_19_100001_add_currency_and_base_amount_to_payments_table',
        '2026_11_19_100002_create_tenant_currencies_table',
        '2026_11_19_100003_add_overdue_reminder_settings_to_tenant_settings_table',
        '2026_11_19_100004_add_reminder_columns_to_invoices_table',
    ];

    private function migration(string $name)
    {
        return require database_path("migrations/{$name}.php");
    }

    #[Test]
    public function CP_106_the_migrations_run_back_and_forward(): void
    {
        foreach (array_reverse(self::FILES) as $f) {
            $this->migration($f)->down();
        }
        $this->assertFalse(Schema::hasColumn('payments', 'base_amount'));
        $this->assertFalse(Schema::hasColumn('payments', 'currency_code'));
        $this->assertFalse(Schema::hasTable('tenant_currencies'));
        $this->assertFalse(Schema::hasColumn('tenant_settings', 'overdue_max_reminders'));
        $this->assertFalse(Schema::hasColumn('invoices', 'reminder_count'));

        foreach (self::FILES as $f) {
            $this->migration($f)->up();
        }
        $this->assertTrue(Schema::hasColumns('payments', ['currency_code', 'base_amount']));
        $this->assertTrue(Schema::hasColumns('tenant_currencies', ['tenant_id', 'currency_code', 'is_active', 'created_by_user_id']));
        $this->assertTrue(Schema::hasColumns('tenant_settings', ['overdue_reminders_enabled', 'overdue_first_after_days', 'overdue_repeat_days', 'overdue_max_reminders']));
        $this->assertTrue(Schema::hasColumns('invoices', ['reminder_count', 'last_reminder_at', 'reminders_paused_at', 'reminders_paused_reason']));
    }

    #[Test]
    public function the_part_2_migrations_run_back_and_forward_and_keep_a_rate_with_8_decimals(): void
    {
        $widen = $this->migration('2026_11_20_100001_widen_exchange_rate_on_quotes_and_invoices');
        $items = $this->migration('2026_11_20_100002_add_converted_at_rate_to_invoice_items_table');
        $scale = fn (string $table) => (int) DB::table('information_schema.columns')->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)->where('column_name', 'exchange_rate')->value('NUMERIC_SCALE');

        $items->down();
        $widen->down();
        $this->assertFalse(Schema::hasColumn('invoice_items', 'converted_at_rate'));
        $this->assertSame([6, 6], [$scale('quotes'), $scale('invoices')]);

        $widen->up();
        $items->up();
        $this->assertTrue(Schema::hasColumn('invoice_items', 'converted_at_rate'));
        $this->assertSame([8, 8], [$scale('quotes'), $scale('invoices')]);
    }

    #[Test]
    public function CP_043_existing_payments_get_the_invoice_currency_and_base_amount(): void
    {
        $t = $this->makeTenant();
        $client = $this->makeClient($t);
        $aed = $this->makeInvoice($t, $client);
        $usd = $this->makeInvoice($t, $client, ['currency_code' => 'USD', 'exchange_rate' => '3.6725']);
        $ids = [$t->id];

        try {
            // Release A state: payments without the new columns.
            $this->migration(self::FILES[0])->down();
            foreach ([[$aed, '100.000', 'PAY-OLD-1'], [$usd, '1000.000', 'PAY-OLD-2'], [$usd, '333.333', 'PAY-OLD-3']] as [$inv, $amt, $no]) {
                DB::table('payments')->insert(['tenant_id' => $t->id, 'invoice_id' => $inv->id, 'payment_number' => $no, 'amount' => $amt,
                    'received_at' => '2026-11-01 10:00:00', 'method' => 'cash', 'created_at' => now(), 'updated_at' => now()]);
            }

            $this->migration(self::FILES[0])->up();

            $rows = Payment::where('tenant_id', $t->id)->orderBy('payment_number')->get()->keyBy('payment_number');
            $this->assertSame(['AED', '100.000'], [$rows['PAY-OLD-1']->currency_code, (string) $rows['PAY-OLD-1']->base_amount]);
            $this->assertSame(['USD', '3672.500'], [$rows['PAY-OLD-2']->currency_code, (string) $rows['PAY-OLD-2']->base_amount]);
            $this->assertSame('1224.170', (string) $rows['PAY-OLD-3']->base_amount); // 333.333 x 3.6725 = 1224.1657, half up to 2 decimals
        } finally {
            DB::table('payments')->whereIn('tenant_id', $ids)->delete();
            DB::table('invoices')->whereIn('tenant_id', $ids)->delete();
            DB::table('clients')->whereIn('tenant_id', $ids)->delete();
            DB::table('role_permissions')->whereIn('tenant_id', $ids)->delete();
            DB::table('roles')->whereIn('tenant_id', $ids)->delete();
            DB::table('tenants')->whereIn('id', $ids)->delete();
        }
    }
}
