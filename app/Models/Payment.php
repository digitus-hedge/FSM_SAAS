<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id', 'invoice_id', 'payment_number', 'amount',
        'received_at', 'method', 'reference', 'note', 'received_by_user_id',
        'reversed_at', 'reversed_by_user_id', 'reversal_reason',
    ];

    protected $casts = [
        'amount'      => 'decimal:3',
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