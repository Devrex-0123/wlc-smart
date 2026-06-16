<?php

function cwirms_is_department_login(): bool
{
    return isset($_SESSION['login_type'])
        && $_SESSION['login_type'] === 'department'
        && !empty($_SESSION['department_id']);
}

function cwirms_resolve_office_for_department(PDO $db, array $department): ?array
{
    $abbrev = strtoupper(trim((string) ($department['department_abbreviation'] ?? '')));
    if ($abbrev !== '') {
        $stmt = $db->prepare(
            'SELECT office_id, office_name
             FROM offices
             WHERE UPPER(TRIM(office_name)) = ?
             LIMIT 1'
        );
        $stmt->execute([$abbrev]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($office) {
            return $office;
        }
    }

    $name = trim((string) ($department['department_name'] ?? ''));
    if ($name === '') {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT office_id, office_name
         FROM offices
         WHERE UPPER(TRIM(office_name)) = UPPER(TRIM(?))
         LIMIT 1'
    );
    $stmt->execute([$name]);
    $office = $stmt->fetch(PDO::FETCH_ASSOC);

    return $office ?: null;
}

function cwirms_find_dean_user_id_for_office(PDO $db, int $officeId): ?int
{
    if ($officeId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT user_id
         FROM user
         WHERE office_id = ?
           AND LOWER(TRIM(role)) = 'dean'
           AND deleted_at IS NULL
         ORDER BY user_id ASC
         LIMIT 1"
    );
    $stmt->execute([$officeId]);
    $userId = $stmt->fetchColumn();

    return $userId !== false ? (int) $userId : null;
}

function cwirms_build_department_sidebar_user(array $department, int $officeId): array
{
    return [
        'full_name' => $department['department_name'] ?? 'Department',
        'department_abbreviation' => $department['department_abbreviation'] ?? '',
        'Email' => $department['department_username'] ?? '',
        'role' => 'Department',
        'photo_url' => $department['department_photo_url'] ?? null,
        'office_id' => $officeId,
    ];
}

function cwirms_bootstrap_dean_office_context(PDO $db): array
{
    if (cwirms_is_department_login()) {
        $departmentId = (int) $_SESSION['department_id'];
        $stmt = $db->prepare('SELECT * FROM departments WHERE department_id = ? LIMIT 1');
        $stmt->execute([$departmentId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            return [
                'authorized' => false,
                'reason' => 'department_missing',
                'message' => 'Department account not found.',
            ];
        }

        $office = cwirms_resolve_office_for_department($db, $department);
        if (!$office) {
            return [
                'authorized' => false,
                'reason' => 'office_missing',
                'message' => 'No office is linked to this department.',
            ];
        }

        $officeId = (int) $office['office_id'];
        $user = cwirms_build_department_sidebar_user($department, $officeId);

        return [
            'authorized' => true,
            'is_department_login' => true,
            'department_id' => $departmentId,
            'department' => $department,
            'office_id' => $officeId,
            'office_name' => (string) ($office['office_name'] ?? 'Unknown Office'),
            'acting_user_id' => cwirms_find_dean_user_id_for_office($db, $officeId),
            'user' => $user,
            'currentUser' => $user,
        ];
    }

    if (!isset($_SESSION['user_id'])) {
        return [
            'authorized' => false,
            'reason' => 'not_logged_in',
            'message' => 'Unauthorized',
        ];
    }

    $stmt = $db->prepare('SELECT * FROM user WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $roleNorm = strtolower(trim((string) ($currentUser['role'] ?? '')));
    if (!$currentUser || !in_array($roleNorm, ['dean', 'user'], true)) {
        return [
            'authorized' => false,
            'reason' => 'not_dean',
            'message' => 'Access denied.',
        ];
    }

    $officeId = (int) ($currentUser['office_id'] ?? 0);

    // 'user' role accounts (department accounts) may have no office assigned — still authorized.
    if ($officeId <= 0 && $roleNorm !== 'user') {
        return [
            'authorized' => false,
            'reason' => 'office_missing',
            'message' => 'Dean is not assigned to any office.',
        ];
    }

    $officeName = '';
    if ($officeId > 0) {
        $stmt = $db->prepare('SELECT office_name FROM offices WHERE office_id = ? LIMIT 1');
        $stmt->execute([$officeId]);
        $office = $stmt->fetch(PDO::FETCH_ASSOC);
        $officeName = (string) ($office['office_name'] ?? '');
    }

    return [
        'authorized' => true,
        'is_department_login' => false,
        'office_id' => $officeId,
        'office_name' => $officeName,
        'acting_user_id' => (int) $_SESSION['user_id'],
        'user' => $currentUser,
        'currentUser' => $currentUser,
    ];
}

/** Roles that may open shared requisition / canvass form pages from staff progress views. */
function cwirms_requisition_workspace_allowed_roles(): array
{
    return [
        'inventory manager',
        'inventory_manager',
        'comptroller',
        'gsd officer',
        'president',
        'president verifier',
        'verifier president',
        'president_verifier',
        'employee',
        'user',
        'laboratory manager',
        'canvasser',
    ];
}

function cwirms_redirect_staff_to_dashboard(string $roleLc): void
{
    $roleLc = strtolower(trim($roleLc));
    $dashboardByRole = [
        'comptroller' => 'comptroller_dashboard.php',
        'president' => 'president_dashboard.php',
        'president verifier' => 'president_dashboard.php',
        'verifier president' => 'president_dashboard.php',
        'president_verifier' => 'president_dashboard.php',
        'gsd officer' => 'gsd_dashboard.php',
        'employee' => 'employee_dashboard.php',
        'user' => 'dean_dashboard.php',
        'laboratory manager' => 'canvasser_dashboard.php',
        'canvasser' => 'canvasser_dashboard.php',
    ];

    header('Location: ' . ($dashboardByRole[$roleLc] ?? 'dashboard.php'));
    exit;
}

/**
 * Dean/department context first; otherwise allow staff roles used by requisition progress "View" links.
 */
function cwirms_bootstrap_requisition_workspace_page_context(PDO $db): array
{
    $deanCtx = cwirms_bootstrap_dean_office_context($db);
    if (($deanCtx['authorized'] ?? false) === true) {
        return $deanCtx;
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../../index.php');
        exit;
    }

    $stmt = $db->prepare('SELECT * FROM user WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        header('Location: ../../index.php');
        exit;
    }

    $roleLc = strtolower(trim((string) ($currentUser['role'] ?? '')));
    if (!in_array($roleLc, cwirms_requisition_workspace_allowed_roles(), true)) {
        cwirms_redirect_staff_to_dashboard($roleLc);
    }

    $officeId = (int) ($currentUser['office_id'] ?? 0);
    $officeName = 'Unknown Office';
    if ($officeId > 0) {
        $oStmt = $db->prepare('SELECT office_name FROM offices WHERE office_id = ? LIMIT 1');
        $oStmt->execute([$officeId]);
        $office = $oStmt->fetch(PDO::FETCH_ASSOC);
        if ($office) {
            $officeName = (string) ($office['office_name'] ?? $officeName);
        }
    }

    return [
        'authorized' => true,
        'is_department_login' => false,
        'office_id' => $officeId,
        'office_name' => $officeName,
        'acting_user_id' => (int) $_SESSION['user_id'],
        'user' => $currentUser,
        'currentUser' => $currentUser,
    ];
}

function cwirms_bootstrap_dean_page_context(PDO $db): array
{
    $ctx = cwirms_bootstrap_dean_office_context($db);

    if (($ctx['authorized'] ?? false) !== true) {
        $reason = (string) ($ctx['reason'] ?? '');

        if ($reason === 'not_dean') {
            header('Location: dashboard.php');
            exit;
        }

        if ($reason === 'office_missing' && !cwirms_is_department_login()) {
            echo htmlspecialchars((string) ($ctx['message'] ?? 'Office not assigned.'));
            exit;
        }

        if ($reason === 'office_missing') {
            echo htmlspecialchars((string) ($ctx['message'] ?? 'Office not linked.'));
            exit;
        }

        header('Location: ../../index.php');
        exit;
    }

    return $ctx;
}

function cwirms_bootstrap_dean_api_context(PDO $db): array
{
    $ctx = cwirms_bootstrap_dean_office_context($db);

    if (($ctx['authorized'] ?? false) !== true) {
        return $ctx;
    }

    return $ctx;
}

function cwirms_dean_api_require_context(PDO $db): array
{
    $ctx = cwirms_bootstrap_dean_api_context($db);

    if (($ctx['authorized'] ?? false) !== true) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => (string) ($ctx['message'] ?? 'Unauthorized'),
        ]);
        exit;
    }

    return $ctx;
}

