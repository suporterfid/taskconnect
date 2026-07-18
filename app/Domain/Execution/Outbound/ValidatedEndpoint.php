<?php

namespace App\Domain\Execution\Outbound;

final readonly class ValidatedEndpoint
{
    /**
     * @param  list<string>  $resolvedIps
     */
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public string $pinnedIp,
        public array $resolvedIps,
        public bool $hostAllowlisted,
    ) {
    }
}
