<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_type = 'admin' AND is_read = 0");
        if ($stmt) {
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
    } elseif (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $notificationId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

$conn->close();
?>
