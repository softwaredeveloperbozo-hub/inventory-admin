<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user && ! $user->is_active) {
            abort(401, 'Conta desativada.');
        }

        return $next($request);
    }
}
