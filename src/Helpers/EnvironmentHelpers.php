<?php
// src/Helpers/EnvironmentHelpers.php

/**
 * Determine whether the current request is coming from a local/dev
 * environment, based on server name, server address, and host.
 *
 * Used anywhere the app needs to branch between local and production
 * behavior (e.g. db.php's credential fallback, forgot_password.php's
 * reset-link construction and dev logging).
 */
function isLocalEnvironment(string $serverName, string $serverAddr, string $httpHost): bool
{
    return in_array($serverName, ['localhost', '127.0.0.1', '::1', ''], true)
        || $serverAddr === '127.0.0.1'
        || $httpHost === 'localhost';
}
