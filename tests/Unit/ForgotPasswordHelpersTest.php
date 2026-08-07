<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ForgotPasswordHelpersTest extends TestCase
{
    // ── determineProtocol ────────────────────────────────────────────────

    public function testHttpsOnReturnsHttps(): void
    {
        $this->assertSame('https', determineProtocol('on'));
    }

    public function testNullHttpsReturnsHttp(): void
    {
        $this->assertSame('http', determineProtocol(null));
    }

    public function testHttpsOffReturnsHttp(): void
    {
        // Matches original: only the exact string 'on' counts as https.
        $this->assertSame('http', determineProtocol('off'));
    }

    // ── buildResetLink ───────────────────────────────────────────────────

    public function testBuildsLocalResetLinkWithBaseDir(): void
    {
        $link = buildResetLink('http', 'localhost', true, 'abc123');
        $this->assertSame('http://localhost/reservehub/html/reset-password.html?token=abc123', $link);
    }

    public function testBuildsProductionResetLinkWithoutBaseDir(): void
    {
        $link = buildResetLink('https', 'reservehub.com', false, 'abc123');
        $this->assertSame('https://reservehub.com/html/reset-password.html?token=abc123', $link);
    }

    // ── calculateResetExpiry ─────────────────────────────────────────────

    public function testExpiryIsOneHourAfterGivenTimestamp(): void
    {
        $base = strtotime('2026-01-01 12:00:00');
        $this->assertSame('2026-01-01 13:00:00', calculateResetExpiry($base));
    }

    public function testExpiryRollsOverMidnight(): void
    {
        $base = strtotime('2026-01-01 23:30:00');
        $this->assertSame('2026-01-02 00:30:00', calculateResetExpiry($base));
    }

    // ── buildResetEmailBody ──────────────────────────────────────────────

    public function testEmailBodyContainsNameAndLink(): void
    {
        $body = buildResetEmailBody('Alice', 'https://reservehub.com/reset?token=xyz');

        $this->assertStringContainsString('Hi Alice,', $body);
        $this->assertStringContainsString('https://reservehub.com/reset?token=xyz', $body);
        $this->assertStringContainsString('expire in 1 hour', $body);
    }
}
