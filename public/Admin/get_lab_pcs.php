<?php
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['lab'])) {
    echo json_encode(['success' => false, 'message' => 'Lab number not provided']);
    exit();
}

$lab_number = intval($_GET['lab']);

// Default 50 PCs
$pcs = [];
for ($i = 1; $i <= 50; $i++) {
    $pcs[$i] = 'available';
}

// 1. Check lab_pcs table for unavailable PCs
$stmt1 = $conn->prepare("SELECT pc_number, status FROM lab_pcs WHERE lab_number = ? AND pc_number <= 50");
if ($stmt1) {
    $stmt1->bind_param("i", $lab_number);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($row = $res1->fetch_assoc()) {
        $p = intval($row['pc_number']);
        if ($p >= 1 && $p <= 50) {
            if ($row['status'] === 'unavailable') {
                $pcs[$p] = 'occupied';
            }
        }
    }
    $stmt1->close();
}

// 2. Check active reservations currently sitting in
$stmt2 = $conn->prepare("SELECT pc_number FROM reservations WHERE lab_number = ? AND reservation_date = CURDATE() AND time_in_status = 'sit-inned'");
if ($stmt2) {
    $stmt2->bind_param("i", $lab_number);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $p = intval($row['pc_number']);
        if ($p >= 1 && $p <= 50) {
            $pcs[$p] = 'occupied';
        }
    }
    $stmt2->close();
}

// 3. Check active sit-in sessions directly in sitin table
$stmt3 = $conn->prepare("SELECT pc_number FROM sitin WHERE lab_number = ? AND time_out IS NULL");
if ($stmt3) {
    $stmt3->bind_param("i", $lab_number);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) {
        $p = intval($row['pc_number']);
        if ($p >= 1 && $p <= 50) {
            $pcs[$p] = 'occupied';
        }
    }
    $stmt3->close();
}

$conn->close();

$formattedPcs = [];
$availableCount = 0;
$occupiedCount = 0;

for ($i = 1; $i <= 50; $i++) {
    $st = $pcs[$i];
    if ($st === 'available') $availableCount++;
    else $occupiedCount++;

    $formattedPcs[] = [
        'pc_number' => $i,
        'status' => $st
    ];
}

echo json_encode([
    'success' => true,
    'lab' => $lab_number,
    'available_count' => $availableCount,
    'occupied_count' => $occupiedCount,
    'pcs' => $formattedPcs
]);
?>
