<?php
// src/Helpers/ResetPasswordHelpers.php

/**
 * Check whether a decoded reset-password payload contains both a token
 * and a new password.
 *
 * Expects $data to be the result of json_decode() WITHOUT the associative
 * flag (i.e. a stdClass or null), matching how reset_password.php
 * currently decodes the request body.
 */
function hasValidResetRequest(?object $data): bool
{
    return $data !== null && !empty($data->token) && !empty($data->newPassword);
}
