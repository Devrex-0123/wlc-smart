<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__.'/../classes/db.php';

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$db = Database::connect();

// Get current user (Dean)
$stmt = $db->prepare("SELECT * FROM user WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Deans and GSD officers may manage users in their own office
$actorRoleLc = strtolower(trim((string) ($currentUser['role'] ?? '')));
if ($actorRoleLc !== 'dean' && $actorRoleLc !== 'gsd officer') {
    echo json_encode(['success'=>false,'message'=>'You do not have access to this action']);
    exit;
}

$deanOfficeId = $currentUser['office_id'];

if (!$deanOfficeId) {
    echo json_encode(['success'=>false,'message'=>'You are not assigned to any office']);
    exit;
}

// Handle profile photo upload
function handlePhotoUpload($existingPhoto = null) {
    $uploadDir = __DIR__ . '/../../public/uploads/users/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        return $existingPhoto;
    }

    $fileTmp  = $_FILES['photo']['tmp_name'];
    $fileName = $_FILES['photo']['name'];
    $fileType = mime_content_type($fileTmp);

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($fileType, $allowed)) {
        return $existingPhoto;
    }

    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $safeName = 'user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $safeName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        return $existingPhoto;
    }

    if ($existingPhoto) {
        $oldPath = __DIR__ . '/../../public/' . ltrim($existingPhoto, '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    return 'uploads/users/' . $safeName;
}

// Helper function to log user activity
function logActivity($user_id, $activity_type, $description){
    global $db;
    $stmt = $db->prepare("INSERT INTO user_activity (user_id, activity_type, description, created_at) VALUES (?,?,?,NOW())");
    $stmt->execute([$user_id,$activity_type,$description]);
}

// Get POST data
$action = $_POST['action'] ?? 'save';
$user_id = $_POST['user_id'] ?? null;
$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$role = $_POST['role'] ?? null;
$submitted_office_id = $_POST['office_id'] ?? null;

// CRITICAL: Verify that the submitted office_id matches the dean's office
if ($submitted_office_id != $deanOfficeId) {
    echo json_encode(['success'=>false,'message'=>'You can only manage users in your own office']);
    exit;
}

try {
    // DELETE USER
    if($action === 'delete' && $user_id){
        // Get the user to verify they belong to this office
        $stmt = $db->prepare("SELECT Email, photo_url, office_id FROM user WHERE user_id=?");
        $stmt->execute([$user_id]);
        $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userToDelete) {
            echo json_encode(['success'=>false,'message'=>'User not found']);
            exit;
        }

        // Verify user belongs to dean's office
        if ($userToDelete['office_id'] != $deanOfficeId) {
            echo json_encode(['success'=>false,'message'=>'You can only delete users in your office']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM user WHERE user_id=?");
        $stmt->execute([$user_id]);

        if ($userToDelete['photo_url']) {
            $photoPath = __DIR__ . '/../../public/' . ltrim($userToDelete['photo_url'], '/');
            if (is_file($photoPath)) {
                @unlink($photoPath);
            }
        }

        logActivity($_SESSION['user_id'], "Delete Office User", "Deleted user: {$userToDelete['Email']} from " . htmlspecialchars($_POST['dept_name'] ?? 'office'));
        echo json_encode(['success'=>true,'message'=>'User deleted successfully']);
        exit;
    }

    // EDIT USER
    if($user_id){
        $stmt = $db->prepare("SELECT Email, photo_url, office_id FROM user WHERE user_id=?");
        $stmt->execute([$user_id]);
        $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldUser) {
            echo json_encode(['success'=>false,'message'=>'User not found']);
            exit;
        }

        // Verify user belongs to dean's office
        if ($oldUser['office_id'] != $deanOfficeId) {
            echo json_encode(['success'=>false,'message'=>'You can only edit users in your office']);
            exit;
        }

        $oldEmail = $oldUser['Email'];
        $oldPhoto = $oldUser['photo_url'];

        $newPhoto = handlePhotoUpload($oldPhoto);

        // Prevent changing role to restricted roles (Dean, Comptroller, President)
        $restrictedRoles = ['Dean', 'Comptroller', 'President', 'GSD officer'];
        if (in_array($role, $restrictedRoles, true)) {
            echo json_encode(['success'=>false,'message'=>'You cannot assign restricted roles to office users']);
            exit;
        }

        if($password){
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE user SET Email=?, password=?, role=?, photo_url=? WHERE user_id=?");
            $stmt->execute([$email, $hashed, $role, $newPhoto, $user_id]);
            logActivity($_SESSION['user_id'], "Edit Office User", "Updated user ($oldEmail) → Email/Password/Role/Photo changed.");
        } else {
            $stmt = $db->prepare("UPDATE user SET Email=?, role=?, photo_url=? WHERE user_id=?");
            $stmt->execute([$email, $role, $newPhoto, $user_id]);
            logActivity($_SESSION['user_id'], "Edit Office User", "Updated user ($oldEmail) → Email/Role/Photo changed.");
        }

        echo json_encode(['success'=>true,'message'=>'User updated successfully']);
        exit;
    } 
    
    // ADD NEW USER
    else {
        if(!$password){
            echo json_encode(['success'=>false,'message'=>'Password is required']);
            exit;
        }

        // Prevent creating users with restricted roles
        $restrictedRoles = ['Dean', 'Comptroller', 'President', 'GSD officer'];
        if (in_array($role, $restrictedRoles, true)) {
            echo json_encode(['success'=>false,'message'=>'You cannot assign restricted roles to office users']);
            exit;
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT user_id FROM user WHERE LOWER(Email) = LOWER(?)");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success'=>false,'message'=>'Email already exists']);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $photoPath = handlePhotoUpload(null);

        $stmt = $db->prepare("INSERT INTO user (Email,password,role,office_id,photo_url,created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$email, $hashed, $role, $deanOfficeId, $photoPath]);

        logActivity($_SESSION['user_id'], "Add Office User", "Created new user: $email in their office");
        echo json_encode(['success'=>true,'message'=>'User added successfully']);
        exit;
    }
} catch(PDOException $e){
    echo json_encode(['success'=>false,'message'=>'Database error: ' . $e->getMessage()]);
}
?>
