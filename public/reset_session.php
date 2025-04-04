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

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);
$idno = isset($input['idno']) ? $input['idno'] : null;

if ($idno) {
    // Reset session for a specific student
    $stmt = $conn->prepare("UPDATE users SET session = 30 WHERE idno = ? AND role = 'student'");
    $stmt->bind_param("s", $idno);
    $result = $stmt->execute();
    $stmt->close();
} else {
    // Reset session for all students
    $result = $conn->query("UPDATE users SET session = 30 WHERE role = 'student'");
}

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>