<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProfileHelpersTest extends TestCase
{
    public function testNullDataIsInvalid(): void
    {
        $this->assertFalse(hasValidEmail(null));
    }

    public function testMissingEmailPropertyIsInvalid(): void
    {
        $data = new \stdClass();
        // no ->email set at all
        $this->assertFalse(hasValidEmail($data));
    }

    public function testEmptyStringEmailIsInvalid(): void
    {
        $data = new \stdClass();
        $data->email = '';
        $this->assertFalse(hasValidEmail($data));
    }

    public function testNullEmailIsInvalid(): void
    {
        $data = new \stdClass();
        $data->email = null;
        $this->assertFalse(hasValidEmail($data));
    }

    public function testValidEmailReturnsTrue(): void
    {
        $data = new \stdClass();
        $data->email = 'user@example.com';
        $this->assertTrue(hasValidEmail($data));
    }

    public function testWhitespaceOnlyEmailIsConsideredValidByCurrentLogic(): void
    {
        // Documents existing behavior: empty() only catches "", null, 0, false, etc.
        // A string of just spaces is NOT considered empty by PHP, so this currently
        // passes validation even though it's not a real email. Flagging this as a
        // known gap rather than silently "fixing" behavior via the test.
        $data = new \stdClass();
        $data->email = '   ';
        $this->assertTrue(hasValidEmail($data));
    }
}
