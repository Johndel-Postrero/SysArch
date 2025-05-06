<?php
session_start();
require __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            addComment($conn);
            break;
        case 'delete':
            deleteComment($conn);
            break;
        case 'get':
            getComments($conn);
            break;
        case 'edit':
            editComment($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function addComment($conn) {
    $announcement_id = $_POST['announcement_id'] ?? 0;
    $comment_text = trim($_POST['comment_text'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO comments (announcement_id, user_id, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $announcement_id, $user_id, $comment_text);

    if ($stmt->execute()) {
        // Get the newly added comment with user details
        $comment_id = $conn->insert_id;
        $query = "SELECT c.*, u.firstname, u.middlename, u.lastname, u.profile_picture, u.role 
                 FROM comments c 
                 JOIN users u ON c.user_id = u.user_id 
                 WHERE c.comment_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $comment = $result->fetch_assoc();

        echo json_encode([
            'success' => true, 
            'message' => 'Comment added successfully',
            'comment' => $comment
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding comment']);
    }
}

function deleteComment($conn) {
    $comment_id = $_POST['comment_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    // Check if user is admin or the comment owner
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comment = $result->fetch_assoc();

    if ($comment && ($comment['user_id'] == $user_id || $_SESSION['role'] === 'admin')) {
        $stmt = $conn->prepare("DELETE FROM comments WHERE comment_id = ?");
        $stmt->bind_param("i", $comment_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting comment']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to delete this comment']);
    }
}

function getComments($conn) {
    $announcement_id = $_POST['announcement_id'] ?? 0;

    $query = "SELECT c.*, u.firstname, u.middlename, u.lastname, u.profile_picture, u.role 
              FROM comments c 
              JOIN users u ON c.user_id = u.user_id 
              WHERE c.announcement_id = ? 
              ORDER BY c.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    echo json_encode(['success' => true, 'comments' => $comments]);
}

function editComment($conn) {
    if (!isset($_POST['comment_id']) || !isset($_POST['comment_text'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    $comment_id = intval($_POST['comment_id']);
    $comment_text = trim($_POST['comment_text']);
    $user_id = $_SESSION['user_id'];

    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'message' => 'Comment text cannot be empty']);
        return;
    }

    // Check if user is authorized to edit this comment
    $check_query = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
    $check_query->bind_param("i", $comment_id);
    $check_query->execute();
    $result = $check_query->get_result();
    $comment = $result->fetch_assoc();

    if (!$comment || ($comment['user_id'] != $user_id && $_SESSION['role'] !== 'admin')) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized to edit this comment']);
        return;
    }

    $query = $conn->prepare("UPDATE comments SET comment_text = ? WHERE comment_id = ?");
    $query->bind_param("si", $comment_text, $comment_id);

    if ($query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating comment']);
    }
}
?> 