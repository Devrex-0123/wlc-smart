<?php
require_once __DIR__ . '/../models/user.php';

class AuthController {
    private $maxAttempts = 3;
    private $lockSeconds = 30; // Lock duration in seconds
    private $currentConsentVersion = "v1.0";

    public function login($email, $password, $consentAccepted = false) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $email = strtolower(trim($email));
        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Account does not exist
        if (!$user) {
            return [
                "success" => false,
                "message" => "Account does not exist",
                "account_missing" => true
            ];
        }

        // Prevent access to soft-deleted accounts
        if (!empty($user['deleted_at'])) {
            return [
                "success" => false,
                "message" => "Account is unavailable.",
                "account_missing" => true
            ];
        }

        // Admin lifecycle controls
        $accountStatus = strtolower(trim((string)($user['account_status'] ?? 'active')));
        if ($accountStatus === 'disabled') {
            return [
                "success" => false,
                "disabled" => true,
                "message" => "Your account has been disabled. Please contact your administrator."
            ];
        }
        if ($accountStatus === 'locked') {
            // Continue through lock timer logic below.
        }

        $failedAttempts = (int)($user['failed_attempts'] ?? 0);
        $lockTimeRaw = $user['lock_time'] ?? null;
        $lockTime = $lockTimeRaw ? strtotime((string)$lockTimeRaw) : 0;

        if ($accountStatus === 'locked' && $lockTime <= 0) {
            return [
                "blocked" => true,
                "message" => "Your account is currently locked. Please contact your administrator.",
                "remaining" => 0,
                "remaining_seconds" => 0
            ];
        }

        // Check if account is currently locked
        if ($failedAttempts >= $this->maxAttempts) {
            $elapsed = time() - $lockTime;
            if ($lockTime > 0 && $elapsed < $this->lockSeconds) {
                $remaining = $this->lockSeconds - $elapsed;
                return [
                    "blocked" => true,
                    "message" => "Account locked. Try again later.",
                    "attempts" => $failedAttempts,
                    "remaining" => 0,
                    "remaining_seconds" => $remaining
                ];
            } else {
                // Lock time has expired → reset attempts
                $userModel->resetAttempts($email);
                $userModel->unlockAccountIfEligible((int)$user['user_id']);
                $failedAttempts = 0;
            }
        }

        // Verify password
        // Note: Your verifyPassword method accepts 3 params in your current code — adjust if needed
        $passwordVerified = $userModel->verifyPassword($password, $user['password']);

        if ($passwordVerified) {
            // Upgrade legacy passwords to bcrypt after successful authentication
            if ($userModel->needsPasswordRehash((string)$user['password'])) {
                $userModel->updatePasswordHashByUserId((int)$user['user_id'], $password);
            }

            // Reset failed attempts on successful login
            $userModel->markLoginSuccess((int)$user['user_id']);

            // Set session variables
            $_SESSION['user_id']     = $user['user_id'];
            $_SESSION['user_email']  = $user['Email'];
            $_SESSION['user_name']   = $user['full_name'] ?? $user['FullName'] ?? $user['FirstName'] ?? 'User';
            $_SESSION['user_role']   = strtolower(trim($user['role'] ?? 'employee')); // Critical: set role
            $_SESSION['consent_version_current'] = $this->currentConsentVersion;

            $roleNorm = strtolower(trim((string) ($user['role'] ?? 'employee')));
            $employeeLike = in_array($roleNorm, ['employee', 'user', 'laboratory manager', 'canvasser'], true);
            $canvasserWorkspace = $employeeLike;

            $hasConsented = (int)($user['has_consented'] ?? 0) === 1;
            $consentVersion = (string)($user['consent_version'] ?? '');
            $consentRequired = !$hasConsented || $consentVersion !== $this->currentConsentVersion;
            $_SESSION['consent_required'] = $consentRequired;

            // Single-flow consent: capture consent during login when checkbox is checked.
            if ($consentRequired && $consentAccepted) {
                $userModel->updateConsent((int)$user['user_id'], $this->currentConsentVersion);
                $consentRequired = false;
                $_SESSION['consent_required'] = false;
            }

            // Generate session-persistence token placeholder for future remember-me feature.
            $rememberToken = bin2hex(random_bytes(32));
            $userModel->updateRememberTokenByUserId((int)$user['user_id'], hash('sha256', $rememberToken));
            $_SESSION['remember_token'] = $rememberToken;

            // Return success + role for frontend redirection
            return [
                "success" => true,
                "message" => "Login successful",
                "role"    => $_SESSION['user_role'], // This will be used by JavaScript
                "canvasser_workspace" => $canvasserWorkspace,
                "consent_version" => $this->currentConsentVersion
            ];
        }

        // Wrong password → increment attempts
        $failedAttempts++;
        if ($failedAttempts >= $this->maxAttempts) {
            $lockTimestamp = date('Y-m-d H:i:s');
            $userModel->setAttemptsAndLock($email, $failedAttempts, $lockTimestamp);

            return [
                "blocked" => true,
                "message" => "Too many failed attempts. Account locked for 30 seconds.",
                "attempts" => $failedAttempts,
                "remaining" => 0,
                "remaining_seconds" => $this->lockSeconds
            ];
        } else {
            $userModel->updateAttempts($email, $failedAttempts);

            return [
                "success" => false,
                "message" => "Invalid email or password",
                "attempts" => $failedAttempts,
                "remaining" => $this->maxAttempts - $failedAttempts
            ];
        }
    }
}