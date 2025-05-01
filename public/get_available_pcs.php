<?php
session_start();
require __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['lab'])) {
    echo json_encode(['success' => false, 'message' => 'Lab number not provided']);
    exit();
}

$lab_number = (int)$_GET['lab'];
$stmt = $conn->prepare("SELECT pc_number, status FROM lab_pcs WHERE lab_number = ? AND status = 'available' ORDER BY pc_number");
$stmt->bind_param("i", $lab_number);
$stmt->execute();
$result = $stmt->get_result();

$pcs = [];
while ($row = $result->fetch_assoc()) {
    $pcs[] = [
        'pc_number' => $row['pc_number'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'pcs' => $pcs
]);
?>