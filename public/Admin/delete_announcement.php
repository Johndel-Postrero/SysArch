<?php
session_start();
require __DIR__ . '/../../config/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['login_user']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['announcement_id'])) {
    echo json_encode(['success' => false, 'message' => 'No announcement ID provided']);
    exit();
}

$announcement_id = intval($_GET['announcement_id']);

// First, get the attachment filename if it exists
$query = $conn->prepare("SELECT attachment FROM announcements WHERE announcement_id = ?");
$query->bind_param("i", $announcement_id);
$query->execute();
$result = $query->get_result();
$row = $result->fetch_assoc();

// Delete the announcement
$stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
$stmt->bind_param("i", $announcement_id);

if ($stmt->execute()) {
    // If there was an attachment, delete the file
    if ($row && $row['attachment']) {
        $file_path = __DIR__ . '/../announce/' . $row['attachment'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete associated comments
    $delete_comments = $conn->prepare("DELETE FROM comments WHERE announcement_id = ?");
    $delete_comments->bind_param("i", $announcement_id);
    $delete_comments->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error deleting announcement']);
}

$stmt->close();
$conn->close();
?> 