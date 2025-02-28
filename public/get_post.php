<?php
session_start();
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['login_user'])) {
    die("Unauthorized access");
}

if (isset($_GET['id'])) {
    $postId = intval($_GET['id']);
    $query = $conn->prepare("SELECT id, title, description, attachment FROM announcements WHERE id = ?");
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