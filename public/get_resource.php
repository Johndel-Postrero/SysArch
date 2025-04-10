<?php
// get_resources.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!isset($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Resource ID is required']);
    exit();
}

$id = (int)$_GET['id'];
$admin_id = $_SESSION['user_id'];

// Get resource details
$stmt = $conn->prepare("SELECT * FROM resources WHERE id = ? AND admin_id = ?");
$stmt->bind_param("ii", $id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Resource not found or access denied']);
    exit();
}

$resource = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($resource);
exit();
?>