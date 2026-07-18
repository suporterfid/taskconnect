<?php

namespace App\Domain\Execution\Outbound;

use RuntimeException;

final class OutboundPolicyViolation extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
