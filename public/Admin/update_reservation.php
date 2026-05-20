<?php
session_start();
date_default_timezone_set('Asia/Manila');
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
        
        // AUTO TIME-IN LOGIC
        // If reservation is approved, and it is for today and start time has already passed
        if ($status === 'approved') {
            $currentDate = date('Y-m-d');
            $currentTime = date('H:i:s');
            
            if ($reservation['reservation_date'] === $currentDate && $reservation['time_in'] <= $currentTime) {
                // Verify student is not currently sitting in
                $checkActiveSitin = $conn->prepare("
                    SELECT sitin_id FROM sitin 
                    WHERE idno = ? AND lab_number = ? AND sitin_date = ? AND time_out IS NULL
                ");
                if ($checkActiveSitin) {
                    $checkActiveSitin->bind_param("iis", 
                        $reservation['idno'],
                        $reservation['lab_number'],
                        $reservation['reservation_date']
                    );
                    $checkActiveSitin->execute();
                    $activeResult = $checkActiveSitin->get_result();
                    $hasActive = ($activeResult->num_rows > 0);
                    $checkActiveSitin->close();
                    
                    // Verify PC in that lab is not occupied
                    $checkPcOccupied = $conn->prepare("
                        SELECT sitin_id FROM sitin 
                        WHERE lab_number = ? AND pc_number = ? AND time_out IS NULL
                    ");
                    $hasOccupied = false;
                    if ($checkPcOccupied) {
                        $checkPcOccupied->bind_param("ii", 
                            $reservation['lab_number'],
                            $reservation['pc_number']
                        );
                        $checkPcOccupied->execute();
                        $occupiedResult = $checkPcOccupied->get_result();
                        $hasOccupied = ($occupiedResult->num_rows > 0);
                        $checkPcOccupied->close();
                    }
                    
                    if (!$hasActive && !$hasOccupied) {
                        // Record sit-in
                        $insertSitin = $conn->prepare("
                            INSERT INTO sitin (idno, lab_number, pc_number, sitin_date, time_in, purpose) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        if ($insertSitin) {
                            $insertSitin->bind_param("iiisss", 
                                $reservation['idno'],
                                $reservation['lab_number'],
                                $reservation['pc_number'],
                                $reservation['reservation_date'],
                                $reservation['time_in'],
                                $reservation['purpose']
                            );
                            $insertSitin->execute();
                            $insertSitin->close();
                        }
                        
                        // Update reservation status to sit-inned
                        $updateTimeInStatus = $conn->prepare("
                            UPDATE reservations SET time_in_status = 'sit-inned' 
                            WHERE reservation_id = ?
                        ");
                        if ($updateTimeInStatus) {
                            $updateTimeInStatus->bind_param("i", $reservationId);
                            $updateTimeInStatus->execute();
                            $updateTimeInStatus->close();
                        }
                        
                        $studentMessage = "Your reservation for Lab " . $reservation['lab_number'] . ", PC " . $reservation['pc_number'] . 
                                         " on " . $reservation['reservation_date'] . " has been approved and automatically marked as sit-inned. Please proceed to the lab.";
                    }
                }
            }
        }
        
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