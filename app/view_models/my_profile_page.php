<?php

declare(strict_types=1);

/**
 * View data for public/pages/my_profile.php — DB reads, role-based nav, display fields.
 *
 * @return array{
 *     sidebarUser: array<string, mixed>,
 *     profileFallback: array<string, mixed>,
 *     email: string,
 *     displayName: string,
 *     initials: string,
 *     initialPhotoUrl: string,
 *     profileNavItems: list<array{href: string, icon: string, label: string, active?: bool}>
 * }
 */
function my_profile_page_view_model(PDO $db, int $userId): array
{
    $sidebarUser = my_profile_fetch_sidebar_user($db, $userId);
    $profileFallback = my_profile_fetch_profile_row($db, $userId);
    $roleLc = strtolower(trim((string)($sidebarUser['role'] ?? '')));

    $email = (string)($sidebarUser['Email'] ?? '');
    $displayName = (string)($sidebarUser['full_name'] ?? '');
    if ($displayName === '') {
        $displayName = $email !== '' ? (explode('@', $email)[0] ?? 'User') : 'User';
    }

    return [
        'sidebarUser' => $sidebarUser,
        'profileFallback' => $profileFallback,
        'email' => $email,
        'displayName' => $displayName,
        'initials' => strtoupper(substr($displayName, 0, 1)),
        'initialPhotoUrl' => (string)($sidebarUser['photo_url'] ?? ''),
        'profileNavItems' => my_profile_nav_items_for_role($roleLc),
    ];
}

/** @return array<string, mixed> */
function my_profile_fetch_sidebar_user(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT Email, role, photo_url, full_name FROM user WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

/** @return array<string, mixed> */
function my_profile_fetch_profile_row(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT u.full_name, u.contact_number, u.password_updated_at, u.has_consented, u.consent_version, '
        . 'd.`office_name` AS office_name '
        . 'FROM user u '
        . 'LEFT JOIN offices d ON d.office_id = u.office_id '
        . 'WHERE u.user_id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

/**
 * @return list<array{href: string, icon: string, label: string, active?: bool}>
 */
function my_profile_nav_items_for_role(string $roleLc): array
{
    $myProfile = ['href' => 'my_profile.php', 'icon' => 'fa-user', 'label' => 'My Profile', 'active' => true];

    $byExactRole = [
        'dean' => [
            ['href' => 'dean_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'dean_requisition_management.php', 'icon' => 'fa-file-signature', 'label' => 'Requisition Management'],
            ['href' => 'dean_requisition_status.php', 'icon' => 'fa-bars-progress', 'label' => 'Status'],
            $myProfile,
            ['href' => 'dean_account_management.php', 'icon' => 'fa-users-cog', 'label' => 'Account Management'],
        ],
        'canvasser' => [
            ['href' => 'canvasser_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'canvasser_request.php', 'icon' => 'fa-file-signature', 'label' => 'Request'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ],
        'gsd officer' => [
            ['href' => 'gsd_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'gsd_request.php', 'icon' => 'fa-file-signature', 'label' => 'Request'],
            ['href' => 'gsd_account_management.php', 'icon' => 'fa-users-cog', 'label' => 'Account Management'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ],
        'comptroller' => [
            ['href' => 'comptroller_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'comptroller_requests.php', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Requests'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ],
    ];

    if (isset($byExactRole[$roleLc])) {
        return $byExactRole[$roleLc];
    }

    if ($roleLc === 'user') {
        return [
            ['href' => 'dean_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ];
    }

    if (in_array($roleLc, ['employee', 'laboratory manager'], true)) {
        return [
            ['href' => 'employee_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ];
    }

    if (in_array($roleLc, ['president', 'president verifier', 'verifier president', 'president_verifier'], true)) {
        return [
            ['href' => 'president_dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
            ['href' => 'president_request.php', 'icon' => 'fa-file-signature', 'label' => 'Request'],
            ['href' => 'president_account_management.php', 'icon' => 'fa-users-cog', 'label' => 'Account Management'],
            ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
            $myProfile,
        ];
    }

    return [
        ['href' => 'dashboard.php', 'icon' => 'fa-home', 'label' => 'Dashboard'],
        ['href' => 'requisition_management.php', 'icon' => 'fa-file-signature', 'label' => 'Requisition Management'],
        ['href' => 'requisition_status.php', 'icon' => 'fa-bars-progress', 'label' => 'Status'],
        ['href' => 'audit_trail.php', 'icon' => 'fa-shield-alt', 'label' => 'Audit Trail'],
        $myProfile,
        ['href' => 'account_management.php', 'icon' => 'fa-users-cog', 'label' => 'Account Management'],
        ['href' => 'facility_management.php', 'icon' => 'fa-building', 'label' => 'Facility Management'],
        ['href' => 'item_management.php', 'icon' => 'fa-box', 'label' => 'Item Management'],
        ['href' => 'inventory_management.php', 'icon' => 'fa-cubes', 'label' => 'Inventory Management'],
        ['href' => 'supplier_management.php', 'icon' => 'fa-truck', 'label' => 'Supplier Management'],
    ];
}
