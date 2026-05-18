<?php

class AuditLogger
{
    public static function clientIp(): string
    {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $value = (string)$_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $parts = explode(',', $value);
                    return trim((string)($parts[0] ?? ''));
                }
                return $value;
            }
        }
        return '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return (string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
    }

    public static function logCriticalAction(
        PDO $db,
        int $performedBy,
        int $affectedUserId,
        string $actionType,
        string $entityType,
        int $entityId,
        string $description
    ): void {
        try {
            $stmt = $db->prepare("
                INSERT INTO critical_action_logs
                (performed_by, affected_user_id, action_type, entity_type, entity_id, description, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $performedBy,
                $affectedUserId,
                $actionType,
                $entityType,
                $entityId,
                $description,
                self::clientIp(),
                self::userAgent(),
            ]);
        } catch (Throwable $e) {
            // Keep app flow working even if audit table is missing.
        }
    }
}
