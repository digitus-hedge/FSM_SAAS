<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'tenant_id','quote_number','service_request_id','client_id',
        'source','status','version_no','supersedes_quote_id',
        'currency_code','exchange_rate','rate_source',
        'subtotal','tax_total','discount_total','total','base_total',
        'valid_until','terms','pdf_attachment_id',
        'discount_type','discount_value',
        'expired_at','sent_at','responded_at','responded_by_name','responded_ip','rejection_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'sent_at'     => 'datetime',
        'responded_at'=> 'datetime',
        'expired_at'  => 'datetime',
    ];

    public function items()           { return $this->hasMany(QuoteItem::class)->orderBy('line_no'); }
    public function serviceRequest()  { return $this->belongsTo(ServiceRequest::class); }
    public function client()          { return $this->belongsTo(Client::class); }
    public function creator()         { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function pdfAttachment()   { return $this->belongsTo(Attachment::class, 'pdf_attachment_id'); }
    public function previousVersion() { return $this->belongsTo(Quote::class, 'supersedes_quote_id'); }
    public function events()          { return $this->hasMany(QuoteEvent::class)->orderByDesc('occurred_at'); }

    /** Generate the next QT-YYYY-NNNNNN for this tenant */
    public static function nextNumber(int $tenantId): string {
        $year = date('Y');
        $prefix = "QT-{$year}-";
        $last = self::where('tenant_id', $tenantId)
            ->where('quote_number', 'like', $prefix.'%')
            ->orderByDesc('id')->value('quote_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }
}