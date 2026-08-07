<?php
// api/search.php
header('Content-Type: application/json');
require_once 'db.php';
require_once __DIR__ . '/../src/Helpers/SearchHelpers.php';

$query    = $_GET['q']        ?? '';
$location = $_GET['location'] ?? '';
$time     = $_GET['time']     ?? '';
$halal    = $_GET['halal']    ?? '';

$built = buildSearchQuery($query, $location, $time, $halal);

try {
    $stmt = $pdo->prepare($built['sql']);
    $stmt->execute($built['params']);
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $restaurants
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
