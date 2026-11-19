<?php

namespace App\Http\Middleware;

use App\Services\Permissions\PermissionLevel;
use Closure;
use Illuminate\Http\Request;

/**
 * Route gate on a permission key, by what the request does (blueprint section 4, signed matrix):
 *
 *     ->middleware('level:client.manage')            one key
 *     ->middleware('level:invoice.manage,invoice.approve')   any of these keys
 *
 *   - A request that only reads (GET or HEAD) needs canView: full, grantable or view_only.
 *   - A request that changes something (POST, PUT, PATCH, DELETE) needs canChange: full or grantable.
 *   - A GET that shows a create or edit form (a path with create, edit, build, upload or import in it) also needs canChange,
 *     so a view-only holder is never handed a form they cannot submit.
 * Anything else is 403. view_own is never enough: it belongs to client and field users, not to an office screen.
 * Sign-in comes first: put this after the auth middleware.
 */
class EnsurePermissionLevel
{
    private const FORM_SEGMENTS = ['create', 'edit', 'build', 'upload', 'import'];

    public function handle(Request $request, Closure $next, string ...$keys)
    {
        $user = $request->user();

        $changes = ! $request->isMethodSafe() || $this->isFormPage($request);
        $allowed = $changes
            ? PermissionLevel::canChange($user, ...$keys)
            : PermissionLevel::canView($user, ...$keys);

        abort_unless($allowed, 403);

        return $next($request);
    }

    private function isFormPage(Request $request): bool
    {
        $uri = $request->route()?->uri() ?? $request->path();

        return count(array_intersect(explode('/', $uri), self::FORM_SEGMENTS)) > 0;
    }
}
