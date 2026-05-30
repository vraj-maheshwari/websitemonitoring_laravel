<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = $request->route('site');

        if ($site && $request->user() && (int) $site->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        return $next($request);
    }
}
