<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../models/user.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
$version = trim((string)($_POST['consent_version'] ?? 'v1.0'));

if ($email === '') {
    echo json_encode(['success' => false, 'consent_current' => false]);
    exit;
}

try {
    $userModel = new User();
    $hasCurrentConsent = $userModel->hasCurrentConsentByEmail($email, $version);
    echo json_encode([
        'success' => true,
        'consent_current' => $hasCurrentConsent
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'consent_current' => false]);
}

