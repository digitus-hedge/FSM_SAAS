<?php

namespace App\Services\Billing;

use App\Mail\InvoiceOverdueMail;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceToken;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\Money;
use App\Services\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The daily overdue job for one company (LED-03, spec 6.10 and 6.11, D13 to D18). Safe to run twice on the same day.
 *  1. Mark sent and part-paid invoices overdue from the day after their due date.
 *  2. Send each reminder that is due. Reminder slots fall on: due date + first-reminder days + k x gap. A slot that
 *     was missed is caught up with ONE reminder (never several), and the gap then counts from the day it was sent.
 * The dedupe is an atomic compare-and-set on reminder_count, so an invoice never gets the same reminder number twice.
 * No company is signed in when this runs, so every query carries the tenant id.
 */
class OverdueReminderService
{
    public function __construct(private AuditLogger $audit, private NotificationService $notifications)
    {
    }

    /** @return array{marked:int, reminders:int} */
    public function runForTenant(Tenant $tenant, Carbon $today): array
    {
        $out = ['marked' => 0, 'reminders' => 0];
        $day = $today->toDateString();

        // 1. Overdue marking is unchanged from Release A (D13), so it runs for every plan.
        $due = Invoice::query()->where('tenant_id', $tenant->id)
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereNotNull('due_date')->whereDate('due_date', '<', $day)
            ->where('balance', '>', 0)->get();

        foreach ($due as $inv) {
            $inv->forceFill(['status' => 'overdue'])->save();
            $out['marked']++;
        }

        // 2. Reminders: Professional and above, and only when the company has them on.
        $settings = TenantSetting::forTenant($tenant->id);
        if (! $tenant->hasFeature('billing_full') || ! $settings->overdue_reminders_enabled) {
            return $out;
        }

        $candidates = Invoice::query()->where('tenant_id', $tenant->id)
            ->where('status', 'overdue')->where('balance', '>', 0)
            ->whereNull('reminders_paused_at')
            ->where('reminder_count', '<', $settings->overdue_max_reminders)
            ->whereNotNull('due_date')->get();

        foreach ($candidates as $inv) {
            if ($this->isDue($inv, $settings, $today) && $this->send($tenant, $inv, $settings, $today)) {
                $out['reminders']++;
            }
        }

        return $out;
    }

    /** Is a reminder slot at or before today, and later than the last reminder or the last resume? (6.10) */
    public function isDue(Invoice $inv, TenantSetting $s, Carbon $today): bool
    {
        $first = Carbon::parse($inv->due_date->toDateString(), 'UTC')->addDays((int) $s->overdue_first_after_days);
        $now   = Carbon::parse($today->toDateString(), 'UTC');

        if ($now->lt($first)) {
            return false;
        }

        $gap        = max(1, (int) $s->overdue_repeat_days);
        $latestSlot = $first->copy()->addDays(intdiv($first->diffInDays($now), $gap) * $gap);

        $anchor = $this->anchor($inv, $today->timezone);

        return $anchor === null || $latestSlot->gt(Carbon::parse($anchor, 'UTC'));
    }

    /** The date (company time zone) of the last reminder or the last resume, whichever is later. */
    private function anchor(Invoice $inv, $tz): ?string
    {
        $dates = [];
        if ($inv->last_reminder_at) {
            $dates[] = $inv->last_reminder_at->copy()->setTimezone($tz)->toDateString();
        }
        $resumed = InvoiceEvent::query()->where('invoice_id', $inv->id)->where('type', 'reminders_resumed')->max('occurred_at');
        if ($resumed) {
            $dates[] = Carbon::parse($resumed, config('app.timezone'))->setTimezone($tz)->toDateString();
        }

        return $dates ? max($dates) : null;
    }

