<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderSignatureIsValid
{
    public function handle(Request $request, Closure $next, string $provider): Response
    {
        $secret = config("integrations.webhook_secrets.{$provider}");

        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'message' => "Webhook secret for [{$provider}] is not configured.",
            ], Response::HTTP_UNAUTHORIZED);
        }

        $timestamp = $request->header('X-Provider-Timestamp');
        $signature = $request->header('X-Provider-Signature');

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature) || $signature === '') {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $tolerance = (int) config('integrations.webhook_signature_tolerance_seconds', 300);

        if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
            return response()->json([
                'message' => 'Webhook signature timestamp is outside the allowed tolerance.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $expectedSignature = 'sha256='.hash_hmac(
            'sha256',
            $timestamp.'.'.$request->getContent(),
            $secret,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
