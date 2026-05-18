<?php
// app/api/reset_attempts.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . '/../../models/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';

$userModel = new User();

if (!empty($email)) {
    // Reset attempts for this email
    $userModel->resetAttempts($email);
    echo json_encode(["success" => true, "message" => "Attempts reset for email"]);
    exit;
} else {
    // If email not provided, we reset nothing by default for security reasons.
    // If you really want to reset all accounts, you can call resetAllAttempts() with caution.
    echo json_encode(["success" => false, "message" => "No email provided"]);
    exit;
}
