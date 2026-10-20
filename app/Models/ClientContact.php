<?php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'client_id', 'name', 'email', 'phone',
        'whatsapp_number', 'whatsapp_opt_in_at', 'whatsapp_opt_out_at',
        'receives_events_json', 'site_scope_json', 'is_primary',
    ];

    protected $casts = [
        'is_primary'            => 'boolean',
        'whatsapp_opt_in_at'    => 'datetime',
        'whatsapp_opt_out_at'   => 'datetime',
        'receives_events_json'  => 'array',
        'site_scope_json'       => 'array',
    ];

    public function client() { return $this->belongsTo(Client::class); }
}