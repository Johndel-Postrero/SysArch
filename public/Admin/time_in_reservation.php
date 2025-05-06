<?php
session_start();
require __DIR__ . '/../../config/db.php';

// Clear any previous output
ob_start();

// Set header to ensure JSON response
header('Content-Type: application/json');

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST['reservation_id'])) {
        throw new Exception("Invalid request method or missing parameters");
    }

    $reservationId = (int)$_POST['reservation_id'];
    
    if ($reservationId <= 0) {
        throw new Exception("Invalid reservation ID");
    }

    // Start transaction
    $conn->begin_transaction();
    
    // 1. Get reservation details
    $reservationQuery = $conn->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
    if (!$reservationQuery) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $reservationQuery->bind_param("i", $reservationId);
    
    if (!$reservationQuery->execute()) {
        throw new Exception("Failed to fetch reservation details: " . $reservationQuery->error);
    }
    
    $reservation = $reservationQuery->get_result()->fetch_assoc();
    $reservationQuery->close();
    
    if (!$reservation) {
        throw new Exception("Reservation not found");
    }
    
    // 2. Validate reservation can be timed in
    if ($reservation['status'] != 'approved') {
        throw new Exception("Only approved reservations can be timed in");
    }
    
    if ($reservation['time_in_status'] != 'pending') {
        throw new Exception("Reservation already timed in or completed");
    }
    
    // 3. Check for existing active sit-in
    $checkSitin = $conn->prepare("SELECT sitin_id FROM sitin WHERE idno = ? AND lab_number = ? AND sitin_date = ? AND time_out IS NULL");
    if (!$checkSitin) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkSitin->bind_param("iis", 
        $reservation['idno'],
        $reservation['lab_number'],
        $reservation['reservation_date']
    );
    
    if (!$checkSitin->execute()) {
        throw new Exception("Failed to check sit-in records: " . $checkSitin->error);
    }
    
    if ($checkSitin->get_result()->num_rows > 0) {
        throw new Exception("Active sit-in already exists for this reservation");
    }
    $checkSitin->close();
    
    // 4. Mark PC as unavailable
    $updatePcStatus = $conn->prepare("INSERT INTO lab_pcs (lab_number, pc_number, status) 
                                    VALUES (?, ?, 'unavailable')
                                    ON DUPLICATE KEY UPDATE status = 'unavailable'");
    if (!$updatePcStatus) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $updatePcStatus->bind_param("ii", 
        $reservation['lab_number'],
        $reservation['pc_number']
    );
    
    if (!$updatePcStatus->execute()) {
        throw new Exception("Failed to update PC status: " . $updatePcStatus->error);
    }
    $updatePcStatus->close();
    
    // 5. Record sit-in (without time_out)
    $insertSitin = $conn->prepare("INSERT INTO sitin (idno, lab_number, sitin_date, time_in, purpose) 
                                  VALUES (?, ?, ?, ?, ?)");
    if (!$insertSitin) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $insertSitin->bind_param("iisss", 
        $reservation['idno'],
        $reservation['lab_number'],
        $reservation['reservation_date'],
        $reservation['time_in'],
        $reservation['purpose']
    );
    
    if (!$insertSitin->execute()) {
        throw new Exception("Failed to record sit-in: " . $insertSitin->error);
    }
    $insertSitin->close();
    
    // 6. Update reservation status to 'sit-inned'
    $updateReservation = $conn->prepare("UPDATE reservations SET time_in_status = 'sit-inned' WHERE reservation_id = ?");
    if (!$updateReservation) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $updateReservation->bind_param("i", $reservationId);
    
    if (!$updateReservation->execute()) {
        throw new Exception("Failed to update reservation status: " . $updateReservation->error);
    }
    $updateReservation->close();
    
    // Commit transaction
    $conn->commit();
    
    // Notify student
    $studentMessage = "Your reservation for Lab " . $reservation['lab_number'] . ", PC " . $reservation['pc_number'] . 
                     " on " . $reservation['reservation_date'] . " at " . $reservation['time_in'] . 
                     " has been marked as sit-inned. Please proceed to the lab.";

    $userStmt = $conn->prepare("SELECT user_id FROM users WHERE idno = ?");
    if ($userStmt) {
        $userStmt->bind_param("i", $reservation['idno']);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($user = $userResult->fetch_assoc()) {
            saveStudentNotification($studentMessage, $user['user_id'], $conn);
        }
        $userStmt->close();
    }

    $response['success'] = true;
    $response['message'] = 'Reservation successfully marked as sit-inned and PC marked as unavailable';
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    $response['message'] = $e->getMessage();
    error_log("Time In Reservation Error: " . $e->getMessage());
}

// Clear any output buffer
ob_end_clean();

echo json_encode($response);
exit();

function saveStudentNotification($message, $userId, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    if ($stmt) {
        $stmt->bind_param("si", $message, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?>