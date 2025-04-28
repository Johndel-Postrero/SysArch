<?php
require __DIR__ .'/../../config/db.php';

if (!isset($_GET['date']) || !isset($_GET['lab'])) {
    header("HTTP/1.1 400 Bad Request");
    exit();
}

$date = $_GET['date'];
$lab = $_GET['lab'];

$schedules = [];

if ($lab === 'all') {
    $labs = ['524', '526', '528', '530', '542', '544'];
    
    foreach ($labs as $labNum) {
        $stmt = $conn->prepare("
            SELECT id, TIME_FORMAT(start_time, '%H:%i') as start_time, 
                   TIME_FORMAT(end_time, '%H:%i') as end_time, 
                   status, reason 
            FROM lab_schedule 
            WHERE schedule_type = 'specific' 
              AND specific_date = ? 
              AND lab_number = ?
            ORDER BY start_time
        ");
        $stmt->bind_param("ss", $date, $labNum);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $schedules[$labNum] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare("
        SELECT id, TIME_FORMAT(start_time, '%H:%i') as start_time, 
               TIME_FORMAT(end_time, '%H:%i') as end_time, 
               status, reason 
        FROM lab_schedule 
        WHERE schedule_type = 'specific' 
          AND specific_date = ? 
          AND lab_number = ?
        ORDER BY start_time
    ");
    $stmt->bind_param("ss", $date, $lab);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules[$lab] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($schedules);
?>