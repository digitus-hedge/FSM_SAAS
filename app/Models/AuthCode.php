<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthCode extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'channel',
        'code_hash', 'expires_at', 'attempts', 'verified_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];
}