<?php

namespace Tests\Unit\Auth;

use App\Domain\Auth\CallbackHmac;
use PHPUnit\Framework\TestCase;

class CallbackHmacTest extends TestCase
{
    public function test_sign_and_verify_round_trip(): void
    {
        $hmac = new CallbackHmac;
        $sig = $hmac->sign('secret', '1700000000', 'nonce-1', '{"ok":true}');

        $this->assertTrue($hmac->verify(
            secret: 'secret',
            timestamp: '1700000000',
            nonce: 'nonce-1',
            rawBody: '{"ok":true}',
            signature: $sig,
            maxSkewSeconds: 10_000_000,
            nowUnix: 1_700_000_000,
        ));
    }

    public function test_tampered_body_fails_verification(): void
    {
        $hmac = new CallbackHmac;
        $sig = $hmac->sign('secret', '1700000000', 'nonce-1', '{"ok":true}');

        $this->assertFalse($hmac->verify(
            secret: 'secret',
            timestamp: '1700000000',
            nonce: 'nonce-1',
            rawBody: '{"ok":false}',
            signature: $sig,
            maxSkewSeconds: 10_000_000,
            nowUnix: 1_700_000_000,
        ));
    }

    public function test_rejects_skewed_timestamp(): void
    {
        $hmac = new CallbackHmac;
        $sig = $hmac->sign('secret', '1000', 'n', 'body');

        $this->assertFalse($hmac->verify(
            secret: 'secret',
            timestamp: '1000',
            nonce: 'n',
            rawBody: 'body',
            signature: $sig,
            maxSkewSeconds: 300,
            nowUnix: 10_000,
        ));
    }
}
