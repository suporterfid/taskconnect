<?php

namespace Tests\Unit\Scheduling;

use App\Application\Scheduling\TickBudget;
use Tests\TestCase;

class TickBudgetTest extends TestCase
{
    public function test_can_claim_until_elapsed_reaches_limit(): void
    {
        $now = 1000.0;
        $budget = new TickBudget(1000.0, 2.0, static function () use (&$now): float {
            return $now;
        });

        $this->assertTrue($budget->canClaimMore());
        $now = 1001.5;
        $this->assertTrue($budget->canClaimMore());
        $now = 1002.0;
        $this->assertFalse($budget->canClaimMore());
        $this->assertTrue($budget->exhausted());
    }

    public function test_from_config_uses_target_duration(): void
    {
        config([
            'scheduler.target_duration_seconds' => 30,
            'scheduler.budget_safety_margin_seconds' => 5,
        ]);

        $budget = TickBudget::fromConfig();
        $this->assertSame(30.0, $budget->limitSeconds());
    }
}
