<?php
// api/profile.php
header('Content-Type: application/json');
require_once 'db.php';
require_once __DIR__ . '/../src/Helpers/ProfileHelpers.php';

$data = json_decode(file_get_contents("php://input"));

if (!hasValidEmail($data)) {
    echo json_encode(['success' => false, 'message' => 'Email is required to fetch profile.']);
    exit;
}

$email = $data->email;

try {
    $stmt = $pdo->prepare("SELECT user_id AS id, username, name, email, role, phone, profile_picture, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
