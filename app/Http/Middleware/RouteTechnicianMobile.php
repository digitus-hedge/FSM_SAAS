<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RouteTechnicianMobile
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        $fieldCodes = ['field_technician', 'team_lead'];

        // The path inside the company (path mode: without "{slug}/"), so these checks work in both tenancy modes.
        $path = \App\Support\Tenancy::companyPath($request);

        if ($u && in_array($u->role?->code, $fieldCodes, true)
            && ! \Illuminate\Support\Str::is(['technician*', 'logout', 'field/logout', 'two-factor*', 'profile/devices*'], $path)
            // Let ForcePasswordChange finish its job first — without this, a field
            // user who must change their password gets bounced forever between
            // /change-password (sent here) and /technician/jobs (sent back there).
            && ! $request->routeIs('password.change', 'password.change.submit')) {
            return redirect()->route('technician.jobs.index');
        }

        return $next($request);
    }
}