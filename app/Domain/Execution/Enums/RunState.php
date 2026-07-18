<?php

namespace App\Domain\Execution\Enums;

enum RunState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case RetryWait = 'retry_wait';
    case Succeeded = 'succeeded';
    case Dead = 'dead';
    case Cancelled = 'cancelled';
    case Blocked = 'blocked';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Dead, self::Cancelled, self::Blocked], true);
    }
}
