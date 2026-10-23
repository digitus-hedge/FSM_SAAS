<?php
namespace App\Http\Middleware;

use App\Services\Auth\DeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TouchDevice
{
    public function __construct(private DeviceService $devices) {}

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user() ?? Auth::guard('platform')->user();
        if ($user) {
            $this->devices->touch($user, $request);
        }
        return $next($request);
    }
}