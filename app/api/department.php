<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../classes/db.php';

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
    $deptType = trim((string) ($_GET['type'] ?? $_POST['type'] ?? ''));
    $allowedTypes = ['academic', 'administrative', 'executive'];
    $photoSelect = departmentsHasPhotoColumn($db) ? ', department_photo_url' : '';

    $sql = "
        SELECT
            department_id,
            department_name,
            department_abbreviation,
            department_type,
            department_username,
            department_status,
            department_created_at,
            department_updated_at
            {$photoSelect}
        FROM departments
    ";

    if ($deptType !== '' && in_array($deptType, $allowedTypes, true)) {
        $sql .= ' WHERE LOWER(department_type) = ? ORDER BY department_name ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute([$deptType]);
    } else {
        $stmt = $db->query($sql . ' ORDER BY department_name ASC');
    }

    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'departments' => $departments,
    ]);
} catch (PDOException $e) {
    if (stripos($e->getMessage(), 'departments') !== false && stripos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode(['success' => true, 'departments' => []]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
