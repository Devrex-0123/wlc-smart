<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__.'/../classes/db.php';
require_once __DIR__.'/../models/user.php'; // Optional if you use User class

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$db = Database::connect();

// Handle profile photo upload
function handlePhotoUpload($existingPhoto = null) {
    $uploadDir = __DIR__ . '/../../public/uploads/users/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        // No new photo uploaded, keep existing
        return $existingPhoto;
    }

    $fileTmp  = $_FILES['photo']['tmp_name'];
    $fileName = $_FILES['photo']['name'];
    $fileType = mime_content_type($fileTmp);

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($fileType, $allowed)) {
        return $existingPhoto; // Ignore invalid types
    }

    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $safeName = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        return $existingPhoto;
    }

    // Remove old photo file if exists
    if ($existingPhoto) {
        $oldPath = __DIR__ . '/../../public/' . ltrim($existingPhoto, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    // Store relative path from public root
    return 'uploads/users/' . $safeName;
}

// Helper function to log user activity
function logActivity($user_id, $activity_type, $description){
    global $db;
    $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$user_id,$activity_type,$description]);
}

function isValidContactNumber(string $contact): bool {
    return (bool) preg_match('/^\d{11}$/', $contact);
}

function validateUserProfileFields(string $full_name, string $contact_number, string $email, ?string $role, $office_id): ?string {
    if ($full_name === '') {
        return 'Full Name is required';
    }
    if ($contact_number === '') {
        return 'Contact Number is required';
    }
    if (!isValidContactNumber($contact_number)) {
        return 'Contact Number must be exactly 11 digits';
    }
    if ($email === '') {
        return 'Email Address is required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address';
    }
    if (!$role) {
        return 'Assigned Role is required';
    }
    if (!$office_id) {
        return 'Office / Department is required';
    }
    return null;
}

// Get POST data
$action = $_POST['action'] ?? 'save';
$user_id = $_POST['user_id'] ?? null;
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = $_POST['password'] ?? null;
$role = $_POST['role'] ?? null;
$office_id = $_POST['office_id'] ?? null;
$full_name = trim((string)($_POST['full_name'] ?? ''));
$contact_number = trim((string)($_POST['contact_number'] ?? ''));
$account_status = strtolower(trim((string)($_POST['account_status'] ?? 'active')));
if (!in_array($account_status, ['active', 'disabled', 'locked'], true)) {
    $account_status = 'active';
}

try {
    if($action === 'delete' && $user_id){
        // Fetch user to get photo path before delete
        $stmt = $db->prepare("SELECT Email, photo_url FROM user WHERE user_id=?");
        $stmt->execute([$user_id]);
        $deletedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $deletedEmail = $deletedUser['Email'] ?? "Unknown";
        $stmt = $db->prepare("UPDATE user SET deleted_at = NOW(), account_status = 'disabled', updated_at = NOW() WHERE user_id=?");
        $stmt->execute([$user_id]);
        logActivity($_SESSION['user_id'], "Delete User", "Soft-deleted user: $deletedEmail");
        echo json_encode(['success'=>true,'message'=>'User soft-deleted']);
        exit;
    }

    if($action === 'toggle_status' && $user_id){
        $stmt = $db->prepare("UPDATE user SET account_status = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$account_status, $user_id]);
        logActivity($_SESSION['user_id'], "Account Status", "Set user #$user_id status to $account_status");
        echo json_encode(['success'=>true,'message'=>'Account status updated']);
        exit;
    }

    if($user_id){ // Edit user
        $profileError = validateUserProfileFields($full_name, $contact_number, $email, $role, $office_id);
        if ($profileError !== null) {
            echo json_encode(['success' => false, 'message' => $profileError]);
            exit;
        }

        $stmt = $db->prepare("SELECT Email, photo_url FROM user WHERE user_id=?");
        $stmt->execute([$user_id]);
        $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldEmail = $oldUser['Email'] ?? "Unknown";
        $oldPhoto = $oldUser['photo_url'] ?? null;

        // Handle photo upload (keeps old if none uploaded)
        $newPhoto = handlePhotoUpload($oldPhoto);

        if($password){
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE user SET Email=?, password=?, password_updated_at = NOW(), role=?, office_id=?, photo_url=?, full_name=?, contact_number=?, account_status=?, updated_at = NOW() WHERE user_id=?");
            $stmt->execute([$email, $hashed, $role, $office_id, $newPhoto, ($full_name !== '' ? $full_name : null), ($contact_number !== '' ? $contact_number : null), $account_status, $user_id]);
            logActivity($_SESSION['user_id'], "Edit User", "Updated user ($oldEmail) → profile/security fields updated.");
        } else {
            $stmt = $db->prepare("UPDATE user SET Email=?, role=?, office_id=?, photo_url=?, full_name=?, contact_number=?, account_status=?, updated_at = NOW() WHERE user_id=?");
            $stmt->execute([$email, $role, $office_id, $newPhoto, ($full_name !== '' ? $full_name : null), ($contact_number !== '' ? $contact_number : null), $account_status, $user_id]);
            logActivity($_SESSION['user_id'], "Edit User", "Updated user ($oldEmail) → profile/account fields changed.");
        }

        echo json_encode(['success'=>true,'message'=>'User updated']);
        exit;
    } else { // Add new user
        $profileError = validateUserProfileFields($full_name, $contact_number, $email, $role, $office_id);
        if ($profileError !== null) {
            echo json_encode(['success' => false, 'message' => $profileError]);
            exit;
        }
        if (!$password) {
            echo json_encode(['success' => false, 'message' => 'Password is required']);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        // Handle new photo upload (no existing photo)
        $photoPath = handlePhotoUpload(null);

        $stmt = $db->prepare("
            INSERT INTO user (
                Email,password,role,office_id,photo_url,full_name,contact_number,
                has_consented,consent_version,consent_date,last_login,account_status,password_updated_at,created_at,updated_at
            ) VALUES (?,?,?,?,?,?,?,0,NULL,NULL,NULL,?,NOW(),NOW(),NOW())
        ");
        $stmt->execute([$email, $hashed, $role, $office_id, $photoPath, ($full_name !== '' ? $full_name : null), ($contact_number !== '' ? $contact_number : null), $account_status]);

        logActivity($_SESSION['user_id'], "Add User", "Created new user: $email");
        echo json_encode(['success'=>true,'message'=>'User added']);
        exit;
    }
} catch(PDOException $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
