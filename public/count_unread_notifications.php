<?php
require __DIR__ . '/../config/db.php';

// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread_count' => 0]);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'student';

if ($role === 'student') {
    $query = "SELECT COUNT(*) as unread_count 
             FROM notifications 
             WHERE is_read = 0 
             AND user_id = ?";
} else {
    $query = "SELECT COUNT(*) as unread_count 
             FROM notifications 
             WHERE is_read = 0 
             AND notification_type = 'admin'";
}

$stmt = $conn->prepare($query);

if ($role === 'student') {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['unread_count'] ?? 0;

echo json_encode(['unread_count' => $count]);