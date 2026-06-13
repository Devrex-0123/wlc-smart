<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../classes/db.php';

function logActivity($user_id, $activity_type, $description) {
    global $db;
    $stmt = $db->prepare(
        'INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$user_id, $activity_type, $description]);
}

function normalizeDepartmentType(?string $type): ?string {
    $value = strtolower(trim((string) $type));
    $map = [
        'academic' => 'Academic',
        'administrative' => 'Administrative',
        'executive' => 'Executive',
    ];
    return $map[$value] ?? null;
}

function normalizeDepartmentStatus(?string $status): ?string {
    $value = strtolower(trim((string) $status));
    $map = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];
    return $map[$value] ?? null;
}

function validateDepartmentPassword(?string $password): ?string {
    $password = (string) $password;
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

function handleDepartmentPhotoUpload(): ?string {
    if (!isset($_FILES['department_photo']) || $_FILES['department_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['department_photo']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed to upload department photo.');
    }

    $fileTmp = $_FILES['department_photo']['tmp_name'];
    $fileType = mime_content_type($fileTmp);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($fileType, $allowed, true)) {
        throw new RuntimeException('Photo must be JPEG, PNG, GIF, or WEBP.');
    }

    $size = (int) ($_FILES['department_photo']['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('Photo must be less than 2MB.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$fileType];
    $uploadDir = __DIR__ . '/../../public/uploads/departments/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create department uploads directory.');
    }

    $safeName = 'dept_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;
    if (!move_uploaded_file($fileTmp, $targetPath)) {
        throw new RuntimeException('Failed to save department photo.');
    }

    return 'uploads/departments/' . $safeName;
}

function departmentsHasPhotoColumn(PDO $db): bool
{
    static $has = null;
    if ($has === null) {
        $has = (bool) $db->query("SHOW COLUMNS FROM departments LIKE 'department_photo_url'")->fetch(PDO::FETCH_ASSOC);
    }
    return $has;
}