function cwirms_dean_requisition_owner_id(array $ctx): int
{
    return (int) ($ctx['acting_user_id'] ?? 0);
}

function cwirms_dean_requisition_scope_sql(array $ctx): array
{
    if (!empty($ctx['is_department_login'])) {
        return ['sql' => 'r.office_id = ?', 'param' => (int) $ctx['office_id']];
    }

    return ['sql' => 'r.user_id = ?', 'param' => cwirms_dean_requisition_owner_id($ctx)];
}

function cwirms_dean_requisition_item_scope_sql(array $ctx): array
{
    if (!empty($ctx['is_department_login'])) {
        return ['sql' => 'office_id = ?', 'param' => (int) $ctx['office_id']];
    }

    return ['sql' => 'user_id = ?', 'param' => cwirms_dean_requisition_owner_id($ctx)];
}

function cwirms_dean_fetch_requisition_item(PDO $db, array $ctx, int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $scope = cwirms_dean_requisition_item_scope_sql($ctx);
    $stmt = $db->prepare("SELECT * FROM requisition_item WHERE request_id = ? AND {$scope['sql']} LIMIT 1");
    $stmt->execute([$requestId, $scope['param']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function cwirms_dean_require_requisition_owner_id(array $ctx): int
{
    $ownerId = cwirms_dean_requisition_owner_id($ctx);
    if ($ownerId <= 0) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'No dean account is linked to this department office.',
        ]);
        exit;
    }

    return $ownerId;
}

function cwirms_dean_api_actor_user_id(array $ctx): int
{
    $actorId = cwirms_dean_requisition_owner_id($ctx);

    return $actorId > 0 ? $actorId : 0;
}
