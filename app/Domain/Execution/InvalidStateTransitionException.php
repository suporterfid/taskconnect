<?php

namespace App\Domain\Execution;

use RuntimeException;

final class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $entity,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            'Invalid %s state transition from "%s" to "%s".',
            $entity,
            $from,
            $to,
        ));
    }
}
