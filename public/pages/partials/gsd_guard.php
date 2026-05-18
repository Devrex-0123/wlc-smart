<?php
/**
 * GSD workspace — require logged-in user with role "GSD officer".
 * Expects $user loaded from DB (associative array with role).
 */
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
if ($roleLc !== 'gsd officer') {
    header('Location: ../../index.php');
    exit;
}
