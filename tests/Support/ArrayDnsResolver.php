<?php

namespace Tests\Support;

use App\Domain\Execution\Outbound\DnsResolverInterface;

final class ArrayDnsResolver implements DnsResolverInterface
{
    /**
     * @param  array<string, list<string>>  $map
     */
    public function __construct(
        private readonly array $map,
    ) {
    }

    /**
     * @return list<string>
     */
    public function resolve(string $hostname): array
    {
        return $this->map[strtolower($hostname)] ?? [];
    }
}
