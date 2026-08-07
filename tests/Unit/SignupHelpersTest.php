<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SignupHelpersTest extends TestCase
{
    // ── normalizeRole ────────────────────────────────────────────────────

    public function testCustomerRoleIsUnchanged(): void
    {
        $this->assertSame('customer', normalizeRole('customer'));
    }

    public function testVendorRoleIsUnchanged(): void
    {
        $this->assertSame('vendor', normalizeRole('vendor'));
    }

    public function testUnknownRoleFallsBackToCustomer(): void
    {
        $this->assertSame('customer', normalizeRole('admin'));
    }

    public function testEmptyRoleFallsBackToCustomer(): void
    {
        $this->assertSame('customer', normalizeRole(''));
    }

    // ── hasRequiredSignupFields ──────────────────────────────────────────

    public function testAllFieldsPresentReturnsTrue(): void
    {
        $this->assertTrue(hasRequiredSignupFields('bob123', 'Bob', 'bob@example.com', 'secret123', '5551234567'));
    }

    public function testMissingUsernameReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredSignupFields('', 'Bob', 'bob@example.com', 'secret123', '5551234567'));
    }

    public function testMissingNameReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredSignupFields('bob123', '', 'bob@example.com', 'secret123', '5551234567'));
    }

    public function testMissingEmailReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredSignupFields('bob123', 'Bob', '', 'secret123', '5551234567'));
    }

    public function testMissingPasswordReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredSignupFields('bob123', 'Bob', 'bob@example.com', '', '5551234567'));
    }

    public function testMissingPhoneReturnsFalse(): void
    {
        $this->assertFalse(hasRequiredSignupFields('bob123', 'Bob', 'bob@example.com', 'secret123', ''));
    }

    // ── isValidEmailFormat ───────────────────────────────────────────────

    public function testValidEmailReturnsTrue(): void
    {
        $this->assertTrue(isValidEmailFormat('bob@example.com'));
    }

    public function testMissingAtSymbolReturnsFalse(): void
    {
        $this->assertFalse(isValidEmailFormat('bobexample.com'));
    }

    public function testMissingDomainReturnsFalse(): void
    {
        $this->assertFalse(isValidEmailFormat('bob@'));
    }

    public function testEmptyStringReturnsFalse(): void
    {
        $this->assertFalse(isValidEmailFormat(''));
    }

    // ── isPasswordLongEnough ─────────────────────────────────────────────

    public function testSixCharPasswordIsLongEnough(): void
    {
        $this->assertTrue(isPasswordLongEnough('abcdef'));
    }

    public function testFiveCharPasswordIsTooShort(): void
    {
        $this->assertFalse(isPasswordLongEnough('abcde'));
    }

    public function testEmptyPasswordIsTooShort(): void
    {
        $this->assertFalse(isPasswordLongEnough(''));
    }

    public function testLongPasswordIsLongEnough(): void
    {
        $this->assertTrue(isPasswordLongEnough('a-very-long-password-123'));
    }
}
