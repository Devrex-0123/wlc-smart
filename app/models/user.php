<?php
require_once __DIR__ . '/../classes/db.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::connect(); 
    }

    public function findByEmail($email) {
        $sql = "SELECT * FROM `user` WHERE LOWER(Email) = LOWER(?) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($userId) {
        $sql = "SELECT * FROM `user` WHERE user_id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** @return string|null */
    public function getOfficeNameForUserId($userId) {
        $sql = 'SELECT d.`office_name` AS office_name FROM `user` u
                LEFT JOIN offices d ON d.office_id = u.office_id
                WHERE u.user_id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int) $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $name = $row['office_name'] ?? null;

        return $name !== null && $name !== '' ? (string) $name : null;
    }

    public function verifyPassword($inputPassword, $storedPassword) {
        if (empty($storedPassword)) return false;
        if (password_verify($inputPassword, $storedPassword)) {
            return true;
        }

        // Legacy compatibility (plain text / sha256). Successful legacy login should be rehashed.
        return $inputPassword === $storedPassword || hash('sha256', $inputPassword) === $storedPassword;
    }

    public function needsPasswordRehash($storedPassword) {
        if (empty($storedPassword)) return false;
        return password_get_info($storedPassword)['algo'] === null;
    }

    public function updatePasswordHashByUserId($userId, $plainPassword) {
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $sql = "UPDATE `user` SET password = ?, password_updated_at = NOW(), updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newHash, (int)$userId]);
    }

    // Update attempts only
    public function updateAttempts($email, $attempts) {
        $sql = "UPDATE `user` SET failed_attempts = ? WHERE LOWER(Email) = LOWER(?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$attempts, $email]);
    }

    // Set attempts and lock time
    public function setAttemptsAndLock($email, $attempts, $lockTimestamp) {
        $sql = "UPDATE `user` SET failed_attempts = ?, lock_time = ?, account_status = 'locked', updated_at = NOW() WHERE LOWER(Email) = LOWER(?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$attempts, $lockTimestamp, $email]);
    }

    // Reset attempts and lock_time
    public function resetAttempts($email) {
        $sql = "UPDATE `user` SET failed_attempts = 0, lock_time = NULL, updated_at = NOW() WHERE LOWER(Email) = LOWER(?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$email]);
    }

    // Optional: Reset all attempts
    public function resetAllAttempts() {
        $sql = "UPDATE `user` SET failed_attempts = 0, lock_time = NULL, updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }

    public function markLoginSuccess($userId) {
        $sql = "UPDATE `user` SET failed_attempts = 0, lock_time = NULL, last_login = NOW(), updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(int)$userId]);
    }

    public function unlockAccountIfEligible($userId) {
        $sql = "UPDATE `user` SET account_status = 'active', failed_attempts = 0, lock_time = NULL, updated_at = NOW() WHERE user_id = ? AND account_status = 'locked'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(int)$userId]);
    }

    public function updateConsent($userId, $version) {
        $sql = "UPDATE `user` SET has_consented = 1, consent_version = ?, consent_date = NOW(), updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(string)$version, (int)$userId]);
    }

    public function hasCurrentConsentByEmail($email, $version) {
        $sql = "SELECT has_consented, consent_version FROM `user` WHERE LOWER(Email) = LOWER(?) AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(string)$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        return (int)($row['has_consented'] ?? 0) === 1 && (string)($row['consent_version'] ?? '') === (string)$version;
    }

    public function updateRememberTokenByUserId($userId, $token) {
        $sql = "UPDATE `user` SET remember_token = ?, updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(string)$token, (int)$userId]);
    }

    public function clearRememberTokenByUserId($userId) {
        $sql = "UPDATE `user` SET remember_token = NULL, updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([(int)$userId]);
    }

    public function updateProfile($userId, $fullName, $contactNumber) {
        $sql = "UPDATE `user` SET full_name = ?, contact_number = ?, updated_at = NOW() WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $fullName !== null ? trim((string)$fullName) : null,
            $contactNumber !== null ? trim((string)$contactNumber) : null,
            (int)$userId
        ]);
    }
}
