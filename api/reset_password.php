<?php
// api/reset_password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../src/Helpers/ResetPasswordHelpers.php';
require_once __DIR__ . '/../src/Helpers/LoginHelpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'load_env.php';

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

if (!hasValidResetRequest($data)) {
    echo json_encode(['success' => false, 'message' => 'Missing token or password.']);
    exit;
}

$token = trimCredential($data->token);
$newPassword = trimCredential($data->newPassword);

try {
    // Check if token exists and is valid (not expired)
    // Compare using PHP's current time to avoid timezone mismatch with MySQL NOW()
    $currentTime = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("SELECT user_id AS id FROM users WHERE reset_token = ? AND reset_token_expiry > ?");
    $stmt->execute([$token, $currentTime]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Hash the new password
        $hashedPassword = $newPassword;

        // Update password and clear token
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $updateStmt->execute([$hashedPassword, $user['id']]);

        echo json_encode(['success' => true, 'message' => 'Your password has been successfully updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
