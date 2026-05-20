<?php
session_start();
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php"); 
    exit();
}

require __DIR__ . '/../../config/db.php';

// Handle Announcement Post from Dashboard Popup
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'post_announcement') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $admin_id = $_SESSION['user_id'];
    $attachment = null;

    if (!empty($_FILES['attachment']['name'])) {
        $targetDir = __DIR__ . '/../announce/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $file_name = basename($_FILES["attachment"]["name"]);
        $new_file_name = time() . "_" . $file_name;
        $target_file = $targetDir . $new_file_name;
        
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            $attachment = $new_file_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO announcements (title, description, attachment, admin_id) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssi", $title, $description, $attachment, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['post_success'] = true;
            header("Location: adminIndex.php");
            exit();
        }
        $stmt->close();
    }
}

// Fetch stats
$totalStudents = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'")->fetch_assoc()['total'];
$currentSitIn = $conn->query("SELECT COUNT(*) as total FROM sitin WHERE time_out IS NULL")->fetch_assoc()['total'];
$totalSitIn = $conn->query("SELECT COUNT(*) as total FROM sitin WHERE time_in IS NOT NULL")->fetch_assoc()['total'];
$pendingReservations = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'];

// Fetch Recent Activity
$activityQuery = "SELECT s.sitin_id, u.idno, u.firstname, u.lastname, u.course, s.lab_number, s.purpose, s.time_in, s.time_out 
                  FROM sitin s 
                  JOIN users u ON s.idno = u.idno 
                  ORDER BY s.created_at DESC LIMIT 5";
$activityResult = $conn->query($activityQuery);

// Fetch Announcements
$announcementsQuery = "SELECT a.*, u.firstname, u.lastname FROM announcements a JOIN users u ON a.admin_id = u.user_id ORDER BY a.created_at DESC LIMIT 2";
$announcementsResult = $conn->query($announcementsQuery);

// Fetch Top 3 Leaderboard Students
$topLeaderboardQuery = "SELECT u.idno, u.lastname, u.firstname, u.profile_picture, 
                       COALESCE(SUM(r.leaderboard_score), 0) as total_points
                       FROM users u
                       LEFT JOIN rewards r ON u.idno = r.idno
                       WHERE u.role = 'student'
                       GROUP BY u.idno
                       ORDER BY total_points DESC, u.lastname ASC
                       LIMIT 3";
$topLeaderboardResult = $conn->query($topLeaderboardQuery);
$topStudents = [];
if ($topLeaderboardResult && $topLeaderboardResult->num_rows > 0) {
    while ($row = $topLeaderboardResult->fetch_assoc()) {
        $topStudents[] = $row;
    }
}

