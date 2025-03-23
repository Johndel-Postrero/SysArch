<?php
require __DIR__ . '/../config/db.php';

if (isset($_GET['idno'])) {
    $idno = $_GET['idno'];
    $sql = "SELECT idno, lastname, firstname, username, middlename, course, level, email, session, profile_picture
            FROM users
            WHERE idno = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Prepend the folder path to the profile_picture filename
        if (!empty($row['profile_picture'])) {
            $row['profile_picture'] = 'upload/' . $row['profile_picture'];
        } else {
            // If no profile picture is set, use a default image
            $row['profile_picture'] = 'images/default-profile.png';
        }

        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Student not found']);
    }
} else {
    echo json_encode(['error' => 'ID number not provided']);
}

$conn->close();
?>