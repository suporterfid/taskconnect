<?php

namespace App\Domain\Execution\Outbound;

final readonly class OutboundPolicyConfig
{
    /**
     * @param  list<int>  $allowedPorts
     * @param  list<string>  $platformAllowHosts
     * @param  list<string>  $testingAllowHosts
     * @param  list<string>  $metadataHosts
     * @param  list<string>  $metadataIps
     */
    public function __construct(
        public array $allowedPorts = [80, 443],
        public bool $allowHttp = false,
        public array $platformAllowHosts = [],
        public array $testingAllowHosts = [],
        public array $metadataHosts = [],
        public array $metadataIps = [],
        public int $redirectLimit = 3,
        public string $userAgent = 'OpenHttpScheduler/1.1',
        public int $connectTimeout = 5,
        public int $totalTimeout = 15,
        public int $responseBodyLimit = 65536,
    ) {
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            allowedPorts: $config['allowed_ports'] ?? [80, 443],
            allowHttp: (bool) ($config['allow_http'] ?? false),
            platformAllowHosts: array_map('strtolower', $config['platform_allow_hosts'] ?? []),
            testingAllowHosts: array_map('strtolower', $config['testing_allow_hosts'] ?? []),
            metadataHosts: array_map('strtolower', $config['metadata_hosts'] ?? []),
            metadataIps: $config['metadata_ips'] ?? [],
            redirectLimit: (int) ($config['redirect_limit'] ?? 3),
            userAgent: (string) ($config['user_agent'] ?? 'OpenHttpScheduler/1.1'),
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            totalTimeout: (int) ($config['total_timeout'] ?? 15),
            responseBodyLimit: (int) ($config['response_body_limit'] ?? 65536),
        );
    }
}
