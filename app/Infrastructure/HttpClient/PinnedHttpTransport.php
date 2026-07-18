<?php

namespace App\Infrastructure\HttpClient;

interface PinnedHttpTransport
{
    public function send(PinnedHttpRequest $request): PinnedHttpResponse;
}
