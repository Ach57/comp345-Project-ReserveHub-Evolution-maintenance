<?php
// src/Helpers/SearchHelpers.php

/**
 * Build the SQL WHERE-clause filters and matching bound params for a
 * restaurant search, based on the four optional query-string filters.
 *
 * Returns ['sql' => string, 'params' => array] ready to hand to
 * $pdo->prepare()/execute().
 *
 * Note: $halal uses a `!== ''` check (not empty()), matching the
 * original logic — this means halal="0" IS treated as an active filter
 * (only an empty string skips it), unlike the other three filters which
 * use empty().
 */
function buildSearchQuery(string $query, string $location, string $time, string $halal): array
{
    $sql = "
        SELECT r.*, r.restaurant_id AS id,
               ROUND(COALESCE(AVG(rev.rating), r.seed_rating), 1) AS rating
        FROM restaurants r
        LEFT JOIN reviews rev ON rev.restaurant_id = r.restaurant_id
        WHERE r.status = 'approved'
    ";
    $params = [];

    if (!empty($query)) {
        $sql .= " AND (r.name LIKE ? OR r.cuisine LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }

    if (!empty($location)) {
        $sql .= " AND r.location LIKE ?";
        $params[] = "%$location%";
    }

    if ($halal !== '') {
        $sql .= " AND r.is_halal = ?";
        $params[] = (int)$halal;
    }

    if (!empty($time)) {
        $sql .= " AND r.opening_time <= ? AND r.closing_time >= ?";
        $params[] = $time;
        $params[] = $time;
    }

    $sql .= " GROUP BY r.restaurant_id";

    return ['sql' => $sql, 'params' => $params];
}
