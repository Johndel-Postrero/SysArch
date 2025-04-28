<?php
require __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: Log the received data
    error_log("Received POST data: " . print_r($_POST, true));
    error_log("Received FILES data: " . print_r($_FILES, true));

    // Validate required fields
    if (!isset($_POST['oldIdNo'], $_POST['idno'], $_POST['username'], $_POST['firstname'], $_POST['lastname'], $_POST['course'], $_POST['level'], $_POST['email'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }

    $oldIdNo = $_POST['oldIdNo']; // Old ID (for WHERE clause)
    $idno = $_POST['idno']; // New ID (for SET clause)
    $username = $_POST['username'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'] ?? ''; // Optional field
    $lastname = $_POST['lastname'];
    $course = $_POST['course'];
    $level = $_POST['level'];
    $email = $_POST['email'];
    $password = $_POST['password'] ?? ''; // New password (optional)

    // Start a transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        // Step 1: Check if the new idno already exists (if it's being changed)
        if ($oldIdNo !== $idno) {
            $checkIdNoQuery = "SELECT idno FROM users WHERE idno = ? AND idno != ?";
            $stmt = $conn->prepare($checkIdNoQuery);
            $stmt->bind_param("ss", $idno, $oldIdNo);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'The new ID number already exists.']);
                $conn->rollback();
                exit();
            }
            $stmt->close();
        }

        // Step 2: Fetch the existing profile picture and password from the database
        $fetchUserDataQuery = "SELECT profile_picture, password FROM users WHERE idno = ?";
        $stmt = $conn->prepare($fetchUserDataQuery);
        $stmt->bind_param("s", $oldIdNo);
        $stmt->execute();
        $stmt->bind_result($existingProfilePicture, $existingPassword);
        $stmt->fetch();
        $stmt->close();

        // Handle profile picture upload
        $profilePicture = $existingProfilePicture; // Default to the existing profile picture
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../upload/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true); // Create the directory if it doesn't exist
            }

            $fileName = basename($_FILES['profile_picture']['name']); // Get the filename only
            $uploadFile = $uploadDir . $fileName;

            // Move the uploaded file to the upload directory
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
                $profilePicture = $fileName; // Store only the filename in the database
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to upload profile picture']);
                $conn->rollback();
                exit();
            }
        }

        // Step 3: Hash the new password if provided
        $hashedPassword = $existingPassword; // Default to the existing password
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }

        // Step 4: Update the user's details, including the password
        $updateSql = "UPDATE users SET 
                    idno = ?, 
                    username = ?, 
                    firstname = ?, 
                    middlename = ?, 
                    lastname = ?, 
                    course = ?, 
                    level = ?, 
                    email = ?, 
                    profile_picture = ?, 
                    password = ? 
                    WHERE idno = ?";
                    
        $stmt = $conn->prepare($updateSql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssssss", $idno, $username, $firstname, $middlename, $lastname, $course, $level, $email, $profilePicture, $hashedPassword, $oldIdNo);
        
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        // Roll back the transaction if any part fails
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

$conn->close();
?>