<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_SESSION['user_id'];
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $mname = $_POST['mname'] ?? '';
    $email = $_POST['email'] ?? '';
    $course = $_POST['course'] ?? '';
    $address = $_POST['address'] ?? '';

    // Handle file upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newFilename = "profile_" . $id . "_" . time() . "." . $ext;
            $upload_dir = 'uploads/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $newFilename)) {
                $profile_picture = $newFilename;
            } else {
                $_SESSION['profile_error'] = "Failed to upload image.";
                header("Location: Landing.php");
                exit();
            }
        } else {
            $_SESSION['profile_error'] = "Invalid file format. Only JPG, PNG, and GIF are allowed.";
            header("Location: Landing.php");
            exit();
        }
    }

    try {
        if ($profile_picture) {
            $sql = "UPDATE users SET FName = ?, LName = ?, MName = ?, email = ?, course = ?, address = ?, profile_picture = ? WHERE IDNum = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fname, $lname, $mname, $email, $course, $address, $profile_picture, $id]);
        } else {
            $sql = "UPDATE users SET FName = ?, LName = ?, MName = ?, email = ?, course = ?, address = ? WHERE IDNum = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fname, $lname, $mname, $email, $course, $address, $id]);
        }
        
        $_SESSION['user_name'] = trim($fname . " " . $lname);
        $_SESSION['login_success'] = "Profile updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['profile_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: Landing.php");
    exit();
}
?>
