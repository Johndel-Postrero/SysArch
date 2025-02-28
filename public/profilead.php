<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../config/db.php';

// Fetch user details
$current_username = $_SESSION['login_user'];
$user_sql = "SELECT * FROM users WHERE username = ?";
$stmtUser = $conn->prepare($user_sql);
$stmtUser->bind_param("s", $current_username);
$stmtUser->execute();
$user_result = $stmtUser->get_result();
$user = $user_result->fetch_assoc();
$stmtUser->close();

if (!$user) {
    header("Location: logout.php");
    exit();
}

$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname'] = $user['lastname'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $middlename = filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $idno = filter_input(INPUT_POST, 'idno', FILTER_SANITIZE_STRING);
    $profile_picture = $user['profile_picture'];

    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updatePassword = true;
    } else {
        $updatePassword = false;
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        $targetDir = __DIR__ . '/../public/upload/';
        $fileType = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        $allowedTypes = ["jpg", "jpeg", "png", "gif"];

        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(["success" => false, "message" => "Invalid file format."]);
            exit();
        }

        $newFileName = "profile_" . $idno . "_" . time() . "." . $fileType;
        $targetFilePath = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            $profile_picture = $newFileName;
        } else {
            echo json_encode(["success" => false, "message" => "Error uploading profile picture."]);
            exit();
        }
    }

    if ($updatePassword) {
        $update_sql = "UPDATE users SET email=?, username=?, lastname=?, firstname=?, middlename=?, password=?, profile_picture=? WHERE idno=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssi", $email, $username, $lastname, $firstname, $middlename, $hashedPassword, $profile_picture, $idno);
    } else {
      $update_sql = "UPDATE users SET email=?, username=?, lastname=?, firstname=?, middlename=?, profile_picture=?, idno=? WHERE idno=?";
      $stmt = $conn->prepare($update_sql);
      $stmt->bind_param("ssssssii", $email, $username, $lastname, $firstname, $middlename, $profile_picture, $idno, $user['idno']);
      
    }

    if ($stmt->execute()) {
        $_SESSION['login_user'] = $username;
        echo json_encode(["success" => true, "message" => "Profile updated successfully!", "profile_picture" => $profile_picture]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating profile: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
  <link rel="stylesheet" href="css/profilead.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Profile Settings</title>
  <style>
    input::placeholder {
    font-family: poppins-regular !important; /* Adjust the font family */
    font-size: 16px !important; /* Adjust the size as needed */
}

    body { font-family: "Poppins-Regular"; color: #333; font-size: 16px; margin: 0; }
    .inner form { width: 100%; }
    .sidebar { width: 5rem; transition: all 0.3s ease-in-out; }
    .sidebar:hover { width: 16rem; }
    .sidebar:hover .sidebar-text { display: inline; }
    .sidebar-text { display: none; }
    .sidebar a { display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .sidebar:hover a { justify-content: flex-start; }
    .sidebar i { font-size: 16px; }
    .main-content { margin-left: 5rem; transition: margin-left 0.3s ease-in-out; }
    .sidebar:hover + .main-content { margin-left: 16rem; }
    .div-button1 { height: 51px; border-radius: 6px; border: 1px solid #951313; }
    .div-button2 { height: 51px; color: white; background-color: #7952b3; border-radius: 6px; }
  </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
  <div class="flex h-screen">
    <?php include 'sidebarad.php'; ?>
    <div class="main-content flex-1 flex flex-col">
      <?php include 'headerad1.php'; ?>
      <div class="flex-1 p-6">
        <div class="inner max-w-lg mx-auto">
          <form id="profileForm" method="post" enctype="multipart/form-data">
            <div class="flex justify-center items-center w-full">
              <label for="profile-picture-upload" class="cursor-pointer relative">
                <img id="profile-picture-preview" 
                     src="<?php echo isset($user['profile_picture']) 
                     ? 'upload/' . htmlspecialchars($user['profile_picture']) 
                     : 'images/default-profile.png'; ?>" 
                     alt="Profile Picture" 
                     class="rounded-full w-24 h-24 mx-auto object-cover border-2 border-gray-300"/>
                <i class="zmdi zmdi-camera absolute bottom-2 right-2 bg-gray-700 text-white p-1 rounded-full"></i>
              </label>
              <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
            </div>
            <div class="form-wrapper">
                <input class="form-control mt-10" id="idno" name="idno" placeholder="ID Number" type="text" 
                    value="<?php echo isset($user['idno']) ? htmlspecialchars($user['idno']) : ''; ?>"/>
                <i class="zmdi zmdi-card"></i>
            </div>
            <div class="form-group gap-6" style="background-color: transparent !important;">
              <input class="form-control" id="lastname" name="lastname" placeholder="Last Name" type="text" 
                     value="<?php echo isset($user['lastname']) ? htmlspecialchars($user['lastname']) : ''; ?>"/>
              <input class="form-control" id="firstname" name="firstname" placeholder="First Name" type="text" 
                     value="<?php echo isset($user['firstname']) ? htmlspecialchars($user['firstname']) : ''; ?>"/>
              <input class="form-control" id="middlename" name="middlename" placeholder="Middle Name" type="text" 
                     value="<?php echo isset($user['middlename']) ? htmlspecialchars($user['middlename']) : ''; ?>"/>
            </div>
            <div class="form-wrapper">
              <input class="form-control" id="email" name="email" placeholder="Email Address" type="email" 
                     value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>"/>
              <i class="zmdi zmdi-email"></i>
            </div>
            <div class="form-wrapper">
              <input class="form-control" id="username" name="username" placeholder="Username" type="text" 
                     value="<?php echo isset($user['username']) ? htmlspecialchars($user['username']) : ''; ?>"/>
              <i class="zmdi zmdi-account"></i>
            </div>
            <div class="form-wrapper">
              <input type="password" name="password" id="password" placeholder="Password" 
                     class="form-control" value=""/>
              <i class="zmdi zmdi-lock" id="togglePassword" style="cursor: pointer;"></i>
            </div>
            <div class="div-button flex text-center justify-center gap-16">
              <button class="div-button1" style="margin-top: 0;" type="button" onclick="window.location.href='profile.php'">Cancel</button>
              <button class="div-button2" type="submit" style="margin-top: 0;">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Toggle password visibility
    document.getElementById("togglePassword").addEventListener("click", function () {
        let passwordInput = document.getElementById("password");
        passwordInput.type = passwordInput.type === "password" ? "text" : "password";
    });

    // Preview image on file select
    document.getElementById("profile-picture-upload").addEventListener("change", function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById("profile-picture-preview").src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Submit form via fetch API
    document.getElementById("profileForm").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent the default form submission
        let formData = new FormData(this); // Create a FormData object from the form

        fetch('profilead.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json()) // Parse the JSON response
        .then(data => {
            if (data.success) {
                alert(data.message); // Show success message
                if (data.profile_picture) {
                    // Update preview using public URL; add a timestamp to bust cache
                    document.getElementById("profile-picture-preview").src = "upload/" + data.profile_picture + "?t=" + new Date().getTime();
                }
                location.reload(); // Reload the page to reflect changes
            } else {
                alert("Error: " + data.message); // Show error message
            }
        })
        .catch(error => {
            console.error("Fetch error:", error);
            alert("An unexpected error occurred.");
        });
    });
  </script>
</body>
</html>