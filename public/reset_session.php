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

// Begin transaction for atomic operations
$conn->begin_transaction();

try {
    if ($idno) {
        // Reset session and points for a specific student
        // Update session in users table
        $stmt = $conn->prepare("UPDATE users SET session = 30 WHERE idno = ? AND role = 'student'");
        $stmt->bind_param("s", $idno);
        $stmt->execute();
        $stmt->close();
        
        // Delete all reward points for this student
        $stmt = $conn->prepare("DELETE FROM rewards WHERE idno = ?");
        $stmt->bind_param("s", $idno);
        $stmt->execute();
        $stmt->close();
    } else {
        // Reset session and points for all students
        // Update session for all students
        $conn->query("UPDATE users SET session = 30 WHERE role = 'student'");
        
        // Delete all reward points for all students
        $conn->query("DELETE FROM rewards WHERE idno IN (SELECT idno FROM users WHERE role = 'student')");
    }
    
    // Commit the transaction if all queries succeeded
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback the transaction if any query failed
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>