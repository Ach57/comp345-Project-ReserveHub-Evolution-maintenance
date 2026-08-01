<?php
// api/language.php
header('Content-Type: application/json');
require_once 'db.php';

$lang = $_GET['lang'] ?? 'en';
$page = $_GET['page'] ?? null;

if (!$page) {
    echo json_encode((object)[]);
    exit;
}

// Sanitize: only allow alpha + hyphen, max 50 chars
$lang = preg_replace('/[^a-zA-Z\-]/', '', substr($lang, 0, 10));
$page = preg_replace('/[^a-zA-Z0-9\-]/', '', substr($page, 0, 50));

try {
    $stmt = $pdo->prepare("
        SELECT t.translation_key, t.translation_value
        FROM translations t
        JOIN languages l ON t.language_code = l.code
        WHERE t.language_code = ?
          AND t.page_key      = ?
          AND l.is_active     = 1
    ");
    $stmt->execute([$lang, $page]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        // Fall back to English if the requested language has no entries
        $stmt->execute(['en', $page]);
        $rows = $stmt->fetchAll();
    }

    $translations = [];
    foreach ($rows as $row) {
        $translations[$row['translation_key']] = $row['translation_value'];
    }

    echo json_encode($translations);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode((object)[]);
}
?>
