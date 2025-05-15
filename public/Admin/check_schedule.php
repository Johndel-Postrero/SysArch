<?php
// Prevent direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Access denied']));
}

ob_start();
session_start();
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['login_user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Authentication required']));
}

try {
    $lab_number = $_POST['lab_number'] ?? '';
    $schedule_type = $_POST['schedule_type'] ?? '';
    $weekday = $_POST['weekday'] ?? null;
    $specific_date = $_POST['specific_date'] ?? null;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($lab_number) || empty($schedule_type) || empty($start_time) || empty($end_time)) {
        throw new Exception('Missing required fields');
    }

    $response = [
        'has_conflicts' => false,
        'conflicts' => []
    ];

    $labs_to_check = ($lab_number === 'all') ? ['524', '526', '528', '530', '542', '544'] : [$lab_number];
    
    foreach ($labs_to_check as $lab) {
        if ($schedule_type === 'recurring') {
            $sql = "SELECT id, lab_number, 
                    TIME_FORMAT(start_time, '%H:%i') as start_time, 
                    TIME_FORMAT(end_time, '%H:%i') as end_time
                    FROM lab_schedule 
                    WHERE lab_number = ? 
                    AND schedule_type = 'recurring' 
                    AND weekday = ? 
                    AND (
                        (status = 'unavailable' AND (
                            (start_time < ? AND end_time > ?) OR 
                            (start_time < ? AND end_time > ?) OR 
                            (start_time >= ? AND end_time <= ?)
                        )) OR
                        (status = 'available' AND ? = 'unavailable' AND (
                            (start_time < ? AND end_time > ?) OR 
                            (start_time < ? AND end_time > ?) OR 
                            (start_time >= ? AND end_time <= ?)
                        ))
                    )";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $bind_params = [
                $lab, $weekday, 
                $end_time, $start_time, 
                $end_time, $start_time, 
                $start_time, $end_time,
                $status,
                $end_time, $start_time, 
                $end_time, $start_time, 
                $start_time, $end_time
            ];
            
            $types = str_repeat('s', count($bind_params));
            $stmt->bind_param($types, ...$bind_params);
        } else {
            $sql = "SELECT id, lab_number, 
                    TIME_FORMAT(start_time, '%H:%i') as start_time, 
                    TIME_FORMAT(end_time, '%H:%i') as end_time,
                    DATE_FORMAT(specific_date, '%Y-%m-%d') as specific_date
                    FROM lab_schedule 
                    WHERE lab_number = ? 
                    AND schedule_type = 'specific' 
                    AND specific_date = ? 
                    AND (
                        (status = 'unavailable' AND (
                            (start_time < ? AND end_time > ?) OR 
                            (start_time < ? AND end_time > ?) OR 
                            (start_time >= ? AND end_time <= ?)
                        )) OR
                        (status = 'available' AND ? = 'unavailable' AND (
                            (start_time < ? AND end_time > ?) OR 
                            (start_time < ? AND end_time > ?) OR 
                            (start_time >= ? AND end_time <= ?)
                        ))
                    )";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $bind_params = [
                $lab, $specific_date, 
                $end_time, $start_time, 
                $end_time, $start_time, 
                $start_time, $end_time,
                $status,
                $end_time, $start_time, 
                $end_time, $start_time, 
                $start_time, $end_time
            ];
            
            $types = str_repeat('s', count($bind_params));
            $stmt->bind_param($types, ...$bind_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['has_conflicts'] = true;
            $row['lab'] = $lab;
            $response['conflicts'][] = $row;
        }
        
        $stmt->close();
    }
    
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>