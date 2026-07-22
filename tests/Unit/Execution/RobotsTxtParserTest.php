<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\Outbound\RobotsTxtParser;
use PHPUnit\Framework\TestCase;

class RobotsTxtParserTest extends TestCase
{
    public function test_disallow_blocks_matching_prefix(): void
    {
        $parser = new RobotsTxtParser;
        $body = "User-agent: *\nDisallow: /secret\n";

        $this->assertFalse($parser->isPathAllowed($body, 'OpenHttpScheduler/1.1', '/secret/x'));
        $this->assertTrue($parser->isPathAllowed($body, 'OpenHttpScheduler/1.1', '/public'));
    }

    public function test_allow_can_override_shorter_disallow(): void
    {
        $parser = new RobotsTxtParser;
        $body = "User-agent: *\nDisallow: /secret\nAllow: /secret/ok\n";

        $this->assertTrue($parser->isPathAllowed($body, 'bot', '/secret/ok'));
        $this->assertFalse($parser->isPathAllowed($body, 'bot', '/secret/no'));
    }

    public function test_empty_disallow_allows_all(): void
    {
        $parser = new RobotsTxtParser;
        $body = "User-agent: *\nDisallow:\n";

        $this->assertTrue($parser->isPathAllowed($body, 'bot', '/anything'));
    }
}
