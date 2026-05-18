<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

require_once __DIR__.'/../classes/db.php';

try {
    $db = Database::connect();
    $officeType = trim((string)($_GET['type'] ?? $_POST['type'] ?? ''));
    $allowedTypes = ['academic', 'administrative', 'executive'];

    if ($officeType !== '' && in_array($officeType, $allowedTypes, true)) {
        $stmt = $db->prepare("
            SELECT office_id, office_name, type
            FROM offices
            WHERE type = ?
            ORDER BY office_name ASC
        ");
        $stmt->execute([$officeType]);
    } else {
        $stmt = $db->query("
            SELECT office_id, office_name, type
            FROM offices
            ORDER BY office_name ASC
        ");
    }
    $offices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'offices'=>$offices]);
} catch(PDOException $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
