<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'reopen_window_days', 'feedback_link_days',
        'client_sign_in_link_hours', 'require_two_factor',
    ];

    protected $casts = ['require_two_factor' => 'boolean'];
}