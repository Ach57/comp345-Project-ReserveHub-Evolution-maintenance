<?php
// src/Helpers/SignupHelpers.php

/**
 * Normalize a submitted role to one of the two allowed values,
 * falling back to 'customer' for anything else.
 */
function normalizeRole(string $role): string
{
    return in_array($role, ['customer', 'vendor'], true) ? $role : 'customer';
}

/**
 * Check whether all required signup fields are present (non-empty).
 */
function hasRequiredSignupFields(
    string $username,
    string $name,
    string $email,
    string $password,
    string $phone
): bool {
    return !empty($username) && !empty($name) && !empty($email) && !empty($password) && !empty($phone);
}

/**
 * Check whether an email string is a valid email format.
 */
function isValidEmailFormat(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check whether a password meets the minimum length requirement (6 chars).
 */
function isPasswordLongEnough(string $password): bool
{
    return strlen($password) >= 6;
}
