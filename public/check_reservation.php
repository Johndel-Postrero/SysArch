<?php
require __DIR__ . '/../config/db.php';

// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$now = new DateTime();
$timeIn30Minutes = clone $now;
$timeIn30Minutes->add(new DateInterval('PT30M'));

$today = $now->format('Y-m-d');
$timeIn30MinutesStr = $timeIn30Minutes->format('H:i:00');

// Check for reservations starting in exactly 30 minutes
$query = $conn->prepare("
    SELECT r.reservation_id, r.lab_number, r.pc_number, r.time_in, r.reservation_date
    FROM reservations r
    JOIN users u ON r.idno = u.idno
    WHERE u.user_id = ?
    AND r.reservation_date = ?
    AND r.time_in = ?
    AND r.status = 'approved'
    AND r.time_in_status = 'pending'
    AND NOT EXISTS (
        SELECT 1 FROM notifications n 
        WHERE n.user_id = u.user_id 
        AND n.message LIKE CONCAT('%Your reservation for Lab ', r.lab_number, ', PC ', r.pc_number, ' will start in 30 minutes%')
        AND DATE(n.created_at) = ?
    )
");

if (!$query) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$query->bind_param("isss", $userId, $today, $timeIn30MinutesStr, $today);
$query->execute();
$result = $query->get_result();

$notificationsSent = [];
while ($row = $result->fetch_assoc()) {
    // Create notification message
    $message = "Your reservation for Lab {$row['lab_number']}, PC {$row['pc_number']} will start in 30 minutes at " . date('g:i A', strtotime($row['time_in']));
    
    // Insert notification
    $insertQuery = $conn->prepare("
        INSERT INTO notifications (user_id, message, notification_type)
        VALUES (?, ?, 'student')
    ");
    
    if (!$insertQuery) {
        continue;
    }
    
    $insertQuery->bind_param("is", $userId, $message);
    $insertSuccess = $insertQuery->execute();
    
    if ($insertSuccess) {
        $notificationsSent[] = $row;
    }
    
    $insertQuery->close();
}

$query->close();
$conn->close();

echo json_encode([
    'success' => true,
    'notifications_sent' => count($notificationsSent),
    'reservations' => $notificationsSent
]);