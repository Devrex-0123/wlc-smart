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

$email = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$consentAccepted = isset($_POST['privacy_agreement']) && $_POST['privacy_agreement'] === 'on';

if (empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Email and password required"]);
    exit;
}

$auth = new AuthController();
$result = $auth->login($email, $password, $consentAccepted);

// Add role to success response if login is successful
if (isset($result['success']) && $result['success'] && isset($_SESSION['user_role'])) {
    $result['role'] = $_SESSION['user_role']; // Make sure authController sets this in session

    try {
        $db = Database::connect();
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, 'Login', 'Successful login', NOW())");
        $stmt->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // Non-blocking audit logging
    }
}

session_write_close();

echo json_encode($result);
exit;