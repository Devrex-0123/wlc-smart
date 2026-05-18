<?php
/**
 * One-off maintenance: ensure facilities.facility_id exists (legacy installs).
 *
 * CLI (project root): php app/api/add_facility_id.php
 * Legacy root wrapper: php add_facility_id.php
 * Browser (dev only): …/add_facility_id.php at project root delegates here
 */
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $check = $db->query("SHOW COLUMNS FROM facilities LIKE 'facility_id'")->fetch();

    if (!$check) {
        $db->exec('ALTER TABLE facilities ADD COLUMN facility_id INT AUTO_INCREMENT UNIQUE AFTER office_id');
        echo "✓ facility_id column added successfully\n";
    } else {
        echo "✓ facility_id column already exists\n";
    }

    $columns = $db->query('DESCRIBE facilities')->fetchAll(PDO::FETCH_ASSOC);
    echo "\nFacilities table structure:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
