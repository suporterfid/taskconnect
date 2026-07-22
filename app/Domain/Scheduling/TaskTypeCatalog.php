<?php

namespace App\Domain\Scheduling;

/**
 * Resolves task-type governance defaults from config (R4).
 */
final class TaskTypeCatalog
{
    /**
     * @return array{priority: int, weight: int, timeout_ms: int, max_attempts: int, egress_profile: string, concurrency_cap: int}
     */
    public function definition(?string $taskType): array
    {
        $types = config('task_types.types', []);
        $key = $taskType !== null && $taskType !== '' && isset($types[$taskType])
            ? $taskType
            : 'default';

        $defaults = $types['default'] ?? [
            'priority' => 0,
            'weight' => 1,
            'timeout_ms' => 15000,
            'max_attempts' => 5,
            'egress_profile' => 'internal',
            'concurrency_cap' => 4,
        ];

        $resolved = array_merge($defaults, $types[$key] ?? []);

        return [
            'priority' => max(0, (int) ($resolved['priority'] ?? 0)),
            'weight' => max(1, (int) ($resolved['weight'] ?? 1)),
            'timeout_ms' => max(1, (int) ($resolved['timeout_ms'] ?? 15000)),
            'max_attempts' => max(1, (int) ($resolved['max_attempts'] ?? 5)),
            'egress_profile' => (string) ($resolved['egress_profile'] ?? 'internal'),
            'concurrency_cap' => max(0, (int) ($resolved['concurrency_cap'] ?? 4)),
        ];
    }

    public function globalCeiling(): int
    {
        return max(0, (int) config('task_types.global_inflight_ceiling', 4));
    }

    /**
     * @return list<string>
     */
    public function knownTypes(): array
    {
        return array_keys(config('task_types.types', []));
    }

    public function isKnown(?string $taskType): bool
    {
        if ($taskType === null || $taskType === '') {
            return false;
        }

        return array_key_exists($taskType, config('task_types.types', []));
    }

    /**
     * Merge request overrides with catalog defaults for persistence on tasks.
     *
     * @param  array{priority?: int, weight?: int, timeout_ms?: int, egress_profile?: string}  $overrides
     * @return array{task_type: ?string, priority: int, weight: int, timeout_ms: int, egress_profile: string, max_attempts: int}
     */
    public function resolveTaskAttributes(?string $taskType, array $overrides = []): array
    {
        $normalized = ($taskType !== null && $taskType !== '') ? $taskType : null;
        $def = $this->definition($normalized);

        return [
            'task_type' => $normalized,
            'priority' => array_key_exists('priority', $overrides) ? max(0, (int) $overrides['priority']) : $def['priority'],
            'weight' => array_key_exists('weight', $overrides) ? max(1, (int) $overrides['weight']) : $def['weight'],
            'timeout_ms' => array_key_exists('timeout_ms', $overrides) ? max(1, (int) $overrides['timeout_ms']) : $def['timeout_ms'],
            'egress_profile' => array_key_exists('egress_profile', $overrides)
                ? (string) $overrides['egress_profile']
                : $def['egress_profile'],
            'max_attempts' => $def['max_attempts'],
        ];
    }
}
