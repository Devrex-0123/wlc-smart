<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

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

$isDepartmentLogin = isset($_SESSION['login_type'])
    && $_SESSION['login_type'] === 'department'
    && !empty($_SESSION['department_id']);

if (!$isDepartmentLogin) {
    sendJson(['success' => false, 'message' => 'Unauthorized']);
}

$db = Database::connect();
$departmentId = (int) $_SESSION['department_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? 'get';

function departmentsHasPhotoColumn(PDO $db): bool
{
    static $has = null;
    if ($has === null) {
        $has = (bool) $db->query("SHOW COLUMNS FROM departments LIKE 'department_photo_url'")->fetch(PDO::FETCH_ASSOC);
    }

    return $has;
}

function handleDepartmentProfilePhotoUpload(array $file, ?string $existingPhoto): ?string
{
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

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        return $existingPhoto;
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mime] ?? 'jpg';
    $uploadDir = __DIR__ . '/../../public/uploads/departments/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return $existingPhoto;
    }

    $safeName = 'dept_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . $safeName;
    if (!move_uploaded_file($tmp, $target)) {
        return $existingPhoto;
    }

    if (!empty($existingPhoto)) {
        $oldPath = __DIR__ . '/../../public/' . ltrim($existingPhoto, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return 'uploads/departments/' . $safeName;
}

function validateDepartmentPassword(string $password): ?string
{
    if ($password === '') {
        return 'Password is required';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include an uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include a lowercase letter';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must include a number';
    }
    if (!preg_match('/[@$!%*?&#\-_.]/', $password)) {
        return 'Password must include a special character (@$!%*?&#-_.)';
    }

    return null;
}

try {
    $photoSelect = departmentsHasPhotoColumn($db) ? ', department_photo_url' : '';

    if ($action === 'get') {
        $stmt = $db->prepare("
            SELECT
                department_id,
                department_name,
                department_abbreviation,
                department_type,
                department_username,
                department_status,
                had_consented,
                consented_at,
                consent_version,
                department_created_at,
                department_updated_at
                {$photoSelect}
            FROM departments
            WHERE department_id = ?
            LIMIT 1
        ");
        $stmt->execute([$departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            sendJson(['success' => false, 'message' => 'Department not found']);
        }

        sendJson(['success' => true, 'department' => $department]);
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($current === '' || $next === '') {
            sendJson(['success' => false, 'message' => 'Current and new password are required']);
        }
        if ($next !== $confirm) {
            sendJson(['success' => false, 'message' => 'New password and confirmation do not match']);
        }

        $passwordError = validateDepartmentPassword($next);
        if ($passwordError !== null) {
            sendJson(['success' => false, 'message' => $passwordError]);
        }

        $stmt = $db->prepare('SELECT department_password_hash FROM departments WHERE department_id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            sendJson(['success' => false, 'message' => 'Department not found']);
        }

        $hash = (string) ($row['department_password_hash'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) {
            sendJson(['success' => false, 'message' => 'Current password is incorrect']);
        }

        $newHash = password_hash($next, PASSWORD_BCRYPT);
        $db->prepare('UPDATE departments SET department_password_hash = ?, department_updated_at = NOW() WHERE department_id = ?')
            ->execute([$newHash, $departmentId]);

        session_destroy();

        sendJson([
            'success' => true,
            'logout_required' => true,
            'message' => 'Password updated. Please log in again with your new password.',
        ]);
    }

    if ($action === 'update_photo') {
        if (!departmentsHasPhotoColumn($db)) {
            sendJson(['success' => false, 'message' => 'Photo uploads are not available for this account']);
        }

        $stmt = $db->prepare('SELECT department_photo_url FROM departments WHERE department_id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            sendJson(['success' => false, 'message' => 'Department not found']);
        }
        if (!isset($_FILES['photo'])) {
            sendJson(['success' => false, 'message' => 'Please choose an image']);
        }

        $newPhoto = handleDepartmentProfilePhotoUpload($_FILES['photo'], $row['department_photo_url'] ?? null);
        if ($newPhoto === ($row['department_photo_url'] ?? null)) {
            sendJson(['success' => false, 'message' => 'Photo upload failed or invalid file']);
        }

        $db->prepare('UPDATE departments SET department_photo_url = ?, department_updated_at = NOW() WHERE department_id = ?')
            ->execute([$newPhoto, $departmentId]);

        sendJson(['success' => true, 'message' => 'Department photo updated', 'photo_url' => $newPhoto]);
    }

    sendJson(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    sendJson(['success' => false, 'message' => 'Request failed']);
}
