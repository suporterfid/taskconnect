<?php

namespace App\Domain\Execution\Enums;

enum TaskDefinitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function isExecutable(): bool
    {
        return $this === self::Active;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Active, self::Archived], true),
            self::Active => in_array($target, [self::Paused, self::Completed, self::Archived], true),
            self::Paused => in_array($target, [self::Active, self::Archived], true),
            self::Completed => in_array($target, [self::Archived], true),
            self::Archived => false,
        };
    }
}
