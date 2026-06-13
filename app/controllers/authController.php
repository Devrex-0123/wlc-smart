<?php
require_once __DIR__ . '/../config/consent.php';
require_once __DIR__ . '/../models/user.php';
require_once __DIR__ . '/../models/department.php';
require_once __DIR__ . '/../classes/db.php';

class AuthController {
    private $maxAttempts = 3;
    private $lockSeconds = 30;

    private function logFailedLoginAttempt($userId, $email, $reason) {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                INSERT INTO user_activity (user_id, activity_type, description, created_at)
                VALUES (?, 'Failed Login', ?, NOW())
            ");
            $stmt->execute([
                (int) $userId,
                "Failed login attempt for {$email}: {$reason}"
            ]);
        } catch (Throwable $e) {
            // Non-blocking audit logging
        }
    }

    private function resolveDashboardUrl($roleNorm, $canvasserWorkspace) {
        switch ($roleNorm) {
            case 'department':
            case 'dean':
                return 'public/pages/dean_dashboard.php';
            case 'employee':
            case 'user':
            case 'laboratory manager':
            case 'canvasser':
                return $canvasserWorkspace
                    ? 'public/pages/canvasser_dashboard.php'
                    : 'public/pages/employee_dashboard.php';
            case 'inventory_manager':
            case 'inventory manager':
                return 'public/pages/dashboard.php';
            case 'comptroller':
                return 'public/pages/comptroller_dashboard.php';
            case 'gsd officer':
                return 'public/pages/gsd_dashboard.php';
            case 'president':
            case 'president verifier':
            case 'verifier president':
            case 'president_verifier':
                return 'public/pages/president_dashboard.php';
            default:
                return 'public/pages/dashboard.php';
        }
    }

    private function ensureSessionStarted(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function clearDepartmentSessionFlags(): void {
        unset(
            $_SESSION['login_type'],
            $_SESSION['department_id'],
            $_SESSION['department_name'],
            $_SESSION['department_abbreviation'],
            $_SESSION['department_type']
        );
    }

    public function login($identifier, $password, $consentAccepted = false) {
        $this->ensureSessionStarted();

        $identifier = trim((string) $identifier);
        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            $email = strtolower($identifier);
            $userModel = new User();
            $user = $userModel->findByEmail($email);
            if ($user) {
                return $this->authenticateUser($user, $email, $password, $consentAccepted);
            }
        }

        $departmentResult = $this->authenticateDepartment($identifier, $password, $consentAccepted);
        if ($departmentResult !== null) {
            return $departmentResult;
        }

        if ($isEmail) {
            return [
                'success' => false,
                'message' => 'Account does not exist',
                'account_missing' => true,
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid username or password',
            'attempts' => 1,
            'remaining' => $this->maxAttempts - 1,
        ];
    }

    private function authenticateDepartment($username, $password, $consentAccepted) {
        $username = trim((string) $username);
        if ($username === '') {
            return null;
        }

        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT department_id, department_name, department_abbreviation,
                       department_type, department_username, department_password_hash,
                       department_status, had_consented, consent_version
                FROM departments
                WHERE department_username = ?
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }

        if (!$department) {
            return null;
        }

        if (strcasecmp((string) ($department['department_status'] ?? ''), 'Active') !== 0) {
            return [
                'success' => false,
                'disabled' => true,
                'message' => 'This department account is inactive. Please contact your administrator.',
            ];
        }

        $hash = (string) ($department['department_password_hash'] ?? '');
        if ($hash === '' || !password_verify((string) $password, $hash)) {
            return [
                'success' => false,
                'message' => 'Invalid username or password',
                'attempts' => 1,
                'remaining' => $this->maxAttempts - 1,
            ];
        }

        $hasConsented = (int) ($department['had_consented'] ?? 0) === 1;
        if (!$hasConsented && !$consentAccepted) {
            return [
                'success' => false,
                'consent_required' => true,
                'message' => 'You must agree to the Privacy Notice and Terms & Conditions to continue.',
            ];
        }

        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            $newHash = password_hash((string) $password, PASSWORD_BCRYPT);
            $db->prepare('UPDATE departments SET department_password_hash = ? WHERE department_id = ?')
                ->execute([$newHash, (int) $department['department_id']]);
        }

        if (!$hasConsented && $consentAccepted) {
            $departmentModel = new Department();
            $departmentModel->updateConsent((int) $department['department_id'], CONSENT_VERSION);
        }

        $this->clearDepartmentSessionFlags();

        $_SESSION['login_type'] = 'department';
        $_SESSION['department_id'] = (int) $department['department_id'];
        $_SESSION['department_name'] = $department['department_name'];
        $_SESSION['department_abbreviation'] = $department['department_abbreviation'];
        $_SESSION['department_type'] = $department['department_type'];
        $_SESSION['user_email'] = $department['department_username'];
        $_SESSION['user_name'] = $department['department_name'];
        $_SESSION['user_role'] = 'department';
        $_SESSION['consent_version_current'] = CONSENT_VERSION;
        $_SESSION['dashboard_url'] = $this->resolveDashboardUrl('department', false);
        $_SESSION['has_consented'] = true;
        $_SESSION['consent_required'] = false;

        unset($_SESSION['user_id'], $_SESSION['remember_token']);

        return [
            'success' => true,
            'message' => 'Login successful',
            'role' => 'department',
            'login_type' => 'department',
            'department_name' => $department['department_name'],
            'consent_version' => CONSENT_VERSION,
        ];
    }

    private function authenticateUser(array $user, string $email, $password, $consentAccepted) {
        $userModel = new User();
        $this->clearDepartmentSessionFlags();

        if (!empty($user['deleted_at'])) {
            return [
                'success' => false,
                'message' => 'Account is unavailable.',
                'account_missing' => true,
            ];
        }

        $accountStatus = strtolower(trim((string) ($user['account_status'] ?? 'active')));
        if ($accountStatus === 'disabled') {
            return [
                'success' => false,
                'disabled' => true,
                'message' => 'Your account has been disabled. Please contact your administrator.',
            ];
        }

        $failedAttempts = (int) ($user['failed_attempts'] ?? 0);
        $lockTimeRaw = $user['lock_time'] ?? null;
        $lockTime = $lockTimeRaw ? strtotime((string) $lockTimeRaw) : 0;

        if ($accountStatus === 'locked' && $lockTime <= 0) {
            return [
                'blocked' => true,
                'message' => 'Your account is currently locked. Please contact your administrator.',
                'remaining' => 0,
                'remaining_seconds' => 0,
            ];
        }

        if ($failedAttempts >= $this->maxAttempts) {
            $elapsed = time() - $lockTime;
            if ($lockTime > 0 && $elapsed < $this->lockSeconds) {
                $remaining = $this->lockSeconds - $elapsed;
                return [
                    'blocked' => true,
                    'message' => 'Account locked. Try again later.',
                    'attempts' => $failedAttempts,
                    'remaining' => 0,
                    'remaining_seconds' => $remaining,
                ];
            }

            $userModel->resetAttempts($email);
            $userModel->unlockAccountIfEligible((int) $user['user_id']);
            $failedAttempts = 0;
        }

        $passwordVerified = $userModel->verifyPassword($password, $user['password']);

        if ($passwordVerified) {
            $hasConsented = (int) ($user['has_consented'] ?? 0) === 1;
            if (!$hasConsented && !$consentAccepted) {
                return [
                    'success' => false,
                    'consent_required' => true,
                    'message' => 'You must agree to the Privacy Notice and Terms & Conditions to continue.',
                ];
            }

            if ($userModel->needsPasswordRehash((string) $user['password'])) {
                $userModel->updatePasswordHashByUserId((int) $user['user_id'], $password);
            }

            $userModel->markLoginSuccess((int) $user['user_id']);

            $_SESSION['login_type'] = 'user';
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['Email'];
            $_SESSION['user_name'] = $user['full_name'] ?? $user['FullName'] ?? $user['FirstName'] ?? 'User';
            $_SESSION['user_role'] = strtolower(trim($user['role'] ?? 'employee'));
            $_SESSION['consent_version_current'] = CONSENT_VERSION;

            $roleNorm = strtolower(trim((string) ($user['role'] ?? 'employee')));
            $employeeLike = in_array($roleNorm, ['employee', 'user', 'laboratory manager', 'canvasser'], true);
            $canvasserWorkspace = $employeeLike;

            $_SESSION['dashboard_url'] = $this->resolveDashboardUrl($roleNorm, $canvasserWorkspace);

            if (!$hasConsented && $consentAccepted) {
                $userModel->updateConsent((int) $user['user_id'], CONSENT_VERSION);
            }

            $_SESSION['has_consented'] = true;
            $_SESSION['consent_required'] = false;

            $rememberToken = bin2hex(random_bytes(32));
            $userModel->updateRememberTokenByUserId((int) $user['user_id'], hash('sha256', $rememberToken));
            $_SESSION['remember_token'] = $rememberToken;

            return [
                'success' => true,
                'message' => 'Login successful',
                'role' => $_SESSION['user_role'],
                'login_type' => 'user',
                'canvasser_workspace' => $canvasserWorkspace,
                'consent_version' => CONSENT_VERSION,
            ];
        }

        $failedAttempts++;
        if ($failedAttempts >= $this->maxAttempts) {
            $lockTimestamp = date('Y-m-d H:i:s');
            $userModel->setAttemptsAndLock($email, $failedAttempts, $lockTimestamp);
            $this->logFailedLoginAttempt((int) $user['user_id'], $email, 'Account locked after too many failed attempts');

            return [
                'blocked' => true,
                'message' => 'Too many failed attempts. Account locked for 30 seconds.',
                'attempts' => $failedAttempts,
                'remaining' => 0,
                'remaining_seconds' => $this->lockSeconds,
            ];
        }

        $userModel->updateAttempts($email, $failedAttempts);
        $this->logFailedLoginAttempt((int) $user['user_id'], $email, 'Invalid email or password');

        return [
            'success' => false,
            'message' => 'Invalid email or password',
            'attempts' => $failedAttempts,
            'remaining' => $this->maxAttempts - $failedAttempts,
        ];
    }
}
