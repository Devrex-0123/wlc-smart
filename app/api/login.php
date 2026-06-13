<?php
// app/api/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . '/../controllers/authController.php';
require_once __DIR__ . '/../classes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$identifier = trim((string) ($_POST['email'] ?? $_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';
$consentAccepted = isset($_POST['privacy_agreement']) && $_POST['privacy_agreement'] === 'on';

if ($identifier === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Email/username and password required"]);
    exit;
}

$auth = new AuthController();
$result = $auth->login($identifier, $password, $consentAccepted);

if (isset($result['success']) && $result['success']) {
    if (isset($_SESSION['user_role'])) {
        $result['role'] = $_SESSION['user_role'];
    }
    if (isset($_SESSION['login_type'])) {
        $result['login_type'] = $_SESSION['login_type'];
    }

    if (($_SESSION['login_type'] ?? 'user') === 'user' && isset($_SESSION['user_id'])) {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, 'Login', 'Successful login', NOW())");
            $stmt->execute([(int) $_SESSION['user_id']]);
        } catch (Throwable $e) {
            // Non-blocking audit logging
        }
    }
}

session_write_close();

echo json_encode($result);
exit;