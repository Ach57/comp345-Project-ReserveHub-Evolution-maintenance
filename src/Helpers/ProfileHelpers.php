<?php
// src/Helpers/ProfileHelpers.php

/**
 * Check whether a decoded profile-request payload contains a usable email.
 *
 * Expects $data to be the result of json_decode() WITHOUT the associative
 * flag (i.e. a stdClass or null), matching how profile.php currently
 * decodes the request body.
 */
function hasValidEmail(?object $data): bool
{
    return $data !== null && !empty($data->email);
}
