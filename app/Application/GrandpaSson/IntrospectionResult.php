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
        foreach ($this->audiences as $audience) {
            if ($audience === $workspacePublicId) {
                return true;
            }

            // GrandpaSSOn docs often use workspace/<id>; accept that form too.
            if ($audience === 'workspace/'.$workspacePublicId) {
                return true;
            }
        }

        return false;
    }
}
