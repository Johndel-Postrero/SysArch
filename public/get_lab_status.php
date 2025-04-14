<?php
require __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$lab_numbers = ['524', '526', '528', '530', '542', '544'];
$current_time = date('H:i:s');
$current_weekday = date('N');
$lab_status = [];

foreach ($lab_numbers as $lab) {
    // Check occupancy
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sitin WHERE lab_number = ? AND time_out IS NULL");
    $stmt->bind_param("s", $lab);
    $stmt->execute();
    $res = $stmt->get_result();
    $occupancy = $res->fetch_assoc()['count'];
    $stmt->close();
    
    $lab_status[$lab] = [
        'is_available' => true,
        'reason' => ''
    ];
    
    // Check if lab is full
    if ($occupancy >= 5) {
        $lab_status[$lab] = [
            'is_available' => false,
            'reason' => 'Lab full'
        ];
        continue;
    }
    
    // Check schedule
    $schedule_check = $conn->prepare("
        SELECT status, reason FROM lab_schedule 
        WHERE lab_number = ? 
        AND DAYOFWEEK(start_datetime) = ?
        AND TIME(start_datetime) <= ? 
        AND TIME(end_datetime) >= ?
        AND status = 'unavailable'
        LIMIT 1
    ");
    $schedule_check->bind_param("siss", $lab, $current_weekday, $current_time, $current_time);
    $schedule_check->execute();
    $schedule_result = $schedule_check->get_result()->fetch_assoc();
    $schedule_check->close();
    
    if ($schedule_result) {
        $lab_status[$lab] = [
            'is_available' => false,
            'reason' => $schedule_result['reason'] ?? 'Unavailable'
        ];
    }
}

echo json_encode($lab_status);
$conn->close();
?>  