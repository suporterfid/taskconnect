<?php

namespace App\Application\GrandpaSson;

final readonly class IntrospectionResult
{
    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $audiences
     */
    public function __construct(
        public bool $active,
        public array $scopes = [],
        public array $audiences = [],
        public ?string $clientId = null,
        public ?string $subject = null,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function audienceIncludes(string $workspacePublicId): bool
    {
        return in_array($workspacePublicId, $this->audiences, true);
    }
}
