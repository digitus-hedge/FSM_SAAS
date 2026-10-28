<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id', 'invoice_number', 'service_request_id', 'client_id',
        'source', 'status',
        'currency_code', 'exchange_rate', 'rate_source',
        'subtotal', 'tax_total', 'discount_total', 'total', 'balance', 'base_total',
        'discount_type', 'discount_value',
        'due_date', 'issued_at', 'sent_at',
        'submitted_at', 'submitted_by_user_id',
        'approved_at', 'approved_by_user_id',
        'send_back_at', 'send_back_reason',
        'voided_at', 'void_reason',
        'written_off_at', 'write_off_amount', 'write_off_reason',
        'legal_invoice_number', 'notes',
        'pdf_attachment_id', 'bank_snapshot_json',
        'created_by_user_id',
    ];

    protected $casts = [
        'due_date'           => 'date',
        'issued_at'          => 'datetime',
        'sent_at'            => 'datetime',
        'submitted_at'       => 'datetime',
        'approved_at'        => 'datetime',
        'send_back_at'       => 'datetime',
        'voided_at'          => 'datetime',
        'written_off_at'     => 'datetime',
        'bank_snapshot_json' => 'array',
    ];

    public function items()           { return $this->hasMany(InvoiceItem::class)->orderBy('line_no'); }
    public function serviceRequest()  { return $this->belongsTo(ServiceRequest::class); }
    public function client()          { return $this->belongsTo(Client::class); }
    public function creator()         { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function submitter()       { return $this->belongsTo(User::class, 'submitted_by_user_id'); }
    public function approver()        { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function pdfAttachment()   { return $this->belongsTo(Attachment::class, 'pdf_attachment_id'); }
    public function events()          { return $this->hasMany(InvoiceEvent::class)->orderByDesc('occurred_at'); }
    public function tokens()          { return $this->hasMany(InvoiceToken::class); }
    public function payments()        { return $this->hasMany(Payment::class)->orderByDesc('received_at'); }

    /** Generate the next INV-YYYY-NNNNNN for this tenant */
    public static function nextNumber(int $tenantId): string {
        $year = date('Y');
        $prefix = "INV-{$year}-";
        $last = self::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')->value('invoice_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /** Recompute balance from payments and update status accordingly */
    public function recomputeBalance(): void
    {
        $paid = $this->payments()->whereNull('reversed_at')->sum('amount');
        $writeOff = (float) ($this->write_off_amount ?? 0);
        $balance = max(0, $this->total - $paid - $writeOff);

        $newStatus = $this->status;
        if (in_array($this->status, ['sent', 'partially_paid', 'paid', 'overdue'])) {
            if ($balance <= 0.0005) {
                $newStatus = 'paid';
            } elseif ($paid > 0) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = $this->status === 'overdue' ? 'overdue' : 'sent';
            }
        }

        $this->update(['balance' => round($balance, 3), 'status' => $newStatus]);
    }
}