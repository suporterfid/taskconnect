<?php

namespace App\Domain\Pipelines;

enum PipelineNodeStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Halted = 'halted';
}
