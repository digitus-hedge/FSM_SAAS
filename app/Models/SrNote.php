<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SrNote extends Model
{
    protected $table = 'sr_notes';

    protected $fillable = [
        'tenant_id', 'service_request_id', 'user_id',
        'visibility', 'source', 'body',
    ];

    public function user()           { return $this->belongsTo(User::class); }
    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
}