<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteEvent extends Model
{
    protected $fillable = ['tenant_id','quote_id','user_id','type','label','occurred_at'];
    protected $casts    = ['occurred_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }

    public static function log(int $quoteId, string $type, ?string $label = null): self {
        return self::create([
            'tenant_id'   => auth()->user()->tenant_id,
            'quote_id'    => $quoteId,
            'user_id'     => auth()->id(),
            'type'        => $type,
            'label'       => $label,
            'occurred_at' => now(),
        ]);
    }
}