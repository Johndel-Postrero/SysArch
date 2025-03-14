<?php
// reset_session.php

session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../config/db.php';

// Reset the session for all students
$sql = "UPDATE users SET session = 30 WHERE role = 'student'";
if ($conn->query($sql)) {
    // Session reset successfully
    echo json_encode(['success' => true]);
} else {
    // Error occurred
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>