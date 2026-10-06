<?php

namespace Modules\Bulk\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('bulk.api_key', '');
        $provided = (string) $request->header('X-API-Key', '');

        if ($configured === '' || ! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
