<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuoteToken extends Model
{
    protected $fillable = [
        'tenant_id','quote_id','client_contact_id','recipient_name',
        'token','sent_via','sent_at','viewed_first_at','viewed_count',
    ];
    protected $casts = [
        'sent_at'         => 'datetime',
        'viewed_first_at' => 'datetime',
    ];

    public function quote()   { return $this->belongsTo(Quote::class); }
    public function contact() { return $this->belongsTo(ClientContact::class, 'client_contact_id'); }

    public static function generate(): string {
        return Str::random(64);
    }
}