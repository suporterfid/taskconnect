<?php

namespace Tests\Unit\GrandpaSson;

use App\Application\GrandpaSson\IntrospectionResult;
use PHPUnit\Framework\TestCase;

class IntrospectionResultTest extends TestCase
{
    public function test_audience_matches_raw_workspace_public_id(): void
    {
        $result = new IntrospectionResult(true, audiences: ['env_abc']);
        $this->assertTrue($result->audienceIncludes('env_abc'));
        $this->assertFalse($result->audienceIncludes('env_other'));
    }

    public function test_audience_matches_workspace_prefixed_form(): void
    {
        $result = new IntrospectionResult(true, audiences: ['workspace/env_abc']);
        $this->assertTrue($result->audienceIncludes('env_abc'));
        $this->assertFalse($result->audienceIncludes('env_other'));
    }
}
