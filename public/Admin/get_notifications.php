<?php
session_start();
require __DIR__ . '/../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['login_user']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Function to get notifications from database (admin-specific)
function getAdminNotifications($conn, $limit = 5) {
    $notifications = [];
    
    $query = "SELECT notification_id, message, is_read, created_at FROM notifications WHERE notification_type = 'admin' ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
    }
    
    return $notifications;
}

// Function to count unread admin notifications
function countUnreadAdminNotifications($conn) {
    $count = 0;
    
    $query = "SELECT COUNT(*) as unread_count 
              FROM notifications 
              WHERE is_read = 0 AND notification_type = 'admin'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['unread_count'];
    }
    
    return $count;
}

// Get notifications and unread count
$notifications = getAdminNotifications($conn, 5);
$unreadCount = countUnreadAdminNotifications($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);

$conn->close();
?> 