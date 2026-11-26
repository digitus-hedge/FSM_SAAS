<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\InvoiceToken;
use App\Models\QuoteToken;
use App\Models\Tenant;
use App\Support\TenantUrl;
use Illuminate\Http\RedirectResponse;

/**
 * Path mode: invoice, quote and QR links on the root address (fsm.aiywah.com/i/{token}, /q/{token}) — what the queue sent before
 * the switch — go to the same page on the company's address. Unknown token: 404. The page itself still does every check.
 */
class LegacyLinkController extends Controller
{
    public function invoice(string $token): RedirectResponse
    {
        return $this->to(InvoiceToken::where('token', $token)->value('tenant_id'), '/i/' . $token);
    }

    public function invoicePdf(string $token): RedirectResponse
    {
        return $this->to(InvoiceToken::where('token', $token)->value('tenant_id'), '/i/' . $token . '/pdf');
    }

    public function quote(string $token): RedirectResponse
    {
        $tenantId = QuoteToken::where('token', $token)->value('tenant_id')
            ?? Asset::withoutGlobalScopes()->where('qr_hash', $token)->value('tenant_id');   // an asset QR code

        return $this->to($tenantId, '/q/' . $token);
    }

    public function quotePdf(string $token): RedirectResponse
    {
        return $this->to(QuoteToken::where('token', $token)->value('tenant_id'), '/q/' . $token . '/pdf');
    }

    private function to(?int $tenantId, string $path): RedirectResponse
    {
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        abort_unless($tenant, 404);

        return redirect()->away(TenantUrl::for($tenant, $path), 301);
    }
}
