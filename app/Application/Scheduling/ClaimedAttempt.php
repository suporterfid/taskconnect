<?php

namespace App\Application\Scheduling;

use App\Infrastructure\Persistence\Eloquent\TaskRun;
use App\Infrastructure\Persistence\Eloquent\TaskRunAttempt;

final readonly class ClaimedAttempt
{
    public function __construct(
        public TaskRun $run,
        public TaskRunAttempt $attempt,
    ) {
    }
}
