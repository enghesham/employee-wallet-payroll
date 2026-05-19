<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postJsonWithProviderSignature(
        string $uri,
        array $payload,
        string $provider,
        ?string $secret = null,
        ?int $timestamp = null,
    ): TestResponse {
        $timestamp ??= now()->timestamp;
        $secret ??= (string) config("integrations.webhook_secrets.{$provider}");
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $this
            ->withHeaders([
                'X-Provider-Timestamp' => (string) $timestamp,
                'X-Provider-Signature' => $signature,
            ])
            ->postJson($uri, $payload);
    }
}
