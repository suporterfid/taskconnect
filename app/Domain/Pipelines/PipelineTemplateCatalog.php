<?php

namespace App\Domain\Pipelines;

/**
 * Resolves named pipeline templates from config (R10).
 */
final class PipelineTemplateCatalog
{
    public function __construct(
        private readonly PipelineDagValidator $validator = new PipelineDagValidator,
    ) {
    }

    /**
     * @return list<string>
     */
    public function knownNames(): array
    {
        return array_keys(config('pipeline_templates.templates', []));
    }

    public function isKnown(string $name): bool
    {
        return array_key_exists($name, config('pipeline_templates.templates', []));
    }

    /**
     * @return array{
     *     name: string,
     *     description: string|null,
     *     nodes: array<string, array{
     *         task_type: string,
     *         depends_on: list<string>,
     *         on_success: string|null,
     *         on_failure: string|null
     *     }>
     * }
     *
     * @throws InvalidPipelineTemplateException
     */
    public function get(string $name): array
    {
        $templates = config('pipeline_templates.templates', []);
        if (! array_key_exists($name, $templates) || ! is_array($templates[$name])) {
            throw new InvalidPipelineTemplateException(sprintf('Unknown pipeline template "%s".', $name));
        }

        $raw = $templates[$name];
        $nodes = is_array($raw['nodes'] ?? null) ? $raw['nodes'] : [];
        $this->validator->assertValid($nodes);

        $normalized = [];
        foreach ($nodes as $key => $node) {
            /** @var array{task_type: string, depends_on?: list<string>, on_success?: string|null, on_failure?: string|null} $node */
            $dependsOn = [];
            foreach (($node['depends_on'] ?? []) as $dep) {
                if (is_string($dep) && $dep !== '') {
                    $dependsOn[] = $dep;
                }
            }

            $onSuccess = $node['on_success'] ?? null;
            $onFailure = $node['on_failure'] ?? null;

            $normalized[$key] = [
                'task_type' => (string) $node['task_type'],
                'depends_on' => array_values(array_unique($dependsOn)),
                'on_success' => is_string($onSuccess) && $onSuccess !== '' ? $onSuccess : null,
                'on_failure' => is_string($onFailure) && $onFailure !== '' ? $onFailure : null,
            ];
        }

        return [
            'name' => $name,
            'description' => isset($raw['description']) && is_string($raw['description']) ? $raw['description'] : null,
            'nodes' => $normalized,
        ];
    }

    /**
     * @return list<string>
     */
    public function rootNodeKeys(array $nodes): array
    {
        $roots = [];
        foreach ($nodes as $key => $node) {
            $dependsOn = $node['depends_on'] ?? [];
            if ($dependsOn === []) {
                $roots[] = $key;
            }
        }

        return $roots;
    }
}
