<?php

namespace App\Domain\Execution\Outbound;

final class UrlValidator
{
    public function __construct(
        private readonly OutboundPolicyConfig $config,
        private readonly IpClassifier $ipClassifier,
        private readonly DnsResolverInterface $dnsResolver,
    ) {
    }

    public function validate(string $url): ValidatedEndpoint
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new OutboundPolicyViolation('invalid_url', 'The URL is malformed.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new OutboundPolicyViolation('invalid_scheme', 'Only http and https URLs are allowed.');
        }

        if ($scheme === 'http' && ! $this->config->allowHttp) {
            throw new OutboundPolicyViolation('http_not_allowed', 'Plain HTTP is not allowed by platform policy.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new OutboundPolicyViolation('embedded_credentials', 'URLs with embedded credentials are not allowed.');
        }

        $host = strtolower($parts['host']);

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if ($this->isLocalhostHostname($host)) {
            throw new OutboundPolicyViolation('localhost_hostname', 'Localhost hostnames are not allowed.');
        }

        if ($this->isMetadataHostname($host)) {
            throw new OutboundPolicyViolation('metadata_hostname', 'Cloud metadata hostnames are not allowed.');
        }

        $port = $this->resolvePort($scheme, $parts['port'] ?? null);

        if (! in_array($port, $this->config->allowedPorts, true)) {
            throw new OutboundPolicyViolation(
                'port_not_allowed',
                sprintf('Port %d is not allowed by platform policy.', $port),
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->ipClassifier->isBlocked($host)) {
                throw new OutboundPolicyViolation('blocked_ip', 'The destination IP address is not allowed.');
            }

            return new ValidatedEndpoint(
                url: $url,
                scheme: $scheme,
                host: $host,
                port: $port,
                pinnedIp: $host,
                resolvedIps: [$host],
                hostAllowlisted: false,
            );
        }

        $hostAllowlisted = $this->isHostAllowlisted($host);

        $resolvedIps = $this->dnsResolver->resolve($host);

        if ($resolvedIps === []) {
            throw new OutboundPolicyViolation('dns_resolution_failed', 'Unable to resolve destination hostname.');
        }

        if (! $hostAllowlisted && $this->ipClassifier->containsBlocked($resolvedIps)) {
            throw new OutboundPolicyViolation('blocked_ip', 'The destination resolves to a blocked IP address.');
        }

        return new ValidatedEndpoint(
            url: $url,
            scheme: $scheme,
            host: $host,
            port: $port,
            pinnedIp: $resolvedIps[0],
            resolvedIps: $resolvedIps,
            hostAllowlisted: $hostAllowlisted,
        );
    }

    private function isLocalhostHostname(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        return str_ends_with($host, '.localhost');
    }

    private function isMetadataHostname(string $host): bool
    {
        foreach ($this->config->metadataHosts as $metadataHost) {
            if ($host === $metadataHost || str_ends_with($host, '.'.$metadataHost)) {
                return true;
            }
        }

        return false;
    }

    private function isHostAllowlisted(string $host): bool
    {
        $allowlist = array_merge(
            $this->config->platformAllowHosts,
            $this->config->testingAllowHosts,
        );

        foreach ($allowlist as $allowedHost) {
            if ($host === $allowedHost) {
                return true;
            }
        }

        return false;
    }

    private function resolvePort(string $scheme, mixed $explicitPort): int
    {
        if ($explicitPort !== null && $explicitPort !== '') {
            return (int) $explicitPort;
        }

        return $scheme === 'https' ? 443 : 80;
    }
}
