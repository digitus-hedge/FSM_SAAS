<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceEvent extends Model
{
    protected $fillable = ['tenant_id', 'invoice_id', 'user_id', 'type', 'label', 'occurred_at'];
    protected $casts    = ['occurred_at' => 'datetime'];

    public function user()    { return $this->belongsTo(User::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }

    public static function log(int $invoiceId, string $type, ?string $label = null): self {
        return self::create([
            'tenant_id'   => auth()->user()->tenant_id,
            'invoice_id'  => $invoiceId,
            'user_id'     => auth()->id(),
            'type'        => $type,
            'label'       => $label,
            'occurred_at' => now(),
        ]);
    }
}