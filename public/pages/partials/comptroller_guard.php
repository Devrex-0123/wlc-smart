<?php
/**
 * Require logged-in user with role "comptroller". Expects $user loaded from DB.
 */
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
if ($roleLc !== 'comptroller') {
    header('Location: ../../index.php');
    exit;
}
