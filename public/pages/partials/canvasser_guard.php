<?php
/**
 * Canvasser workspace — employee-type roles institution-wide (not limited to Inventory Office).
 * GSD assigns a specific user via request_approval.canvas_assignee_user_id; those users see
 * assignments on the Request page. Expects $user with role.
 */
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$allowedRoles = ['employee', 'laboratory manager', 'canvasser'];
if (!in_array($roleLc, $allowedRoles, true)) {
    header('Location: ../../index.php');
    exit;
}
