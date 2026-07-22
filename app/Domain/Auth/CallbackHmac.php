<?php

namespace App\Domain\Auth;

/**
 * HMAC-SHA256 over timestamp + "." + nonce + "." + raw body (R8 / S7).
 */
final class CallbackHmac
{
    public function sign(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$rawBody, $secret);
    }

    public function verify(
        string $secret,
        string $timestamp,
        string $nonce,
        string $rawBody,
        string $signature,
        int $maxSkewSeconds = 300,
        ?int $nowUnix = null,
    ): bool {
        $now = $nowUnix ?? time();
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $ts = (int) $timestamp;
        if (abs($now - $ts) > $maxSkewSeconds) {
            return false;
        }

        $expected = $this->sign($secret, $timestamp, $nonce, $rawBody);

        return hash_equals($expected, $signature);
    }
}
