<?php
require __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['lab'])) {
    echo json_encode(['success' => false, 'message' => 'Lab number is required']);
    exit();
}

$labNumber = (int)$_GET['lab'];

try {
    $query = "SELECT pc_number, status FROM lab_pcs WHERE lab_number = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $labNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while ($row = $result->fetch_assoc()) {
        $pcs[] = $row;
    }
    
    echo json_encode(['success' => true, 'pcs' => $pcs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}