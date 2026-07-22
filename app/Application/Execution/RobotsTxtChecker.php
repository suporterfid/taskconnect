<?php

namespace App\Application\Execution;

use App\Domain\Execution\Outbound\EgressProfile;
use App\Domain\Execution\Outbound\OutboundPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use App\Domain\Execution\Outbound\RobotsTxtParser;
use App\Domain\Execution\Outbound\ValidatedEndpoint;
use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Optional robots.txt respect for public-crawl (R7 §6.5). Fail-open on fetch errors.
 */
final class RobotsTxtChecker
{
    public function __construct(
        private readonly OutboundPolicy $outboundPolicy,
        private readonly PinnedHttpTransport $transport,
        private readonly RobotsTxtParser $parser,
    ) {
    }

    /**
     * @param  list<string>  $additionalAllowHosts
     *
     * @throws OutboundPolicyViolation
     */
    public function assertAllowed(
        string $url,
        EgressProfile $profile,
        array $additionalAllowHosts = [],
    ): void {
        if ($profile !== EgressProfile::PublicCrawl) {
            return;
        }

        if (! filter_var(config('outbound.robots_txt.enabled', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '/robots.txt') {
            return;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $authority = $parts['host'].($port !== null ? ':'.$port : '');
        $robotsUrl = sprintf('%s://%s/robots.txt', $parts['scheme'], $authority);
        $userAgent = (string) config('outbound.user_agent', 'OpenHttpScheduler/1.1');

        $body = $this->cachedRobotsBody($robotsUrl, $profile, $additionalAllowHosts);
        if ($body === null) {
            return;
        }

        if (! $this->parser->isPathAllowed($body, $userAgent, $path)) {
            throw new OutboundPolicyViolation(
                'robots_disallow',
                'Destination path is disallowed by robots.txt for this user-agent.',
            );
        }
    }

    /**
     * @param  list<string>  $additionalAllowHosts
     */
    private function cachedRobotsBody(
        string $robotsUrl,
        EgressProfile $profile,
        array $additionalAllowHosts,
    ): ?string {
        $ttl = max(1, (int) config('outbound.robots_txt.cache_seconds', 3600));
        $cacheKey = 'egress_robots:'.sha1($robotsUrl);

        /** @var string|false|null $cached */
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }
        if ($cached === false) {
            return null;
        }

        $body = $this->fetchRobots($robotsUrl, $profile, $additionalAllowHosts);
        Cache::put($cacheKey, $body === null ? false : $body, $ttl);

        return $body;
    }

    /**
     * @param  list<string>  $additionalAllowHosts
     */
    private function fetchRobots(
        string $robotsUrl,
        EgressProfile $profile,
        array $additionalAllowHosts,
    ): ?string {
        try {
            $validated = $this->outboundPolicy->validateUrl($robotsUrl, $additionalAllowHosts, $profile);
            $endpoint = new ValidatedEndpoint(
                url: $validated->url,
                scheme: $validated->scheme,
                host: $validated->host,
                port: $validated->port,
                pinnedIp: $validated->resolvedIps[0],
                resolvedIps: $validated->resolvedIps,
                hostAllowlisted: $validated->hostAllowlisted,
            );

            $response = $this->transport->send(new PinnedHttpRequest(
                method: 'GET',
                endpoint: $endpoint,
                headers: [],
                body: null,
                verifyTls: true,
                followRedirects: true,
                connectTimeout: min(5, (int) config('outbound.connect_timeout', 5)),
                totalTimeout: min(5, (int) config('outbound.total_timeout', 15)),
                responseBodyLimit: 8192,
                additionalAllowHosts: $additionalAllowHosts,
                egressProfile: $profile,
            ));

            if ($response->statusCode === 404) {
                return '';
            }

            if ($response->statusCode < 200 || $response->statusCode >= 300 || $response->transportError !== null) {
                return null;
            }

            return $response->bodyTruncated;
        } catch (Throwable) {
            return null;
        }
    }
}
