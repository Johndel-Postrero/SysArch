<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="navbar-brand">
                <img src="../../wwwroots/favIcon/ccsLogo.png" alt="CCS" class="brand-icon">
                CCS Sit-in Monitoring System
            </a>
            <ul class="navbar-links">
                <li><a href="#" class="nav-active">Dashboard</a></li>
                <li><a href="#">Profile</a></li>
                <li><a href="?logout=1" style="color: #fca5a5;">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="landing-hero" style="position: relative;">
            <?php if (isset($_SESSION['login_success'])): ?>
                <div class="alert alert-success" style="position: absolute; top: -40px; width: auto; min-width: 300px;">
                    <?php 
                        echo $_SESSION['login_success']; 
                        unset($_SESSION['login_success']);
                    ?>
                </div>
            <?php endif; ?>

            <h1 class="landing-text">Welcome Back!</h1>
            <p class="user-welcome">Hello, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>! You are successfully logged in to the CCS Sit-in Monitoring System.</p>
            
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>

    <footer class="footer">
        &copy; 2024 College of Computer Studies &mdash; University of Cebu
    </footer>

</body>
</html>