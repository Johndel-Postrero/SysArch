<?php
session_start();
require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("HTTP/1.1 401 Unauthorized");
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Get POST data
$reservationId = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

// Check if this is an admin status update
if ($status && in_array($status, ['approved', 'declined'])) {
    // ADMIN STATUS UPDATE
    
    // Check if user is admin
    $username = $_SESSION['login_user'];
    $userQuery = $conn->prepare("SELECT role FROM users WHERE username = ?");
    $userQuery->bind_param("s", $username);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $user = $userResult->fetch_assoc();

    if (!$user || $user['role'] !== 'admin') {
        header("HTTP/1.1 403 Forbidden");
        die(json_encode(['success' => false, 'message' => 'Admin privileges required']));
    }

    // Update reservation status
    $updateQuery = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $updateQuery->bind_param("si", $status, $reservationId);

    if ($updateQuery->execute()) {
        echo json_encode(['success' => true, 'message' => 'Reservation status updated successfully!']);
    } else {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    exit();
}

// STUDENT RESERVATION UPDATE (original functionality)
$labNumber = $_POST['lab_number'] ?? null;
$reservationDate = $_POST['reservation_date'] ?? null;
$timeIn = $_POST['time_in'] ?? null;
$purpose = $_POST['purpose'] ?? null;
$otherReason = $_POST['other_reason'] ?? null;

// Validate inputs
if (!$reservationId || !$labNumber || !$reservationDate || !$timeIn || !$purpose) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['success' => false, 'message' => 'All fields are required']));
}

// Fetch user's details
$username = $_SESSION['login_user'];
$userQuery = $conn->prepare("SELECT idno FROM users WHERE username = ?");
$userQuery->bind_param("s", $username);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();

if (!$user) {
    header("HTTP/1.1 404 Not Found");
    die(json_encode(['success' => false, 'message' => 'User not found']));
}

// Verify the reservation belongs to the user
$verifyQuery = $conn->prepare("SELECT id FROM reservations WHERE id = ? AND idno = ?");
$verifyQuery->bind_param("ii", $reservationId, $user['idno']);
$verifyQuery->execute();
$verifyQuery->store_result();

if ($verifyQuery->num_rows === 0) {
    header("HTTP/1.1 403 Forbidden");
    die(json_encode(['success' => false, 'message' => 'Reservation not found or you don\'t have permission to edit it']));
}

// Handle "Others" purpose
if ($purpose === 'Others' && $otherReason) {
    $purpose = $otherReason;
}

// Convert time to 24-hour format
$time_24hr = date("H:i", strtotime($timeIn));
if (!$time_24hr) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['success' => false, 'message' => 'Invalid time format']));
}

// Check if the new time/date conflicts with existing reservations
$conflictQuery = $conn->prepare("SELECT id FROM reservations WHERE idno = ? AND reservation_date = ? AND time_in = ? AND id != ?");
$conflictQuery->bind_param("issi", $user['idno'], $reservationDate, $time_24hr, $reservationId);
$conflictQuery->execute();
$conflictQuery->store_result();

if ($conflictQuery->num_rows > 0) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['success' => false, 'message' => 'You already have a reservation at this time']));
}

// Check lab availability
$labCheckQuery = $conn->prepare("SELECT id FROM reservations WHERE lab_number = ? AND reservation_date = ? AND time_in = ? AND id != ?");
$labCheckQuery->bind_param("issi", $labNumber, $reservationDate, $time_24hr, $reservationId);
$labCheckQuery->execute();
$labCheckQuery->store_result();

if ($labCheckQuery->num_rows > 0) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['success' => false, 'message' => 'Lab already reserved at this time']));
}

// Update reservation
$updateQuery = $conn->prepare("UPDATE reservations SET lab_number = ?, reservation_date = ?, time_in = ?, purpose = ?, status = 'pending' WHERE id = ?");
$updateQuery->bind_param("isssi", $labNumber, $reservationDate, $time_24hr, $purpose, $reservationId);

if ($updateQuery->execute()) {
    echo json_encode(['success' => true, 'message' => 'Reservation updated successfully!']);
} else {
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>