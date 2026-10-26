<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RouteTechnicianMobile
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        // Field Technicians stay inside /technician/*
        if ($u && optional($u->role)->name === 'Field Technician'
            && ! $request->is('technician*')
            && ! $request->is('logout')
            && ! $request->is('two-factor*')
            && ! $request->is('profile/devices*')) {
            return redirect()->route('technician.jobs.index');
        }

        return $next($request);
    }
}