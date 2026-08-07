<?php
// api/language.php
header('Content-Type: application/json');
require_once 'db.php';
require_once '../src/Helpers/LanguageHelpers.php';

$action = $_GET['action'] ?? null;

// action=languages: return all active languages
if ($action === 'languages') {
    try {
        $stmt = $pdo->query("SELECT code, name FROM languages WHERE is_active = 1 ORDER BY code");
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([]);
    }
    exit;
}

// default: return translations for a given lang + page 
$lang = $_GET['lang'] ?? 'en';
$page = $_GET['page'] ?? null;


if (!$page) {
    echo json_encode((object)[]);
    exit;
}

// Sanitize: only allow alpha + hyphen, max 50 chars
$lang = sanitizeLangCode($lang);
$page = sanitizePageKey($page);

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

    $translations = buildTranslationMap($rows);
    echo json_encode($translations);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode((object)[]);
}
?>
