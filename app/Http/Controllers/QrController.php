<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\QuoteToken;
use App\Models\ServiceRequest;

/**
 * One /q/{token} URL serves two things:
 *  - a quote link sent to a client (public, token based), handled as before;
 *  - an asset QR code (staff of the same company only).
 */
class QrController extends Controller
{
    public function resolve(string $token)
    {
        // Quote tokens keep working exactly as before.
        if (QuoteToken::where('token', $token)->exists()) {
            return app(PublicQuoteController::class)->show($token);
        }

        // Anything else is treated as an asset QR hash: sign-in required.
        if (! auth()->check()) {
            return redirect()->guest(route('login'));
        }

        $user = auth()->user();

        $clientCodes = ['client_user', 'client_admin', 'client_viewer'];
        abort_if(in_array($user->role?->code, $clientCodes, true), 403);
        abort_unless($user->canDo('asset.view'), 403);

        // QR pages are a Professional-and-above feature (assets_full).
        if (! $user->tenant?->hasFeature('assets_full')) {
            $layout = in_array($user->role?->code, ['field_technician', 'team_lead'], true) ? 'layouts.technician' : 'layouts.admin';
            return response()->view('errors.plan-feature', ['layout' => $layout], 403);
        }

        // Looked up by hash AND the signed-in user's own company, whatever the host is.
        $asset = Asset::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('qr_hash', $token)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($asset, 404);

        $asset->load([
            'client'   => fn ($q) => $q->withoutGlobalScopes(),
            'site'     => fn ($q) => $q->withoutGlobalScopes(),
            'category' => fn ($q) => $q->withoutGlobalScopes(),
        ]);

        $recent = ServiceRequest::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('primary_asset_id', $asset->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // "Raise a request": team leads use their own screen, other staff the office form.
        // Technicians raise requests from the Field View scan screen (AST-08), not here.
        $raiseUrl = null;
        if (! $asset->is_decommissioned) {
            $code = $user->role?->code;
            if ($code === 'team_lead') {
                $raiseUrl = route('technician.team.desktop.requests.create', ['asset' => $asset->id]);
            } elseif ($code !== 'field_technician' && $user->canDo('sr.create')) {
                $raiseUrl = route('service-requests.create', ['asset' => $asset->id]);
            }
        }

        return view('admin.assets.qr-landing', [
            'asset'     => $asset,
            'recent'    => $recent,
            'canManage' => (bool) $user->canDo('asset.manage'),
            'raiseUrl'  => $raiseUrl,
        ]);
    }
}