<?php
// src/Helpers/ForgotPasswordHelpers.php

/**
 * Determine http vs https based on the raw $_SERVER['HTTPS'] value
 * (which may be null if the key isn't set at all).
 */
function determineProtocol(?string $https): string
{
    return $https === 'on' ? 'https' : 'http';
}

/**
 * Build the full password-reset link sent to the user.
 */
function buildResetLink(string $protocol, string $host, bool $isLocal, string $token): string
{
    $baseDir = $isLocal ? '/reservehub' : '';
    return $protocol . '://' . $host . $baseDir . '/html/reset-password.html?token=' . $token;
}

/**
 * Calculate the reset token's expiry timestamp, formatted for storage.
 *
 * Accepts a base Unix timestamp (instead of always using "now" internally)
 * so the result is deterministic and testable.
 */
function calculateResetExpiry(int $fromTimestamp): string
{
    return date('Y-m-d H:i:s', strtotime('+1 hour', $fromTimestamp));
}

/**
 * Build the plain-text body of the password reset email.
 */
function buildResetEmailBody(string $name, string $resetLink): string
{
    $message = "Hi " . $name . ",\n\n";
    $message .= "We received a request to reset your ReserveHub password.\n";
    $message .= "Click the link below to set a new password:\n\n";
    $message .= $resetLink . "\n\n";
    $message .= "If you didn't request this, you can safely ignore this email.\nThis link will expire in 1 hour.\n\n";
    $message .= "Best regards,\nThe ReserveHub Team";
    return $message;
}
