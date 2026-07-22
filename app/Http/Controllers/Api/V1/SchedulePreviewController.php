<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Scheduling\InvalidScheduleConfigException;
use App\Domain\Scheduling\ScheduleCalculator;
use App\Domain\Scheduling\ScheduleConfig;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchedulePreviewController extends Controller
{
    public function __construct(private readonly ScheduleCalculator $calculator) {}

    public function __invoke(Request $request, string $tenantId, string $environmentId): JsonResponse
    {
        $tenant = $this->tenant($request);
        $this->authorize('viewAny', [Task::class, $tenant]);

        $validated = $request->validate([
            'schedule' => ['required', 'array'],
            'schedule.kind' => ['required', 'string'],
            'schedule.timezone' => ['required', 'string'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $count = (int) ($validated['count'] ?? 3);
        /** @var array<string, mixed> $schedule */
        $schedule = $request->input('schedule');

        try {
            $config = ScheduleConfig::fromArray($schedule);
        } catch (InvalidScheduleConfigException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['schedule' => [$e->getMessage()]],
            ], 422);
        }

        $occurrences = $this->calculator->previewNext($config, $count);

        return response()->json([
            'data' => [
                'occurrences' => array_map(
                    static fn ($dt) => $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                    $occurrences,
                ),
            ],
        ]);
    }

    private function tenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
