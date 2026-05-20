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

    // Get sit-in session duration to calculate total hours used
    // Use ABS() to handle sessions that cross midnight (TIME column wraps around)
    $sitinQuery = $conn->prepare("SELECT ABS(TIMESTAMPDIFF(MINUTE, time_in, time_out)) as duration_mins FROM sitin WHERE sitin_id = ?");
    $sitinQuery->bind_param("i", $sitin_id);
    $sitinQuery->execute();
    $sitinResult = $sitinQuery->get_result();
    $sitinRow = $sitinResult->fetch_assoc();
    $duration_mins = $sitinRow ? max(0, (int)$sitinRow['duration_mins']) : 0;
    $sitinQuery->close();

    $hours_used = round($duration_mins / 60.0, 2);
    $task_completed = isset($_POST['task_completed']) ? (int)$_POST['task_completed'] : 0;
    $leaderboard_score = round((1.0 * 0.60) + ($task_completed * 0.20) + ($hours_used * 0.20), 2);

    // 1. Insert reward record with task and hours details
    $stmt = $conn->prepare("INSERT INTO rewards (idno, lastname, firstname, sitin_id, rewarded_by, task_completed, hours_used, leaderboard_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issiiidd", $idno, $lastname, $firstname, $sitin_id, $_SESSION['user_id'], $task_completed, $hours_used, $leaderboard_score);
    $stmt->execute();
    $stmt->close();
    
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
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>