<?php

namespace App\Domain\Shared;

use DateTimeImmutable;

interface Clock
{
    public function nowUtc(): DateTimeImmutable;
}
