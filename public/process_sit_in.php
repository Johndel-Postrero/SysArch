<?php
session_start();
require __DIR__ . '/../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idno = $_POST['idno'];
    $room_number = $_POST['room_number'];
    $purpose = $_POST['purpose'] === 'Others' ? $_POST['other_reason'] : $_POST['purpose'];
    $time_in = date("H:i:s"); // Current time
    $reservation_date = date("Y-m-d"); // Current date

    // Preserve the previous search query
    $query = isset($_GET['query']) ? $_GET['query'] : (isset($_SESSION['query']) ? $_SESSION['query'] : '');

    // Check if the user has remaining sessions (optional, just for validation)
    $sessionCheckQuery = $conn->prepare("SELECT session FROM users WHERE idno = ?");
    $sessionCheckQuery->bind_param("i", $idno);
    $sessionCheckQuery->execute();
    $sessionCheckQuery->bind_result($remainingSessions);
    $sessionCheckQuery->fetch();
    $sessionCheckQuery->close();

    if ($remainingSessions > 0) {
        // Insert reservation data WITHOUT deducting session count
        $insertQuery = $conn->prepare("INSERT INTO reservations (idno, room_number, reservation_date, time_in, purpose) VALUES (?, ?, ?, ?, ?)");
        $insertQuery->bind_param("iisss", $idno, $room_number, $reservation_date, $time_in, $purpose);

        if ($insertQuery->execute()) {
            $_SESSION['success'] = "Sit-in successfully recorded!";
        } else {
            $_SESSION['error'] = "Error processing sit-in. Please try again.";
        }

        $insertQuery->close();
    } else {
        $_SESSION['error'] = "No remaining sessions available.";
    }
}

$conn->close();

// Redirect with the same search query to display results again
if (!empty($query)) {
    $_SESSION['query'] = $query; // Store in session for persistence
    header("Location: search_results.php?query=" . urlencode($query));
} else {
    header("Location: search_results.php");
}
exit();