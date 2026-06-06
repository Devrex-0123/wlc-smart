<?php

declare(strict_types=1);

/**
 * Supplier master data helpers (suppliers table).
 * Optional fields such as TIN are nullable for online shops without a BIR TIN.
 */

function ensureSupplierTinColumn(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $colCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'suppliers'
           AND COLUMN_NAME = 'tin'"
    );
    $colCheck->execute();
    if (((int) $colCheck->fetchColumn()) === 0) {
        $db->exec(
            'ALTER TABLE suppliers
             ADD COLUMN tin VARCHAR(20) NULL DEFAULT NULL AFTER postal_code'
        );
    }
}

function cwirmsNormalizeSupplierTin(mixed $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $digits = preg_replace('/\D+/', '', trim((string) $raw));
    if ($digits === '') {
        return null;
    }
    $digits = substr($digits, 0, 12);
    $parts = str_split($digits, 3);

    return implode('-', $parts);
}

function cwirmsFormatSupplierTinDisplay(?string $tin): string
{
    $value = $tin !== null ? trim($tin) : '';

    return $value !== '' ? $value : 'N/A';
}
