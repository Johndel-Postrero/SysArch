<?php
session_start();
require __DIR__ .  '/../../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['login_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get POST data
$sitin_id = isset($_POST['sitin_id']) ? (int)$_POST['sitin_id'] : null;
$idno = isset($_POST['idno']) ? (int)$_POST['idno'] : null;
$lastname = $_POST['lastname'] ?? '';
$firstname = $_POST['firstname'] ?? '';

if (!$sitin_id || !$idno) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Prevent duplicate reward for the same sit-in record
    $existingStmt = $conn->prepare("SELECT reward_id FROM rewards WHERE sitin_id = ? LIMIT 1");
    $existingStmt->bind_param("i", $sitin_id);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    if ($existingResult->num_rows > 0) {
        $existingStmt->close();
        throw new Exception("This sit-in record has already been rewarded.");
    }
    $existingStmt->close();

    // Get user ID
    $userQuery = $conn->prepare("SELECT user_id FROM users WHERE idno = ?");
    $userQuery->bind_param("i", $idno);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $user = $userResult->fetch_assoc();
    $user_id = $user['user_id'];
    $userQuery->close();

    // 1. Insert reward record
    $stmt = $conn->prepare("INSERT INTO rewards (idno, lastname, firstname, sitin_id, rewarded_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $idno, $lastname, $firstname, $sitin_id, $_SESSION['user_id']);
    $stmt->execute();
    
    // 2. Count current available reward points for this student
    $pointsStmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS total_points FROM rewards WHERE idno = ?");
    $pointsStmt->bind_param("i", $idno);
    $pointsStmt->execute();
    $pointsResult = $pointsStmt->get_result();
    $row = $pointsResult->fetch_assoc();
    $total_points = (int)($row['total_points'] ?? 0);
    $pointsStmt->close();
    
    // 3. Create notification for the student about the reward (with slightly older timestamp)
    $rewardMessage = "You earned 1 reward point for good behavior! Total points: $total_points";
    $conn->query("INSERT INTO notifications (message, user_id, notification_type) 
                 VALUES ('$rewardMessage', $user_id, 'student')");
    
    // 4. Auto-convert every 3 reward points to 1 session, then consume those points
    if ($total_points >= 3) {
        $sessionsToAdd = intdiv($total_points, 3);
        $pointsToConsume = $sessionsToAdd * 3;

        $updateStmt = $conn->prepare("UPDATE users SET session = session + ? WHERE idno = ?");
        $updateStmt->bind_param("ii", $sessionsToAdd, $idno);
        $updateResult = $updateStmt->execute();
        $updateStmt->close();

        if (!$updateResult) {
            throw new Exception("Failed to update session: " . $conn->error);
        }

        // Consume points from oldest unconsumed rewards first
        // Keep rows (points=0) so day_sit still shows Rewarded for past records
        $consumeResult = $conn->query("UPDATE rewards SET points = 0 WHERE idno = {$idno} AND points > 0 ORDER BY created_at ASC, reward_id ASC LIMIT {$pointsToConsume}");
        if (!$consumeResult) {
            throw new Exception("Failed to consume reward points: " . $conn->error);
        }

        // Remaining points after conversion
        $remainingStmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS remaining_points FROM rewards WHERE idno = ?");
        $remainingStmt->bind_param("i", $idno);
        $remainingStmt->execute();
        $remainingResult = $remainingStmt->get_result();
        $remainingRow = $remainingResult->fetch_assoc();
        $remainingPoints = (int)($remainingRow['remaining_points'] ?? 0);
        $remainingStmt->close();

        $sessionMessage = "{$pointsToConsume} reward points were converted to {$sessionsToAdd} session(s). Remaining points: {$remainingPoints}";
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