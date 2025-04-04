<?php
session_start();
require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Get reservation ID from POST data
if (!isset($_POST['id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["success" => false, "message" => "Reservation ID is required"]);
    exit();
}

$reservationId = $_POST['id'];
$username = $_SESSION['login_user'];

try {
    // Begin transaction
    $conn->begin_transaction();

    // 1. Verify the reservation belongs to the current user
    $verifyQuery = $conn->prepare("
        SELECT r.id 
        FROM reservations r
        JOIN users u ON r.idno = u.idno
        WHERE r.id = ? AND u.username = ? AND r.status = 'pending'
    ");
    $verifyQuery->bind_param("is", $reservationId, $username);
    $verifyQuery->execute();
    
    if ($verifyQuery->get_result()->num_rows === 0) {
        throw new Exception("Reservation not found or you don't have permission to delete it");
    }

    // 2. Delete the reservation
    $deleteQuery = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $deleteQuery->bind_param("i", $reservationId);
    
    if (!$deleteQuery->execute()) {
        throw new Exception("Failed to delete reservation: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Reservation deleted successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>