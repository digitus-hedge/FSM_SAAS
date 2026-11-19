<?php
namespace App\Models;

use App\Services\Currency\Money;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant; // every query is limited to the signed-in company (the scope is off in jobs and commands)

    /**
     * CUR-04 / D7: a payment is in the invoice's currency, and its base amount is the amount times the invoice's
     * fixed rate, rounded to the base currency's decimals. Filled here so every way of recording a payment gets it.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if ($payment->currency_code && $payment->base_amount !== null) {
                return;
            }

            $invoice = Invoice::find($payment->invoice_id);
            if (! $invoice) {
                return;
            }

            $base = Tenant::query()->whereKey($payment->tenant_id ?: $invoice->tenant_id)->value('base_currency') ?: $invoice->currency_code;

            $payment->currency_code = $payment->currency_code ?: $invoice->currency_code;
            if ($payment->base_amount === null) {
                $payment->base_amount = Money::toBase((string) $payment->amount, (string) $invoice->exchange_rate, $base);
            }
        });
    }

    protected $fillable = [
        'currency_code', 'base_amount',
        'tenant_id', 'invoice_id', 'payment_number', 'amount',
        'received_at', 'method', 'reference', 'note', 'received_by_user_id',
        'reversed_at', 'reversed_by_user_id', 'reversal_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:3',
        'base_amount' => 'decimal:3',
        'received_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function invoice()      { return $this->belongsTo(Invoice::class); }
    public function receivedBy()   { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function reversedBy()   { return $this->belongsTo(User::class, 'reversed_by_user_id'); }

    public static function nextNumber(int $tenantId): string {
        $year = date('Y');
        $prefix = "PAY-{$year}-";
        $last = self::where('tenant_id', $tenantId)
            ->where('payment_number', 'like', $prefix.'%')
            ->orderByDesc('id')->value('payment_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}