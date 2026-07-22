<?php

namespace Tests\Support;

use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\HttpClient\PinnedHttpTransport;
use RuntimeException;

final class MockPinnedHttpTransport implements PinnedHttpTransport
{
    /** @var list<PinnedHttpRequest> */
    public array $requests = [];

    /** @var list<PinnedHttpResponse> */
    private array $responses;

    public function __construct(PinnedHttpResponse ...$responses)
    {
        if ($responses === []) {
            throw new RuntimeException('MockPinnedHttpTransport requires at least one response.');
        }

        $this->responses = array_values($responses);
    }

    public function send(PinnedHttpRequest $request): PinnedHttpResponse
    {
        $this->requests[] = $request;

        if (count($this->responses) === 1) {
            return $this->responses[0];
        }

        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('MockPinnedHttpTransport has no remaining responses.');
        }

        return $response;
    }
}
