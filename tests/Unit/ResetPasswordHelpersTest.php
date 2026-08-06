<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ResetPasswordHelpersTest extends TestCase
{
    public function testNullDataIsInvalid(): void
    {
        $this->assertFalse(hasValidResetRequest(null));
    }

    public function testMissingTokenIsInvalid(): void
    {
        $data = new \stdClass();
        $data->newPassword = 'newSecret123';
        $this->assertFalse(hasValidResetRequest($data));
    }

    public function testMissingNewPasswordIsInvalid(): void
    {
        $data = new \stdClass();
        $data->token = 'abc123';
        $this->assertFalse(hasValidResetRequest($data));
    }

    public function testEmptyTokenIsInvalid(): void
    {
        $data = new \stdClass();
        $data->token = '';
        $data->newPassword = 'newSecret123';
        $this->assertFalse(hasValidResetRequest($data));
    }

    public function testEmptyNewPasswordIsInvalid(): void
    {
        $data = new \stdClass();
        $data->token = 'abc123';
        $data->newPassword = '';
        $this->assertFalse(hasValidResetRequest($data));
    }

    public function testBothPresentReturnsTrue(): void
    {
        $data = new \stdClass();
        $data->token = 'abc123';
        $data->newPassword = 'newSecret123';
        $this->assertTrue(hasValidResetRequest($data));
    }
}
