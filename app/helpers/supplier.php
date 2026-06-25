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

    $vatCheck = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'suppliers'
           AND COLUMN_NAME = 'vat_registered'"
    );
    $vatCheck->execute();
    if (((int) $vatCheck->fetchColumn()) === 0) {
        $db->exec(
            'ALTER TABLE suppliers
             ADD COLUMN vat_registered TINYINT(1) NOT NULL DEFAULT 0 AFTER tin'
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

function cwirmsNormalizeSupplierPhone(mixed $raw): string
{
    return preg_replace('/\D+/', '', trim((string) $raw));
}

/**
 * @param array<string, mixed> $data
 * @return string|null Error message, or null when valid.
 */
function cwirmsValidateSupplierFormData(array $data): ?string
{
    $supplierName = trim((string) ($data['supplier_name'] ?? ''));
    $contactPerson = trim((string) ($data['contact_person'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = cwirmsNormalizeSupplierPhone($data['phone_number'] ?? '');
    $address = trim((string) ($data['address'] ?? ''));
    $city = trim((string) ($data['city'] ?? ''));
    $country = trim((string) ($data['country'] ?? ''));
    $status = trim((string) ($data['status'] ?? ''));

    if ($supplierName === '') {
        return 'Supplier name is required';
    }
    if ($status === '' || !in_array($status, ['Active', 'Inactive'], true)) {
        return 'Status is required';
    }
    if ($contactPerson === '') {
        return 'Contact person is required';
    }
    if ($email === '') {
        return 'Email is required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address (e.g. supplier@example.com)';
    }
    if ($phone === '') {
        return 'Phone number is required';
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        return 'Phone number must contain exactly 11 digits (e.g. 09123456789)';
    }
    if ($address === '') {
        return 'Street address is required';
    }
    if ($city === '') {
        return 'City is required';
    }
    if ($country === '') {
        return 'Country is required';
    }

    return null;
}
