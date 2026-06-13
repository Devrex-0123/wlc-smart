<?php

function ensureUserNotificationsTable(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_notifications (
            notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            purchase_order_id INT UNSIGNED NULL DEFAULT NULL,
            requisition_id INT NULL DEFAULT NULL,
            type VARCHAR(50) NOT NULL,
            meta_json JSON NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (notification_id),
            KEY idx_user_notifications_user_read (user_id, is_read, created_at),
            KEY idx_user_notifications_po (purchase_order_id),
            CONSTRAINT fk_user_notifications_user
                FOREIGN KEY (user_id) REFERENCES user (user_id) ON DELETE CASCADE,
            CONSTRAINT fk_user_notifications_po
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function cwirmsEnsurePoTaxStatusColumns(PDO $db): void
{
    $statusCheck = $db->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'purchase_orders'
           AND COLUMN_NAME = 'status'
         LIMIT 1"
    )->fetchColumn();
    if ($statusCheck && !str_contains((string) $statusCheck, 'ready_for_release')) {
        $db->exec(
            "ALTER TABLE purchase_orders
             MODIFY COLUMN status ENUM('pending','approved','rejected','ready_for_release') NOT NULL DEFAULT 'pending'"
        );
    }

    $columns = [
        'tax_status' => "ADD COLUMN tax_status ENUM('draft','finalized') NOT NULL DEFAULT 'draft' AFTER tax_computed",
        'tax_finalized_at' => 'ADD COLUMN tax_finalized_at DATETIME NULL DEFAULT NULL AFTER tax_status',
    ];
    foreach ($columns as $column => $ddl) {
        $colCheck = $db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'purchase_orders'
               AND COLUMN_NAME = ?"
        );
        $colCheck->execute([$column]);
        if (((int) $colCheck->fetchColumn()) === 0) {
            $db->exec("ALTER TABLE purchase_orders {$ddl}");
        }
    }
}

/**
 * @param array<string, mixed> $meta
 */
function cwirmsInsertUserNotification(
    PDO $db,
    int $userId,
    string $type,
    ?int $purchaseOrderId = null,
    ?int $requisitionId = null,
    array $meta = []
): int {
    ensureUserNotificationsTable($db);
    if ($userId <= 0) {
        return 0;
    }

    $stmt = $db->prepare(
        'INSERT INTO user_notifications (user_id, purchase_order_id, requisition_id, type, meta_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $purchaseOrderId > 0 ? $purchaseOrderId : null,
        $requisitionId > 0 ? $requisitionId : null,
        $type,
        $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    ]);

    return (int) $db->lastInsertId();
}

function cwirmsFormatRelativeTimestamp(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }

    $now = time();
    $diff = $now - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);

        return $mins === 1 ? '1 minute ago' : "{$mins} minutes ago";
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);

        return $hours === 1 ? '1 hour ago' : "{$hours} hours ago";
    }
    if ($diff < 172800) {
        return 'Yesterday, ' . date('g:i A', $ts);
    }

    return date('M j, Y g:i A', $ts);
}

/**
 * @param array<string, mixed> $row
 * @return array{type: string, message_html: string, secondary: string, is_read: bool, notification_id: int, created_at: string}
 */
function cwirmsFormatUserNotificationRow(array $row): array
{
    $type = (string) ($row['type'] ?? '');
    $meta = [];
    if (!empty($row['meta_json'])) {
        $decoded = json_decode((string) $row['meta_json'], true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $messageHtml = '';
    $secondary = '';

    if ($type === 'payment_ready') {
        $requesterName = htmlspecialchars((string) ($meta['requester_full_name'] ?? 'Requester'), ENT_QUOTES, 'UTF-8');
        $poNumber = htmlspecialchars((string) ($meta['po_number'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $messageHtml = "Dear <strong>{$requesterName}</strong>, your purchase order <strong>{$poNumber}</strong> has been processed by the comptroller. The payment is now ready for release.";

        $netPayable = isset($meta['net_payable']) ? round((float) $meta['net_payable'], 2) : 0.0;
        $netLabel = 'PHP ' . number_format($netPayable, 2);
        $relative = cwirmsFormatRelativeTimestamp((string) ($row['created_at'] ?? ''));
        $secondary = trim($netLabel . ($relative !== '' ? ' · ' . $relative : ''));
    } else {
        $messageHtml = htmlspecialchars((string) ($meta['message'] ?? 'You have a new notification.'), ENT_QUOTES, 'UTF-8');
        $secondary = cwirmsFormatRelativeTimestamp((string) ($row['created_at'] ?? ''));
    }

    return [
        'notification_id' => (int) ($row['notification_id'] ?? 0),
        'type' => $type,
        'message_html' => $messageHtml,
        'secondary' => $secondary,
        'is_read' => (int) ($row['is_read'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'purchase_order_id' => isset($row['purchase_order_id']) ? (int) $row['purchase_order_id'] : null,
    ];
}
