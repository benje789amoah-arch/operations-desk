<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a request id and the signed-in user's id to every log entry written
 * during the request, and returns the id in an X-Request-Id header so a
 * reported problem can be matched to its log lines.
 */
class AddRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Context::add('request_id', $requestId);

        if ($userId = $request->user()?->getAuthIdentifier()) {
            Context::add('user_id', $userId);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
