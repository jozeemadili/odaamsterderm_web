<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserIsMerchant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isMerchantUser()) {
            abort(403, 'This area is restricted to merchant accounts.');
        }

        if (! $user->merchant || ! $user->merchant->isActive()) {
            $user->currentAccessToken()?->delete();

            abort(403, 'This shop account has been deactivated. Contact DukaFlow support.');
        }

        return $next($request);
    }
}
