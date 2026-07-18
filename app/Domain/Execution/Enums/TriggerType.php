<?php

namespace App\Domain\Execution\Enums;

enum TriggerType: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Test = 'test';
}
