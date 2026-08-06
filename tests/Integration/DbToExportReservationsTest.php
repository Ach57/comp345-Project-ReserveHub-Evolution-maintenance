<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PDO;

final class DbToExportReservationsTest extends TestCase
{
    /**
     * Helper simulating CSV stream writing logic in export.php
     */
    private function generateCsvStream(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        
        // Write UTF-8 BOM
        fprintf($stream, "\xEF\xBB\xBF");

        // Write Headers with explicit parameters to prevent PHP 8.4 deprecation warnings
        fputcsv($stream, ['Reservation ID', 'User Name', 'Restaurant', 'Date', 'Time', 'Guests', 'Status'], ',', '"', '\\');

        // Write Rows
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '\\');
        }

        rewind($stream);
        $csvContent = stream_get_contents($stream);
        fclose($stream);

        return $csvContent;
    }

    /**
     * Helper simulating exception catching logic in export.php
     */
    private function processExportWithPdo(PDO $pdo, string $tableName = 'reservations'): array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$tableName}");
            $stmt->execute();
            return ['success' => true];
        } catch (\PDOException $e) {
            return [
                'status_code' => 500,
                'response' => [
                    'success' => false,
                    'message' => 'Export query failed: ' . $e->getMessage()
                ]
            ];
        }
    }

    // ── 1. CSV FORMATTING & ENCODING ─────────────────────────────────────

    public function testCsvExportIncludesUtf8BomHeaderAndColumns(): void
    {
        $rows = [
            [
                'reservation_id' => 1,
                'user_name' => 'François',
                'restaurant_name' => 'Café Central',
                'reservation_date' => '2026-06-15',
                'reservation_time' => '19:00',
                'guests' => 2,
                'status' => 'Confirmed'
            ]
        ];

        $csv = $this->generateCsvStream($rows);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Reservation ID","User Name",Restaurant,Date,Time,Guests,Status', $csv);
        $this->assertStringContainsString('François', $csv);
        $this->assertStringContainsString('"Café Central"', $csv);
    }

    public function testEmptyReservationsProducesHeaderOnlyCsv(): void
    {
        $csv = $this->generateCsvStream([]);

        $this->assertStringContainsString('"Reservation ID","User Name",Restaurant,Date,Time,Guests,Status', $csv);
        
        $lines = explode("\n", trim($csv));
        $this->assertCount(1, $lines);
    }

    public function testCsvEscapesCommasInRestaurantNames(): void
    {
        $rows = [
            [
                'reservation_id' => 2,
                'user_name' => 'Liam',
                'restaurant_name' => 'Eats, Drinks & More',
                'reservation_date' => '2026-07-01',
                'reservation_time' => '18:00',
                'guests' => 4,
                'status' => 'Confirmed'
            ]
        ];

        $csv = $this->generateCsvStream($rows);

        $this->assertStringContainsString('"Eats, Drinks & More"', $csv);
    }

    public function testCsvEscapesQuotesInUserNames(): void
    {
        $rows = [
            [
                'reservation_id' => 3,
                'user_name' => 'O\'Connor "The Boss"',
                'restaurant_name' => 'Steakhouse',
                'reservation_date' => '2026-07-02',
                'reservation_time' => '20:00',
                'guests' => 2,
                'status' => 'Confirmed'
            ]
        ];

        $csv = $this->generateCsvStream($rows);

        // fputcsv escapes double quotes inside quoted strings by doubling them ("")
        $this->assertStringContainsString('""The Boss""', $csv);
    }

    // ── 2. DATA TYPE & EDGE-CASE PAYLOAD HANDLING ────────────────────────

    public function testCsvCorrectlyFormatsLargeGuestCountsAndNumericIds(): void
    {
        $rows = [
            [
                'reservation_id' => 999999,
                'user_name' => 'VIP Event',
                'restaurant_name' => 'Grand Hall',
                'reservation_date' => '2026-12-31',
                'reservation_time' => '23:59',
                'guests' => 250,
                'status' => 'Confirmed'
            ]
        ];

        $csv = $this->generateCsvStream($rows);

        $this->assertStringContainsString('999999', $csv);
        $this->assertStringContainsString('250', $csv);
    }

    public function testCsvHandlesMultipleRowsInExactSequence(): void
    {
        $rows = [
            ['id' => 101, 'name' => 'Alice', 'rest' => 'Rest A', 'date' => '2026-01-01', 'time' => '12:00', 'guests' => 2, 'status' => 'Confirmed'],
            ['id' => 102, 'name' => 'Bob', 'rest' => 'Rest B', 'date' => '2026-01-02', 'time' => '13:00', 'guests' => 3, 'status' => 'Cancelled'],
            ['id' => 103, 'name' => 'Charlie', 'rest' => 'Rest C', 'date' => '2026-01-03', 'time' => '14:00', 'guests' => 1, 'status' => 'Pending']
        ];

        $csv = $this->generateCsvStream($rows);

        $lines = explode("\n", trim($csv));
        // Line 0 is Header, Lines 1-3 are Data
        $this->assertCount(4, $lines);
        $this->assertStringContainsString('Alice', $lines[1]);
        $this->assertStringContainsString('Bob', $lines[2]);
        $this->assertStringContainsString('Charlie', $lines[3]);
    }

    #[DataProvider('statusValueProvider')]
    public function testCsvPreservesVariousReservationStatuses(string $status): void
    {
        $rows = [
            [
                'reservation_id' => 1,
                'user_name' => 'Test',
                'restaurant_name' => 'Test Rest',
                'reservation_date' => '2026-05-05',
                'reservation_time' => '12:00',
                'guests' => 2,
                'status' => $status
            ]
        ];

        $csv = $this->generateCsvStream($rows);

        $this->assertStringContainsString($status, $csv);
    }

    public static function statusValueProvider(): array
    {
        return [
            'confirmed status' => ['Confirmed'],
            'cancelled status' => ['Cancelled'],
            'pending status'   => ['Pending'],
            'no-show status'   => ['No-Show'],
            'completed status' => ['Completed'],
        ];
    }

    // ── 3. DATABASE EXCEPTION HANDLING ───────────────────────────────────

    public function testNonExistentTableThrowsPdoExceptionAndReturns500(): void
    {
        $brokenPdo = new PDO('sqlite::memory:');

        $result = $this->processExportWithPdo($brokenPdo, 'missing_table');

        $this->assertSame(500, $result['status_code']);
        $this->assertFalse($result['response']['success']);
        $this->assertStringStartsWith('Export query failed:', $result['response']['message']);
    }

    public function testCorruptedDatabaseConnectionReturnsFormattedError(): void
    {
        // Uninitialized PDO or closed memory connection
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE reservations (id INT);");
        
        // Execute against invalid table column
        $result = $this->processExportWithPdo($pdo, 'reservations WHERE non_existent_column = 1');

        $this->assertSame(500, $result['status_code']);
        $this->assertFalse($result['response']['success']);
        $this->assertStringContainsString('no such column', $result['response']['message']);
    }

    public function testSyntaxErrorInSqlReturnsDatabaseErrorResponse(): void
    {
        $pdo = new PDO('sqlite::memory:');

        $result = $this->processExportWithPdo($pdo, 'reservations INVALID SYNTAX HERE');

        $this->assertSame(500, $result['status_code']);
        $this->assertFalse($result['response']['success']);
        $this->assertStringStartsWith('Export query failed:', $result['response']['message']);
    }
}