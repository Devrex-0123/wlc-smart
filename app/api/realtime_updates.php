<?php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
ignore_user_abort(false); // This allows us to detect disconnect fast

if (!isset($_SESSION['user_id'])) {
    echo "data: " . json_encode(["error" => "Unauthorized"]) . "\n\n";
    flush();
    exit;
}

require_once __DIR__ . '/../classes/db.php';
$db = Database::connect();

// Send connected event
echo "data: " . json_encode(["type" => "connected"]) . "\n\n";
flush();

while (true) {
    if (connection_aborted()) {
        break;
    }

    
    if (isset($_GET['logout']) && $_GET['logout'] === '1') {
        break; 
    }

    // Your normal update logic
    $totalUsers = $db->query("SELECT COUNT(*) FROM user")->fetchColumn();
    $totalLogs  = $db->query("SELECT COUNT(*) FROM log_history")->fetchColumn();
    $activeSessions = $db->query("SELECT COUNT(*) FROM log_history WHERE time_out IS NULL OR time_out = '0000-00-00 00:00:00' OR time_out = time_in")->fetchColumn();

    $stmt = $db->query("SELECT l.*, u.Email, u.Role FROM log_history l LEFT JOIN user u ON l.user_id = u.user_id ORDER BY l.time_in DESC LIMIT 15");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($logs as $log) {
        $in = new DateTime($log['time_in']);
        $out = ($log['time_out'] && $log['time_out'] !== '0000-00-00 00:00:00') ? new DateTime($log['time_out']) : null;
        $active = !$out || $in->format('Y-m-d H:i:s') === $out->format('Y-m-d H:i:s');

        $duration = $active ? 'Ongoing' : sprintf('%02d:%02d:%02d',
            $in->diff($out)->h + ($in->diff($out)->d * 24),
            $in->diff($out)->i,
            $in->diff($out)->s
        );

        $formatted[] = [
            'log_id'    => $log['log_id'],
            'email'     => $log['Email'] ?? 'Unknown',
            'role'      => $log['Role'] ?? 'N/A',
            'time_in'   => $in->format('M d, Y h:i A'),
            'time_out'  => $active ? 'Active Session' : $out->format('M d, Y h:i A'),
            'duration'  => $duration,
            'status'    => $active ? 'Active' : 'Completed',
            'is_active' => $active
        ];
    }

    $payload = [
        "type" => "update",
        "data" => [
            "totalUsers"     => (int)$totalUsers,
            "totalLogs"      => (int)$totalLogs,
            "activeSessions" => (int)$activeSessions,
            "recentLogs"     => $formatted
        ]
    ];

    echo "data: " . json_encode($payload) . "\n\n";
    flush();

    sleep(3);
}