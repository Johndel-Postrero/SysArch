<?php
session_start();
require_once 'db_config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: Landing.php");
    exit();
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idNumber = $_POST['idNumber'];
    $password = $_POST['password'];

    if (empty($idNumber) || empty($password)) {
        $message = "Please enter both ID Number and Password.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE IDNum = ?");
            $stmt->execute([$idNumber]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['IDNum'];
                $_SESSION['user_name'] = $user['FName'] . " " . $user['LName'];
                
                $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($user['FName']) . "!";
                header("Location: Landing.php");
                exit();
            } else {
                $message = "Invalid ID Number or Password.";
                $messageType = "danger";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - College of Computer Studies Sit-in Monitoring System</title>
    <meta name="description" content="Login to the College of Computer Studies Sit-in Monitoring System">
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
</head>
<body>


    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="navbar-brand">College of Computer Studies Sit-in Monitoring System</a>
            <ul class="navbar-links">
                <li><a href="index.php">Home</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Community</a>
                    <div class="dropdown-menu">
                        <a href="#">Forum</a>
                        <a href="#">Events</a>
                        <a href="#">Announcements</a>
                    </div>
                </li>
                <li><a href="#">About</a></li>
                <li><a href="#" class="nav-active">Login</a></li>
                <li><a href="Register.php">Register</a></li>
            </ul>
        </div>
    </nav>


    <div class="main-content">
        <div class="login-container">


            <div class="logo-section">
                <img src="../../wwwroots/img/ccsLogo-removebg-preview.png" alt="College of Computer Studies Logo">
                <div class="system-description">
                    <h1>CCS Sit-in Monitoring System</h1>
                    <p>&nbsp;&nbsp;&nbsp;&nbsp; A specialized management platform designed for the College of Computer Studies to streamline laboratory sit-in sessions, track student attendance, and monitor real-time laboratory availability with precision and ease.</p>
                </div>
            </div>


            <div class="form-section">
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your account</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="idNumber">ID Number</label>
                        <input type="text" id="idNumber" name="idNumber" placeholder="e.g. 21411277" pattern="\d{8}" title="Must be exactly 8 digits" maxlength="8" required value="<?php echo isset($_POST['idNumber']) ? htmlspecialchars($_POST['idNumber']) : ''; ?>">
                        <small style="display: block; margin-top: 4px; color: #777; font-size: 11px; font-weight: 500;">Must be exactly 8 digits</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>

                    <div class="options-row">
                        <label class="remember-me">
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login">Login</button>

                    <p class="register-text">Don't have an account? <a href="Register.php">Register</a></p>
                </form>
            </div>

        </div>
    </div>


    <footer class="footer">
        &copy; 2026 IT. All rights reserved. | Designed by POSTRERO
    </footer>

</body>
</html>