<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks a route when the company's plan lacks a feature.
 * Use: EnsurePlanFeature::class . ':assets_full'
 */
class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $tenant = $request->user()?->tenant;

        if (! $tenant || ! $tenant->hasFeature($feature)) {
            $code = $request->user()?->role?->code;
            $layout = in_array($code, ['field_technician', 'team_lead'], true)
                ? 'layouts.technician'
                : 'layouts.admin';

            return response()->view('errors.plan-feature', ['layout' => $layout], 403);
        }

        return $next($request);
    }
}