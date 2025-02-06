<?php
require __DIR__ . '/../config/db.php';
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['login_user'] = $username;
            header("Location: index.php"); 
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
    </style>
</head>
<body>
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
                    <i class="zmdi zmdi-lock"></i>
                </div>
                <button type="submit" style="margin-top: 0;">Sign In</button>
                <footer>Don’t have an account? <a href="register.php">Sign Up here</a></footer>
            </form>
        </div>
    </div>
</body>
</html>
