<?php
/**
 * President verifier workspace — require president role (matches login redirect / user enum).
 * Expects $user loaded from DB.
 */
$roleLc = strtolower(trim((string) ($user['role'] ?? '')));
$presidentRoles = ['president', 'president verifier', 'verifier president', 'president_verifier'];
if (!in_array($roleLc, $presidentRoles, true)) {
    header('Location: ../../index.php');
    exit;
}
