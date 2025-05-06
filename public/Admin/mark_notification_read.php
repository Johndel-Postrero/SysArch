<?php
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);
        $markReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        
        if ($markReadQuery) {
            $markReadQuery->bind_param("i", $notificationId);
            if ($markReadQuery->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            $markReadQuery->close();
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $conn->close();
        exit;
    }

    if (isset($_POST['mark_all_read'])) {
        $markAllReadQuery = $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        
        if ($markAllReadQuery) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $conn->close();
        exit;
    }
}