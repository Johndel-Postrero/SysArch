<?php
session_start();
require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Content-Type: application/json");
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Get reservation ID from POST data
if (!isset($_POST['reservation_id'])) {
    header("Content-Type: application/json");
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["success" => false, "message" => "Reservation ID is required"]);
    exit();
}

$reservationId = $_POST['reservation_id'];

try {
    // Begin transaction
    $conn->begin_transaction();

    // 1. Get reservation details with validation
    $reservationQuery = $conn->prepare("
        SELECT r.idno, r.lab_number, r.reservation_date, r.time_in, r.purpose,
               TIMESTAMPDIFF(MINUTE, NOW(), TIMESTAMP(r.reservation_date, r.time_in)) AS minutes_remaining,
               TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.reservation_date, r.time_in), NOW()) AS minutes_passed
        FROM reservations r
        WHERE r.id = ? AND r.status = 'approved'
    ");
    $reservationQuery->bind_param("i", $reservationId);
    $reservationQuery->execute();
    $reservation = $reservationQuery->get_result()->fetch_assoc();

    if (!$reservation) {
        throw new Exception("Reservation not found or not approved");
    }

    // 2. Validate time window (-30 minutes to +15 minutes)
    if ($reservation['minutes_remaining'] > 30) {
        throw new Exception("Sit-in is only allowed starting 30 minutes before the reservation time");
    }

    if ($reservation['minutes_passed'] > 15) {
        throw new Exception("Sit-in grace period has expired (15 minutes after reservation time)");
    }

    // 3. Check if user already has an active sit-in (not timed out)
    $activeCheckQuery = $conn->prepare("
        SELECT id FROM sitin 
        WHERE idno = ? AND time_out IS NULL
    ");
    $activeCheckQuery->bind_param("i", $reservation['idno']);
    $activeCheckQuery->execute();

    if ($activeCheckQuery->get_result()->num_rows > 0) {
        throw new Exception("You already have an active sit-in. Please time out first before starting a new one.");
    }

    // 4. Check if this specific reservation already has a sit-in recorded
    $checkQuery = $conn->prepare("
        SELECT id FROM sitin 
        WHERE idno = ? AND lab_number = ? AND sitin_date = ? AND time_in = ?
    ");
    $checkQuery->bind_param("iiss", 
        $reservation['idno'], 
        $reservation['lab_number'], 
        $reservation['reservation_date'], 
        $reservation['time_in']
    );
    $checkQuery->execute();
    
    if ($checkQuery->get_result()->num_rows > 0) {
        throw new Exception("Sit-in already recorded for this reservation");
    }

    // 5. Record sit-in
    $insertQuery = $conn->prepare("
        INSERT INTO sitin (idno, lab_number, purpose, sitin_date, time_in, time_out)
        VALUES (?, ?, ?, ?, ?, NULL)
    ");
    $insertQuery->bind_param("iisss", 
        $reservation['idno'], 
        $reservation['lab_number'], 
        $reservation['purpose'], 
        $reservation['reservation_date'], 
        $reservation['time_in']
    );
    
    if (!$insertQuery->execute()) {
        throw new Exception("Failed to record sit-in: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();

    header("Content-Type: application/json");
    echo json_encode(["success" => true, "message" => "Sit-in recorded successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    header("Content-Type: application/json");
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>