<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/consent.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/department.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$identifier = strtolower(trim((string) ($_POST['email'] ?? $_POST['identifier'] ?? '')));
$version = trim((string) ($_POST['consent_version'] ?? CONSENT_VERSION));
if ($version === '') {
    $version = CONSENT_VERSION;
}

if ($identifier === '') {
    echo json_encode(['success' => false, 'has_consented' => false, 'consent_current' => false]);
    exit;
}

try {
    $userModel = new User();
    $hasConsented = $userModel->hasConsentedByEmail($identifier);
    $hasCurrentConsent = $userModel->hasCurrentConsentByEmail($identifier, $version);

    // If not found in user table, check departments table (legacy department accounts).
    if (!$hasConsented && !str_contains($identifier, '@')) {
        $departmentModel = new Department();
        $deptConsented = $departmentModel->hasConsentedByUsername($identifier);
        if ($deptConsented) {
            $hasConsented = true;
            $hasCurrentConsent = $departmentModel->hasCurrentConsentByUsername($identifier, $version);
        }
    }

    echo json_encode([
        'success' => true,
        'has_consented' => $hasConsented,
        'consent_current' => $hasCurrentConsent,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'has_consented' => false, 'consent_current' => false]);
}
