<?php
// api/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../src/Helpers/LoginHelpers.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

// Get JSON data from request body
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

// Fallback: try $_POST if JSON decode failed (e.g. form submission)
if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. No data received.']);
    exit;
}

$identifier = trimCredential($data->identifier ?? null);
$password = trimCredential($data->password ?? null);

if (!hasValidCredentials($identifier, $password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

try {
    // Find user by email or username
    $stmt = $pdo->prepare("SELECT user_id AS id, username, name, email, role, phone, profile_picture, password FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'phone' => $user['phone'],
                'profile_picture' => $user['profile_picture']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email/username or password.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
