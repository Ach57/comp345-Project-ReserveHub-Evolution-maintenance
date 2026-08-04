<?php
// src/Helpers/LoginHelpers.php

/**
 * Trim a raw credential value (identifier or password) from a decoded
 * request payload. Treats a missing/null value as an empty string,
 * matching the `?? ''` fallback used in login.php.
 */
function trimCredential(?string $value): string
{
    return trim($value ?? '');
}

/**
 * Check whether a trimmed identifier + password pair is non-empty
 * enough to attempt a login lookup.
 */
function hasValidCredentials(string $identifier, string $password): bool
{
    return !empty($identifier) && !empty($password);
}
