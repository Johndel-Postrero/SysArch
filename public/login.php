<?php
require __DIR__ . '/../config/db.php';
session_start();
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    // Prepare SQL statement
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
    <link rel="stylesheet" href="login.css">
    <title>Log In</title>
</head>
<body>
<main>
    <div class="left-con">
        <div class="logo">
            <img src="inc/CCS_LOGO.png" alt="CCS LOGO">
            <div style="width: 60%;"><h1>SIT-IN MONITORING SYSTEM</h1></div>
        </div>
        <img src="inc/graphs.svg" alt="graphics" style="width: 95%;">
    </div>
    <div class="right-con"> 
        <form action="login.php" method="post">
            <div class="form-header">
                <h2>Log in to your Account</h2>
                <p>Welcome back! Please enter your details.</p>
            </div>
            <?php if ($error): ?>
                <div style="color: red;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div>
                <label for="username">Username*</label>
                <input type="text" placeholder="Enter your username" name="username" id="username" required>
            </div>
            <div>
                <label for="password">Password*</label>
                <input type="password" placeholder="Enter password" name="password" id="password" required>
            </div>
            <button type="submit">Sign In</button>
            <footer style="font-size: 16px; margin-top: 15px;">Don’t have an account? <a href="register.php">Sign Up here</a></footer>
        </form>
    </div>

</main>
</body>
</html>
