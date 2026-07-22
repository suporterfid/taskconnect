<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Metrics\PlatformMetricsCollector;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlatformMetricsController extends Controller
{
    public function __construct(
        private readonly PlatformMetricsCollector $collector,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isPlatformAdmin()) {
            abort(403);
        }

        $body = $this->collector->renderPrometheus();

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
