<?php
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || trim($_GET['q']) === '') {
    echo json_encode(['students' => [], 'sitins' => []]);
    exit();
}

$query = trim($_GET['q']);
$likeQuery = '%' . $query . '%';

$students = [];
$sitins = [];

// 1. Search Students
$stmt1 = $conn->prepare("SELECT idno, firstname, lastname, course, level, session FROM users WHERE role = 'student' AND (idno LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?) LIMIT 6");
if ($stmt1) {
    $stmt1->bind_param("ssss", $likeQuery, $likeQuery, $likeQuery, $likeQuery);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($row = $res1->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt1->close();
}

// 2. Search Active Sit-ins
$stmt2 = $conn->prepare("SELECT s.sitin_id, s.idno, u.firstname, u.lastname, s.lab_number, s.purpose, s.time_in FROM sitin s JOIN users u ON s.idno = u.idno WHERE s.time_out IS NULL AND (s.idno LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR s.lab_number LIKE ? OR s.purpose LIKE ?) LIMIT 4");
if ($stmt2) {
    $stmt2->bind_param("sssss", $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $sitins[] = $row;
    }
    $stmt2->close();
}

$conn->close();

echo json_encode([
    'students' => $students,
    'sitins' => $sitins
]);
?>
