<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One message the system tried to send outside the app (see the migration for what it may and may not hold). */
class MessageLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'direction', 'channel', 'event_key', 'recipient_user_id', 'recipient_contact_id', 'recipient_address',
        'status', 'failure_reason', 'attempts', 'mailable', 'related_type', 'related_id', 'payload_json',
        'first_attempt_at', 'last_attempt_at', 'sent_at',
    ];

    protected $casts = [
        'payload_json'     => 'array',
        'first_attempt_at' => 'datetime',
        'last_attempt_at'  => 'datetime',
        'sent_at'          => 'datetime',
    ];

    public const STATUSES = ['queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed'];
    public const CHANNELS = ['email' => 'Email', 'whatsapp' => 'WhatsApp', 'push' => 'Push'];

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
