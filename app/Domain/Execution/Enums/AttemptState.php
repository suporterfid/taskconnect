<?php

namespace App\Domain\Execution\Enums;

enum AttemptState: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case FailedRetryable = 'failed_retryable';
    case FailedTerminal = 'failed_terminal';
    case TimedOut = 'timed_out';
    case Interrupted = 'interrupted';
    case Blocked = 'blocked';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::FailedTerminal,
            self::TimedOut,
            self::Blocked,
        ], true);
    }
}
