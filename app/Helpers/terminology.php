<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('term')) {
    function term(string $key, string $form = 'singular'): string
    {
        $tenantId = Tenant::current()?->id;

        if (! $tenantId) {
            return str_replace('_', ' ', $key);
        }

        $cacheKey = "term:{$tenantId}:{$key}:{$form}";

        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $key, $form) {
            $row = DB::table('tenant_terminology')
                ->where('tenant_id', $tenantId)
                ->where('term_key', $key)
                ->first();

            if (! $row) {
                // Fall back to the model's DEFAULTS array
                $defaults = \App\Models\TenantTerminology::DEFAULTS[$key] ?? null;
                if ($defaults) {
                    return $form === 'plural' ? $defaults[1] : $defaults[0];
                }
                return str_replace('_', ' ', $key);
            }

            return $row->{$form} ?? str_replace('_', ' ', $key);
        });
    }
}

if (! function_exists('term_cap')) {
    function term_cap(string $key, string $form = 'singular'): string
    {
        return ucfirst(term($key, $form));
    }
}

if (! function_exists('term_title')) {
    function term_title(string $key, string $form = 'plural'): string
    {
        return ucwords(term($key, $form));
    }
}