<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - College of Computer Studies Sit-in Monitoring System</title>
    <meta name="description" content="College of Computer Studies Sit-in Monitoring System - Login or Register">
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        .hero-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 180px);
            padding: 40px 0;
            background-color: #f0f2f5;
        }

        .hero-container {
            max-width: 1200px;
            width: 100%;
            padding: 0 60px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin: 0 auto;
        }

        .hero-content {
            color: #333;
        }

        .hero-content h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
            color: #2d3748;
        }

        .hero-content p {
            font-size: 1.2rem;
            line-height: 1.8;
            margin-bottom: 30px;
            color: #4a5568;
        }

        .hero-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .hero-logo img {
            width: 180px;
            height: 180px;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
        }

        .action-cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            align-self: stretch;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 50px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
        }

        .action-card h3 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #2d3748;
        }

        .action-card p {
            font-size: 1rem;
            color: #718096;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .action-card .btn {
            display: inline-block;
            padding: 14px 40px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a4190 100%);
            transform: scale(1.02);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .feature-item {
            background: rgba(102, 126, 234, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .feature-item h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2d3748;
        }

        .feature-item p {
            font-size: 0.8rem;
            color: #4a5568;
            margin: 0;
        }

        @media (max-width: 968px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .hero-content h1 {
                font-size: 2.2rem;
            }

            .features {
                grid-template-columns: 1fr;
            }

            .action-cards {
                max-width: 500px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="navbar-brand">College of Computer Studies Sit-in Monitoring System</a>
            <ul class="navbar-links">
                <li><a href="index.php" class="nav-active">Home</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Community</a>
                    <div class="dropdown-menu">
                        <a href="#">Forum</a>
                        <a href="#">Events</a>
                        <a href="#">Announcements</a>
                    </div>
                </li>
                <li><a href="#">About</a></li>
                <li><a href="Login.php">Login</a></li>
                <li><a href="Register.php">Register</a></li>
            </ul>
        </div>
    </nav>

    <div class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-logo">
                    <img src="../../wwwroots/img/ccsLogo-removebg-preview.png" alt="College of Computer Studies Logo">
                </div>
                <h1>CCS Sit-in Monitoring System</h1>
                <p>A specialized management platform designed for the College of Computer Studies to streamline laboratory sit-in sessions, track student attendance, and monitor real-time laboratory availability with precision and ease.</p>
                
                <div class="features">
                    <div class="feature-item">
                        <h4>📊 Real-time Tracking</h4>
                        <p>Monitor lab availability instantly</p>
                    </div>
                    <div class="feature-item">
                        <h4>✅ Attendance System</h4>
                        <p>Automated student check-ins</p>
                    </div>
                    <div class="feature-item">
                        <h4>🔒 Secure Access</h4>
                        <p>Protected student data</p>
                    </div>
                </div>
            </div>

            <div class="action-cards">
                <div class="action-card">
                    <h3>Already a Member?</h3>
                    <p>Sign in to access your dashboard, track your sit-in hours, and manage your laboratory sessions.</p>
                    <a href="Login.php" class="btn btn-primary">Login to Your Account</a>
                </div>

                <div class="action-card">
                    <h3>New Student?</h3>
                    <p>Create your account to start using the CCS Sit-in Monitoring System and track your progress.</p>
                    <a href="Register.php" class="btn btn-secondary">Create New Account</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        &copy; 2026 IT. All rights reserved. | Designed by POSTRERO
    </footer>

</body>
</html>
