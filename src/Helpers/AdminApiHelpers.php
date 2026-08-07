<?php
// src/Helpers/AdminApiHelpers.php

/**
 * Check required fields for creating a user via the admin panel
 * (users POST). Note: no phone requirement here, unlike signup.php.
 */
function hasRequiredAdminUserFields(string $username, string $name, string $email, string $password): bool
{
    return !empty($username) && !empty($name) && !empty($email) && !empty($password);
}

/**
 * Normalize a submitted role for admin user creation, falling back
 * to 'vendor' for anything not in the allowed list.
 */
function normalizeAdminRole(string $role): string
{
    return in_array($role, ['customer', 'vendor', 'admin'], true) ? $role : 'vendor';
}

/**
 * Strictly validate a role for admin user updates (users PUT) —
 * unlike normalizeAdminRole(), an invalid role here is rejected
 * rather than silently defaulted.
 */
function hasValidRoleStrict(string $role): bool
{
    return !empty($role) && in_array($role, ['customer', 'vendor', 'admin'], true);
}

/**
 * Check that both name and email are present (users PUT second check).
 */
function hasRequiredNameAndEmail(string $name, string $email): bool
{
    return !empty($name) && !empty($email);
}

/**
 * Extract a lowercased file extension from an uploaded filename.
 */
function getFileExtension(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Resolve a submitted vendor_id: empty values become null so the
 * DB query can COALESCE against the existing value on update.
 */
function resolveVendorId(mixed $rawVendorId): mixed
{
    return !empty($rawVendorId) ? $rawVendorId : null;
}

/**
 * Resolve the seed rating to store for a restaurant, checking
 * 'seed_rating' first, then falling back to a legacy 'rating' field,
 * then null if neither is present/non-empty.
 */
function resolveSeedRating(array $input): ?float
{
    if (isset($input['seed_rating']) && $input['seed_rating'] !== '') {
        return (float) $input['seed_rating'];
    }
    if (isset($input['rating']) && $input['rating'] !== '') {
        return (float) $input['rating'];
    }
    return null;
}

/**
 * Validate an approvals decision: an id must be present and status
 * must be one of the two allowed decision values.
 */
function isValidApprovalDecision(mixed $id, mixed $status): bool
{
    return !empty($id) && in_array($status, ['approved', 'rejected'], true);
}

/**
 * Check required fields for a contact message submission.
 */
function hasRequiredMessageFields(string $name, string $email, string $subject, string $message): bool
{
    return !empty($name) && !empty($email) && !empty($subject) && !empty($message);
}
