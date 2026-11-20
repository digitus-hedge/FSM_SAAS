<?php

namespace App\Http\Middleware;

use App\Models\ImpersonationSession;
use App\Services\Platform\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Runs on every company request while a support session is signed in (session key impersonation_session_id, set by the handoff).
 * It ends the session when its time is up, refuses the blocked actions (Services/Platform/Impersonation), counts the changes
 * and gives the pages the data for the banner.
 */
class EnforceImpersonation
{
    public function __construct(private Impersonation $impersonation)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $id = $request->hasSession() ? $request->session()->get('impersonation_session_id') : null;
        if (! $id) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();
        $session = ImpersonationSession::with('platformUser')->find($id);

        if (! $user || ! $session || (int) $session->target_user_id !== (int) $user->id || (int) $session->tenant_id !== (int) $user->tenant_id) {
            return $this->signOut($request, 'The support session is not valid.');
        }
        if ($session->ended_at || $session->expires_at->isPast()) {
            $this->impersonation->end($session, 'expired');

            return $this->signOut($request, 'The support session has ended.');
        }

        if ($what = $this->impersonation->blockedReason($request->route()?->getName(), $request->method())) {
            abort(403, "This is a support session. It cannot {$what}.");
        }
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            ImpersonationSession::whereKey($session->id)->increment('actions_count');
        }

        view()->share('impersonation', [
            'by' => $session->platformUser?->name, 'reason' => $session->reason, 'minutes_left' => $session->minutesLeft(),
            'ends_at' => $session->expires_at->copy()->setTimezone($user->tenant?->timezone ?: config('app.timezone'))->format('H:i'),
        ]);

        return $next($request);
    }

    private function signOut(Request $request, string $message)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['identifier' => $message]);
    }
}
