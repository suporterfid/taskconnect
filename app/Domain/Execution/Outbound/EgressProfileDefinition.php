<?php

namespace App\Domain\Execution\Outbound;

/**
 * Per-profile allow/deny + limit stubs (R7).
 */
final readonly class EgressProfileDefinition
{
    /**
     * @param  list<string>  $allowHosts  Host allowlist for profiles that require one (api).
     */
    public function __construct(
        public EgressProfile $profile,
        public array $allowHosts = [],
        public ?int $redirectLimit = null,
        public ?int $responseBodyLimit = null,
        public ?int $connectTimeout = null,
        public ?int $totalTimeout = null,
    ) {
    }
}