    private function send(Tenant $tenant, Invoice $inv, TenantSetting $s, Carbon $today): bool
    {
        $n = $inv->reminder_count + 1;

        // Once for each invoice and reminder number: only the run that moves the count from n-1 to n sends it (CP-080).
        $won = Invoice::query()->whereKey($inv->id)->where('reminder_count', $n - 1)
            ->update(['reminder_count' => $n, 'last_reminder_at' => now()]);
        if (! $won) {
            return false;
        }
        $inv->refresh();
        $inv->load(['client', 'serviceRequest']);

        $daysOverdue = (int) Carbon::parse($inv->due_date->toDateString(), 'UTC')->diffInDays(Carbon::parse($today->toDateString(), 'UTC'));
        $recipients  = $this->sendToClient($inv);
        $this->sendToFinance($tenant, $inv, $n, (int) $s->overdue_max_reminders, $daysOverdue, $recipients);

        InvoiceEvent::create([
            'tenant_id' => $tenant->id, 'invoice_id' => $inv->id, 'user_id' => null,
            'type' => 'reminder_sent', 'label' => "Reminder {$n} of {$s->overdue_max_reminders} sent", 'occurred_at' => now(),
        ]);
        $this->audit->log('invoice.reminder_sent', [
            'tenant_id' => $tenant->id, 'item_type' => 'invoice', 'item_id' => $inv->id,
            'new' => ['reminder' => $n, 'recipients' => $recipients],
        ]);

        return true;
    }

    /**
     * The client contacts the invoice was sent to (its secure links), D15. WhatsApp is not built yet, so every
     * contact gets the email (D16: reminders go by email until the template is approved).
     * @return string[] the email addresses that were sent to
     */
    private function sendToClient(Invoice $inv): array
    {
        $sent = [];
        $tokens = InvoiceToken::query()->where('invoice_id', $inv->id)->with('contact')->get();

        foreach ($tokens as $token) {
            $email = $token->contact?->email;
            if (! filled($email) || ($token->contact->is_active === false) || isset($sent[strtolower($email)])) {
                continue;
            }
            try {
                app(\App\Services\Messaging\MessageLogger::class)->send($email, new InvoiceOverdueMail($inv, $token), 'invoice.overdue', [
                    'tenant_id' => $inv->tenant_id, 'contact_id' => $token->client_contact_id, 'related' => $inv, 'payload' => ['invoice_id' => $inv->id, 'token_id' => $token->id],
                ]);
                $sent[strtolower($email)] = $email;
            } catch (\Throwable $e) {
                Log::warning('Overdue reminder email failed', ['invoice' => $inv->id, 'error' => $e->getMessage()]);
            }
        }

        return array_values($sent);
    }

    /** Pause reminders on one invoice with a reason of 3 to 255 characters (D17). Audited. */
    public function pause(Invoice $inv, string $reason, User $user): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw new \InvalidArgumentException('Enter a reason of 3 to 255 characters.');
        }

        $inv->forceFill(['reminders_paused_at' => now(), 'reminders_paused_reason' => $reason])->save();
        InvoiceEvent::create(['tenant_id' => $inv->tenant_id, 'invoice_id' => $inv->id, 'user_id' => $user->id,
            'type' => 'reminders_paused', 'label' => $reason, 'occurred_at' => now()]);
        $this->audit->log('invoice.reminders_paused', ['tenant_id' => $inv->tenant_id, 'item_type' => 'invoice', 'item_id' => $inv->id, 'new' => ['reason' => $reason]]);
    }

    /**
     * Resume reminders. Reminders missed while paused are not made up: the resume day counts like a sent reminder,
     * so the next one goes on the next schedule slot after today (6.10, CP-075).
     */
    public function resume(Invoice $inv, User $user): void
    {
        $inv->forceFill(['reminders_paused_at' => null, 'reminders_paused_reason' => null])->save();
        InvoiceEvent::create(['tenant_id' => $inv->tenant_id, 'invoice_id' => $inv->id, 'user_id' => $user->id,
            'type' => 'reminders_resumed', 'label' => null, 'occurred_at' => now()]);
        $this->audit->log('invoice.reminders_resumed', ['tenant_id' => $inv->tenant_id, 'item_type' => 'invoice', 'item_id' => $inv->id]);
    }

    /** Every active Finance user gets the email of section 7 (event invoice.overdue). */
    private function sendToFinance(Tenant $tenant, Invoice $inv, int $n, int $max, int $days, array $recipients): void
    {
        $finance = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deactivated_at')
            ->with('role')->get()->filter(fn ($u) => $u->role?->code === 'finance');

        $this->notifications->notify('invoice.overdue', $finance, [
            'n'              => $n,
            'max'            => $max,
            'invoice_number' => $inv->invoice_number,
            'client'         => $inv->client?->name ?? '',
            'balance'        => $inv->currency_code . ' ' . Money::format($inv->balance, $inv->currency_code),
            'days'           => $days,
            'recipients'     => $recipients ? implode(', ', $recipients) : 'no client contact',
        ], route('admin.invoices.show', $inv, false), ['invoice_id' => $inv->id, 'reminder' => $n]);
    }
}
