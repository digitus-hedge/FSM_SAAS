<?php

namespace Tests\Feature\Currency;

use App\Services\Billing\DocumentTotals;
use PHPUnit\Framework\Attributes\Test;

/** The arithmetic of blueprint 7.7a (spec 6.3, D22), and CP-036: documents in KWD and JPY round to 3 and 0 decimals. */
class DocumentTotalsTest extends BillingTestCase
{
    private function line(float $q, float $r, float $tax = 0): array
    {
        return ['quantity' => $q, 'unit_rate' => $r, 'tax_percent' => $tax];
    }

    #[Test]
    public function a_plain_document_adds_up(): void
    {
        $t = DocumentTotals::compute([$this->line(2, 100), $this->line(1, 50.5, 5)], 'none', 0, 'AED');

        $this->assertSame(['200.00', '50.50'], $t['lines']);
        $this->assertSame(['250.50', '0.00', '2.53', '253.03'], [$t['subtotal'], $t['discount_total'], $t['tax_total'], $t['total']]); // 5% of 50.50 = 2.525, half up
    }

    #[Test]
    public function the_discount_is_shared_over_the_lines_and_tax_is_on_what_is_left_of_each_line(): void
    {
        // 10% off 300 = 30: 20 off the 200 line (untaxed), 10 off the 100 line (taxed 10%): tax = 10% x 90 = 9
        $t = DocumentTotals::compute([$this->line(1, 200), $this->line(1, 100, 10)], 'percent', 10, 'AED');

        $this->assertSame(['300.00', '30.00', '9.00', '279.00'], [$t['subtotal'], $t['discount_total'], $t['tax_total'], $t['total']]);
    }

    #[Test]
    public function an_amount_discount_never_goes_below_zero_and_its_shares_add_up_exactly(): void
    {
        $t = DocumentTotals::compute([$this->line(1, 0.01), $this->line(1, 0.01), $this->line(1, 0.01)], 'amount', 0.02, 'AED');
        $this->assertSame('0.02', $t['discount_total']);
        $this->assertSame('0.01', $t['total']);

        $over = DocumentTotals::compute([$this->line(1, 10)], 'amount', 500, 'AED');
        $this->assertSame(['10.00', '0.00'], [$over['discount_total'], $over['total']]);
    }

    #[Test]
    public function CP_036_kwd_rounds_to_three_decimals_and_jpy_to_none(): void
    {
        $kwd = DocumentTotals::compute([$this->line(3, 1.2345)], 'none', 0, 'KWD'); // 3.7035 -> 3.704
        $this->assertSame(['3.704', '3.704'], [$kwd['lines'][0], $kwd['total']]);

        $jpy = DocumentTotals::compute([$this->line(3, 33.333, 10)], 'none', 0, 'JPY'); // 99.999 -> 100, tax 10
        $this->assertSame(['100', '10', '110'], [$jpy['subtotal'], $jpy['tax_total'], $jpy['total']]);
    }
}
