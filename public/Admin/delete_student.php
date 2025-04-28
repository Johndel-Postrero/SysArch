<?php
require __DIR__ . '/../../config/db.php'; // Include the database connection

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Get the ID number from the query string
    $idno = $_GET['idno'] ?? null;

    if (!$idno) {
        echo json_encode(['success' => false, 'error' => 'ID number is required.']);
        exit();
    }

    // Prepare the SQL query to delete the student
    $sql = "DELETE FROM users WHERE idno = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Failed to prepare the SQL statement.']);
        exit();
    }

    // Bind the ID number parameter and execute the query
    $stmt->bind_param("s", $idno);
    $stmt->execute();

    // Check if the deletion was successful
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No student found with the provided ID number.']);
    }

    // Close the statement and database connection
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>