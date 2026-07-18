<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\Outbound\HeaderPolicy;
use App\Domain\Execution\Outbound\OutboundPolicyViolation;
use PHPUnit\Framework\TestCase;

class HeaderPolicyTest extends TestCase
{
    private HeaderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new HeaderPolicy();
    }

    public function test_rejects_forbidden_headers(): void
    {
        $forbidden = ['Host', 'Content-Length', 'Transfer-Encoding', 'Connection', 'Proxy-Authorization'];

        foreach ($forbidden as $header) {
            try {
                $this->policy->validate([$header => 'value']);
                $this->fail(sprintf('Expected rejection for header %s', $header));
            } catch (OutboundPolicyViolation $exception) {
                $this->assertSame('forbidden_header', $exception->reasonCode);
            }
        }
    }

    public function test_sanitize_adds_user_agent(): void
    {
        $headers = $this->policy->sanitize(['X-Custom' => 'ok'], 'OpenHttpScheduler/1.1');

        $this->assertSame('ok', $headers['X-Custom']);
        $this->assertSame('OpenHttpScheduler/1.1', $headers['User-Agent']);
    }
}
