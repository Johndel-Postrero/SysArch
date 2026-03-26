<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// Ensure only admin (level == 1) can access this page
if (!isset($_SESSION['user_level']) || $_SESSION['user_level'] != 1) {
    header("Location: Landing.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

// Handle posting an announcement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'post_announcement') {
    $content = trim($_POST['announcement_content'] ?? '');
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author) VALUES (?, ?, ?)");
            // Title is unused in design but required by schema, we can mock it
            $stmt->execute(['Important Announcement', $content, 'CCS Admin']);
            $_SESSION['admin_success'] = "Announcement posted successfully!";
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "Failed to post announcement: " . $e->getMessage();
        }
    }
    header("Location: AdminLanding.php");
    exit();
}

// Fetch stats
$studentCount = 0;
$courseData = [];
$announcements = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE level != 1");
    $studentCount = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT course, COUNT(*) as count FROM users WHERE level != 1 GROUP BY course");
    $courseDataDB = $stmt->fetchAll();
    
    // Default mapping for Chart
    $courseMapping = [
        'BSIT' => 0,
        'BSCS' => 0,
        'BSIS' => 0,
        'ACT'  => 0
    ];
    foreach($courseDataDB as $row) {
        $c = strtoupper(trim($row['course']));
        if(isset($courseMapping[$c])) {
            $courseMapping[$c] = $row['count'];
        } else {
            $courseMapping['Other'] = ($courseMapping['Other'] ?? 0) + $row['count'];
        }
    }
    
    $labels = array_keys($courseMapping);
    $dataSeries = array_values($courseMapping);

    // Fetch Announcements
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
    $announcements = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }

        .dashboard-panel {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(15, 42, 74, 0.08);
            overflow: hidden;
        }

        .panel-header {
            background: #007bff; /* Primary blue from mockup */
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-content {
            padding: 24px;
        }

        .stat-item {
            margin-bottom: 20px;
            font-size: 15px;
            display: flex;
            align-items: center;
        }

        .stat-item strong {
            margin-right: 8px;
            color: var(--text);
            font-weight: 700;
        }
        
        .stat-value {
            color: var(--text-muted);
        }

        .chart-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            margin-top: 32px;
        }

        .announcement-form {
            margin-bottom: 32px;
            border-bottom: 2px solid var(--bg-alt);
            padding-bottom: 24px;
        }

        .announcement-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .announcement-form textarea {
            width: 100%;
            border: 1px solid #dde1e8;
            border-radius: var(--radius);
            padding: 12px;
            min-height: 80px;
            margin-bottom: 12px;
            font-family: var(--font);
            resize: vertical;
        }

        .announcement-form textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-submit {
            background: #198754; /* Green from mockup */
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-submit:hover {
            background: #157347;
        }

        .announcement-heading {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .announcement-list .announcement-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--bg-alt);
        }
        .announcement-item:last-child {
            border-bottom: none;
        }
        .announcement-title {
            font-weight: 700;
            color: var(--text);
            font-size: 15px;
            margin-bottom: 8px;
        }
        .announcement-meta {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
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
                <li><a href="?logout=1" style="color: #fca5a5;">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content" style="padding: 32px 24px; display: block;">
        
        <?php if (isset($_SESSION['admin_success'])): ?>
            <div class="alert alert-success" style="max-width: 1200px; margin: 0 auto 24px auto;">
                <?php 
                    echo $_SESSION['admin_success']; 
                    unset($_SESSION['admin_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="alert alert-danger" style="max-width: 1200px; margin: 0 auto 24px auto;">
                <?php 
                    echo $_SESSION['admin_error']; 
                    unset($_SESSION['admin_error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-layout">
            <!-- Left Pane: Statistics -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20v-6"></path></svg>
                    Statistics
                </div>
                <div class="panel-content">
                    <div class="stat-item">
                        <strong>Students Registered:</strong> <span class="stat-value"><?php echo $studentCount; ?></span>
                    </div>
                    <div class="stat-item">
                        <strong>Currently Sit-in:</strong> <span class="stat-value">0</span>
                    </div>
                    <div class="stat-item">
                        <strong>Total Sit-in:</strong> <span class="stat-value">15</span>
                    </div>

                    <div class="chart-container">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Announcements -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    Announcement
                </div>
                <div class="panel-content">
                    
                    <form class="announcement-form" method="POST" action="">
                        <input type="hidden" name="action" value="post_announcement">
                        <label>New Announcement</label>
                        <textarea name="announcement_content" required></textarea>
                        <button type="submit" class="btn-submit">Submit</button>
                    </form>

                    <h3 class="announcement-heading">Posted Announcement</h3>
                    <div class="announcement-list">
                        <?php if (count($announcements) > 0): ?>
                            <?php foreach ($announcements as $ann): ?>
                                <div class="announcement-item">
                                    <div class="announcement-title">
                                        <?php echo htmlspecialchars($ann['author']) . ' | ' . date('Y-M-d', strtotime($ann['created_at'])); ?>
                                    </div>
                                    <div class="announcement-meta">
                                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="announcement-item">
                                <div class="announcement-meta">No announcements posted yet.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <footer class="footer">
        &copy; 2024 College of Computer Studies &mdash; University of Cebu
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('courseChart').getContext('2d');
            
            var labels = <?php echo json_encode($labels); ?>;
            var data = <?php echo json_encode($dataSeries); ?>;
            
            // Replicate mockup colors: Blue, Red/Pink, Orange, Yellow, Teal
            var backgroundColors = [
                '#36a2eb', // C# / BSIT base
                '#ff6384', // C / BSCS base
                '#ff9f40', // Java / BSIS base
                '#ffcd56', // ASP.Net / ACT base
                '#4bc0c0'  // Php / Others
            ];

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors.slice(0, labels.length),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 24,
                                usePointStyle: false,
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
