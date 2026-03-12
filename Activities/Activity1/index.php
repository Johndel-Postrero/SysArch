<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: Landing.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-in Monitoring System</title>
    <meta name="description" content="College of Computer Studies Sit-in Monitoring System — manage laboratory sit-in sessions, track attendance, and monitor lab availability.">
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
                <li><a href="index.php" class="nav-active">Home</a></li>
                <li><a href="Login.php">Login</a></li>
                <li><a href="Register.php">Register</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="hero-section">
            <div class="hero-text">
                <span class="hero-badge">College of Computer Studies</span>
                <h1 class="hero-title">
                    Sit-in <span class="accent">Monitoring</span> System
                </h1>
                <p class="hero-subtitle">
                    Manage laboratory sit-in sessions, monitor student attendance, and track real-time lab availability with precision and ease.
                </p>
                <div class="hero-cta">
                    <a href="Login.php" class="btn-cta btn-primary">Login Now</a>
                    <a href="Register.php" class="btn-cta btn-secondary">Create Account</a>
                </div>
            </div>
            <div class="hero-visual">
                <img src="../../wwwroots/img/ccsLogo-removebg-preview.png" alt="CCS Logo">
            </div>
        </div>
    </div>

    <section class="features-section">
        <div class="features-container">
            <div class="features-header">
                <h2>Key Features</h2>
                <p>Everything you need to manage and monitor laboratory sit-in sessions efficiently.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">&#128200;</div>
                    <h3>Real-time Tracking</h3>
                    <p>Monitor active sit-in sessions and lab occupancy in real time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#128203;</div>
                    <h3>Attendance Records</h3>
                    <p>Keep accurate records of student attendance across all labs.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#128187;</div>
                    <h3>Lab Availability</h3>
                    <p>Check laboratory schedules and available seats at a glance.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">&#128202;</div>
                    <h3>Session Reports</h3>
                    <p>Generate detailed usage reports and analytics for administrators.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        &copy; 2024 College of Computer Studies &mdash; University of Cebu
    </footer>

</body>
</html>