// Fetch Chart Data (Last 7 days)
$selectedLab = isset($_GET['filter_lab']) ? $_GET['filter_lab'] : 'all';
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    if ($selectedLab === 'all') {
        $count = $conn->query("SELECT COUNT(*) as total FROM sitin WHERE DATE(created_at) = '$date'")->fetch_assoc()['total'];
    } else {
        $count = $conn->query("SELECT COUNT(*) as total FROM sitin WHERE DATE(created_at) = '$date' AND lab_number = " . intval($selectedLab))->fetch_assoc()['total'];
    }
    $chartData[] = ['label' => $label, 'count' => $count];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – CCS Sit-In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #060411;
            --bg-card: rgba(22, 19, 38, 0.6);
            --purple-glow: #8B3FD9;
            --purple-light: #C084FC;
            --gold: #D4870A;
            --text-main: #ffffff;
            --text-dim: #9A8FB0;
            --border: rgba(139, 63, 217, 0.2);
            --font-h: 'Orbitron', sans-serif;
            --font-b: 'Inter', sans-serif;
        }

        body {
            background-color: #0D0B1A;
            color: var(--text-main);
            font-family: var(--font-b);
            margin: 0;
            overflow-x: hidden;
        }

        /* Star canvas sits behind everything */
        #star-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            display: block;
        }

        .main-wrapper {
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dashboard-content {
            padding: 30px 40px;
            flex: 1;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px 16px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: transform 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 110px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--purple-glow);
            box-shadow: 0 10px 30px rgba(139, 63, 217, 0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .stat-header h3 {
            font-family: var(--font-h);
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #fff;
            line-height: 1;
        }

        .stat-info p {
            color: var(--text-dim);
            font-size: 11px;
            margin: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Middle Grid */
        .middle-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        /* Bottom Grid (2/3 Recent Activity & 1/3 Leaderboard) */
        .bottom-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }

        .xp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(168,85,247,0.12);
            color: #a855f7;
            border: 1px solid rgba(168,85,247,0.25);
        }

        .middle-grid > div {
            min-width: 0; /* Important for Chart.js 50/50 split */
        }

        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            backdrop-filter: blur(10px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-header h2 {
            font-family: var(--font-h);
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 1px;
            color: #fff;
        }

        /* Post Box */
        .post-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .post-box textarea {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            resize: none;
            font-size: 14px;
            min-height: 80px;
        }

        .post-box textarea:focus { outline: none; }

        .btn-post {
            background: linear-gradient(90deg, var(--purple-glow), var(--gold));
            color: #fff;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            width: 100%;
            letter-spacing: 1px;
            font-family: var(--font-h);
            transition: all 0.3s;
        }

        .btn-post:hover {
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.4);
            transform: scale(1.02);
        }

        /* Table Area */
        .activity-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .activity-table th {
            text-align: left;
            color: var(--text-dim);
            font-weight: 600;
            font-size: 12px;
            padding: 0 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .activity-table tr:not(:first-child) {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
        }

        .activity-table td {
            padding: 14px 20px;
            font-size: 13px;
            border-top: 1px solid rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .activity-table td:first-child { border-left: 1px solid rgba(255, 255, 255, 0.03); border-radius: 12px 0 0 12px; }
        .activity-table td:last-child { border-right: 1px solid rgba(255, 255, 255, 0.03); border-radius: 0 12px 12px 0; }

        .status-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-done { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-active { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

        /* Custom Dropdown Styling — scoped to .custom-dropdown to avoid sidebar conflicts */
        .custom-dropdown {
            position: relative;
            display: inline-block;
            user-select: none;
            z-index: 100;
        }

        .custom-dropdown .dropdown-trigger {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: #fff;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 140px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .custom-dropdown .dropdown-trigger:hover {
            border-color: var(--purple-glow);
            background: rgba(139, 63, 217, 0.08);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.15);
        }

        .custom-dropdown .dropdown-trigger i {
            font-size: 11px;
            color: var(--text-dim);
            transition: transform 0.3s ease;
        }

        .custom-dropdown.active .dropdown-trigger i {
            transform: rotate(180deg);
            color: var(--purple-light);
        }

        .custom-dropdown.active .dropdown-trigger {
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.2);
        }

        .custom-dropdown .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            width: 100%;
            min-width: 140px;
            background: rgba(22, 19, 38, 0.95);
            border: 1px solid rgba(139, 63, 217, 0.3);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
            display: none;
            flex-direction: column;
            overflow: hidden;
            backdrop-filter: blur(15px);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.25s ease, transform 0.25s ease;
        }

        .custom-dropdown.active .dropdown-menu {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }

        .custom-dropdown .dropdown-item {
            padding: 10px 16px;
            font-size: 13px;
            color: #D1C7E0;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }

        .custom-dropdown .dropdown-item:hover {
            background: rgba(139, 63, 217, 0.15);
            color: #C084FC;
        }

        .custom-dropdown .dropdown-item.selected {
            background: rgba(139, 63, 217, 0.25);
            color: #fff;
            font-weight: 600;
        }

        /* Modal Backdrop styling */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(6, 4, 17, 0.8);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-backdrop.show {
            display: flex;
            opacity: 1;
        }

        /* Modal Content Card styling */
        .modal-content-card {
            background: rgba(22, 19, 38, 0.95);
            border: 1px solid rgba(139, 63, 217, 0.3);
            border-radius: 24px;
            padding: 32px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), 0 0 30px rgba(139, 63, 217, 0.2);
            color: #fff;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .modal-backdrop.show .modal-content-card {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-family: var(--font-h);
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .modal-close-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-dim);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-close-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Form elements */
        .modal-form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .modal-form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-dim);
            margin-bottom: 8px;
        }

        .modal-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(139, 63, 217, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            color: #fff;
            font-size: 14px;
            font-family: var(--font-b);
            transition: all 0.3s;
        }

        .modal-input:focus {
            background: rgba(139, 63, 217, 0.05);
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.15);
            outline: none;
        }

        .modal-textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(139, 63, 217, 0.2);
            border-radius: 12px;
            padding: 14px 16px;
            color: #fff;
            font-size: 14px;
            font-family: var(--font-b);
            resize: none;
            min-height: 120px;
            transition: all 0.3s;
        }

        .modal-textarea:focus {
            background: rgba(139, 63, 217, 0.05);
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.15);
            outline: none;
        }

        /* File Upload Area styling */
        .upload-area {
            border: 2px dashed rgba(139, 63, 217, 0.3);
            background: rgba(255, 255, 255, 0.01);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-area:hover {
            border-color: var(--purple-glow);
            background: rgba(139, 63, 217, 0.05);
        }

        .upload-area i {
            font-size: 24px;
            color: var(--purple-light);
            margin-bottom: 8px;
        }

        .upload-area p {
            margin: 0;
            font-size: 13px;
            color: var(--text-dim);
        }

        .upload-area span {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
            display: block;
            margin-top: 4px;
        }

        .modal-submit-btn {
            background: linear-gradient(90deg, var(--purple-glow), #9D50EA);
            color: #fff;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            width: 100%;
            border: none;
            letter-spacing: 1px;
            font-family: var(--font-h);
            cursor: pointer;
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.3);
            transition: all 0.3s;
        }

        .modal-submit-btn:hover {
            box-shadow: 0 0 25px rgba(139, 63, 217, 0.5);
            transform: translateY(-1px);
        }

        /* Success Toast */
        .toast-notify {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: rgba(22, 19, 38, 0.95);
            border: 1px solid rgba(16, 185, 129, 0.5);
            padding: 16px 24px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            animation: slideInUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #fff;
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>

    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="dashboard-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon bg-purple-900/40 text-purple-400">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3><?php echo number_format($totalStudents); ?></h3>
                    </div>
                    <div class="stat-info">
                        <p>Total Registered Students</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon bg-orange-900/40 text-orange-400">
                            <i class="fas fa-desktop"></i>
                        </div>
                        <h3><?php echo number_format($currentSitIn); ?></h3>
                    </div>
                    <div class="stat-info">
                        <p>Currently Sit-In</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon bg-teal-900/40 text-teal-400">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3><?php echo number_format($totalSitIn); ?></h3>
                    </div>
                    <div class="stat-info">
                        <p>Total Sit-In Sessions</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon bg-pink-900/40 text-pink-400">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo number_format($pendingReservations); ?></h3>
                    </div>
                    <div class="stat-info">
                        <p>Pending Reservations</p>
                    </div>
                </div>
            </div>

            <!-- Middle Grid -->
            <div class="middle-grid">
                <!-- Trend Chart -->
                <div class="content-card">
                    <div class="card-header">
                        <div class="flex items-center gap-3">
                            <h2><i class="fas fa-chart-area text-purple-500"></i> DAILY SIT-IN TREND</h2>
                            <div class="custom-dropdown" id="labDropdown">
                                <div class="dropdown-trigger">
                                    <span id="selectedLabLabel">
                                        <?php 
                                        if ($selectedLab === 'all') {
                                            echo 'All Labs';
                                        } else {
                                            echo 'Lab ' . htmlspecialchars($selectedLab);
                                        }
                                        ?>
                                    </span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="dropdown-menu">
                                    <div class="dropdown-item <?php echo $selectedLab === 'all' ? 'selected' : ''; ?>" data-value="all">All Labs</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '524' ? 'selected' : ''; ?>" data-value="524">Lab 524</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '526' ? 'selected' : ''; ?>" data-value="526">Lab 526</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '528' ? 'selected' : ''; ?>" data-value="528">Lab 528</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '530' ? 'selected' : ''; ?>" data-value="530">Lab 530</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '542' ? 'selected' : ''; ?>" data-value="542">Lab 542</div>
                                    <div class="dropdown-item <?php echo $selectedLab === '544' ? 'selected' : ''; ?>" data-value="544">Lab 544</div>
                                </div>
                            </div>
                        </div>
                        <a href="leaderboard.php#charts" class="text-xs text-purple-400 hover:underline">View All</a>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="sitInTrendChart"></canvas>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="content-card">
                    <div class="card-header">
                        <h2><i class="fas fa-bullhorn text-orange-500"></i> ANNOUNCEMENTS</h2>
                        <a href="Cannouncement.php" class="text-xs text-purple-400 hover:underline">View All</a>
                    </div>
                    <div class="post-box" style="cursor: pointer; transition: all 0.3s; background: rgba(139, 63, 217, 0.03); border: 1px dashed rgba(139, 63, 217, 0.3);" id="openAnnounceModalTrigger">
                        <div class="flex items-center gap-3 text-purple-200">
                            <i class="fas fa-bullhorn text-lg"></i>
                            <span class="text-sm font-medium">Click to create and post a new announcement...</span>
                        </div>
                        <button class="btn-post mt-3" style="pointer-events: none;">POST ANNOUNCEMENT</button>
                    </div>
                    <div class="announcement-feed space-y-3">
                        <?php while ($row = $announcementsResult->fetch_assoc()): ?>
                        <div class="p-3 bg-white/5 rounded-xl border border-white/5">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-bold text-purple-400"><?php echo htmlspecialchars($row['firstname']); ?></span>
                                <span class="bg-orange-500/20 text-orange-500 px-1.5 py-0.5 rounded-[4px] text-[9px] font-bold">ADMIN</span>
                            </div>
                            <p class="text-[13px] text-gray-300 leading-relaxed"><?php echo nl2br(htmlspecialchars(substr($row['description'], 0, 80))) . '...'; ?></p>
                            <span class="text-[10px] text-gray-500 mt-2 block"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Bottom Grid (Recent Activity 2/3 & Leaderboard 1/3) -->
            <div class="bottom-grid">
                <!-- Recent Activity -->
                <div class="content-card">
                    <div class="card-header">
                        <h2><i class="fas fa-history text-purple-500"></i> RECENT ACTIVITY</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Student Name</th>
                                    <th>Course</th>
                                    <th>Lab/PC</th>
                                    <th>Purpose</th>
                                    <th>Time In</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $activityResult->data_seek(0); // Reset pointer
                                while ($row = $activityResult->fetch_assoc()): 
                                    $statusClass = $row['time_out'] ? 'status-done' : 'status-active';
                                    $statusText = $row['time_out'] ? 'DONE' : 'ACTIVE';
                                ?>
                                <tr>
                                    <td class="text-dim" style="color:#D4870A; font-weight:600;"><?php echo htmlspecialchars($row['idno']); ?></td>
                                    <td class="font-bold"><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['course']); ?></td>
                                    <td>Lab <?php echo htmlspecialchars($row['lab_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($row['time_in'])); ?></td>
                                    <td><span class="status-pill <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Leaderboard Top 3 -->
                <div class="content-card flex flex-col justify-between">
                    <div>
                        <div class="card-header">
                            <h2><i class="fas fa-trophy text-yellow-500"></i> TOP STUDENTS</h2>
                            <a href="rewards.php?tab=leaderboard" class="text-xs text-purple-400 hover:underline">View All</a>
                        </div>
                        <div class="space-y-4">
                            <?php 
                            if (!empty($topStudents)):
                                $rank = 1;
                                foreach ($topStudents as $student):
                                    $avatar = $student['profile_picture'] && $student['profile_picture'] != 'default-profile.png' ? "../upload/" . $student['profile_picture'] : "";
                                    $initials = strtoupper($student['firstname'][0] . $student['lastname'][0]);
                                    
                                    // Rank visual styles
                                    $rankColors = [
                                        1 => ['border' => 'border-yellow-500/30', 'text' => 'text-yellow-400', 'bg' => 'bg-yellow-500/10'],
                                        2 => ['border' => 'border-gray-500/30', 'text' => 'text-gray-300', 'bg' => 'bg-gray-500/10'],
                                        3 => ['border' => 'border-amber-700/30', 'text' => 'text-amber-500', 'bg' => 'bg-amber-700/10']
                                    ];
                                    $style = $rankColors[$rank] ?? ['border' => 'border-white/10', 'text' => 'text-gray-400', 'bg' => 'bg-white/5'];
                            ?>
                                <div class="flex items-center justify-between p-3 bg-white/5 rounded-xl border border-white/5 hover:border-purple-500/40 transition duration-300">
                                    <div class="flex items-center gap-3">
                                        <!-- Rank Badge -->
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center font-black text-xs <?php echo $style['bg'] . ' ' . $style['text'] . ' ' . $style['border']; ?> border">
                                            #<?php echo $rank; ?>
                                        </div>
                                        <!-- Student Profile -->
                                        <?php if ($avatar): ?>
                                            <img src="<?php echo $avatar; ?>" class="w-9 h-9 rounded-full object-cover border border-white/10">
                                        <?php else: ?>
                                            <div class="w-9 h-9 rounded-full bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden">
                                                <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 4px;">
                                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></span>
                                    </div>
                                    <span class="xp-badge">
                                        <i class="fas fa-star text-[10px]"></i>
                                        <span><?php echo number_format((float)$student['total_points'], 2); ?> XP</span>
                                    </span>
                                </div>
                            <?php 
                                    $rank++;
                                endforeach;
                            else:
                            ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-trophy text-2xl mb-2 opacity-30"></i>
                                    <p class="text-xs">No points awarded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Star Background Animation
        (function(){
            const canvas = document.getElementById('star-canvas');
            const ctx = canvas.getContext('2d');
            let W, H, stars = [], shoots = [];

            function resize() {
                W = canvas.width  = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            for (let i = 0; i < 150; i++) {
                stars.push({
                    x: Math.random() * 9999,
                    y: Math.random() * 9999,
                    r: Math.random() * 1.2 + 0.3,
                    a: Math.random(),
                    da: (Math.random() * 0.005 + 0.002) * (Math.random() < .5 ? 1 : -1)
                });
            }

            function spawnShoot() {
                shoots.push({
                    x: Math.random() * W * 1.2,
                    y: Math.random() * H * 0.5,
                    len: Math.random() * 100 + 50,
                    speed: Math.random() * 5 + 3,
                    angle: Math.PI / 4,
                    alpha: 1
                });
            }
            setInterval(spawnShoot, 3000);

            function draw() {
                ctx.clearRect(0, 0, W, H);
                stars.forEach(s => {
                    s.a += s.da;
                    if (s.a <= 0 || s.a >= 1) s.da *= -1;
                    ctx.beginPath();
                    ctx.arc(s.x % W, s.y % H, s.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(200,180,255,${s.a.toFixed(2)})`;
                    ctx.fill();
                });

                shoots.forEach((s, i) => {
                    s.x += Math.cos(s.angle) * s.speed;
                    s.y += Math.sin(s.angle) * s.speed;
                    s.alpha -= 0.015;

                    const grad = ctx.createLinearGradient(
                        s.x - Math.cos(s.angle) * s.len,
                        s.y - Math.sin(s.angle) * s.len,
                        s.x, s.y
                    );
                    grad.addColorStop(0, `rgba(212,135,10,0)`);
                    grad.addColorStop(1, `rgba(200,160,255,${s.alpha.toFixed(2)})`);

                    ctx.beginPath();
                    ctx.moveTo(s.x - Math.cos(s.angle) * s.len, s.y - Math.sin(s.angle) * s.len);
                    ctx.lineTo(s.x, s.y);
                    ctx.strokeStyle = grad;
                    ctx.lineWidth = 1;
                    ctx.stroke();

                    if (s.alpha <= 0) shoots.splice(i, 1);
                });
                requestAnimationFrame(draw);
            }
            draw();
        })();

        // Custom Dropdown Logic
        const dropdown = document.getElementById('labDropdown');
        if (dropdown) {
            const trigger = dropdown.querySelector('.dropdown-trigger');
            const menu = dropdown.querySelector('.dropdown-menu');
            const items = dropdown.querySelectorAll('.dropdown-item');

            trigger.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });

            document.addEventListener('click', function() {
                dropdown.classList.remove('active');
            });

            items.forEach(item => {
                item.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    const url = new URL(window.location.href);
                    url.searchParams.set('filter_lab', value);
                    window.location.href = url.toString();
                });
            });
        }

        // Chart Initialization
        const ctxChart = document.getElementById('sitInTrendChart').getContext('2d');
        const gradient = ctxChart.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(139, 63, 217, 0.4)');
        gradient.addColorStop(1, 'rgba(139, 63, 217, 0)');

        new Chart(ctxChart, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($chartData, 'label')) . "'"; ?>],
                datasets: [{
                    label: 'Sessions',
                    data: [<?php echo implode(",", array_column($chartData, 'count')); ?>],
                    borderColor: '#8B3FD9',
                    borderWidth: 2,
                    pointBackgroundColor: '#8B3FD9',
                    pointRadius: 4,
                    fill: true,
                    backgroundColor: gradient,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        min: 0,
                        suggestedMax: 5,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }, 
                        ticks: { 
                            color: '#9A8FB0', 
                            font: { size: 10 },
                            precision: 0,
                            stepSize: 1
                        } 
                    },
                    x: { grid: { display: false }, ticks: { color: '#9A8FB0', font: { size: 10 } } }
                }
            }
        });
    </script>

    <!-- Announcement Popup Modal -->
    <div id="announcementModal" class="modal-backdrop">
        <div class="modal-content-card">
            <div class="modal-header">
                <h2><i class="fas fa-bullhorn text-purple-400"></i> Post Announcement</h2>
                <button type="button" class="modal-close-btn" id="closeAnnounceModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="adminIndex.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="post_announcement">
                
                <div class="modal-form-group">
                    <label for="postTitle">Title</label>
                    <input type="text" id="postTitle" name="title" class="modal-input" placeholder="Enter announcement title..." required>
                </div>

                <div class="modal-form-group">
                    <label for="postDescription">Description</label>
                    <textarea id="postDescription" name="description" class="modal-textarea" placeholder="Write your announcement details here..." required></textarea>
                </div>

                <div class="modal-form-group">
                    <label>Attachment (Optional)</label>
                    <div class="upload-area" onclick="document.getElementById('modalFileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p id="fileUploadText">Click to browse files</p>
                        <span>Images, PDFs, documents up to 5MB</span>
                        <input type="file" id="modalFileInput" name="attachment" style="display: none;">
                    </div>
                </div>

                <button type="submit" class="modal-submit-btn">POST ANNOUNCEMENT</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['post_success'])): ?>
        <div class="toast-notify" id="toastNotify">
            <i class="fas fa-check-circle text-green-500 text-xl"></i>
            <div style="text-align: left;">
                <h4 style="margin: 0; font-size: 13px; font-weight: 700;">Announcement Posted!</h4>
                <p style="margin: 2px 0 0; font-size: 11px; color: #9A8FB0;">Your post is now live for all students.</p>
            </div>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('toastNotify');
                if (toast) {
                    toast.style.transition = 'opacity 0.5s ease';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }
            }, 4000);
        </script>
        <?php unset($_SESSION['post_success']); ?>
    <?php endif; ?>

    <script>
        // Announcement Popup Modal Logic
        const trigger = document.getElementById('openAnnounceModalTrigger');
        const modal = document.getElementById('announcementModal');
        const closeBtn = document.getElementById('closeAnnounceModal');
        const fileInput = document.getElementById('modalFileInput');
        const fileUploadText = document.getElementById('fileUploadText');

        if (trigger && modal && closeBtn) {
            trigger.addEventListener('click', () => {
                // Reset form on open
                document.getElementById('postTitle').value = '';
                document.getElementById('postDescription').value = '';
                fileInput.value = '';
                if(fileUploadText) {
                    fileUploadText.textContent = 'Click to browse files';
                    fileUploadText.style.color = '#9A8FB0';
                }
                modal.classList.add('show');
            });

            closeBtn.addEventListener('click', () => {
                modal.classList.remove('show');
            });

            // Close on click outside modal content
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });

            // Update file name in upload area when selected
            if (fileInput && fileUploadText) {
                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length > 0) {
                        fileUploadText.textContent = fileInput.files[0].name;
                        fileUploadText.style.color = '#C084FC';
                    } else {
                        fileUploadText.textContent = 'Click to browse files';
                        fileUploadText.style.color = '#9A8FB0';
                    }
                });
            }
        }
    </script>
</body>
</html>