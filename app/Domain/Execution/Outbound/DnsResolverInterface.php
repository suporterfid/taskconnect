<?php

namespace App\Domain\Execution\Outbound;

interface DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(string $hostname): array;
}
