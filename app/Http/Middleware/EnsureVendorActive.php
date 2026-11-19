<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A vendor technician belongs to a vendor company. While the vendor is suspended its technicians cannot use the product:
 * the open session ends on the next request (Multi-Trade-Vendors-Depot spec D8, VENDOR_SUSPENDED). Reactivating the
 * vendor lets them sign in again.
 */
class EnsureVendorActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->vendor_id) {
            $vendor = Vendor::withoutGlobalScopes()->find($user->vendor_id);
            if (! $vendor || $vendor->trashed() || $vendor->status !== 'active') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login')->withErrors(['email' => 'This vendor is suspended.']);
            }
        }

        return $next($request);
    }
}
