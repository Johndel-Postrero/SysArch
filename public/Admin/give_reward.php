<?php
session_start();
require __DIR__ .  '/../../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['login_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$sitin_id = $_POST['sitin_id'] ?? null;
$idno = $_POST['idno'] ?? null;
$lastname = $_POST['lastname'] ?? '';
$firstname = $_POST['firstname'] ?? '';

if (!$sitin_id || !$idno) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Get user ID
    $userQuery = $conn->prepare("SELECT id FROM users WHERE idno = ?");
    $userQuery->bind_param("i", $idno);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $user = $userResult->fetch_assoc();
    $user_id = $user['id'];
    $userQuery->close();

    // 1. Insert reward record
    $stmt = $conn->prepare("INSERT INTO rewards (idno, lastname, firstname, sitin_id, rewarded_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $idno, $lastname, $firstname, $sitin_id, $_SESSION['user_id']);
    $stmt->execute();
    
    // 2. Count total rewards for this student
    $result = $conn->query("SELECT COUNT(*) as total_points FROM rewards WHERE idno = $idno");
    $row = $result->fetch_assoc();
    $total_points = $row['total_points'];
    
    // 3. Create notification for the student about the reward (with slightly older timestamp)
    $rewardMessage = "You earned 1 reward point for good behavior! Total points: $total_points";
    $conn->query("INSERT INTO notifications (message, user_id, notification_type) 
                 VALUES ('$rewardMessage', $user_id, 'student')");
    
// 4. Check if points reached 3 to convert to session
// In the session conversion section:
if ($total_points % 3 === 0) {
    $updateStmt = $conn->prepare("UPDATE users SET session = session + 1 WHERE idno = ?");
    $updateStmt->bind_param("i", $idno);
    $updateResult = $updateStmt->execute();
    $updateStmt->close();
    
    if (!$updateResult) {
        throw new Exception("Failed to update session: " . $conn->error);
    }
    
    $sessionMessage = "Your 3 reward points have been converted to 1 session!";
    $conn->query("INSERT INTO notifications (message, user_id, notification_type) 
                VALUES ('$sessionMessage', $user_id, 'student')");
}   
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>