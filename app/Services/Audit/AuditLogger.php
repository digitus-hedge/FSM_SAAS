<?php
namespace App\Services\Audit;

use App\Models\{ActivityLog, PlatformUser, Tenant, User};
use Illuminate\Support\Facades\{Auth, DB};

/**
 * CLAUDE.md §4 Audit:
 *   activity_logs is append-only with a hash chain.
 *   Never update or delete rows.
 *   Log who, when, address, device, and old and new values.
 *   NEVER write a password, code, token or secret.
 */
class AuditLogger
{
    /** Keys that are stripped from values before persisting. */
    private const SECRET_KEYS = [
        'password', 'remember_token', 'two_factor_secret',
        'token', 'code', 'token_hash', 'code_hash', 'current', 'new', 'repeat',
    ];

    public function log(string $action, array $context = []): ActivityLog
    {
        $request = request();
        $actor   = $this->resolveActor();

        return DB::transaction(function () use ($action, $context, $actor, $request) {
            // Fetch the chain's current tail row-locked, so concurrent writes serialise.
            $last = ActivityLog::query()
                ->where('tenant_id', $context['tenant_id'] ?? $this->currentTenantId())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $createdAt = now();

            $row = [
                'tenant_id'   => $context['tenant_id'] ?? $this->currentTenantId(),
                'actor_type'  => $actor['type'],
                'actor_id'    => $actor['id'],
                'actor_name'  => $actor['name'],
                'action'      => $action,
                'item_type'   => $context['item_type'] ?? null,
                'item_id'     => $context['item_id']   ?? null,
                'old_values'  => $this->sanitize($context['old'] ?? null),
                'new_values'  => $this->sanitize($context['new'] ?? null),
                'ip_address'  => $request?->ip(),
                'user_agent'  => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'prev_hash'   => $last?->hash,
                'created_at'  => $createdAt,
            ];

            $row['hash'] = $this->computeHash($row);

            return ActivityLog::create($row);
        });
    }

    public function verifyChain(?int $tenantId = null, int $limit = 1000): array
    {
        $rows = ActivityLog::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $expectedPrev = null;
        foreach ($rows as $r) {
            if ($r->prev_hash !== $expectedPrev) {
                return ['ok' => false, 'broken_at' => $r->id, 'reason' => 'prev_hash_mismatch'];
            }
            if ($r->hash !== $this->computeHash($r->toArray())) {
                return ['ok' => false, 'broken_at' => $r->id, 'reason' => 'hash_mismatch'];
            }
            $expectedPrev = $r->hash;
        }
        return ['ok' => true, 'rows' => $rows->count()];
    }

    // ─────────────────── helpers ───────────────────

    private function resolveActor(): array
    {
        if (Auth::guard('platform')->check()) {
            $u = Auth::guard('platform')->user();
            return ['type' => 'platform_user', 'id' => $u->id, 'name' => $u->name];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return ['type' => 'user', 'id' => $u->id, 'name' => $u->name];
        }
        return ['type' => 'system', 'id' => null, 'name' => 'system'];
    }

    private function currentTenantId(): ?int
    {
        $u = Auth::guard('web')->user();
        return $u?->tenant_id ?? Tenant::current()?->id;
    }

    private function sanitize(mixed $values): ?array
    {
        if ($values === null) return null;
        if (! is_array($values)) return ['value' => (string) $values];

        $clean = [];
        foreach ($values as $k => $v) {
            if (in_array($k, self::SECRET_KEYS, true)) continue;
            $clean[$k] = is_array($v) ? $this->sanitize($v) : $v;
        }
        return $clean;
    }

    private function computeHash(array $row): string
    {
        $payload = implode('|', [
            $row['prev_hash'] ?? '',
            $row['tenant_id'] ?? '',
            $row['actor_type'] ?? '',
            $row['actor_id'] ?? '',
            $row['action'] ?? '',
            $row['item_type'] ?? '',
            $row['item_id'] ?? '',
            json_encode($row['old_values'] ?? null),
            json_encode($row['new_values'] ?? null),
            is_string($row['created_at'] ?? null) ? $row['created_at'] : ($row['created_at']?->toIso8601String() ?? ''),
        ]);
        return hash('sha256', $payload);
    }
}