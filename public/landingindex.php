<?php
session_start();

if (isset($_SESSION['login_user'])) {
    $isAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
    header('Location: ' . ($isAdmin ? 'Admin/adminIndex.php' : 'index.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - College of Computer Studies Sit-in Monitoring System</title>
    <meta name="description" content="College of Computer Studies Sit-in Monitoring System - Login or Register">
    <link rel="icon" type="image/png" href="inc/CCS_LOGO.png">
    <style>
        :root {
            --primary: #0f3d73;
            --primary-dark: #06274d;
            --accent: #3b82f6;
            --bg-soft: #f5f8fc;
            --text: #10213a;
            --muted: #4d627e;
            --white: #ffffff;
            --shadow: 0 14px 32px rgba(10, 33, 70, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background: radial-gradient(circle at center, #ffffff 0%, #e8e8e8 50%, #808080 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            width: 100%;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary));
            color: var(--white);
            padding: 14px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
        }

        .nav-inner {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--white);
        }

        .brand img {
            width: 42px;
            height: 42px;
            object-fit: contain;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
        }

        .brand span {
            font-weight: 700;
            letter-spacing: .2px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links a {
            text-decoration: none;
            border-radius: 8px;
            padding: 9px 14px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: var(--white);
            transition: all .2s ease;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .hero-section {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 40px 0;
        }

        .hero-container {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 26px;
            align-items: stretch;
        }

        .hero-content {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 30px;
        }

        .hero-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }

        .hero-logo img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(1.7rem, 2.5vw, 2.2rem);
            line-height: 1.2;
            color: var(--primary-dark);
        }

        .lead {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .hero-visual {
            margin-top: 18px;
            width: 100%;
            border-radius: 12px;
            background: var(--bg-soft);
            border: 1px solid #dde8f6;
            padding: 10px;
        }

        .hero-visual img {
            width: 100%;
            display: block;
        }

        .features {
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .feature-item {
            border: 1px solid #dce7f6;
            border-radius: 12px;
            background: #f8fbff;
            padding: 12px;
        }

        .feature-item h4 {
            margin: 0 0 6px;
            font-size: .96rem;
            color: #0f2f59;
        }

        .feature-item p {
            margin: 0;
            color: var(--muted);
            font-size: .9rem;
        }

        .action-cards {
            display: grid;
            grid-template-rows: 1fr 1fr;
            gap: 16px;
        }

        .action-card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-card h3 {
            margin: 0 0 8px;
            color: var(--primary-dark);
        }

        .action-card p {
            margin: 0 0 16px;
            color: var(--muted);
            line-height: 1.55;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            padding: 11px 14px;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f3d73, #1d5ca3);
            color: var(--white);
            box-shadow: 0 8px 16px rgba(15, 61, 115, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #1f77e5, #3f8fff);
            color: var(--white);
            box-shadow: 0 8px 16px rgba(31, 119, 229, 0.3);
        }

        .footer {
            text-align: center;
            font-size: .9rem;
            color: #3b4f69;
            padding: 14px 20px 20px;
        }

        @media (max-width: 960px) {
            .hero-container {
                grid-template-columns: 1fr;
            }

            .action-cards {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: unset;
            }
        }

        @media (max-width: 680px) {
            .features {
                grid-template-columns: 1fr;
            }

            .action-cards {
                grid-template-columns: 1fr;
            }

            .brand span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="nav-inner">
            <a class="brand" href="landingindex.php">
                <img src="inc/CCS_LOGO.png" alt="CCS Logo">
                <span>CCS Sit-in Monitoring System</span>
            </a>
            <nav class="nav-links">
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            </nav>
        </div>
    </header>

    <main class="hero-section">
        <div class="hero-container">
            <section class="hero-content">
                <div class="hero-logo">
                    <img src="inc/CCS_LOGO.png" alt="College of Computer Studies Logo">
                    <h1>CCS Sit-in Monitoring System</h1>
                </div>
                <p class="lead">
                    A specialized management platform designed for the College of Computer Studies to streamline laboratory sit-in sessions,
                    track student attendance, and monitor real-time laboratory availability with precision and ease.
                </p>

                <div class="hero-visual">
                    <img src="inc/graphs.svg" alt="System dashboard illustration">
                </div>

                <div class="features">
                    <div class="feature-item">
                        <h4>📊 Real-time Tracking</h4>
                        <p>Monitor lab availability instantly.</p>
                    </div>
                    <div class="feature-item">
                        <h4>✅ Attendance System</h4>
                        <p>Automated student check-ins.</p>
                    </div>
                    <div class="feature-item">
                        <h4>🔒 Secure Access</h4>
                        <p>Protected student data.</p>
                    </div>
                </div>
            </section>

            <aside class="action-cards">
                <div class="action-card">
                    <h3>Already a Member?</h3>
                    <p>Sign in to access your dashboard, track your sit-in hours, and manage your laboratory sessions.</p>
                    <a href="login.php" class="btn btn-primary">Login to Your Account</a>
                </div>

                <div class="action-card">
                    <h3>New Student?</h3>
                    <p>Create your account to start using the CCS Sit-in Monitoring System and monitor your progress.</p>
                    <a href="register.php" class="btn btn-secondary">Create New Account</a>
                </div>
            </aside>
        </div>
    </main>

    <footer class="footer">
        &copy; 2026 CCS SIT-IN. All rights reserved.
    </footer>
</body>
</html>