try {
    $db = Database::connect();
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'add') {
        $name = trim((string) ($_POST['department_name'] ?? ''));
        $abbreviation = strtoupper(trim((string) ($_POST['department_abbreviation'] ?? '')));
        $type = normalizeDepartmentType($_POST['department_type'] ?? '');
        $status = normalizeDepartmentStatus($_POST['department_status'] ?? 'Active') ?? 'Active';
        $username = trim((string) ($_POST['department_username'] ?? ''));
        $password = (string) ($_POST['department_password'] ?? '');
        $confirmPassword = (string) ($_POST['department_confirm_password'] ?? '');

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Department name is required']);
            exit;
        }
        if (strlen($name) > 150) {
            echo json_encode(['success' => false, 'message' => 'Department name must be 150 characters or less']);
            exit;
        }
        if ($abbreviation === '') {
            echo json_encode(['success' => false, 'message' => 'Department abbreviation is required']);
            exit;
        }
        if (strlen($abbreviation) > 20) {
            echo json_encode(['success' => false, 'message' => 'Abbreviation must be 20 characters or less']);
            exit;
        }
        if (!preg_match('/^[A-Z0-9\-_]+$/', $abbreviation)) {
            echo json_encode(['success' => false, 'message' => 'Abbreviation may only contain letters, numbers, hyphens, and underscores']);
            exit;
        }
        if ($type === null) {
            echo json_encode(['success' => false, 'message' => 'Valid department type is required']);
            exit;
        }
        if ($username === '') {
            echo json_encode(['success' => false, 'message' => 'Username is required']);
            exit;
        }
        if (strlen($username) > 50) {
            echo json_encode(['success' => false, 'message' => 'Username must be 50 characters or less']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Username may only contain letters, numbers, dots, hyphens, and underscores']);
            exit;
        }
        $passwordError = validateDepartmentPassword($password);
        if ($passwordError !== null) {
            echo json_encode(['success' => false, 'message' => $passwordError]);
            exit;
        }
        if ($password !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
            exit;
        }

        $stmtUserDup = $db->prepare('SELECT department_id FROM departments WHERE department_username = ? LIMIT 1');
        $stmtUserDup->execute([$username]);
        if ($stmtUserDup->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'That username is already in use']);
            exit;
        }

        $stmtDup = $db->prepare('SELECT department_id FROM departments WHERE department_abbreviation = ? LIMIT 1');
        $stmtDup->execute([$abbreviation]);
        if ($stmtDup->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'That abbreviation is already in use']);
            exit;
        }

        $stmtName = $db->prepare('SELECT department_id FROM departments WHERE department_name = ? LIMIT 1');
        $stmtName->execute([$name]);
        if ($stmtName->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'A department with this name already exists']);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $photoUrl = handleDepartmentPhotoUpload();
        $hasPhotoColumn = departmentsHasPhotoColumn($db);

        if ($hasPhotoColumn) {
            $stmt = $db->prepare(
                'INSERT INTO departments (
                    department_name, department_abbreviation, department_type,
                    department_username, department_password_hash, department_photo_url, department_status
                 ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $abbreviation, $type, $username, $passwordHash, $photoUrl, $status]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO departments (
                    department_name, department_abbreviation, department_type,
                    department_username, department_password_hash, department_status
                 ) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $abbreviation, $type, $username, $passwordHash, $status]);
        }
        $departmentId = (int) $db->lastInsertId();

        logActivity(
            $_SESSION['user_id'],
            'Add Department',
            "Added department: $name ($abbreviation)"
        );

        echo json_encode([
            'success' => true,
            'message' => 'Department added successfully.',
            'department_id' => $departmentId,
        ]);
        exit;
    }

    if ($action === 'edit') {
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $name = trim((string) ($_POST['department_name'] ?? ''));
        $abbreviation = strtoupper(trim((string) ($_POST['department_abbreviation'] ?? ''));
        $type = normalizeDepartmentType($_POST['department_type'] ?? '');
        $status = normalizeDepartmentStatus($_POST['department_status'] ?? 'Active') ?? 'Active';
        $username = trim((string) ($_POST['department_username'] ?? ''));
        $password = (string) ($_POST['department_password'] ?? '');
        $confirmPassword = (string) ($_POST['department_confirm_password'] ?? '');

        if ($departmentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid department']);
            exit;
        }

        $stmtOld = $db->prepare('SELECT department_name, department_abbreviation FROM departments WHERE department_id = ? LIMIT 1');
        $stmtOld->execute([$departmentId]);
        $oldDept = $stmtOld->fetch(PDO::FETCH_ASSOC);
        if (!$oldDept) {
            echo json_encode(['success' => false, 'message' => 'Department not found']);
            exit;
        }

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Department name is required']);
            exit;
        }
        if ($abbreviation === '' || $type === null || $username === '') {
            echo json_encode(['success' => false, 'message' => 'Please complete all required fields']);
            exit;
        }
        if (!preg_match('/^[A-Z0-9\-_]+$/', $abbreviation)) {
            echo json_encode(['success' => false, 'message' => 'Abbreviation may only contain letters, numbers, hyphens, and underscores']);
            exit;
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            echo json_encode(['success' => false, 'message' => 'Username may only contain letters, numbers, dots, hyphens, and underscores']);
            exit;
        }

        if ($password !== '' || $confirmPassword !== '') {
            $passwordError = validateDepartmentPassword($password);
            if ($passwordError !== null) {
                echo json_encode(['success' => false, 'message' => $passwordError]);
                exit;
            }
            if ($password !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
                exit;
            }
        }

        $stmtUserDup = $db->prepare('SELECT department_id FROM departments WHERE department_username = ? AND department_id <> ? LIMIT 1');
        $stmtUserDup->execute([$username, $departmentId]);
        if ($stmtUserDup->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'That username is already in use']);
            exit;
        }

        $stmtDup = $db->prepare('SELECT department_id FROM departments WHERE department_abbreviation = ? AND department_id <> ? LIMIT 1');
        $stmtDup->execute([$abbreviation, $departmentId]);
        if ($stmtDup->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'That abbreviation is already in use']);
            exit;
        }

        $stmtName = $db->prepare('SELECT department_id FROM departments WHERE department_name = ? AND department_id <> ? LIMIT 1');
        $stmtName->execute([$name, $departmentId]);
        if ($stmtName->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'A department with this name already exists']);
            exit;
        }

        $photoUrl = handleDepartmentPhotoUpload();
        $hasPhotoColumn = departmentsHasPhotoColumn($db);
        $oldName = $oldDept['department_name'];

        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            if ($hasPhotoColumn && $photoUrl !== null) {
                $stmt = $db->prepare(
                    'UPDATE departments SET department_name = ?, department_abbreviation = ?, department_type = ?,
                     department_username = ?, department_password_hash = ?, department_photo_url = ?, department_status = ?
                     WHERE department_id = ?'
                );
                $stmt->execute([$name, $abbreviation, $type, $username, $passwordHash, $photoUrl, $status, $departmentId]);
            } elseif ($hasPhotoColumn) {
                $stmt = $db->prepare(
                    'UPDATE departments SET department_name = ?, department_abbreviation = ?, department_type = ?,
                     department_username = ?, department_password_hash = ?, department_status = ?
                     WHERE department_id = ?'
                );
                $stmt->execute([$name, $abbreviation, $type, $username, $passwordHash, $status, $departmentId]);
            } else {
                $stmt = $db->prepare(
                    'UPDATE departments SET department_name = ?, department_abbreviation = ?, department_type = ?,
                     department_username = ?, department_password_hash = ?, department_status = ?
                     WHERE department_id = ?'
                );
                $stmt->execute([$name, $abbreviation, $type, $username, $passwordHash, $status, $departmentId]);
            }
        } elseif ($hasPhotoColumn && $photoUrl !== null) {
            $stmt = $db->prepare(
                'UPDATE departments SET department_name = ?, department_abbreviation = ?, department_type = ?,
                 department_username = ?, department_photo_url = ?, department_status = ?
                 WHERE department_id = ?'
            );
            $stmt->execute([$name, $abbreviation, $type, $username, $photoUrl, $status, $departmentId]);
        } else {
            $stmt = $db->prepare(
                'UPDATE departments SET department_name = ?, department_abbreviation = ?, department_type = ?,
                 department_username = ?, department_status = ?
                 WHERE department_id = ?'
            );
            $stmt->execute([$name, $abbreviation, $type, $username, $status, $departmentId]);
        }

        logActivity(
            $_SESSION['user_id'],
            'Edit Department',
            "Updated department: {$oldName} → {$name} ({$abbreviation})"
        );

        echo json_encode(['success' => true, 'message' => 'Department updated successfully.']);
        exit;
    }

    if ($action === 'delete') {
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        if ($departmentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid department']);
            exit;
        }

        $stmtDept = $db->prepare('SELECT department_name FROM departments WHERE department_id = ? LIMIT 1');
        $stmtDept->execute([$departmentId]);
        $deptRow = $stmtDept->fetch(PDO::FETCH_ASSOC);
        if (!$deptRow) {
            echo json_encode(['success' => false, 'message' => 'Department not found']);
            exit;
        }

        $deptName = $deptRow['department_name'];

        $db->prepare('DELETE FROM departments WHERE department_id = ?')->execute([$departmentId]);

        logActivity(
            $_SESSION['user_id'],
            'Delete Department',
            "Deleted department: {$deptName}"
        );

        echo json_encode(['success' => true, 'message' => 'Department deleted successfully.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if (stripos($message, 'departments') !== false && stripos($message, "doesn't exist") !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'Departments table is not set up yet. Run the migration app/migrations/20260613_create_departments_table.sql',
        ]);
        exit;
    }
    if ((int) $e->getCode() === 23000) {
        if (stripos($message, 'department_username') !== false) {
            echo json_encode(['success' => false, 'message' => 'Username must be unique']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Department abbreviation must be unique']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Database error while saving department']);
}
