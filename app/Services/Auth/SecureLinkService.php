<?php
namespace App\Services\Auth;

use App\Models\{SecureLink, User};
use Illuminate\Support\Str;

class SecureLinkService
{
    /**
     * Returns the plaintext token (shown once). The hash is what's stored.
     * D3/D18: invitation lasts 7 days, reset lasts 60 minutes, client sign-in lasts 24 hours.
     */
    public function create(User $user, string $purpose, int $ttlMinutes): string
    {
        // A new link of this purpose cancels earlier ones (6.8).
        SecureLink::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(48);

        SecureLink::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'purpose'    => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $token;
    }

    /**
     * Look up a live link by its plaintext token. Returns null if not found,
     * expired, or already used.
     */
    public function find(string $token, string $purpose): ?SecureLink
    {
        $link = SecureLink::where('purpose', $purpose)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $link?->isLive() ? $link : null;
    }

    /**
     * A link for a record and a client contact instead of a user (feedback, blueprint 7.7b). The earlier unused links of the same purpose for
     * the same record are cancelled, so a reopened and completed-again request has one live link. Returns the plaintext token (shown once).
     */
    public function createFor(int $tenantId, string $purpose, string $entityType, int $entityId, ?int $contactId, int $ttlMinutes): string
    {
        SecureLink::where('purpose', $purpose)->where('entity_type', $entityType)->where('entity_id', $entityId)->whereNull('used_at')->delete();

        $token = Str::random(48);

        SecureLink::create([
            'tenant_id'         => $tenantId,
            'user_id'           => null,
            'client_contact_id' => $contactId,
            'purpose'           => $purpose,
            'entity_type'       => $entityType,
            'entity_id'         => $entityId,
            'token_hash'        => hash('sha256', $token),
            'expires_at'        => now()->addMinutes($ttlMinutes),
        ]);

        return $token;
    }

    /** The link for a token whatever its state (live, used or expired), or null when the token is unknown. Use isLive() / used_at to tell the states apart. */
    public function lookup(string $token, string $purpose): ?SecureLink
    {
        return SecureLink::withoutGlobalScopes()->where('purpose', $purpose)->where('token_hash', hash('sha256', $token))->first();
    }

    public function consume(SecureLink $link, string $ip): void
    {
        $link->update(['used_at' => now(), 'request_ip' => $ip]);
    }
}