<?php
/**
 * One-time: add Canvasser to user.role enum and backfill known canvasser accounts.
 * Run: php app/migrations/run_20260604_canvasser_role.php
 */
require_once __DIR__ . '/../classes/db.php';

$db = Database::connect();

$db->exec("
    ALTER TABLE `user`
      MODIFY `role` enum(
        'Inventory Manager',
        'Dean',
        'Laboratory Manager',
        'Comptroller',
        'President',
        'Employee',
        'User',
        'GSD officer',
        'Canvasser'
      ) NOT NULL DEFAULT 'User'
");

$updated = $db->exec("
    UPDATE `user` u
    SET u.role = 'Canvasser', u.updated_at = NOW()
    WHERE u.deleted_at IS NULL
      AND u.role IN ('Employee', 'User', 'Laboratory Manager')
      AND (
        EXISTS (SELECT 1 FROM canvasser_action_history h WHERE h.user_id = u.user_id)
        OR EXISTS (
            SELECT 1 FROM canvass_verification_approval c
            WHERE c.canvas_assignee_user_id = u.user_id
        )
      )
");

echo "Canvasser role migration OK. Backfilled rows: " . (int) $updated . PHP_EOL;
