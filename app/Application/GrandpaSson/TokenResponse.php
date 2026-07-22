<?php

namespace App\Application\GrandpaSson;

final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public int $expiresAtUnix,
        public string $tokenType = 'Bearer',
        public ?string $scope = null,
    ) {
    }

    public function isExpired(int $nowUnix, int $refreshSkewSeconds = 60): bool
    {
        return $nowUnix >= ($this->expiresAtUnix - $refreshSkewSeconds);
    }
}
