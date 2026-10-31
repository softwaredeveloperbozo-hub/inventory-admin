<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('api');

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Sem permissão para esta ação.');
        }

        return $next($request);
    }
}
