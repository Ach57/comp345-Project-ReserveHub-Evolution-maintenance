<?php
// src/Helpers/LanguageHelpers.php

/**
 * Sanitize a raw language code from the query string: keep only
 * alpha characters and hyphens, capped at 10 characters.
 */
function sanitizeLangCode(string $lang): string
{
    return preg_replace('/[^a-zA-Z\-]/', '', substr($lang, 0, 10));
}

/**
 * Sanitize a raw page key from the query string: keep only
 * alphanumeric characters and hyphens, capped at 50 characters.
 */
function sanitizePageKey(string $page): string
{
    return preg_replace('/[^a-zA-Z0-9\-]/', '', substr($page, 0, 50));
}

/**
 * Convert DB rows shaped like ['translation_key' => ..., 'translation_value' => ...]
 * into a flat associative array of key => value.
 *
 * If the same translation_key appears more than once, the later row wins
 * (matches the original foreach's overwrite behavior).
 */
function buildTranslationMap(array $rows): array
{
    $translations = [];
    foreach ($rows as $row) {
        $translations[$row['translation_key']] = $row['translation_value'];
    }
    return $translations;
}
