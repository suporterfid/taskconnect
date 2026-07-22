<?php

namespace App\Application\Tasks;

use App\Domain\Shared\Clock;
use App\Infrastructure\Persistence\Eloquent\Environment;
use App\Infrastructure\Persistence\Eloquent\Task;
use App\Infrastructure\Persistence\Eloquent\Tenant;
use DateTimeImmutable;

/**
 * Collapses bursty enqueues that share a coalesce_key within a workspace window (R11).
 */
final class CoalesceService
{
    public function __construct(
        private readonly Clock $clock,
    ) {
    }

    public function windowSeconds(): int
    {
        return max(1, (int) config('scheduler.coalesce_window_seconds', 60));
    }

    public function findWithinWindow(
        Tenant $tenant,
        Environment $environment,
        string $coalesceKey,
        ?DateTimeImmutable $now = null,
    ): ?Task {
        $key = trim($coalesceKey);
        if ($key === '') {
            return null;
        }

        $now ??= $this->clock->nowUtc();
        $cutoff = $now->modify(sprintf('-%d seconds', $this->windowSeconds()));

        /** @var Task|null $task */
        $task = Task::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment_id', $environment->id)
            ->where('coalesce_key', mb_substr($key, 0, 255))
            ->whereNull('archived_at')
            ->where('created_at', '>=', $cutoff->format('Y-m-d H:i:s'))
            ->orderBy('id')
            ->first();

        return $task;
    }

    /**
     * Default coalesce key for pipeline-materialized publish.build nodes.
     */
    public function defaultPipelinePublishKey(string $templateName): string
    {
        return 'pipeline:'.$templateName.':publish.build';
    }
}
