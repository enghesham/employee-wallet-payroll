<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderTokenIsValid
{
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $expectedToken = config("integrations.provider_tokens.{$provider}");
        $providedToken = $request->bearerToken();

        if (! is_string($expectedToken) || $expectedToken === '' || ! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Invalid provider token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
