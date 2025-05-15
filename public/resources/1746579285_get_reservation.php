<?php
session_start();
require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("HTTP/1.1 401 Unauthorized");
    die(json_encode(['error' => 'Unauthorized access']));
}

// Get reservation ID from query string
$reservationId = $_GET['id'] ?? null;
if (!$reservationId) {
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(['error' => 'Reservation ID is required']));
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
    die(json_encode(['error' => 'User not found']));
}

// Fetch reservation details
$reservationQuery = $conn->prepare("SELECT id, lab_number, reservation_date, time_in, purpose FROM reservations WHERE id = ? AND idno = ?");
$reservationQuery->bind_param("ii", $reservationId, $user['idno']);
$reservationQuery->execute();
$reservationResult = $reservationQuery->get_result();
$reservation = $reservationResult->fetch_assoc();

if (!$reservation) {
    header("HTTP/1.1 404 Not Found");
    die(json_encode(['error' => 'Reservation not found']));
}

header('Content-Type: application/json');
echo json_encode($reservation);
?>