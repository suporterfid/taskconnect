<?php

namespace Tests\Unit\Execution;

use App\Domain\Execution\Outbound\IpClassifier;
use PHPUnit\Framework\TestCase;

class IpClassifierTest extends TestCase
{
    private IpClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new IpClassifier([
            '169.254.169.254',
            '100.100.100.200',
            'fd00:ec2::254',
        ]);
    }

    public function test_blocks_loopback_ipv4(): void
    {
        $this->assertTrue($this->classifier->isBlocked('127.0.0.1'));
    }

    public function test_blocks_private_ipv4_ranges(): void
    {
        $this->assertTrue($this->classifier->isBlocked('10.0.0.1'));
        $this->assertTrue($this->classifier->isBlocked('172.16.0.5'));
        $this->assertTrue($this->classifier->isBlocked('192.168.1.10'));
    }

    public function test_blocks_link_local_and_metadata_ipv4(): void
    {
        $this->assertTrue($this->classifier->isBlocked('169.254.169.254'));
        $this->assertTrue($this->classifier->isBlocked('169.254.0.10'));
        $this->assertTrue($this->classifier->isBlocked('100.100.100.200'));
    }

    public function test_blocks_ipv6_loopback_private_and_multicast(): void
    {
        $this->assertTrue($this->classifier->isBlocked('::1'));
        $this->assertTrue($this->classifier->isBlocked('::'));
        $this->assertTrue($this->classifier->isBlocked('fe80::1'));
        $this->assertTrue($this->classifier->isBlocked('fc00::1'));
        $this->assertTrue($this->classifier->isBlocked('ff02::1'));
        $this->assertTrue($this->classifier->isBlocked('fd00:ec2::254'));
    }

    public function test_allows_public_ipv4(): void
    {
        $this->assertFalse($this->classifier->isBlocked('8.8.8.8'));
        $this->assertFalse($this->classifier->isBlocked('1.1.1.1'));
    }
}
