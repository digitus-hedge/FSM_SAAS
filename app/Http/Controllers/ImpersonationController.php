<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Services\Platform\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Support impersonation. start() is the platform console button (platform guard, root address); it signs the platform user in to the company through
 * the CentralLoginController handoff token. end() is the banner's "End session" on the company address.
 */
class ImpersonationController extends Controller
{
    public function __construct(private Impersonation $impersonation)
    {
    }

    public function start(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason'  => ['required', 'string', 'min:10', 'max:255'],
            'minutes' => ['nullable', 'integer', 'between:1,' . Impersonation::MAX_MINUTES],
            'user_id' => ['nullable', 'integer'],
        ], ['reason.min' => 'Give a reason of 10 to 255 characters.', 'reason.required' => 'Give a reason of 10 to 255 characters.']);

        $platform = Auth::guard('platform')->user();
        $session  = $this->impersonation->start($platform, $tenant, $data['user_id'] ?? null, $data['reason'], $data['minutes'] ?? null, $request->ip());

        return app(CentralLoginController::class)->handoff($session->targetUser, false, ['impersonation_session_id' => $session->id]);
    }

    public function end(Request $request)
    {
        $id = $request->session()->get('impersonation_session_id');
        abort_unless($id, 404);

        if ($session = ImpersonationSession::find($id)) {
            $this->impersonation->end($session, 'ended');
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(rtrim(config('app.url'), '/') . '/admin');
    }
}
