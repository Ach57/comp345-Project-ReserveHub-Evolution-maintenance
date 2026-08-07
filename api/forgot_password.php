<?php
// api/forgot_password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../src/Helpers/ProfileHelpers.php';
require_once __DIR__ . '/../src/Helpers/ForgotPasswordHelpers.php';
require_once __DIR__ . '/../src/Helpers/EnvironmentHelpers.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'load_env.php';

// Get JSON data from request body
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

// Fallback: try $_POST if JSON decode failed
if (!$data && !empty($_POST)) {
    $data = (object) $_POST;
}

if (!hasValidEmail($data)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$email = trim($data->email);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id AS id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate a secure token
        $token = bin2hex(random_bytes(32));
        
        // Set expiry time to 1 hour from now
        $expiry = calculateResetExpiry(time());

        // Save token to database
        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
        $updateStmt->execute([$token, $expiry, $user['id']]);

        // Create the reset link
        $isLocal = isLocalEnvironment($_SERVER['SERVER_NAME'], $_SERVER['SERVER_ADDR'], $_SERVER['HTTP_HOST']);
            
        $protocol = determineProtocol($_SERVER['HTTPS'] ?? null);
        $host = $_SERVER['HTTP_HOST'];
        $resetLink = buildResetLink($protocol, $host, $isLocal, $token);

        // Send email using PHPMailer
        require_once 'PHPMailer/Exception.php';
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
            $mail->Username   = $_ENV['SMTP_USER'];       // SMTP username
            $mail->Password   = $_ENV['SMTP_PASS'];                  // SMTP password (use an App Password)
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;                                  // TCP port to connect to

            // Disable SSL peer verification for local XAMPP environment compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom($_ENV['SMTP_USER'], 'ReserveHub Team');
            $mail->addAddress($email, $user['name']);                 // Add a recipient

            // Content
            $mail->isHTML(false);
            $mail->Subject = "Password Reset Request - ReserveHub";
            $message = buildResetEmailBody($user['name'], $resetLink);
            $mail->Body = $message;

            $mail->send();
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

        // Log reset link locally if in development environment
        if ($isLocal) {
            $logFile = __DIR__ . '/../reservehub_emails.log';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Password Reset Request for $email\n";
            $logMessage .= "Reset Link: $resetLink\n";
            $logMessage .= "--------------------------------------------------\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        }
    }
    
    // Always return success even if email doesn't exist to prevent email enumeration
    $response = [
        'success' => true,
        'message' => 'If an account matches that email, we have sent a password reset link.'
    ];
    if ($isLocal && $user) {
        $response['debug_link'] = $resetLink;
    }
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
