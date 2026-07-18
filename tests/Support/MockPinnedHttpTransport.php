<?php

namespace Tests\Support;

use App\Infrastructure\HttpClient\PinnedHttpRequest;
use App\Infrastructure\HttpClient\PinnedHttpResponse;
use App\Infrastructure\HttpClient\PinnedHttpTransport;

final class MockPinnedHttpTransport implements PinnedHttpTransport
{
    /** @var list<PinnedHttpRequest> */
    public array $requests = [];

    public function __construct(
        private PinnedHttpResponse $response,
    ) {
    }

    public function send(PinnedHttpRequest $request): PinnedHttpResponse
    {
        $this->requests[] = $request;

        return $this->response;
    }
}
