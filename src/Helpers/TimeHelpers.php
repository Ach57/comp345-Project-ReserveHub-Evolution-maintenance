<?php
// src/Helpers/TimeHelpers.php
//
// Shared helpers for reservation time logic.
// Previously duplicated in api/reserve.php and api/tables.php —
// both should require_once this file instead of redefining these functions.

/**
 * Convert "HH:MM:SS" or "HH:MM" to total minutes since midnight.
 */
function timeToMinutes(string $t): int
{
    $parts = explode(':', $t);
    return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
}

/**
 * Check if a requested time falls within a restaurant's operating hours.
 * Handles same-day spans (e.g. 11:00–22:00), overnight spans
 * (e.g. 18:00–03:00), and 24-hour operation (00:00–23:59+).
 */
function isWithinOperatingHours(string $requestedTime, string $openTime, string $closeTime): bool
{
    $req   = timeToMinutes($requestedTime);
    $open  = timeToMinutes($openTime);
    $close = timeToMinutes($closeTime);

    // 24-hour restaurant (00:00 – 23:59)
    if ($open === 0 && $close >= 1439) {
        return true;
    }

    if ($close > $open) {
        // Normal same-day span: e.g. 11:00 – 22:00
        return $req >= $open && $req < $close;
    } else {
        // Overnight span: e.g. 18:00 – 03:00
        return $req >= $open || $req < $close;
    }
}
