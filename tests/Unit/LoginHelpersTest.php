<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoginHelpersTest extends TestCase
{
    // ── trimCredential ───────────────────────────────────────────────────

    public function testTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('bob', trimCredential('  bob  '));
    }

    public function testNullBecomesEmptyString(): void
    {
        $this->assertSame('', trimCredential(null));
    }

    public function testAlreadyTrimmedValueIsUnchanged(): void
    {
        $this->assertSame('bob', trimCredential('bob'));
    }

    public function testWhitespaceOnlyBecomesEmptyString(): void
    {
        $this->assertSame('', trimCredential('   '));
    }

    // ── hasValidCredentials ──────────────────────────────────────────────

    public function testValidIdentifierAndPasswordReturnsTrue(): void
    {
        $this->assertTrue(hasValidCredentials('bob', 'secret123'));
    }

    public function testEmptyIdentifierReturnsFalse(): void
    {
        $this->assertFalse(hasValidCredentials('', 'secret123'));
    }

    public function testEmptyPasswordReturnsFalse(): void
    {
        $this->assertFalse(hasValidCredentials('bob', ''));
    }

    public function testBothEmptyReturnsFalse(): void
    {
        $this->assertFalse(hasValidCredentials('', ''));
    }
}
