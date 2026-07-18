<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyConfig;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Domain\Execution\Outbound\ValidatedEndpoint;
use App\Infrastructure\HttpClient\GuzzlePinnedHttpTransport;
use App\Infrastructure\HttpClient\PinnedHttpRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArrayDnsResolver;

class GuzzlePinnedHttpTransportTest extends TestCase
{
    /**
     * @param  list<Response>  $responses
     */
    private function transport(array $responses, array $dnsMap = []): GuzzlePinnedHttpTransport
    {
        $mock = new MockHandler($responses);
        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
            'allow_redirects' => false,
        ]);

        $policy = OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allowed_ports' => [80, 443],
                'allow_http' => true,
                'redirect_limit' => 3,
                'user_agent' => 'OpenHttpScheduler/1.1',
                'response_body_limit' => 8,
            ]),
            new ArrayDnsResolver($dnsMap),
        );

        return new GuzzlePinnedHttpTransport($policy, $client);
    }

    private function endpoint(string $url, string $host, string $ip, int $port = 80): ValidatedEndpoint
    {
        return new ValidatedEndpoint(
            url: $url,
            scheme: 'http',
            host: $host,
            port: $port,
            pinnedIp: $ip,
            resolvedIps: [$ip],
            hostAllowlisted: false,
        );
    }

    public function test_revalidates_redirect_destination(): void
    {
        $transport = $this->transport([
            new Response(302, ['Location' => 'http://127.0.0.1/evil']),
        ]);

        $this->expectExceptionObject(new OutboundPolicyViolation('blocked_ip', 'The destination IP address is not allowed.'));

        $transport->send(new PinnedHttpRequest(
            method: 'GET',
            endpoint: $this->endpoint('http://example.com/start', 'example.com', '93.184.216.34'),
        ));
    }

    public function test_limits_redirects(): void
    {
        $transport = $this->transport([
            new Response(302, ['Location' => 'http://example.com/a']),
            new Response(302, ['Location' => 'http://example.com/b']),
            new Response(302, ['Location' => 'http://example.com/c']),
            new Response(302, ['Location' => 'http://example.com/d']),
        ], dnsMap: [
            'example.com' => ['93.184.216.34'],
        ]);

        $this->expectExceptionObject(new OutboundPolicyViolation('redirect_limit_exceeded', 'Redirect limit exceeded.'));

        $transport->send(new PinnedHttpRequest(
            method: 'GET',
            endpoint: $this->endpoint('http://example.com/start', 'example.com', '93.184.216.34'),
        ));
    }

    public function test_captures_truncated_body_and_sha256(): void
    {
        $transport = $this->transport([
            new Response(200, [], '123456789'),
        ]);

        $response = $transport->send(new PinnedHttpRequest(
            method: 'GET',
            endpoint: $this->endpoint('http://example.com/start', 'example.com', '93.184.216.34'),
        ));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('12345678', $response->bodyTruncated);
        $this->assertTrue($response->bodyTruncatedFlag);
        $this->assertSame(hash('sha256', '12345678'), $response->bodySha256);
    }

    public function test_sets_user_agent_header(): void
    {
        $capturedUserAgent = null;
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
            'allow_redirects' => false,
            'on_headers' => function ($response) use (&$capturedUserAgent): void {
                // no-op
            },
        ]);

        $policy = OutboundPolicy::fromConfig(
            OutboundPolicyConfig::fromArray([
                'allow_http' => true,
                'user_agent' => 'OpenHttpScheduler/1.1',
            ]),
            new ArrayDnsResolver(['example.com' => ['93.184.216.34']]),
        );

        $transport = new GuzzlePinnedHttpTransport($policy, $client);

        $transport->send(new PinnedHttpRequest(
            method: 'GET',
            endpoint: $this->endpoint('http://example.com/start', 'example.com', '93.184.216.34'),
            headers: ['X-Test' => '1'],
        ));

        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);
        $this->assertSame('OpenHttpScheduler/1.1', $lastRequest->getHeaderLine('User-Agent'));
        $this->assertSame('1', $lastRequest->getHeaderLine('X-Test'));
    }
}
