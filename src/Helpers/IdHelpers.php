<?php
// src/Helpers/IdHelpers.php

/**
 * Generate a prefixed, zero-padded sequential ID (e.g. 'c001', 'r042').
 *
 * Used anywhere a new record needs an ID like {prefix}{padded number} —
 * currently: users ('c'), reservations ('b'), restaurants ('r'),
 * tables ('t'), and contact messages ('m').
 */
function generateSequentialId(string $prefix, int $nextNumber, int $padLength = 3): string
{
    return $prefix . str_pad((string)$nextNumber, $padLength, '0', STR_PAD_LEFT);
}
