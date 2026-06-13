<?php

require_once __DIR__ . '/../classes/db.php';

class Department
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function hasConsentedByUsername(string $username): bool
    {
        $stmt = $this->db->prepare(
            'SELECT had_consented FROM departments WHERE LOWER(department_username) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return (int) ($row['had_consented'] ?? 0) === 1;
    }

    public function hasCurrentConsentByUsername(string $username, string $version): bool
    {
        $stmt = $this->db->prepare(
            'SELECT had_consented, consent_version FROM departments WHERE LOWER(department_username) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([trim($username)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return (int) ($row['had_consented'] ?? 0) === 1
            && (string) ($row['consent_version'] ?? '') === (string) $version;
    }

    public function updateConsent(int $departmentId, string $version): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE departments
             SET had_consented = 1, consent_version = ?, consented_at = NOW(), department_updated_at = NOW()
             WHERE department_id = ?'
        );

        return $stmt->execute([(string) $version, $departmentId]);
    }
}
