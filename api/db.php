<?php
session_start();
// api/db.php
// Auto-detects environment: uses local XAMPP credentials when running on localhost,
// and configures for live environment (InfinityFree / x10Hosting / custom) otherwise.

// Load environment variables if available
$loadEnvPath = __DIR__ . '/load_env.php';
if (file_exists($loadEnvPath)) {
    require_once $loadEnvPath;
}

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1', ''])
        || ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1'
        || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';

// Use environment variables if set, otherwise fallback to defaults
$host = $_ENV['DB_HOST'] ?? null;
$db   = $_ENV['DB_NAME'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$pass = $_ENV['DB_PASS'] ?? null;

if (!$host || !$db || !$user) {
    if ($isLocal) {
        // ── Local XAMPP ──────────────────────────────
        $host    = 'localhost';
        $db      = 'reserve-hub';   // your local database name
        $user    = 'root';
        $pass    = '';             // default XAMPP has no root password
    } else {
        // ── Production Default (e.g. InfinityFree / x10Hosting fallback) ──
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        if (strpos($serverName, 'infinityfree') !== false) {
            $host = 'sql101.infinityfree.com';
            $db   = 'if0_41799227_reservehub';
            $user = 'if0_41799227';
            $pass = 'Sekolah1123';
        } else {
            // x10Hosting uses 'localhost' as MySQL host
            $host = 'localhost'; 
            $db   = 'your_x10hosting_database_name'; // Update this or use .env in production
            $user = 'your_x10hosting_database_user'; // Update this or use .env in production
            $pass = 'your_x10hosting_database_password'; // Update this or use .env in production
        }
    }
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . $e->getMessage()]));
}
?>
