<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit();
}

require __DIR__ . '/../config/db.php';

$idno = $_SESSION['login_user'];

// Start transaction
$conn->begin_transaction();

try {
    // 1. Fetch current unconverted reward points
    $pointsStmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS total_points FROM rewards WHERE idno = ? AND points > 0");
    $pointsStmt->bind_param("s", $idno);
    $pointsStmt->execute();
    $pointsRes = $pointsStmt->get_result()->fetch_assoc();
    $total_points = (int)($pointsRes['total_points'] ?? 0);
    $pointsStmt->close();

    if ($total_points < 3) {
        throw new Exception("You need at least 3 reward points to convert to 1 session.");
    }

    // 2. Fetch current session count and user_id
    $userStmt = $conn->prepare("SELECT user_id, session FROM users WHERE idno = ?");
    $userStmt->bind_param("s", $idno);
    $userStmt->execute();
    $userRes = $userStmt->get_result()->fetch_assoc();
    if (!$userRes) {
        throw new Exception("User not found.");
    }
    $user_id = $userRes['user_id'];
    $current_sessions = (int)$userRes['session'];
    $userStmt->close();

    // 3. Calculate sessions to add and check the 30-session limit
    $sessionsToAdd = intdiv($total_points, 3);
    $newSessions = $current_sessions + $sessionsToAdd;

    if ($newSessions > 30) {
        throw new Exception("Conversion rejected! Your session count would exceed the maximum limit of 30 (Current: {$current_sessions}, Attempting to add: {$sessionsToAdd}).");
    }

    $pointsToConsume = $sessionsToAdd * 3;

    // 4. Update the user's session count in users table
    $updateStmt = $conn->prepare("UPDATE users SET session = session + ? WHERE idno = ?");
    $updateStmt->bind_param("is", $sessionsToAdd, $idno);
    $updateResult = $updateStmt->execute();
    $updateStmt->close();

    if (!$updateResult) {
        throw new Exception("Failed to update student session count: " . $conn->error);
    }

    // 5. Consume reward points (set points = 0) from oldest rewards first
    $consumeStmt = $conn->prepare("UPDATE rewards SET points = 0 WHERE idno = ? AND points > 0 ORDER BY created_at ASC, reward_id ASC LIMIT ?");
    $consumeStmt->bind_param("si", $idno, $pointsToConsume);
    $consumeResult = $consumeStmt->execute();
    $consumeStmt->close();

    if (!$consumeResult) {
        throw new Exception("Failed to consume reward points: " . $conn->error);
    }

    // 6. Remaining points after conversion
    $remainingStmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS remaining_points FROM rewards WHERE idno = ? AND points > 0");
    $remainingStmt->bind_param("s", $idno);
    $remainingStmt->execute();
    $remainingRes = $remainingStmt->get_result()->fetch_assoc();
    $remainingPoints = (int)($remainingRes['remaining_points'] ?? 0);
    $remainingStmt->close();

    // 7. Create notification for the student
    $sessionMessage = "Successfully converted {$pointsToConsume} reward points to {$sessionsToAdd} session(s). Remaining unconverted points: {$remainingPoints}";
    $notifStmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    $notifStmt->bind_param("si", $sessionMessage, $user_id);
    $notifStmt->execute();
    $notifStmt->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Successfully converted {$pointsToConsume} points to {$sessionsToAdd} session(s)!",
        'unconvertedPoints' => $remainingPoints,
        'currentSessions' => $newSessions
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
