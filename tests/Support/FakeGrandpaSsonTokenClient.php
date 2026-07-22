<?php

namespace Tests\Support;

use App\Application\GrandpaSson\TokenClientInterface;
use App\Application\GrandpaSson\TokenResponse;

final class FakeGrandpaSsonTokenClient implements TokenClientInterface
{
    public function __construct(
        private readonly string $accessToken = 'gss-test-token',
        private readonly int $expiresIn = 3600,
    ) {
    }

    public function clientCredentialsToken(string $scope): TokenResponse
    {
        return new TokenResponse(
            accessToken: $this->accessToken,
            expiresAtUnix: time() + $this->expiresIn,
            scope: $scope,
        );
    }
}
