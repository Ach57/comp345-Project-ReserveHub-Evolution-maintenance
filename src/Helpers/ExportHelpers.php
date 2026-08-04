<?php
// src/Helpers/ExportHelpers.php

/**
 * Generate the timestamped CSV export filename.
 *
 * Accepts a Unix timestamp (instead of always using "now" internally)
 * so the result is deterministic and testable.
 */
function generateExportFilename(int $timestamp): string
{
    return 'reservations_export_' . date('Y-m-d_His', $timestamp) . '.csv';
}

/**
 * Convert a single associative reservation row (as returned by the DB
 * query or sample data) into the flat, positional array fputcsv() expects.
 */
function mapReservationRowToCsvRow(array $row): array
{
    return [
        $row['reservation_id'],
        $row['user_name'],
        $row['restaurant_name'],
        $row['reservation_date'],
        $row['reservation_time'],
        $row['guests'],
        $row['status'],
    ];
}
