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

        if ($u && in_array($u->role?->code, $fieldCodes, true)
            && ! $request->is('technician*')
            && ! $request->is('logout')
            && ! $request->is('field/logout')
            && ! $request->is('two-factor*')
            && ! $request->is('profile/devices*')) {
            return redirect()->route('technician.jobs.index');
        }

        return $next($request);
    }
}