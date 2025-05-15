<?php
require __DIR__ . '/../../config/db.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Get current date and time
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

try {
    // Start transaction
    $conn->begin_transaction();

    // Get approved reservations that need to be auto-timed in
    $query = "SELECT r.*, u.user_id 
              FROM reservations r 
              JOIN users u ON r.idno = u.idno 
              WHERE r.status = 'approved' 
              AND r.time_in_status = 'pending'
              AND r.reservation_date = ?
              AND r.time_in <= ?
              AND NOT EXISTS (
                  SELECT 1 FROM sitin s 
                  WHERE s.idno = r.idno 
                  AND s.lab_number = r.lab_number 
                  AND s.sitin_date = r.reservation_date 
                  AND s.time_out IS NULL
              )";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $currentDate, $currentTime);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $reservations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($reservations as $reservation) {
        // Mark PC as unavailable
        $updatePcStatus = $conn->prepare("UPDATE lab_pcs SET status = 'unavailable' WHERE lab_number = ? AND pc_number = ?");
        if (!$updatePcStatus) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $updatePcStatus->bind_param("ii", $reservation['lab_number'], $reservation['pc_number']);
        
        if (!$updatePcStatus->execute()) {
            throw new Exception("Failed to update PC status: " . $updatePcStatus->error);
        }
        $updatePcStatus->close();

        // Record sit-in
        $insertSitin = $conn->prepare("INSERT INTO sitin (idno, lab_number, sitin_date, time_in, purpose) VALUES (?, ?, ?, ?, ?)");
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

        // Update reservation status
        $updateReservation = $conn->prepare("UPDATE reservations SET time_in_status = 'sit-inned' WHERE reservation_id = ?");
        if (!$updateReservation) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $updateReservation->bind_param("i", $reservation['reservation_id']);
        
        if (!$updateReservation->execute()) {
            throw new Exception("Failed to update reservation status: " . $updateReservation->error);
        }
        $updateReservation->close();

        // Send notification to student
        $studentMessage = "Your reservation for Lab " . $reservation['lab_number'] . ", PC " . $reservation['pc_number'] . 
                         " on " . $reservation['reservation_date'] . " at " . $reservation['time_in'] . 
                         " has been automatically marked as sit-inned. Please proceed to the lab.";

        saveStudentNotification($studentMessage, $reservation['user_id'], $conn);
    }

    // Commit transaction
    $conn->commit();

} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    error_log("Auto Time In Error: " . $e->getMessage());
}

function saveStudentNotification($message, $userId, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    if ($stmt) {
        $stmt->bind_param("si", $message, $userId);
        $stmt->execute();
        $stmt->close();
    }
}
?> 