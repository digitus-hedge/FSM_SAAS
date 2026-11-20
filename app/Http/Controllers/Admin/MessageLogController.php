<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Services\Audit\AuditLogger;
use App\Services\Messaging\MessageLogger;
use Illuminate\Http\Request;

/**
 * The company's message log (Release A scope "the message log with retry"; blueprint 11.10). Route gate level:notification.manage:
 * the Company Admin (full) opens it and sends a failed message again; the Operations Manager (view only) only reads it.
 * A company sees only its own rows. A retry only ever applies to a failed row that can be built again, and every retry is audited.
 */
class MessageLogController extends Controller
{
    public function __construct(private MessageLogger $messages, private AuditLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:' . implode(',', array_keys(MessageLog::STATUSES))],
            'channel' => ['nullable', 'in:' . implode(',', array_keys(MessageLog::CHANNELS))],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        $tz = \App\Models\Tenant::current()?->timezone ?: config('app.timezone');
        $logs = MessageLog::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->input('channel')))
            ->when($request->filled('event'), fn ($q) => $q->where('event_key', $request->input('event')))
            ->when(trim((string) $request->input('q', '')) !== '', fn ($q) => $q->where('recipient_address', 'like', '%' . trim((string) $request->input('q')) . '%'))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($request->input('from'), $tz)->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($request->input('to'), $tz)->endOfDay()))
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.message-log.index', [
            'logs'      => $logs,
            'events'    => MessageLog::query()->whereNotNull('event_key')->distinct()->orderBy('event_key')->pluck('event_key'),
            'failed'    => MessageLog::query()->where('status', 'failed')->count(),
            'logger'    => $this->messages,
            'tz'        => $tz,
        ]);
    }

    public function retry(MessageLog $messageLog)
    {
        if (! $messageLog->isFailed()) {
            return back()->with('error', 'Only a failed message can be sent again.');
        }

        try {
            $log = $this->messages->retry($messageLog);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('message.retried', [
            'item_type' => MessageLog::class, 'item_id' => $log->id,
            'new' => ['event' => $log->event_key, 'channel' => $log->channel, 'recipient' => $log->recipient_address, 'result' => $log->status, 'attempts' => $log->attempts],
        ]);

        return $log->status === 'sent'
            ? back()->with('success', 'The message was sent.')
            : back()->with('error', 'It failed again: ' . ($log->failure_reason ?: 'unknown error') . '.');
    }
}
