<?php
session_start();
// api/db.example.php
// Copy this file to db.php and update the credentials for your local environment.

$host = 'localhost';          // Your MySQL Hostname
$port = '3307';               // Your MySQL Port (3307 for XAMPP when Oracle MySQL occupies 3306)
$db   = 'reservehub';         // Your MySQL Database Name
$user = 'root';               // Your MySQL Username
$pass = '';                   // Your MySQL Password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
