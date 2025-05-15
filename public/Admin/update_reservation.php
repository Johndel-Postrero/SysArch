<?php
session_start();
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login_user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reservation_id']) && isset($_POST['status'])) {
    $reservationId = (int)$_POST['reservation_id'];
    $status = $_POST['status'];
    
    try {
        // Get reservation details
        $stmt = $conn->prepare("SELECT r.*, u.firstname, u.lastname, u.idno FROM reservations r JOIN users u ON r.idno = u.idno WHERE r.reservation_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $reservationId);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $reservation = $result->fetch_assoc();
        $stmt->close();
        
        if (!$reservation) {
            throw new Exception("Reservation not found");
        }
        
        // Update reservation status
        $updateStmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
        if (!$updateStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $updateStmt->bind_param("si", $status, $reservationId);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update reservation: " . $updateStmt->error);
        }
        $updateStmt->close();
        
        // Send notification to student
        $studentMessage = "Your reservation for Lab " . $reservation['lab_number'] . ", PC " . $reservation['pc_number'] . 
                         " on " . $reservation['reservation_date'] . " has been " . $status;
        
        // Get user ID from idno
        $userStmt = $conn->prepare("SELECT user_id FROM users WHERE idno = ?");
        if (!$userStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $userStmt->bind_param("i", $reservation['idno']);
        if (!$userStmt->execute()) {
            throw new Exception("Execute failed: " . $userStmt->error);
        }
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        if ($user) {
            saveStudentNotification($studentMessage, $user['user_id'], $conn);
        }
        
        echo json_encode(['success' => true, 'message' => 'Reservation updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();

function saveStudentNotification($message, $userId, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    if ($stmt) {
        $stmt->bind_param("si", $message, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?>