<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function sendJson(array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/db.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../classes/audit_logger.php';

if (!isset($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Unauthorized']);
}

$db = Database::connect();
$userModel = new User();
$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

function logProfileActivity(PDO $db, int $userId, string $activity, string $desc): void {
    $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $activity, $desc]);
}

function handleProfilePhotoUpload(array $file, ?string $existingPhoto): ?string
{
    $uploadDir = __DIR__ . '/../../public/uploads/users/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $existingPhoto;
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $existingPhoto;
    }

    $mime = mime_content_type($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        return $existingPhoto;
    }

    $ext = pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION);
    $safe = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext ?: 'jpg');
    $target = $uploadDir . $safe;
    if (!move_uploaded_file($tmp, $target)) {
        return $existingPhoto;
    }

    if (!empty($existingPhoto)) {
        $oldPath = __DIR__ . '/../../public/' . ltrim($existingPhoto, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return 'uploads/users/' . $safe;
}

try {
    if ($action === 'get') {
        $stmt = $db->prepare("
            SELECT 
                u.user_id, u.Email AS email, u.role, u.full_name, u.contact_number,
                u.last_login, u.password_updated_at, u.account_status, u.office_id,
                u.has_consented, u.consent_date, u.consent_version, u.photo_url,
                d.`office_name` AS office_name
            FROM user u
            LEFT JOIN offices d ON d.office_id = u.office_id
            WHERE u.user_id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendJson(['success' => false, 'message' => 'User not found']);
        }
        sendJson(['success' => true, 'user' => $user]);
    }

    if ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $contact = trim((string)($_POST['contact_number'] ?? ''));

        if ($fullName !== '' && strlen($fullName) > 150) {
            sendJson(['success' => false, 'message' => 'Full name must be 150 characters or less']);
        }
        if ($contact !== '' && (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact))) {
            sendJson(['success' => false, 'message' => 'Contact number format is invalid']);
        }

        $userModel->updateProfile($userId, $fullName !== '' ? $fullName : null, $contact !== '' ? $contact : null);
        if ($fullName !== '') {
            $_SESSION['user_name'] = $fullName;
        }
        logProfileActivity($db, $userId, 'Profile Update', 'Updated profile information');
        AuditLogger::logCriticalAction($db, $userId, $userId, 'update', 'profile', $userId, 'Updated profile fields full_name/contact_number');
        sendJson(['success' => true, 'message' => 'Profile updated']);
    }

    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $next = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($next === '' || $current === '') {
            sendJson(['success' => false, 'message' => 'Current and new password are required']);
        }
        if ($next !== $confirm) {
            sendJson(['success' => false, 'message' => 'New password and confirmation do not match']);
        }
        if (strlen($next) < 8) {
            sendJson(['success' => false, 'message' => 'Password must be at least 8 characters']);
        }
        if (
            !preg_match('/[A-Z]/', $next) ||
            !preg_match('/[a-z]/', $next) ||
            !preg_match('/[0-9]/', $next) ||
            !preg_match('/[@$!%*?&#\-_\.]/', $next)
        ) {
            sendJson(['success' => false, 'message' => 'Password must include uppercase, lowercase, number, and special character']);
        }

        $user = $userModel->findById($userId);
        if (!$user || !$userModel->verifyPassword($current, (string)($user['password'] ?? ''))) {
            sendJson(['success' => false, 'message' => 'Current password is incorrect']);
        }

        $userModel->updatePasswordHashByUserId($userId, $next);
        logProfileActivity($db, $userId, 'Password Change', 'Changed account password');
        AuditLogger::logCriticalAction($db, $userId, $userId, 'update', 'profile_password', $userId, 'Updated own account password');
        sendJson(['success' => true, 'message' => 'Password updated']);
    }

    if ($action === 'request_deletion') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, 'Deletion Request', ?, NOW())");
        $desc = $reason !== '' ? "Requested account deletion. Reason: {$reason}" : 'Requested account deletion.';
        $stmt->execute([$userId, $desc]);
        AuditLogger::logCriticalAction($db, $userId, $userId, 'update', 'profile_deletion_request', $userId, 'Requested account deletion');
        echo json_encode(['success' => true, 'message' => 'Deletion request sent. Please wait for administrator review.']);
        exit;
    }

    if ($action === 'update_photo') {
        $user = $userModel->findById($userId);
        if (!$user) {
            sendJson(['success' => false, 'message' => 'User not found']);
        }
        if (!isset($_FILES['photo'])) {
            sendJson(['success' => false, 'message' => 'Please choose an image']);
        }
        $newPhoto = handleProfilePhotoUpload($_FILES['photo'], $user['photo_url'] ?? null);
        if ($newPhoto === ($user['photo_url'] ?? null)) {
            sendJson(['success' => false, 'message' => 'Photo upload failed or invalid file']);
        }

        $stmt = $db->prepare("UPDATE user SET photo_url = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$newPhoto, $userId]);
        logProfileActivity($db, $userId, 'Profile Photo Update', 'Updated profile photo');
        AuditLogger::logCriticalAction($db, $userId, $userId, 'update', 'profile_photo', $userId, 'Updated own profile photo');
        sendJson(['success' => true, 'message' => 'Profile photo updated', 'photo_url' => $newPhoto]);
    }

    sendJson(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Request failed']);
}

