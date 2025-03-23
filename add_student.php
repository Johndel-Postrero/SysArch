<?php
require __DIR__ . '/../config/db.php'; // Include the database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $idno = $_POST['idno'];
    $username = $_POST['username'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'] ?? '';
    $lastname = $_POST['lastname'];
    $course = $_POST['course'];
    $level = $_POST['level'];
    $email = $_POST['email'];
    $password = $_POST['password']; // Default password generated in JavaScript
    $role = 'student'; // Default role for students

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Handle profile picture upload
    $profilePicture = 'default-profile.png'; // Default profile picture
    if (isset($_FILES['profile_picture'])) {
        $uploadDir = __DIR__ . '/../public/upload/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true); // Create the directory if it doesn't exist
        }

        $fileName = basename($_FILES['profile_picture']['name']); // Get the filename only
        $uploadFile = $uploadDir . $fileName;

        // Move the uploaded file to the upload directory
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
            $profilePicture = $fileName; // Store only the filename in the database
        }
    }

    // Insert the new student into the database
    $sql = "INSERT INTO users (idno, username, firstname, middlename, lastname, course, level, email, password, profile_picture, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $idno, $username, $firstname, $middlename, $lastname, $course, $level, $email, $hashedPassword, $profilePicture, $role);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>