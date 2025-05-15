<?php
// Add at the top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (!isset($_SESSION['login_user'])) {
    header("HTTP/1.1 401 Unauthorized");
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require __DIR__ . '/../config/db.php';

if (!isset($_GET['resource_id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Resource ID is required']);
    exit();
}

$id = (int)$_GET['resource_id'];

// Get resource details
$stmt = $conn->prepare("SELECT * FROM resources WHERE resource_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Resource not found']);
    exit();
}

$resource = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($resource);
exit();
?> 