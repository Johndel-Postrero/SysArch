<?php
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['login_user'])) {
    die("Unauthorized access");
}

if (isset($_GET['announcement_id'])) {  // Changed from 'id' to 'announcement_id'
    $postId = intval($_GET['announcement_id']);
    $query = $conn->prepare("SELECT announcement_id, title, description, attachment FROM announcements WHERE announcement_id = ?");
    if ($query === false) {
        die("Prepare failed: " . $conn->error);
    }
    $query->bind_param("i", $postId);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();
        echo json_encode($post);
    } else {
        echo json_encode(["error" => "Post not found"]);
    }
    $query->close();
} else {
    echo json_encode(["error" => "Invalid request"]);
}

$conn->close();
?>