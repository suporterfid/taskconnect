<?php

namespace App\Domain\Pipelines;

/**
 * Validates pipeline template DAGs (depends_on + on_success/on_failure edges).
 */
final class PipelineDagValidator
{
    /**
     * @param  array<string, array{
     *     task_type?: string,
     *     depends_on?: list<string>|mixed,
     *     on_success?: string|null,
     *     on_failure?: string|null
     * }>  $nodes
     *
     * @throws InvalidPipelineTemplateException
     */
    public function assertValid(array $nodes): void
    {
        if ($nodes === []) {
            throw new InvalidPipelineTemplateException('Pipeline template must define at least one node.');
        }

        foreach ($nodes as $key => $node) {
            if (! is_string($key) || $key === '') {
                throw new InvalidPipelineTemplateException('Pipeline node keys must be non-empty strings.');
            }

            if (! is_array($node)) {
                throw new InvalidPipelineTemplateException(sprintf('Node "%s" must be an object.', $key));
            }

            $taskType = $node['task_type'] ?? null;
            if (! is_string($taskType) || $taskType === '') {
                throw new InvalidPipelineTemplateException(sprintf('Node "%s" requires a task_type.', $key));
            }

            $dependsOn = $node['depends_on'] ?? [];
            if (! is_array($dependsOn)) {
                throw new InvalidPipelineTemplateException(sprintf('Node "%s" depends_on must be a list.', $key));
            }

            foreach ($dependsOn as $dep) {
                if (! is_string($dep) || $dep === '') {
                    throw new InvalidPipelineTemplateException(sprintf('Node "%s" has an invalid depends_on entry.', $key));
                }
                if ($dep === $key) {
                    throw new InvalidPipelineTemplateException(sprintf('Node "%s" cannot depend on itself.', $key));
                }
                if (! array_key_exists($dep, $nodes)) {
                    throw new InvalidPipelineTemplateException(sprintf(
                        'Node "%s" depends_on unknown node "%s".',
                        $key,
                        $dep,
                    ));
                }
            }

            foreach (['on_success', 'on_failure'] as $edge) {
                $target = $node[$edge] ?? null;
                if ($target === null || $target === '') {
                    continue;
                }
                if (! is_string($target)) {
                    throw new InvalidPipelineTemplateException(sprintf('Node "%s" %s must be a string or null.', $key, $edge));
                }
                if ($target === $key) {
                    throw new InvalidPipelineTemplateException(sprintf('Node "%s" %s cannot point to itself.', $key, $edge));
                }
                if (! array_key_exists($target, $nodes)) {
                    throw new InvalidPipelineTemplateException(sprintf(
                        'Node "%s" %s points to unknown node "%s".',
                        $key,
                        $edge,
                        $target,
                    ));
                }
            }
        }

        $adjacency = $this->buildAdjacency($nodes);
        if ($this->hasCycle($adjacency)) {
            throw new InvalidPipelineTemplateException('Pipeline template contains a cycle.');
        }
    }

    /**
     * @param  array<string, array{
     *     depends_on?: list<string>|mixed,
     *     on_success?: string|null,
     *     on_failure?: string|null
     * }>  $nodes
     * @return array<string, list<string>>
     */
    private function buildAdjacency(array $nodes): array
    {
        $adjacency = [];
        foreach (array_keys($nodes) as $key) {
            $adjacency[$key] = [];
        }

        foreach ($nodes as $key => $node) {
            $dependsOn = is_array($node['depends_on'] ?? null) ? $node['depends_on'] : [];
            foreach ($dependsOn as $dep) {
                if (is_string($dep) && $dep !== '') {
                    $adjacency[$dep][] = $key;
                }
            }

            foreach (['on_success', 'on_failure'] as $edge) {
                $target = $node[$edge] ?? null;
                if (is_string($target) && $target !== '') {
                    $adjacency[$key][] = $target;
                }
            }
        }

        foreach ($adjacency as $from => $tos) {
            $adjacency[$from] = array_values(array_unique($tos));
        }

        return $adjacency;
    }

    /**
     * @param  array<string, list<string>>  $adjacency
     */
    private function hasCycle(array $adjacency): bool
    {
        $WHITE = 0;
        $GRAY = 1;
        $BLACK = 2;
        $color = [];
        foreach (array_keys($adjacency) as $node) {
            $color[$node] = $WHITE;
        }

        $visit = function (string $node) use (&$visit, &$color, $adjacency, $WHITE, $GRAY, $BLACK): bool {
            $color[$node] = $GRAY;
            foreach ($adjacency[$node] as $next) {
                if ($color[$next] === $GRAY) {
                    return true;
                }
                if ($color[$next] === $WHITE && $visit($next)) {
                    return true;
                }
            }
            $color[$node] = $BLACK;

            return false;
        };

        foreach (array_keys($adjacency) as $node) {
            if ($color[$node] === $WHITE && $visit($node)) {
                return true;
            }
        }

        return false;
    }
}
