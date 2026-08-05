<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReserveHelpersTest extends TestCase
{
    public function testAllFieldsPresentReturnsTrue(): void
    {
        $this->assertTrue(hasValidReservationFields('u001', 'r001', 't001', '2026-01-01', '19:00', 4));
    }

    public function testMissingUserIdReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields(null, 'r001', 't001', '2026-01-01', '19:00', 4));
    }

    public function testMissingRestaurantIdReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('u001', null, 't001', '2026-01-01', '19:00', 4));
    }

    public function testMissingTableIdReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('u001', 'r001', null, '2026-01-01', '19:00', 4));
    }

    public function testMissingDateReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('u001', 'r001', 't001', null, '19:00', 4));
    }

    public function testMissingReservationTimeReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('u001', 'r001', 't001', '2026-01-01', null, 4));
    }

    public function testMissingGuestCountReturnsFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('u001', 'r001', 't001', '2026-01-01', '19:00', null));
    }

    public function testZeroGuestCountReturnsFalse(): void
    {
        // Matches original truthy check: 0 guests is treated as "missing", same as null.
        $this->assertFalse(hasValidReservationFields('u001', 'r001', 't001', '2026-01-01', '19:00', 0));
    }

    public function testStringZeroGuestCountReturnsFalse(): void
    {
        // '0' is falsy in PHP, same reasoning as the int 0 case.
        $this->assertFalse(hasValidReservationFields('u001', 'r001', 't001', '2026-01-01', '19:00', '0'));
    }

    public function testEmptyStringFieldsReturnFalse(): void
    {
        $this->assertFalse(hasValidReservationFields('', 'r001', 't001', '2026-01-01', '19:00', 4));
    }
}
