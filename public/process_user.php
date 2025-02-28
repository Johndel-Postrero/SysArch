<?php
// Include database connection
require __DIR__ . '/../config/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $course = $_POST['course'];
    $level = $_POST['level'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $raw_password = $_POST['password'];
    $role = $_POST['role'];

    // Hash the password
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
    // Default session value: 30 for students, NULL for admins/staff
    $session = ($role == 'student') ? 30 : NULL;

    // Modify your SQL statement to include `session`
    $stmt = $conn->prepare("INSERT INTO users (idno, lastname, firstname, middlename, course, level, email, username, password, role, session) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssssssi", $idno, $lastname, $firstname, $middlename, $course, $level, $email, $username, $hashed_password, $role, $session);


    // Execute query
    if ($stmt->execute()) {
        echo "User added successfully! <a href='add_user.php'>Add Another User</a>";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close connections
    $stmt->close();
    $conn->close();
}
?>