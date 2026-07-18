<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayDnsResolver;

class OutboundPolicyTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $configOverrides
     * @param  array<string, list<string>>  $dnsMap
     */
    private function policy(array $configOverrides = [], array $dnsMap = []): OutboundPolicy
    {
        $config = OutboundPolicyConfig::fromArray(array_merge([
            'allowed_ports' => [80, 443],
            'allow_http' => true,
            'platform_allow_hosts' => [],
            'testing_allow_hosts' => ['receiver'],
            'metadata_hosts' => ['metadata.google.internal', 'metadata.goog', 'metadata'],
            'metadata_ips' => ['169.254.169.254', '100.100.100.200', 'fd00:ec2::254'],
        ], $configOverrides));

        return OutboundPolicy::fromConfig($config, new ArrayDnsResolver($dnsMap));
    }

    public function test_allows_only_http_and_https(): void
    {
        $policy = $this->policy();

        $this->expectExceptionObject(new OutboundPolicyViolation('invalid_scheme', 'Only http and https URLs are allowed.'));
        $policy->validateUrl('ftp://example.com/file');
    }

    public function test_rejects_embedded_credentials(): void
    {
        $policy = $this->policy();

        $this->expectExceptionObject(new OutboundPolicyViolation('embedded_credentials', 'URLs with embedded credentials are not allowed.'));
        $policy->validateUrl('https://user:pass@example.com/hook');
    }

    public function test_rejects_localhost_hostnames(): void
    {
        $policy = $this->policy();

        foreach (['http://localhost/path', 'https://api.localhost/path'] as $url) {
            try {
                $policy->validateUrl($url);
                $this->fail(sprintf('Expected localhost rejection for %s', $url));
            } catch (OutboundPolicyViolation $exception) {
                $this->assertSame('localhost_hostname', $exception->reasonCode);
            }
        }
    }

    public function test_rejects_literal_private_and_loopback_ips(): void
    {
        $policy = $this->policy();

        foreach (['http://127.0.0.1/', 'http://192.168.0.5/', 'http://[::1]/'] as $url) {
            try {
                $policy->validateUrl($url);
                $this->fail(sprintf('Expected blocked IP rejection for %s', $url));
            } catch (OutboundPolicyViolation $exception) {
                $this->assertSame('blocked_ip', $exception->reasonCode);
            }
        }
    }

    public function test_rejects_metadata_endpoints(): void
    {
        $policy = $this->policy();

        $this->expectExceptionObject(new OutboundPolicyViolation('blocked_ip', 'The destination IP address is not allowed.'));
        $policy->validateUrl('http://169.254.169.254/latest/meta-data');

        try {
            $policy->validateUrl('http://metadata.google.internal/computeMetadata/v1/');
            $this->fail('Expected metadata hostname rejection.');
        } catch (OutboundPolicyViolation $exception) {
            $this->assertSame('metadata_hostname', $exception->reasonCode);
        }
    }

    public function test_rejects_disallowed_ports(): void
    {
        $policy = $this->policy();

        $this->expectExceptionObject(new OutboundPolicyViolation('port_not_allowed', 'Port 8080 is not allowed by platform policy.'));
        $policy->validateUrl('http://example.com:8080/hook');
    }

    public function test_rejects_dns_resolving_to_private_ips(): void
    {
        $policy = $this->policy(dnsMap: [
            'internal.example' => ['10.0.0.5'],
        ]);

        $this->expectExceptionObject(new OutboundPolicyViolation('blocked_ip', 'The destination resolves to a blocked IP address.'));
        $policy->validateUrl('https://internal.example/hook');
    }

    public function test_testing_allowlist_permits_private_resolution_but_not_bad_ports(): void
    {
        $policy = $this->policy(dnsMap: [
            'receiver' => ['10.0.0.5'],
        ]);

        $validated = $policy->validateUrl('http://receiver/hook');
        $this->assertTrue($validated->hostAllowlisted);
        $this->assertSame('10.0.0.5', $validated->pinnedIp);

        $this->expectExceptionObject(new OutboundPolicyViolation('port_not_allowed', 'Port 8080 is not allowed by platform policy.'));
        $policy->validateUrl('http://receiver:8080/hook');
    }

    public function test_platform_allowlist_does_not_bypass_port_policy(): void
    {
        $policy = $this->policy(
            configOverrides: ['platform_allow_hosts' => ['trusted.example']],
            dnsMap: ['trusted.example' => ['8.8.8.8']],
        );

        $validated = $policy->validateUrl('https://trusted.example/hook');
        $this->assertTrue($validated->hostAllowlisted);

        $this->expectExceptionObject(new OutboundPolicyViolation('port_not_allowed', 'Port 8080 is not allowed by platform policy.'));
        $policy->validateUrl('http://trusted.example:8080/hook');
    }

    public function test_rejects_plain_http_when_disabled(): void
    {
        $policy = $this->policy(['allow_http' => false]);

        $this->expectExceptionObject(new OutboundPolicyViolation('http_not_allowed', 'Plain HTTP is not allowed by platform policy.'));
        $policy->validateUrl('http://example.com/hook');
    }
}
