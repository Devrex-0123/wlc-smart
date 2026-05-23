<?php
/**
 * Notification Views API - No-op endpoint for backward compatibility
 * 
 * The notification_views table has been removed to simplify the notification system.
 * Badges now count directly from pending items in relevant tables.
 * This endpoint now simply returns success without tracking views.
 */
session_start();
header('Content-Type: application/json');

function sendJson(array $payload): void
{
    echo json_encode($payload);
    exit;
}

function getJsonPayload(): array
{
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        return [];
    }
    return $payload;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJson(['success' => false, 'message' => 'Method not allowed.']);
    }

    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Unauthorized']);
    }

    $payload = getJsonPayload();
    $notificationKey = trim((string)($payload['notification_key'] ?? ''));
    
    if ($notificationKey === '') {
        sendJson(['success' => false, 'message' => 'Invalid notification key.']);
    }

    // No-op: Simply acknowledge the request
    sendJson(['success' => true, 'message' => 'Acknowledged.']);
} catch (Throwable $exception) {
    sendJson(['success' => false, 'message' => 'Could not process request.']);
}
