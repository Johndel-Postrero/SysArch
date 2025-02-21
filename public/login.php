<?php
require __DIR__ . '/../config/db.php';
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = filter_input(INPUT_POST, 'username');
    $password = filter_input(INPUT_POST, 'password');

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if (password_verify($password, $user['password'])) {
            // Set session variables after verifying credentials
            $_SESSION['login_user'] = $username;
            $_SESSION['idno'] = $user['idno']; 
            // Set firstname and lastname after fetching user data
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];

            header("Location: login.php?success=true"); 
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }
    $stmt->close();
    $conn->close();
}
?>
<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <link rel="stylesheet" href="fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="css/login.css">
    <title>Log In</title>
    <style>
        .image-holder{
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .image-holder .logo{
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .image-holder .logo h1{
            font-size: 25px;
        }
        .image-holder .logo img{
            width: 20%;
        }
        :root {
            --input-background-color: none;
        }
        input {
            background-color: var(--input-background-color);
        }
        #successDialog {
            border: none;
            border-radius: 8px;
            background: white;
            color: black;
            padding: 20px;
            width: 300px; 
            text-align: center;
            box-shadow: 0 4px 10px rgba(255, 255, 255, 0.3);
            position: fixed;
            top: 13%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        #closeDialog {
            align-self: center; 
            background: #7952b3;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
            width:auto;
            height: auto;
        }

        #closeDialog:hover {
            background: #7952b3;
        }
    </style>
</head>
<body>
<dialog id="successDialog">
    <div class="modal-content">
        <p>Login Successful!</p>
        <button id="closeDialog">OK</button>
    </div>
</dialog>
<div class="wrapper" style="background-image: url('inc/computer.png');">
        <div class="inner">
            <div class="image-holder">
                <div class="logo">
                    <img src="inc/CCS_LOGO.png" alt="CCS LOGO">
                    <div style="width: 60%;"><h1>SIT-IN MONITORING SYSTEM</h1></div>
                </div>
                <img src="inc/graphs.svg" alt="graphics" style="width: 95%;">
            </div>
            <form action="login.php" method="post">
                <h3>Sign In</h3>
                <p>Welcome back! Please enter your details.</p>
                <?php if ($error): ?>
                    <div style="color: red;"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="form-wrapper">
                    <input type="text" name="username" id="username" required placeholder="Username" class="form-control">
                    <i class="zmdi zmdi-account"></i>
                </div>
                <div class="form-wrapper">
                    <input type="password" name="password" id="password" required placeholder="Password" class="form-control">
                    <i class="zmdi zmdi-lock" id="togglePassword" style="cursor: pointer;"></i>
                </div>
                <button type="submit" style="margin-top: 0;">Sign In</button>
                <footer>Don’t have an account? <a href="register.php">Sign Up here</a></footer>
            </form>
        </div>
    </div>
</body>
<script>
document.getElementById("togglePassword").addEventListener("click", function() {
    var passwordInput = document.getElementById("password");
    var icon = document.getElementById("togglePassword");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("zmdi-lock");
        icon.classList.add("zmdi-lock-open");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("zmdi-lock-open");
        icon.classList.add("zmdi-lock");
    }
});
document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("success") === "true") {
        const dialog = document.getElementById("successDialog");
        if (dialog) {
            dialog.showModal();

            window.history.replaceState({}, document.title, "login.php");
        }
    }
    document.getElementById("closeDialog").addEventListener("click", function () {
        document.getElementById("successDialog").close();
        window.location.href = "index.php"; 
    });
});
</script>
</html>
