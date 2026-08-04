<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExportHelpersTest extends TestCase
{
    // ── generateExportFilename ───────────────────────────────────────────

    public function testFilenameContainsFormattedTimestamp(): void
    {
        $timestamp = strtotime('2026-01-05 09:30:15');
        $this->assertSame('reservations_export_2026-01-05_093015.csv', generateExportFilename($timestamp));
    }

    public function testFilenameHasCsvExtension(): void
    {
        $timestamp = strtotime('2026-06-01 00:00:00');
        $this->assertStringEndsWith('.csv', generateExportFilename($timestamp));
    }

    public function testDifferentTimestampsProduceDifferentFilenames(): void
    {
        $first = generateExportFilename(strtotime('2026-01-01 12:00:00'));
        $second = generateExportFilename(strtotime('2026-01-01 12:00:01'));
        $this->assertNotSame($first, $second);
    }

    // ── mapReservationRowToCsvRow ────────────────────────────────────────

    public function testMapsRowFieldsInCorrectOrder(): void
    {
        $row = [
            'reservation_id'   => 1001,
            'user_name'        => 'ME',
            'restaurant_name'  => 'FOOD',
            'reservation_date' => '2026-01-01',
            'reservation_time' => '19:30',
            'guests'           => 4,
            'status'           => 'Confirmed',
        ];

        $this->assertSame(
            [1001, 'ME', 'FOOD', '2026-01-01', '19:30', 4, 'Confirmed'],
            mapReservationRowToCsvRow($row)
        );
    }

    public function testMapsDifferentRowCorrectly(): void
    {
        $row = [
            'reservation_id'   => 1002,
            'user_name'        => 'LEBRON JAMES',
            'restaurant_name'  => 'LEBRON JAMES RESTAURANT',
            'reservation_date' => '2026-08-12',
            'reservation_time' => '18:00',
            'guests'           => 2,
            'status'           => 'Cancelled',
        ];

        $this->assertSame(
            [1002, 'LEBRON JAMES', 'LEBRON JAMES RESTAURANT', '2026-08-12', '18:00', 2, 'Cancelled'],
            mapReservationRowToCsvRow($row)
        );
    }
}
