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
        case 'like_comment':
            toggleCommentLike($conn);
            break;
        case 'like_announcement':
            toggleAnnouncementLike($conn);
            break;
        case 'get_announcement_like':
            getAnnouncementLikeStatus($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function addComment($conn) {
    $announcement_id = $_POST['announcement_id'] ?? 0;
    $comment_text = trim($_POST['comment_text'] ?? '');
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $user_id = $_SESSION['user_id'];

    if (empty($comment_text)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO comments (announcement_id, user_id, comment_text, parent_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $announcement_id, $user_id, $comment_text, $parent_id);

    if ($stmt->execute()) {
        // Get the newly added comment with user details
        $comment_id = $conn->insert_id;
        $query = "SELECT c.*, u.firstname, u.middlename, u.lastname, u.profile_picture, u.role,
                         0 AS like_count, 0 AS user_liked
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
    $current_user_id = $_SESSION['user_id'];

    $query = "SELECT c.*, u.firstname, u.middlename, u.lastname, u.profile_picture, u.role,
                     (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.comment_id) AS like_count,
                     (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.comment_id AND cl.user_id = ?) AS user_liked
              FROM comments c 
              JOIN users u ON c.user_id = u.user_id 
              WHERE c.announcement_id = ? 
              ORDER BY c.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $current_user_id, $announcement_id);
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

function toggleCommentLike($conn) {
    $comment_id = intval($_POST['comment_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Check if already liked
    $stmt = $conn->prepare("SELECT like_id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $liked = $result->fetch_assoc();
    $stmt->close();

    if ($liked) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $action = 'unliked';
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();
        $action = 'liked';
    }
    $stmt->close();

    // Get new count
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM comment_likes WHERE comment_id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => $count
    ]);
}

function toggleAnnouncementLike($conn) {
    $announcement_id = intval($_POST['announcement_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Check if already liked
    $stmt = $conn->prepare("SELECT like_id FROM announcement_likes WHERE announcement_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $announcement_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $liked = $result->fetch_assoc();
    $stmt->close();

    if ($liked) {
        // Unlike
        $stmt = $conn->prepare("DELETE FROM announcement_likes WHERE announcement_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $announcement_id, $user_id);
        $stmt->execute();
        $action = 'unliked';
    } else {
        // Like
        $stmt = $conn->prepare("INSERT INTO announcement_likes (announcement_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $announcement_id, $user_id);
        $stmt->execute();
        $action = 'liked';
    }
    $stmt->close();

    // Get new count
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM announcement_likes WHERE announcement_id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => $count
    ]);
}

function getAnnouncementLikeStatus($conn) {
    $announcement_id = intval($_POST['announcement_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM announcement_likes WHERE announcement_id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT like_id FROM announcement_likes WHERE announcement_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $announcement_id, $user_id);
    $stmt->execute();
    $user_liked = $stmt->get_result()->num_rows > 0 ? 1 : 0;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'like_count' => $count,
        'user_liked' => $user_liked
    ]);
}
?>