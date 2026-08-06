<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnvironmentHelpersTest extends TestCase
{
    // ── isLocalEnvironment ───────────────────────────────────────────────

    public function testLocalhostServerNameIsLocal(): void
    {
        $this->assertTrue(isLocalEnvironment('localhost', '203.0.113.5', 'example.com'));
    }

    public function testEmptyServerNameIsLocal(): void
    {
        // Matches original behavior: '' is in the allowed local list.
        $this->assertTrue(isLocalEnvironment('', '203.0.113.5', 'example.com'));
    }

    public function testLoopbackIpv4ServerNameIsLocal(): void
    {
        $this->assertTrue(isLocalEnvironment('127.0.0.1', '203.0.113.5', 'example.com'));
    }

    public function testLoopbackIpv6ServerNameIsLocal(): void
    {
        $this->assertTrue(isLocalEnvironment('::1', '203.0.113.5', 'example.com'));
    }

    public function testLocalServerAddrIsLocal(): void
    {
        $this->assertTrue(isLocalEnvironment('example.com', '127.0.0.1', 'example.com'));
    }

    public function testLocalhostHttpHostIsLocal(): void
    {
        $this->assertTrue(isLocalEnvironment('example.com', '203.0.113.5', 'localhost'));
    }

    public function testProductionValuesAreNotLocal(): void
    {
        $this->assertFalse(isLocalEnvironment('example.com', '203.0.113.5', 'example.com'));
    }
}
