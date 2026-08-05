<?php
// src/Helpers/ReserveHelpers.php

/**
 * Check that all required reservation fields are present.
 *
 * Uses plain truthy checks (matching the original code), not empty() —
 * this is a deliberate distinction from most other validators in this
 * codebase. It means a guest_count of 0 (or the string '0') correctly
 * fails validation, same as null/missing, which is desired here since
 * a reservation for 0 guests isn't valid.
 */
function hasValidReservationFields(
    mixed $userId,
    mixed $restaurantId,
    mixed $tableId,
    mixed $date,
    mixed $reservationTime,
    mixed $guestCount
): bool {
    return (bool) ($userId && $restaurantId && $tableId && $date && $reservationTime && $guestCount);
}
