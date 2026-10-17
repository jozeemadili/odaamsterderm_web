<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isCustomer()) {
            abort(403, 'This area is for marketplace customer accounts.');
        }

        if ($user->status === 'suspended') {
            abort(403, 'This account has been suspended. Contact Odaamsterderm Hub support.');
        }

        return $next($request);
    }
}
