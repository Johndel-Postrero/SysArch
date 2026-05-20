<?php
error_reporting(0); // Suppress any warnings or notices from polluting the JSON output
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    require __DIR__ . '/../../config/db.php';
    
    $response = ['available' => true];

    if (isset($_GET['idno'])) {
        $idno = trim($_GET['idno']);
        $exclude = trim($_GET['exclude'] ?? '');
        
        if (!empty($idno)) {
            $sql = "SELECT idno FROM users WHERE idno = ? AND idno != ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $idno, $exclude);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $response['available'] = false;
                }
                $stmt->close();
            }
        }
    } elseif (isset($_GET['username'])) {
        $username = trim($_GET['username']);
        $exclude = trim($_GET['exclude'] ?? '');
        
        if (!empty($username)) {
            $sql = "SELECT username FROM users WHERE username = ? AND idno != ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $username, $exclude);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $response['available'] = false;
                }
                $stmt->close();
            }
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['available' => true, 'error' => $e->getMessage()]);
}

if (isset($conn) && $conn) {
    @$conn->close();
}
?>
