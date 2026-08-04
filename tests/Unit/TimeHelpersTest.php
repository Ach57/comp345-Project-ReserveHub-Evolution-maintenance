<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TimeHelpersTest extends TestCase
{
    // ── timeToMinutes ────────────────────────────────────────────────────

    public function testTimeToMinutesWithHoursAndMinutes(): void
    {
        $this->assertSame(0, timeToMinutes('00:00'));
        $this->assertSame(60, timeToMinutes('01:00'));
        $this->assertSame(90, timeToMinutes('01:30'));
        $this->assertSame(1439, timeToMinutes('23:59'));
    }

    public function testTimeToMinutesWithSeconds(): void
    {
        // Seconds portion should be ignored — MySQL TIME columns often
        // come back as "HH:MM:SS".
        $this->assertSame(90, timeToMinutes('01:30:45'));
        $this->assertSame(0, timeToMinutes('00:00:00'));
    }

    public function testTimeToMinutesWithHourOnly(): void
    {
        // No colon at all — minutes part defaults to 0.
        $this->assertSame(300, timeToMinutes('05'));
    }

    // ── isWithinOperatingHours: normal same-day span ───────────────────────

    public function testWithinNormalHoursReturnsTrue(): void
    {
        $this->assertTrue(isWithinOperatingHours('12:00', '11:00', '22:00'));
    }

    public function testAtOpeningTimeIsInclusive(): void
    {
        $this->assertTrue(isWithinOperatingHours('11:00', '11:00', '22:00'));
    }

    public function testAtClosingTimeIsExclusive(): void
    {
        $this->assertFalse(isWithinOperatingHours('22:00', '11:00', '22:00'));
    }

    public function testBeforeOpeningReturnsFalse(): void
    {
        $this->assertFalse(isWithinOperatingHours('10:59', '11:00', '22:00'));
    }

    public function testAfterClosingReturnsFalse(): void
    {
        $this->assertFalse(isWithinOperatingHours('22:01', '11:00', '22:00'));
    }

    // ── isWithinOperatingHours: overnight span ──────────────────────────────

    public function testOvernightSpanLateEveningReturnsTrue(): void
    {
        // Open 18:00, close 03:00 next day
        $this->assertTrue(isWithinOperatingHours('23:30', '18:00', '03:00'));
    }

    public function testOvernightSpanEarlyMorningReturnsTrue(): void
    {
        $this->assertTrue(isWithinOperatingHours('02:00', '18:00', '03:00'));
    }

    public function testOvernightSpanDaytimeReturnsFalse(): void
    {
        // Midday falls outside an 18:00–03:00 overnight window.
        $this->assertFalse(isWithinOperatingHours('14:00', '18:00', '03:00'));
    }

    public function testOvernightSpanAtCloseIsExclusive(): void
    {
        $this->assertFalse(isWithinOperatingHours('03:00', '18:00', '03:00'));
    }

    public function testOvernightSpanAtOpenIsInclusive(): void
    {
        $this->assertTrue(isWithinOperatingHours('18:00', '18:00', '03:00'));
    }

    // ── isWithinOperatingHours: 24-hour restaurant ──────────────────────────

    public function test24HourRestaurantAlwaysReturnsTrue(): void
    {
        $this->assertTrue(isWithinOperatingHours('00:00', '00:00', '23:59'));
        $this->assertTrue(isWithinOperatingHours('12:00', '00:00', '23:59'));
        $this->assertTrue(isWithinOperatingHours('23:58', '00:00', '23:59'));
    }

    // ── isWithinOperatingHours: with seconds precision from DB ─────────────

    public function testHandlesTimeWithSecondsFromDatabase(): void
    {
        $this->assertTrue(isWithinOperatingHours('12:30:00', '11:00:00', '22:00:00'));
        $this->assertFalse(isWithinOperatingHours('23:00:00', '11:00:00', '22:00:00'));
    }
}
