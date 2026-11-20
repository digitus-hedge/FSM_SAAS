<?php

namespace App\Services\Platform;

use App\Models\ImpersonationSession;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Support impersonation (blueprint 7.9a "Impersonation", 9.2 impersonation.started, decision 116).
 *  - Only the owner and support platform roles; a reason of 10 to 255 characters; 30 minutes by default, 2 hours at most; one open session for each platform user.
 *  - The session signs the platform user in to the company as one of its staff users, through the CentralLoginController handoff token.
 *  - Blocked while it runs (blockedReason): passwords, two-factor, users and roles, approving or voiding an invoice, sending a quote or an invoice,
 *    recording or reversing a payment, writing off a balance, and exporting data.
 *  - Start and end are written to the platform audit (company empty) and to the company's audit; every audit row of the company written during the
 *    session carries impersonation_session_id (AuditLogger). Every Company Admin is told when a session starts.
 */
class Impersonation
{
    public const DEFAULT_MINUTES = 30;
    public const MAX_MINUTES     = 120;
    public const ROLES           = ['owner', 'support'];

    /** [route name pattern, 'WRITE' (not GET or HEAD) or 'ANY', what the person tried to do] */
    private const BLOCKED = [
        ['password.*',                    'ANY',   'change a password'],
        ['two-factor.*',                  'ANY',   'change two-factor sign-in'],
        ['tenant.users.*',                'WRITE', 'change users'],
        ['tenant.roles.*',                'WRITE', 'change roles or permissions'],
        ['admin.invoices.approve',        'ANY',   'approve an invoice'],
        ['admin.invoices.void',           'ANY',   'void an invoice'],
        ['admin.invoices.send',           'ANY',   'send an invoice'],
        ['admin.quotes.send',             'ANY',   'send a quote'],
        ['admin.invoices.payment',        'ANY',   'record a payment'],
        ['admin.invoices.payment.reverse', 'ANY',  'reverse a payment'],
        ['admin.invoices.write-off',      'ANY',   'write off a balance'],
        ['admin.invoices.undo-write-off', 'ANY',   'undo a write-off'],
        ['*.export',                      'ANY',   'export data'],
        ['admin.reports.excel',           'ANY',   'export data'],
        ['admin.reports.pdf',             'ANY',   'export data'],
    ];

    public function __construct(private AuditLogger $audit)
    {
    }

    /** @throws ValidationException when the reason, the time, the person or the company is not acceptable */
    public function start(PlatformUser $platform, Tenant $tenant, ?int $targetUserId, string $reason, ?int $minutes, ?string $ip): ImpersonationSession
    {
        abort_unless(in_array($platform->platform_role, self::ROLES, true), 403, 'Only owner and support staff can sign in to a company.');

        $reason  = trim($reason);
        $minutes = $minutes ?: self::DEFAULT_MINUTES;
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Give a reason of 10 to 255 characters.']);
        }
        if ($minutes < 1 || $minutes > self::MAX_MINUTES) {
            throw ValidationException::withMessages(['minutes' => 'A session lasts at most ' . self::MAX_MINUTES . ' minutes.']);
        }
        if (! $tenant->isActive()) {
            throw ValidationException::withMessages(['tenant' => 'This company is not active.']);
        }

        $this->expireStale();
        if (ImpersonationSession::where('platform_user_id', $platform->id)->whereNull('ended_at')->where('expires_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['reason' => 'You already have a support session open. End it first.']);
        }

        $target = $this->target($tenant, $targetUserId);

        $session = ImpersonationSession::create([
            'platform_user_id' => $platform->id, 'tenant_id' => $tenant->id, 'target_user_id' => $target->id, 'reason' => $reason,
            'started_at' => now(), 'expires_at' => now()->addMinutes($minutes), 'ip_address' => $ip, 'actions_count' => 0,
        ]);

        $this->record('impersonation.started', $session, $platform, [
            'company' => $tenant->slug, 'signed_in_as_user_id' => $target->id, 'signed_in_as' => $target->name, 'reason' => $reason, 'minutes' => $minutes,
            'expires_at' => $session->expires_at->toIso8601String(),
        ]);

        $svc = app(NotificationService::class);
        $svc->notify('impersonation.started', $svc->usersWithRole('company_admin', (int) $tenant->id), [
            'who' => $platform->name, 'as_user' => $target->name, 'minutes' => $minutes, 'reason' => $reason,
        ], null, ['impersonation_session_id' => $session->id, 'priority' => 'urgent']);

        return $session;
    }

    /** $reason is ended (the End session button), expired (the time ran out) or signed_out. */
    public function end(ImpersonationSession $session, string $reason): void
    {
        if ($session->ended_at) {
            return;
        }
        $session->forceFill(['ended_at' => now(), 'end_reason' => $reason])->save();

        $this->record('impersonation.ended', $session, $session->platformUser, [
            'company' => $session->tenant?->slug, 'end_reason' => $reason, 'actions' => (int) $session->actions_count,
            'minutes_used' => (int) round($session->started_at->diffInSeconds($session->ended_at) / 60),
        ]);
    }

    /** Closes the sessions whose time is up and that nobody ended (run every minute). Returns how many. */
    public function expireStale(): int
    {
        $n = 0;
        foreach (ImpersonationSession::whereNull('ended_at')->where('expires_at', '<=', now())->get() as $s) {
            $this->end($s, 'expired');
            $n++;
        }

        return $n;
    }

    /** What the signed-in session may not do: the thing it tried to do, or null when the request is allowed. */
    public function blockedReason(?string $routeName, string $method): ?string
    {
        if (! $routeName) {
            return null;
        }
        $write = ! in_array(strtoupper($method), ['GET', 'HEAD'], true);
        foreach (self::BLOCKED as [$pattern, $when, $label]) {
            if (Str::is($pattern, $routeName) && ($when === 'ANY' || $write)) {
                return $label;
            }
        }

        return null;
    }

    private function target(Tenant $tenant, ?int $userId): User
    {
        $q = User::withoutGlobalScopes()->with('role')->where('tenant_id', $tenant->id)->whereNull('deactivated_at')->whereNull('client_id')
            ->whereHas('role', fn ($r) => $r->whereNotIn('code', ['client_user', 'client_admin', 'client_viewer']));

        $target = $userId
            ? (clone $q)->whereKey($userId)->first()
            : (clone $q)->whereHas('role', fn ($r) => $r->where('code', 'company_admin'))->orderBy('id')->first();

        if (! $target) {
            throw ValidationException::withMessages(['user_id' => $userId ? 'Choose an active staff user of this company.' : 'This company has no active Company Admin to sign in as.']);
        }

        return $target;
    }

    /** One row in the platform audit (no company) and one in the company's audit. */
    private function record(string $action, ImpersonationSession $s, ?PlatformUser $platform, array $new): void
    {
        $actor = ['type' => 'platform_user', 'id' => $platform?->id, 'name' => $platform?->name ?? 'platform'];
        foreach ([null, $s->tenant_id] as $tenantId) {
            $this->audit->log($action, [
                'tenant_id' => $tenantId, 'global' => $tenantId === null, 'actor' => $actor, 'item_type' => ImpersonationSession::class, 'item_id' => $s->id,
                'impersonation_session_id' => $s->id, 'impersonated_by_platform_user_id' => $platform?->id, 'new' => $new,
            ]);
        }
    }
}
