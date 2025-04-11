<?php
require __DIR__ . '/../config/db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Resource ID not provided']);
    exit();
}

$resourceId = (int)$_GET['id'];

// Fetch resource details
$query = "SELECT * FROM resources WHERE id = $resourceId";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $resource = $result->fetch_assoc();
    echo json_encode($resource);
} else {
    echo json_encode(['error' => 'Resource not found']);
}

$conn->close();
?>