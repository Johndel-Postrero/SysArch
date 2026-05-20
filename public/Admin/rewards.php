<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . '/../../config/db.php';

// 1. Fetch Rewards Data (Completed Sit-ins)
$rewardsSql = "SELECT s.sitin_id, s.idno, u.lastname, u.firstname, u.profile_picture, s.purpose, s.lab_number, s.time_in, s.time_out, s.created_at,
        ABS(TIMESTAMPDIFF(MINUTE, s.time_in, s.time_out)) as duration_mins,
        r.reward_id,
        (SELECT res.pc_number FROM reservations res WHERE res.idno = s.idno AND res.lab_number = s.lab_number AND res.reservation_date = DATE(s.created_at) ORDER BY res.reservation_id DESC LIMIT 1) as res_pc
        FROM sitin s
        JOIN users u ON s.idno = u.idno
        LEFT JOIN rewards r ON s.sitin_id = r.sitin_id
        WHERE s.time_out IS NOT NULL
        ORDER BY s.created_at DESC";

$rewardsResult = $conn->query($rewardsSql);
$sitinData = [];
if ($rewardsResult && $rewardsResult->num_rows > 0) {
    while ($row = $rewardsResult->fetch_assoc()) {
        $row['pc_number'] = $row['res_pc'] ? $row['res_pc'] : (($row['sitin_id'] % 30) + 1);
        $sitinData[] = $row;
    }
}

// 2. Fetch Leaderboard Data (Total Points per Student)
$leaderboardSql = "SELECT 
    u.idno, 
    u.firstname, 
    u.lastname, 
    u.profile_picture,
    COALESCE(rp.reward_count, 0) AS reward_points,
    COALESCE(rp.total_hours, 0.00) AS total_hours,
    COALESCE(rp.tasks_completed, 0) AS tasks_completed,
    COALESCE(rp.total_score, 0.00) AS total_points
FROM users u
LEFT JOIN (
    SELECT 
        idno, 
        COUNT(reward_id) AS reward_count,
        SUM(hours_used) AS total_hours,
        SUM(task_completed) AS tasks_completed,
        SUM(leaderboard_score) AS total_score
    FROM rewards
    GROUP BY idno
) rp ON u.idno = rp.idno
WHERE u.role = 'student'
ORDER BY total_points DESC, u.lastname ASC";
$leaderboardResult = $conn->query($leaderboardSql);
$leaderboardData = [];
if ($leaderboardResult && $leaderboardResult->num_rows > 0) {
    while ($row = $leaderboardResult->fetch_assoc()) { 
        $leaderboardData[] = $row; 
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Leaderboards – CCS Sit-In</title>
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom Sliding Switch styling */
        .toggle-bg {
            position: relative;
            width: 66px;
            height: 28px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6px;
        }
        .toggle-handle {
            position: absolute;
            top: 3px;
            left: 4px;
            width: 20px;
            height: 20px;
            background: #6B7280;
            border-radius: 50%;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .bulk-task-toggle:checked + .toggle-bg {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.4);
        }
        .bulk-task-toggle:checked + .toggle-bg .toggle-handle {
            transform: translateX(36px);
            background: #10B981;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }
        .bulk-task-toggle:checked + .toggle-bg .label-no {
            opacity: 0;
        }
        .bulk-task-toggle:checked + .toggle-bg .label-yes {
            opacity: 1;
        }
        .label-no {
            color: #6B7280;
            font-size: 9px;
            font-weight: 800;
            margin-left: auto;
            padding-right: 4px;
        }
        .label-yes {
            color: #10B981;
            font-size: 9px;
            font-weight: 800;
            opacity: 0;
            padding-left: 4px;
        }
        .label-no, .label-yes {
            transition: opacity 0.2s ease-in-out;
            pointer-events: none;
        }

        /* Tab content visibility */
        .tab-content { display: none; flex: 1; }
        .tab-content.active { display: flex; flex-direction: column; }

        /* XP and Badge Styles */
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

        /* Action Buttons */
        .btn-reward {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.25);
            cursor: pointer;
            transition: all 0.3s;
            font-family: var(--font-b);
        }
        .btn-reward:hover {
            background: #10b981;
            color: #fff;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        }
        .btn-rewarded {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
        }

        .lab-badge {
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            background: rgba(59, 130, 246, 0.1) !important;
            color: #60A5FA !important;
            border: 1px solid rgba(59, 130, 246, 0.2) !important;
        }

        .purpose-badge {
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
        }

        .pc-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(139,63,217,0.1);
            color: var(--purple-light);
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--border);
        }

        .table-container {
            height: 430px !important;
            min-height: 430px !important;
            max-height: 430px !important;
            overflow: hidden !important;
            position: relative;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .custom-table th {
            text-align: left;
            color: var(--text-dim);
            font-weight: 600;
            font-size: 12px;
            padding: 0 20px 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border);
        }

        .custom-table tr {
            height: 52px !important;
        }

        .custom-table td {
            height: 52px !important;
            padding: 0 20px !important;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid transparent;
            border-bottom: 1px solid transparent;
            white-space: nowrap;
        }

        .custom-table tr:hover td {
            background: rgba(139, 63, 217, 0.05);
            border-top: 1px solid rgba(139, 63, 217, 0.2);
            border-bottom: 1px solid rgba(139, 63, 217, 0.2);
        }

        .custom-table td:first-child { border-radius: 12px 0 0 12px; border-left: 1px solid transparent; }
        .custom-table td:last-child { border-radius: 0 12px 12px 0; border-right: 1px solid transparent; }
        .custom-table tr:hover td:first-child { border-left: 1px solid rgba(139, 63, 217, 0.2); }
        .custom-table tr:hover td:last-child { border-right: 1px solid rgba(139, 63, 217, 0.2); }

        .pagination-row {
            margin-top: auto !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
        }

        .dark-checkbox {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border: 1px solid rgba(139, 63, 217, 0.4);
            border-radius: 4px;
            outline: none;
            background-color: rgba(22, 19, 38, 0.6);
            cursor: pointer;
            position: relative;
            display: inline-block;
            vertical-align: middle;
            transition: all 0.3s;
        }

        .dark-checkbox:checked {
            background-color: var(--purple-glow);
            border-color: var(--purple-light);
        }

        .dark-checkbox:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 10px;
            color: #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* Leaderboard Podium Styling (3D pedestal columns, no bounding box) */
        .leaderboard-podium {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            max-width: 680px;
            margin: 40px auto 50px auto;
            gap: 24px;
            align-items: flex-end;
            justify-items: center;
        }

        .podium-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 180px;
            position: relative;
            animation: springUp 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            opacity: 0;
            transform: translateY(80px);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.3s ease;
        }

        .podium-column:hover {
            transform: translateY(-12px) scale(1.03) !important;
            filter: drop-shadow(0 15px 30px rgba(139, 63, 217, 0.3));
        }
        
        .podium-hover-card {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            background: rgba(22, 19, 38, 0.98);
            border: 1px solid rgba(139, 63, 217, 0.5);
            border-radius: 14px;
            padding: 14px 18px;
            width: 220px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), 0 0 20px rgba(139, 63, 217, 0.35);
            backdrop-filter: blur(15px);
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: left;
        }
        
        .podium-column:hover .podium-hover-card {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(-50%) translateY(-20px);
        }
        
        .podium-hover-card::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 8px;
            border-style: solid;
            border-color: rgba(22, 19, 38, 0.98) transparent transparent transparent;
        }
        
        .hover-card-title {
            font-family: var(--font-h);
            font-size: 13px;
            font-weight: 700;
            color: #C084FC;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 6px;
        }
        
        .hover-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .hover-card-row:last-child {
            margin-bottom: 0;
        }
        
        .hover-card-label {
            color: var(--text-dim);
            font-weight: 500;
        }
        
        .hover-card-val {
            color: #fff;
            font-weight: 700;
        }

        .podium-column.second { animation-delay: 0.2s; }
        .podium-column.first { animation-delay: 0s; z-index: 10; width: 200px; }
        .podium-column.third { animation-delay: 0.4s; }

        .pedestal {
            width: 100%;
            background: linear-gradient(180deg, #1b162b 0%, #0d0a17 100%);
            border: 1px solid rgba(139, 63, 217, 0.25);
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px 10px;
        }

        /* 1st Place Pedestal */
        .podium-column.first .pedestal {
            height: 190px;
            border-color: rgba(251, 191, 36, 0.5);
            box-shadow: 0 20px 40px rgba(251, 191, 36, 0.15), 0 15px 35px rgba(0, 0, 0, 0.6);
        }

        /* 2nd Place Pedestal */
        .podium-column.second .pedestal {
            height: 150px;
            border-color: rgba(156, 163, 175, 0.4);
        }

        /* 3rd Place Pedestal */
        .podium-column.third .pedestal {
            height: 120px;
            border-color: rgba(180, 83, 9, 0.4);
        }

        .avatar-ring {
            position: relative;
        }

        /* Ensure all podium images are perfectly circle */
        .avatar-ring img {
            border-radius: 50% !important;
            object-fit: cover !important;
        }

        /* Avatar sizing based on rank */
        .podium-column.first .avatar-ring img, .podium-column.first .avatar-placeholder {
            width: 98px; height: 98px;
            border: 4px solid #FBBF24;
            animation: champGlow 2.5s ease-in-out infinite;
        }
        .podium-column.second .avatar-ring img, .podium-column.second .avatar-placeholder {
            width: 82px; height: 82px;
            border: 4px solid #9CA3AF;
            box-shadow: 0 0 15px rgba(156, 163, 175, 0.25);
        }
        .podium-column.third .avatar-ring img, .podium-column.third .avatar-placeholder {
            width: 72px; height: 72px;
            border: 4px solid #B45309;
            box-shadow: 0 0 12px rgba(180, 83, 9, 0.2);
        }

        .avatar-placeholder {
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
        }

        /* Animations */
        @keyframes springUp {
            0% { opacity: 0; transform: translateY(80px) scale(0.9); }
            70% { transform: translateY(-10px) scale(1.02); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }

        @keyframes floatCrown {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(3deg); }
        }

        @keyframes champGlow {
            0%, 100% { 
                box-shadow: 0 0 15px rgba(251, 191, 36, 0.4), inset 0 0 10px rgba(251, 191, 36, 0.2); 
                border-color: #FBBF24;
            }
            50% { 
                box-shadow: 0 0 35px rgba(251, 191, 36, 0.75), inset 0 0 15px rgba(251, 191, 36, 0.4); 
                border-color: #FFFbeb;
            }
        }

        .rank-1-crown {
            animation: floatCrown 3s ease-in-out infinite;
        }

        /* Custom Scrollbar matching the theme */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0d0a17;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(139, 63, 217, 0.4);
            border-radius: 4px;
            border: 1px solid rgba(139, 63, 217, 0.1);
            transition: background 0.3s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 63, 217, 0.7);
            box-shadow: 0 0 10px rgba(139, 63, 217, 0.5);
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php 
    $pageTitle = "Reward";
    include 'sidebarad.php'; 
    ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <!-- Tabs (Styled exactly as Reservation) -->
            <div class="analytics-tabs">
                <button id="rewardsTab" class="analytics-tab-btn active" onclick="switchTab('rewards')">
                    <i class="fas fa-gift"></i>
                    <span>Rewards</span>
                </button>
                <button id="leaderboardTab" class="analytics-tab-btn" onclick="switchTab('leaderboard')">
                    <i class="fas fa-trophy"></i>
                    <span>Leaderboards</span>
                </button>
            </div>

            <!-- Controls Box (Styled exactly as Reservation) -->
            <div class="controls-row">
                <div class="controls-left">
                    <button id="selectStudentBtn" class="filter-btn" style="background: rgba(139,63,217,0.15); border: 1px solid var(--border); color: var(--purple-light); display: flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 13px; cursor: pointer; transition: all 0.3s;">
                        <i class="fas fa-list-check"></i> <span>Select Student</span>
                    </button>
                    <div id="bulkActions" style="display: none; gap: 8px; margin-left: 8px; align-items: center;">
                        <button id="bulkRewardBtn" class="btn-reward" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #10b981; padding: 10px 20px; border-radius: 12px; font-weight: 600; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-gift"></i> Reward Selected
                        </button>
                    </div>
                    <!-- Hidden entries select to keep pagination/filtering logic from breaking, set default to 6 -->
                    <select id="entries" style="display:none;"><option value="6" selected>6</option></select>
                </div>
                <div class="controls-right">
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" placeholder="Search..." type="text" oninput="filterTable()"/>
                    </div>
                    <div style="position:relative;" id="filterContainer">
                        <button id="filterButton" class="filter-btn">
                            <i class="fas fa-filter"></i><span>Filter</span>
                        </button>
                        <div id="filterDropdown" class="filter-dropdown hidden">
                            <label>Laboratory</label>
                            <select id="labFilter" class="dark-select" style="width:100%;" onchange="filterTable()">
                                <option value="">All Labs</option>
                                <option value="524">524</option>
                                <option value="526">526</option>
                                <option value="528">528</option>
                                <option value="530">530</option>
                                <option value="542">542</option>
                                <option value="544">544</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- REWARDS TAB -->
            <div id="rewardsContent" class="tab-content active">
              <div class="content-card">
                <div class="records-header">
                    <div class="records-title">
                        <h3>Pending Rewards</h3>
                    </div>
                </div>
                <div class="table-container">
                    <div class="table-wrapper">
                        <table id="rewardsTable" class="custom-table">
                            <thead>
                                <tr>
                                    <th class="select-col" style="display:none; width: 40px;"><input type="checkbox" id="selectAllRewards" class="dark-checkbox"></th>
                                    <th>PC NUMBER</th>
                                    <th>ID NUMBER</th>
                                    <th>STUDENT NAME</th>
                                    <th>PURPOSE</th>
                                    <th>LAB</th>
                                    <th>LOGIN</th>
                                    <th>LOGOUT</th>
                                    <th>DATE</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $purposeColors = [
                                    "C Programming" => "bg-pink-500/20 text-pink-400 border border-pink-500/30",
                                    "C# Programming" => "bg-purple-500/20 text-purple-400 border border-purple-500/30",
                                    "Java Programming" => "bg-yellow-500/20 text-yellow-400 border border-yellow-500/30",
                                    "PHP Programming" => "bg-blue-500/20 text-blue-400 border border-blue-500/30",
                                    "ASP Net" => "bg-orange-500/20 text-orange-400 border border-orange-500/30",
                                    "Web Development" => "bg-green-500/20 text-green-400 border border-green-500/30",
                                    "Systems Integration & Architecture" => "bg-indigo-500/20 text-indigo-400 border border-indigo-500/30",
                                    "Embedded Systems & IoT" => "bg-red-500/20 text-red-400 border border-red-500/30",
                                    "Digital Logic & Design" => "bg-teal-500/20 text-teal-400 border border-teal-500/30",
                                    "Computer Application" => "bg-cyan-500/20 text-cyan-400 border border-cyan-500/30",
                                    "Database" => "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30",
                                    "Project Management" => "bg-amber-500/20 text-amber-400 border border-amber-500/30",
                                    "Mobile Application" => "bg-fuchsia-500/20 text-fuchsia-400 border border-fuchsia-500/30",
                                    "Others" => "bg-gray-500/20 text-gray-400 border border-gray-500/30"
                                ];
                                
                                if (!empty($sitinData)): 
                                    foreach ($sitinData as $sitin): 
                                        $pColor = $purposeColors[$sitin['purpose']] ?? "bg-gray-500/20 text-gray-400 border border-gray-500/30";
                                        $isRewarded = !empty($sitin['reward_id']);
                                        
                                        // Prepare data for JS
                                        $fullName = addslashes($sitin['lastname'] . ', ' . $sitin['firstname']);
                                        $avatar = $sitin['profile_picture'] && $sitin['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/../upload/' . $sitin['profile_picture']) ? "../upload/" . $sitin['profile_picture'] : "";
                                        $duration = $sitin['duration_mins'] . " mins";
                                ?>
                                    <tr class="table-row">
                                        <td class="select-col" style="display:none;"><input type="checkbox" class="reward-checkbox dark-checkbox" value="<?php echo $sitin['sitin_id']; ?>" data-idno="<?php echo $sitin['idno']; ?>" data-fullname="<?php echo $fullName; ?>" data-purpose="<?php echo htmlspecialchars($sitin['purpose']); ?>" data-lab="<?php echo htmlspecialchars($sitin['lab_number']); ?>" data-duration="<?php echo $duration; ?>" data-avatar="<?php echo $avatar; ?>" <?php echo $isRewarded ? 'disabled' : ''; ?>></td>
                                        <td class="font-medium text-white">PC <?php echo htmlspecialchars($sitin['pc_number']); ?></td>
                                        <td class="text-orange-400 font-medium"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                        <td class="text-white"><?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname']); ?></td>
                                        <td><span class="purpose-badge <?php echo $pColor; ?>"><?php echo htmlspecialchars($sitin['purpose']); ?></span></td>
                                        <td><span class="lab-badge"><?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                        <td class="text-gray-300"><?php echo date('h:i A', strtotime($sitin['time_in'])); ?></td>
                                        <td class="text-gray-300"><?php echo date('h:i A', strtotime($sitin['time_out'])); ?></td>
                                        <td class="text-gray-300"><?php echo date('Y-m-d', strtotime($sitin['created_at'])); ?></td>
                                        <td>
                                            <div id="action-cell-<?php echo $sitin['sitin_id']; ?>">
                                                <?php if (!$isRewarded): ?>
                                                    <button onclick="openRewardModal(<?php echo $sitin['sitin_id']; ?>, <?php echo $sitin['idno']; ?>, '<?php echo $fullName; ?>', '<?php echo htmlspecialchars($sitin['purpose']); ?>', '<?php echo htmlspecialchars($sitin['lab_number']); ?>', '<?php echo $duration; ?>', '<?php echo $avatar; ?>')" class="btn-reward">
                                                        <i class="fas fa-gift"></i> Reward
                                                    </button>
                                                <?php else: ?>
                                                    <button disabled class="btn-rewarded">
                                                        <i class="fas fa-check-circle"></i> Rewarded
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endforeach; 
                                else:
                                ?>
                                    <tr class="not-record"><td colspan="10" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-inbox mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>No completed sessions found</td></tr>
                                <?php endif; ?>
                                <tr class="not-record" id="rewardsNoMatch" style="display:none;"><td colspan="10" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-search mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>No matching pending rewards found</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="pagination-row">
                    <div class="pagination-info" id="rewardsPaginationInfo"></div>
                    <div class="pagination-controls" id="rewardsPaginationControls"></div>
                </div>
              </div><!-- end content-card -->
            </div>

            <!-- LEADERBOARD TAB -->
            <div id="leaderboardContent" class="tab-content">
                <!-- Podium (Now completely outside the box, sitting on pure background) -->
                <?php if (count($leaderboardData) >= 1): ?>
                <div class="leaderboard-podium">
                    <!-- 2nd Place (Grid Column 1) -->
                    <?php if (isset($leaderboardData[1])): 
                        $p2 = $leaderboardData[1];
                        $p2Avatar = $p2['profile_picture'] && $p2['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/../upload/' . $p2['profile_picture']) ? "../upload/".$p2['profile_picture'] : "";
                        $p2Initials = strtoupper($p2['firstname'][0] . $p2['lastname'][0]);
                    ?>
                    <div class="podium-column second">
                        <div class="podium-hover-card">
                            <div class="hover-card-title"><?php echo htmlspecialchars($p2['firstname'] . ' ' . $p2['lastname']); ?></div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Hours</span>
                                <span class="hover-card-val"><?php echo number_format((float)$p2['total_hours'], 2); ?> hrs</span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Rewards</span>
                                <span class="hover-card-val"><?php echo (int)$p2['reward_points']; ?></span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Tasks Completed</span>
                                <span class="hover-card-val"><?php echo (int)$p2['tasks_completed']; ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center mb-4">
                            <div class="avatar-ring mb-2">
                                <?php if($p2Avatar): ?>
                                    <img src="<?php echo $p2Avatar; ?>" alt="2nd Place">
                                <?php else: ?>
                                    <div class="avatar-placeholder w-[80px] h-[80px] rounded-full bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden">
                                        <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 6px;">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="font-bold text-white text-base text-center truncate w-40"><?php echo htmlspecialchars($p2['firstname'] . ' ' . $p2['lastname']); ?></div>
                        </div>
                        <div class="pedestal">
                            <i class="fas fa-trophy text-gray-400 text-xl mb-1.5"></i>
                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">2nd Place</span>
                            <span class="text-white font-extrabold text-lg mt-1"><?php echo number_format($p2['total_points'], 2); ?> XP</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="podium-column second opacity-0 pointer-events-none select-none"></div>
                    <?php endif; ?>
 
                    <!-- 1st Place (Grid Column 2) -->
                    <?php if (isset($leaderboardData[0])): 
                        $p1 = $leaderboardData[0];
                        $p1Avatar = $p1['profile_picture'] && $p1['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/../upload/' . $p1['profile_picture']) ? "../upload/".$p1['profile_picture'] : "";
                        $p1Initials = strtoupper($p1['firstname'][0] . $p1['lastname'][0]);
                    ?>
                    <div class="podium-column first">
                        <div class="podium-hover-card">
                            <div class="hover-card-title"><?php echo htmlspecialchars($p1['firstname'] . ' ' . $p1['lastname']); ?></div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Hours</span>
                                <span class="hover-card-val"><?php echo number_format((float)$p1['total_hours'], 2); ?> hrs</span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Rewards</span>
                                <span class="hover-card-val"><?php echo (int)$p1['reward_points']; ?></span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Tasks Completed</span>
                                <span class="hover-card-val"><?php echo (int)$p1['tasks_completed']; ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center mb-4">
                            <i class="fas fa-crown text-yellow-400 text-3xl mb-1 drop-shadow-[0_0_8px_rgba(251,191,36,0.7)]"></i>
                            <div class="avatar-ring mb-2">
                                <?php if($p1Avatar): ?>
                                    <img src="<?php echo $p1Avatar; ?>" alt="1st Place">
                                <?php else: ?>
                                    <div class="avatar-placeholder w-[96px] h-[96px] rounded-full bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden">
                                        <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 8px;">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="font-bold text-white text-lg text-center truncate w-44"><?php echo htmlspecialchars($p1['firstname'] . ' ' . $p1['lastname']); ?></div>
                        </div>
                        <div class="pedestal">
                            <i class="fas fa-trophy text-yellow-400 text-2xl mb-1.5 drop-shadow-[0_0_10px_rgba(251,191,36,0.4)]"></i>
                            <span class="text-[10px] text-yellow-400 font-bold uppercase tracking-wider">Champion</span>
                            <span class="text-yellow-400 font-black text-xl mt-1"><?php echo number_format($p1['total_points'], 2); ?> XP</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="podium-column first opacity-0 pointer-events-none select-none"></div>
                    <?php endif; ?>
 
                    <!-- 3rd Place (Grid Column 3) -->
                    <?php if (isset($leaderboardData[2])): 
                        $p3 = $leaderboardData[2];
                        $p3Avatar = $p3['profile_picture'] && $p3['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/../upload/' . $p3['profile_picture']) ? "../upload/".$p3['profile_picture'] : "";
                        $p3Initials = strtoupper($p3['firstname'][0] . $p3['lastname'][0]);
                    ?>
                    <div class="podium-column third">
                        <div class="podium-hover-card">
                            <div class="hover-card-title"><?php echo htmlspecialchars($p3['firstname'] . ' ' . $p3['lastname']); ?></div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Hours</span>
                                <span class="hover-card-val"><?php echo number_format((float)$p3['total_hours'], 2); ?> hrs</span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Total Rewards</span>
                                <span class="hover-card-val"><?php echo (int)$p3['reward_points']; ?></span>
                            </div>
                            <div class="hover-card-row">
                                <span class="hover-card-label">Tasks Completed</span>
                                <span class="hover-card-val"><?php echo (int)$p3['tasks_completed']; ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center mb-4">
                            <div class="avatar-ring mb-2">
                                <?php if($p3Avatar): ?>
                                    <img src="<?php echo $p3Avatar; ?>" alt="3rd Place">
                                <?php else: ?>
                                    <div class="avatar-placeholder w-[70px] h-[70px] rounded-full bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden">
                                        <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 5px;">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="font-bold text-white text-sm text-center truncate w-36"><?php echo htmlspecialchars($p3['firstname'] . ' ' . $p3['lastname']); ?></div>
                        </div>
                        <div class="pedestal">
                            <i class="fas fa-trophy text-amber-700 text-lg mb-1.5"></i>
                            <span class="text-[10px] text-amber-600 font-bold uppercase tracking-wider">3rd Place</span>
                            <span class="text-white font-extrabold text-base mt-1"><?php echo number_format($p3['total_points'], 2); ?> XP</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="podium-column third opacity-0 pointer-events-none select-none"></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="table-container">
                    <div class="table-wrapper">
                        <table id="leaderboardTable" class="custom-table">
                            <thead>
                                <tr>
                                    <th>RANK</th>
                                    <th>STUDENT</th>
                                    <th>ID NUMBER</th>
                                    <th style="text-align: center;">TOTAL HOURS</th>
                                    <th style="text-align: center;">TOTAL REWARDS</th>
                                    <th style="text-align: center;">TASKS COMPLETED</th>
                                    <th>TOTAL XP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $ranks4to10 = array_slice($leaderboardData, 3, 7); // Ranks 4 to 10
                                if (!empty($ranks4to10)): 
                                    $rank = 4;
                                    foreach ($ranks4to10 as $user):
                                        $avatar = $user['profile_picture'] && $user['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/../upload/' . $user['profile_picture']) ? "../upload/".$user['profile_picture'] : "";
                                        $initials = strtoupper($user['firstname'][0] . $user['lastname'][0]);
                                ?>
                                    <tr class="table-row">
                                        <td><span class="text-gray-400 font-bold">#<?php echo $rank; ?></span></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <?php if($avatar): ?>
                                                    <img src="<?php echo $avatar; ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
                                                <?php else: ?>
                                                    <div class="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden">
                                                        <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 2px;">
                                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="text-white font-medium"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-orange-400 font-medium"><?php echo htmlspecialchars($user['idno']); ?></td>
                                        <td style="text-align: center;"><span class="text-white font-medium"><?php echo number_format((float)$user['total_hours'], 2); ?> hrs</span></td>
                                        <td style="text-align: center;"><span class="text-white font-medium"><?php echo (int)$user['reward_points']; ?></span></td>
                                        <td style="text-align: center;"><span class="text-white font-medium"><?php echo (int)$user['tasks_completed']; ?></span></td>
                                        <td>
                                            <span class="xp-badge">
                                                <i class="fas fa-star"></i>
                                                <span><?php echo number_format($user['total_points'], 2); ?> XP</span>
                                            </span>
                                        </td>
                                    </tr>
                                <?php 
                                        $rank++;
                                    endforeach; 
                                else:
                                ?>
                                    <tr class="not-record"><td colspan="7" style="text-align:center;padding:40px 20px;color:#9A8FB0;"><i class="fas fa-trophy mb-3" style="font-size:30px;display:block;opacity:0.3;color:#8B3FD9;"></i>No other ranked students yet</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- end student-content -->
    </div><!-- end main-wrapper -->

    <!-- Bulk Reward Modal -->
    <div id="bulkRewardModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[600px] bg-[#161326] border border-orange-500/50 rounded-2xl p-6 shadow-[0_0_40px_rgba(212,135,10,0.3)] flex flex-col max-h-[90vh] overflow-hidden">
            <!-- Glowing Top Bar -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-400 via-yellow-300 to-orange-400"></div>

            <div class="flex flex-col items-center mb-4 mt-2">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-b from-orange-400 to-orange-600 flex items-center justify-center shadow-[0_0_20px_rgba(245,158,11,0.5)] mb-3 border border-orange-300/50">
                    <i class="fas fa-gift text-2xl text-white drop-shadow-md"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-1">Bulk Reward Students</h2>
                <p class="text-xs text-gray-400 text-center px-4">Review and customize the task completion status for each selected student individually before awarding XP.</p>
            </div>

            <!-- Scrollable Student List -->
            <div class="flex-1 overflow-y-auto pr-1 mb-6 max-h-[45vh] custom-scrollbar border border-white/5 rounded-xl bg-black/20 p-3">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 text-gray-400 font-semibold uppercase tracking-wider text-[10px]">
                            <th class="py-2.5 px-3">Student</th>
                            <th class="py-2.5 px-3 text-center">Duration</th>
                            <th class="py-2.5 px-3 text-center">PC Number</th>
                            <th class="py-2.5 px-3 text-center">Task Status</th>
                        </tr>
                    </thead>
                    <tbody id="bulkRewardTableBody" class="divide-y divide-white/5">
                        <!-- Dynamically filled with selected students -->
                    </tbody>
                </table>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button onclick="closeBulkRewardModal()" class="flex-1 py-3 rounded-xl border border-white/10 text-gray-300 font-semibold text-sm hover:bg-white/5 transition">
                    <i class="fas fa-times mr-2 text-gray-500"></i>Cancel
                </button>
                <button id="btnConfirmBulkReward" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-orange-500 to-yellow-500 text-black font-bold text-sm shadow-[0_0_15px_rgba(245,158,11,0.4)] hover:shadow-[0_0_25px_rgba(245,158,11,0.6)] transition flex justify-center items-center gap-2">
                    <i class="fas fa-star"></i>Confirm & Award All
                </button>
            </div>
        </div>
    </div>

    <!-- Task Completion Confirmation Dialog -->
    <!-- Custom Themed Alert Modal -->
    <div id="customAlertModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[360px] bg-[#161326] border border-purple-500/30 rounded-2xl p-6 shadow-[0_0_50px_rgba(139,63,217,0.3)] flex flex-col items-center overflow-hidden transition-all duration-300 transform scale-95 opacity-0" id="customAlertBox">
            <!-- Glowing Accent Line -->
            <div id="customAlertAccent" class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-indigo-500"></div>
            
            <div class="flex flex-col items-center mb-5 mt-2">
                <div id="customAlertIconWrapper" class="w-14 h-14 rounded-2xl flex items-center justify-center mb-3">
                    <i id="customAlertIcon" class="fas text-2xl"></i>
                </div>
                <h3 id="customAlertTitle" class="text-lg font-bold text-white mb-2">Notification</h3>
                <p id="customAlertMessage" class="text-xs text-gray-400 text-center px-2 leading-relaxed"></p>
            </div>
            
            <button id="btnCustomAlertClose" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs transition duration-200 shadow-[0_0_15px_rgba(168,85,247,0.3)] hover:shadow-[0_0_20px_rgba(168,85,247,0.5)]">
                Acknowledge
            </button>
        </div>
    </div>

    <div id="taskConfirmModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[400px] bg-[#161326] border border-orange-500/50 rounded-2xl p-6 shadow-[0_0_50px_rgba(212,135,10,0.4)] flex flex-col overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-400 via-yellow-300 to-orange-400"></div>
            
            <div class="flex flex-col items-center mb-6 mt-2">
                <div class="w-14 h-14 rounded-2xl bg-orange-500/20 border border-orange-500/40 flex items-center justify-center shadow-[0_0_15px_rgba(245,158,11,0.3)] mb-4">
                    <i class="fas fa-clipboard-check text-2xl text-orange-400"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Task Completion</h3>
                <p id="taskConfirmMessage" class="text-sm text-gray-400 text-center px-2"></p>
            </div>
            
            <div class="flex flex-col gap-3 mb-6">
                <!-- Option Yes -->
                <button type="button" id="btnTaskYes" class="flex items-center justify-between p-4 rounded-xl border border-orange-500 bg-orange-500/10 text-white font-semibold transition hover:bg-orange-500/20">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-orange-400 text-lg"></i>
                        <div class="text-left">
                            <span class="block text-sm font-bold">Yes, Task Completed</span>
                            <span class="block text-[10px] text-gray-400">Grants +20% score weight</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-xs text-orange-400"></i>
                </button>
                
                <!-- Option No -->
                <button type="button" id="btnTaskNo" class="flex items-center justify-between p-4 rounded-xl border border-white/10 bg-white/5 text-gray-300 font-semibold transition hover:bg-white/10 hover:text-white">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-times-circle text-gray-500 text-lg"></i>
                        <div class="text-left">
                            <span class="block text-sm font-bold">No, Incomplete / Other</span>
                            <span class="block text-[10px] text-gray-500">Grants +0% score weight</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-xs text-gray-500"></i>
                </button>
            </div>
            
            <button onclick="closeTaskConfirmModal()" class="py-2.5 rounded-xl border border-white/5 bg-white/5 text-xs text-gray-400 hover:bg-white/10 hover:text-white transition font-medium">
                Cancel Reward
            </button>
        </div>
    </div>

    <!-- Give Reward Modal -->
    <div id="rewardModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[420px] bg-[#161326] border border-orange-500/50 rounded-2xl p-6 shadow-[0_0_40px_rgba(212,135,10,0.3)] flex flex-col overflow-hidden">
            <!-- Glowing Top Bar -->
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-400 via-yellow-300 to-orange-400"></div>

            <div class="flex flex-col items-center mb-6 mt-2">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-b from-orange-400 to-orange-600 flex items-center justify-center shadow-[0_0_20px_rgba(245,158,11,0.5)] mb-4 border border-orange-300/50">
                    <i class="fas fa-star text-3xl text-white drop-shadow-md"></i>
                </div>
                <h2 class="text-2xl font-bold text-white mb-1">Give Reward?</h2>
                <p class="text-sm text-gray-400 text-center px-4">You are about to award points to this student for completing their sit-in session.</p>
            </div>

            <!-- Student Card -->
            <div class="bg-black/40 border border-white/10 rounded-xl p-4 mb-4">
                <div class="flex items-center gap-4 mb-4">
                    <div class="relative">
                        <div id="modAvatarWrapper" class="w-12 h-12 rounded-lg bg-purple-500/20 border border-purple-500/40 text-purple-400 flex items-center justify-center font-bold text-lg"></div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-orange-500 rounded-full flex items-center justify-center border border-[#161326]"><i class="fas fa-star text-[8px] text-white"></i></div>
                    </div>
                    <div>
                        <h3 id="modName" class="text-white font-bold text-base leading-tight"></h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span id="modIdno" class="text-orange-400 font-bold text-xs"></span>
                            <span class="text-gray-500 text-[10px]">&bull;</span>
                            <span class="text-gray-400 text-xs">Student</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 text-center">
                    <div class="flex-1 bg-white/5 rounded-lg p-2 border border-white/5">
                        <div class="text-[9px] text-gray-500 uppercase tracking-wider mb-1"><i class="fas fa-code mr-1"></i>Purpose</div>
                        <div id="modPurpose" class="text-blue-400 font-bold text-xs truncate w-full px-1"></div>
                    </div>
                    <div class="flex-1 bg-white/5 rounded-lg p-2 border border-white/5">
                        <div class="text-[9px] text-gray-500 uppercase tracking-wider mb-1"><i class="fas fa-desktop mr-1"></i>Lab</div>
                        <div id="modLab" class="text-teal-400 font-bold text-xs"></div>
                    </div>
                    <div class="flex-1 bg-white/5 rounded-lg p-2 border border-white/5">
                        <div class="text-[9px] text-gray-500 uppercase tracking-wider mb-1"><i class="fas fa-clock mr-1"></i>Duration</div>
                        <div id="modDuration" class="text-purple-400 font-bold text-xs"></div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard Score Projection Breakdown -->
            <div class="border border-white/10 rounded-xl p-4 mb-4 bg-gradient-to-b from-transparent to-orange-950/10 flex flex-col gap-2">
                <div class="text-[10px] text-gray-400 uppercase tracking-widest font-semibold text-center mb-1"><i class="fas fa-calculator mr-1"></i>Leaderboard XP Breakdown</div>
                
                <div class="flex justify-between items-center text-[11px]">
                    <span class="text-gray-400">1. Reward Points (60%)</span>
                    <span id="xpBreakdownBase" class="text-white font-medium"></span>
                </div>
                <div class="flex justify-between items-center text-[11px]">
                    <span class="text-gray-400">2. Task Completion (20%)</span>
                    <span id="xpBreakdownTask"></span>
                </div>
                <div class="flex justify-between items-center text-[11px]">
                    <span class="text-gray-400">3. Total Hours Used (20%)</span>
                    <span id="xpBreakdownHours" class="text-white font-medium"></span>
                </div>
                
                <div class="h-[1px] bg-white/10 my-1"></div>
                
                <div class="flex justify-between items-center">
                    <span class="text-xs text-orange-400 font-bold uppercase tracking-wider">Projected XP Gain</span>
                    <span id="xpTotalProjected" class="text-xl font-black text-orange-400 drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]"></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button onclick="closeRewardModal()" class="flex-1 py-3 rounded-xl border border-white/10 text-gray-300 font-semibold text-sm hover:bg-white/5 transition">
                    <i class="fas fa-times mr-2 text-gray-500"></i>Cancel
                </button>
                <button id="btnConfirmReward" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-orange-500 to-yellow-500 text-black font-bold text-sm shadow-[0_0_15px_rgba(245,158,11,0.4)] hover:shadow-[0_0_25px_rgba(245,158,11,0.6)] transition flex justify-center items-center gap-2">
                    <i class="fas fa-star"></i>Confirm Reward
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Toast -->
    <div id="rewardToast" class="fixed top-24 right-6 z-[10000] transform transition-all duration-300 translate-x-[150%] flex items-center bg-[#1a1625] border border-orange-500/40 rounded-xl p-3 pr-4 shadow-[0_10px_25px_rgba(0,0,0,0.5)]">
        <div class="w-1 h-full bg-gradient-to-b from-orange-400 to-yellow-500 absolute left-0 top-0 rounded-l-xl"></div>
        <div class="w-10 h-10 rounded-lg bg-orange-500/20 border border-orange-500/30 flex justify-center items-center ml-2 mr-3">
            <i class="fas fa-star text-orange-400 text-lg drop-shadow-md"></i>
        </div>
        <div>
            <h4 class="text-orange-400 font-bold text-sm mb-0.5">Reward Given!</h4>
            <p id="toastMessage" class="text-gray-300 text-xs"><i class="fas fa-star text-[10px] text-yellow-400 mr-1"></i>Reward given to <span id="toastName" class="font-bold text-white"></span>!</p>
        </div>
        <button onclick="closeToast()" class="ml-6 w-6 h-6 flex items-center justify-center rounded-full bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white transition">
            <i class="fas fa-times text-[10px]"></i>
        </button>
    </div>

    <script>
        // Star Background Animation
        (function(){
            const canvas = document.getElementById('star-canvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            let W, H, stars = [];

            function resize() {
                W = canvas.width  = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            for (let i = 0; i < 150; i++) {
                stars.push({
                    x: Math.random() * 9999, y: Math.random() * 9999,
                    r: Math.random() * 1.2 + 0.3, a: Math.random(),
                    da: (Math.random() * 0.005 + 0.002) * (Math.random() < .5 ? 1 : -1)
                });
            }

            function draw() {
                ctx.clearRect(0, 0, W, H);
                stars.forEach(s => {
                    s.a += s.da;
                    if (s.a <= 0 || s.a >= 1) s.da *= -1;
                    ctx.beginPath();
                    ctx.arc(s.x % W, s.y % H, s.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(200,180,255,${Math.abs(s.a).toFixed(2)})`;
                    ctx.fill();
                });
                requestAnimationFrame(draw);
            }
            draw();
        })();

        // Tab Switching Logic
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.analytics-tab-btn').forEach(b => b.classList.remove('active'));
            
            if (tabId === 'rewards') {
                document.getElementById('rewardsContent').classList.add('active');
                document.getElementById('rewardsTab').classList.add('active');
                
                // Show the entire controls row for rewards page (Entries select, Search and Filter)
                document.querySelector('.controls-row').style.display = 'flex';
            } else {
                document.getElementById('leaderboardContent').classList.add('active');
                document.getElementById('leaderboardTab').classList.add('active');
                
                // Completely hide the entire controls row on leaderboard tab (as requested)
                document.querySelector('.controls-row').style.display = 'none';
            }
            
            document.getElementById('searchInput').value = '';
            document.getElementById('labFilter').value = '';
            currentPage = 1;
            filterTable();
        }

        // Filter dropdown toggle
        document.getElementById('filterButton').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('filterDropdown').classList.toggle('hidden');
        });
        document.addEventListener('click', function() {
            document.getElementById('filterDropdown').classList.add('hidden');
        });
        document.getElementById('filterDropdown').addEventListener('click', function(e) { e.stopPropagation(); });

        // Table Pagination & Search Logic
        let currentPage = 1, totalPages = 1;

        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const labValue = document.getElementById('labFilter').value.toLowerCase();
            const activeTab = document.querySelector('.tab-content.active').id;
            const activeTable = document.querySelector('.tab-content.active').querySelector('table');
            const rows = activeTable.querySelectorAll('tbody tr:not(.not-record)');
            
            let visibleRows = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const labCellIndex = cells[0].querySelector('input[type="checkbox"]') ? 5 : 4;
                const labCell = cells[labCellIndex] ? cells[labCellIndex].textContent.toLowerCase() : '';
                
                let matchesSearch = searchValue ? Array.from(cells).some(c => c.textContent.toLowerCase().includes(searchValue)) : true;
                let matchesLab = true;
                if (activeTab === 'rewardsContent' && labValue) {
                    matchesLab = labCell.includes(labValue);
                }
                
                if (matchesSearch && matchesLab) {
                    visibleRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            const noMatchRow = activeTable.querySelector('[id$="NoMatch"]');
            if (rows.length > 0) {
                if (visibleRows.length === 0) {
                    if (noMatchRow) noMatchRow.style.display = '';
                } else {
                    if (noMatchRow) noMatchRow.style.display = 'none';
                }
            }

            // On Leaderboard tab, no pagination display
            if (activeTab === 'leaderboardContent') {
                visibleRows.forEach(r => r.style.display = '');
                updateSingleRewardButtons();
                return;
            }

            const entriesPerPage = document.getElementById('entries').value;
            if (entriesPerPage === 'all') {
                visibleRows.forEach(r => r.style.display = '');
                updatePagination(visibleRows.length, true);
                updateSingleRewardButtons();
                return;
            }

            const num = parseInt(entriesPerPage);
            totalPages = Math.ceil(visibleRows.length / num);
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
            else if (totalPages === 0) currentPage = 1;

            const start = (currentPage - 1) * num;
            visibleRows.forEach(r => r.style.display = 'none');
            visibleRows.slice(start, start + num).forEach(r => r.style.display = '');
            
            updatePagination(visibleRows.length, false);
            updateSingleRewardButtons();
        }

        function updatePagination(total, showAll) {
            const info = document.getElementById('rewardsPaginationInfo');
            const controls = document.getElementById('rewardsPaginationControls');
            const epp = document.getElementById('entries').value;

            if (epp === 'all' || showAll || totalPages <= 1) {
                info.textContent = `Showing ${total} entries`;
                controls.innerHTML = '';
                return;
            }

            const num = parseInt(epp);
            const s = total === 0 ? 0 : (currentPage - 1) * num + 1;
            const e = Math.min(currentPage * num, total);
            info.textContent = `Showing ${s} to ${e} of ${total} entries`;
            controls.innerHTML = '';

            // Prev
            const prev = document.createElement('button');
            prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prev.className = 'page-btn'; 
            prev.disabled = currentPage === 1;
            prev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; filterTable(); } });
            controls.appendChild(prev);

            // Pages
            const max = 5;
            let sp = Math.max(1, currentPage - Math.floor(max / 2));
            let ep = Math.min(totalPages, sp + max - 1);
            if (ep - sp + 1 < max) sp = Math.max(1, ep - max + 1);

            for (let i = sp; i <= ep; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
                btn.addEventListener('click', () => { currentPage = i; filterTable(); });
                controls.appendChild(btn);
            }

            // Next
            const next = document.createElement('button');
            next.innerHTML = '<i class="fas fa-chevron-right"></i>';
            next.className = 'page-btn'; 
            next.disabled = currentPage === totalPages;
            next.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; filterTable(); } });
            controls.appendChild(next);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'leaderboard') {
                switchTab('leaderboard');
            } else {
                filterTable();
            }
        });

        // Custom Alert Logic
        function showCustomAlert(title, message, type = 'success') {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            const iconWrapper = document.getElementById('customAlertIconWrapper');
            const icon = document.getElementById('customAlertIcon');
            const accent = document.getElementById('customAlertAccent');
            const closeBtn = document.getElementById('btnCustomAlertClose');
            
            document.getElementById('customAlertTitle').textContent = title;
            document.getElementById('customAlertMessage').innerHTML = message;
            
            // Set styles based on type
            if (type === 'success') {
                iconWrapper.className = 'w-14 h-14 rounded-2xl bg-green-500/10 border border-green-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(16,185,129,0.2)]';
                icon.className = 'fas fa-check-circle text-green-400 text-2xl';
                accent.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-green-500 to-emerald-400';
                closeBtn.className = 'w-full py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold text-xs transition duration-200 shadow-[0_0_15px_rgba(16,185,129,0.3)] hover:shadow-[0_0_20px_rgba(16,185,129,0.5)]';
            } else if (type === 'warning') {
                iconWrapper.className = 'w-14 h-14 rounded-2xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(245,158,11,0.2)]';
                icon.className = 'fas fa-exclamation-triangle text-orange-400 text-2xl';
                accent.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-orange-500 to-yellow-400';
                closeBtn.className = 'w-full py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs transition duration-200 shadow-[0_0_15px_rgba(245,158,11,0.3)] hover:shadow-[0_0_20px_rgba(245,158,11,0.5)]';
            } else if (type === 'error') {
                iconWrapper.className = 'w-14 h-14 rounded-2xl bg-red-500/10 border border-red-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(239,68,68,0.2)]';
                icon.className = 'fas fa-times-circle text-red-400 text-2xl';
                accent.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 to-rose-400';
                closeBtn.className = 'w-full py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs transition duration-200 shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:shadow-[0_0_20px_rgba(239,68,68,0.5)]';
            }
            
            // Show modal with animation
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            setTimeout(() => {
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            }, 10);
            
            return new Promise((resolve) => {
                const handleClose = () => {
                    box.classList.remove('scale-100', 'opacity-100');
                    box.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.add('hidden');
                        closeBtn.removeEventListener('click', handleClose);
                        resolve();
                    }, 200);
                };
                closeBtn.addEventListener('click', handleClose);
            });
        }

        // Modal Logic
        let selectionMode = false;

        function updateSingleRewardButtons() {
            const singleButtons = document.querySelectorAll('tbody .btn-reward');
            singleButtons.forEach(btn => {
                if (selectionMode) {
                    btn.disabled = true;
                    btn.style.opacity = '0.35';
                    btn.style.pointerEvents = 'none';
                } else {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.pointerEvents = 'auto';
                }
            });
        }

        let activeRewardSitinId = null;
        let activeStudentName = '';
        let taskCompletedChoice = 0;
        let tempRewardParams = {};

        function openRewardModal(sitinId, idno, fullName, purpose, lab, duration, avatarUrl) {
            if (selectionMode) return;
            tempRewardParams = { sitinId, idno, fullName, purpose, lab, duration, avatarUrl };
            
            // Set text inside custom task confirmation modal
            document.getElementById('taskConfirmMessage').innerHTML = `Did <span class="text-orange-400 font-semibold">${fullName}</span> complete the assigned lab task successfully?`;
            
            // Open task confirmation modal (themed popup)
            document.getElementById('taskConfirmModal').classList.remove('hidden');
            document.getElementById('taskConfirmModal').style.display = 'flex';
        }

        function closeTaskConfirmModal() {
            document.getElementById('taskConfirmModal').style.display = 'none';
            document.getElementById('taskConfirmModal').classList.add('hidden');
        }

        document.getElementById('btnTaskYes').addEventListener('click', () => {
            taskCompletedChoice = 1;
            proceedToRewardModal();
        });

        document.getElementById('btnTaskNo').addEventListener('click', () => {
            taskCompletedChoice = 0;
            proceedToRewardModal();
        });

        function proceedToRewardModal() {
            closeTaskConfirmModal();
            
            const { sitinId, idno, fullName, purpose, lab, duration, avatarUrl } = tempRewardParams;
            activeRewardSitinId = sitinId;
            activeStudentName = fullName;

            document.getElementById('modName').textContent = fullName;
            document.getElementById('modIdno').textContent = idno;
            document.getElementById('modPurpose').textContent = purpose;
            document.getElementById('modLab').textContent = "Lab " + lab;
            document.getElementById('modDuration').textContent = duration;

            const avatarWrapper = document.getElementById('modAvatarWrapper');
            if (avatarUrl) {
                avatarWrapper.innerHTML = `<img src="${avatarUrl}" class="w-full h-full rounded-lg object-cover border border-white/20">`;
            } else {
                avatarWrapper.innerHTML = `<div class="w-full h-full rounded-lg bg-white/5 border border-white/10 flex items-center justify-center overflow-hidden"><svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 4px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;
            }

            // Calculate and display projected XP score details inside rewardModal
            const durationMins = parseInt(duration) || 0;
            const hoursUsed = durationMins / 60;
            const xpBase = 0.60;
            const xpTask = taskCompletedChoice * 0.20;
            const xpHours = hoursUsed * 0.20;
            const xpTotal = xpBase + xpTask + xpHours;

            document.getElementById('xpBreakdownBase').textContent = `+${xpBase.toFixed(2)} XP`;
            
            const taskBadge = document.getElementById('xpBreakdownTask');
            if (taskCompletedChoice === 1) {
                taskBadge.innerHTML = `<span class="bg-green-500/10 text-green-400 border border-green-500/25 px-2 py-0.5 rounded-md text-[10px] font-bold"><i class="fas fa-check-circle mr-1"></i>+${xpTask.toFixed(2)} XP (Completed)</span>`;
            } else {
                taskBadge.innerHTML = `<span class="bg-gray-500/10 text-gray-400 border border-white/5 px-2 py-0.5 rounded-md text-[10px] font-bold"><i class="fas fa-times-circle mr-1"></i>+${xpTask.toFixed(2)} XP (Incomplete)</span>`;
            }

            document.getElementById('xpBreakdownHours').textContent = `+${xpHours.toFixed(2)} XP (${hoursUsed.toFixed(2)} hrs)`;
            document.getElementById('xpTotalProjected').textContent = `+${xpTotal.toFixed(2)} XP`;

            document.getElementById('rewardModal').classList.remove('hidden');
            document.getElementById('rewardModal').style.display = 'flex';
        }

        function closeRewardModal() {
            document.getElementById('rewardModal').style.display = 'none';
            document.getElementById('rewardModal').classList.add('hidden');
            activeRewardSitinId = null;
        }

        // Bulk selection logic
        const selectStudentBtn = document.getElementById('selectStudentBtn');
        const bulkActions = document.getElementById('bulkActions');
        const selectCols = document.querySelectorAll('.select-col');
        const selectAllRewards = document.getElementById('selectAllRewards');
        const rewardCheckboxes = document.querySelectorAll('.reward-checkbox');

        if (selectStudentBtn) {
            selectStudentBtn.addEventListener('click', function() {
                selectionMode = !selectionMode;
                if (selectionMode) {
                    this.innerHTML = '<i class="fas fa-times"></i> <span>Cancel Selection</span>';
                    this.style.background = 'rgba(239,68,68,0.15)';
                    this.style.borderColor = '#ef4444';
                    this.style.color = '#ef4444';
                    bulkActions.style.display = 'flex';
                    selectCols.forEach(col => col.style.display = '');
                } else {
                    this.innerHTML = '<i class="fas fa-list-check"></i> <span>Select Student</span>';
                    this.style.background = 'rgba(139,63,217,0.15)';
                    this.style.borderColor = 'var(--border)';
                    this.style.color = 'var(--purple-light)';
                    bulkActions.style.display = 'none';
                    selectCols.forEach(col => col.style.display = 'none');
                    // Uncheck all
                    if(selectAllRewards) selectAllRewards.checked = false;
                    rewardCheckboxes.forEach(cb => cb.checked = false);
                }
                updateSingleRewardButtons();
            });
        }

        if (selectAllRewards) {
            selectAllRewards.addEventListener('change', function() {
                const isChecked = this.checked;
                rewardCheckboxes.forEach(cb => {
                    if (cb.closest('tr').style.display !== 'none' && !cb.disabled) {
                        cb.checked = isChecked;
                    }
                });
            });
        }

        function closeBulkRewardModal() {
            document.getElementById('bulkRewardModal').style.display = 'none';
            document.getElementById('bulkRewardModal').classList.add('hidden');
        }

        function giveSingleReward(sitinId, idno, fullName, taskCompleted) {
            const nameParts = fullName.split(',');
            const lastname = nameParts[0].trim();
            const firstname = nameParts[1] ? nameParts[1].trim() : '';

            const formData = new URLSearchParams();
            formData.append('sitin_id', sitinId);
            formData.append('idno', idno);
            formData.append('lastname', lastname);
            formData.append('firstname', firstname);
            formData.append('task_completed', taskCompleted);

            return fetch('give_reward.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.ok ? res.json() : Promise.reject('Network error'))
            .then(data => {
                if (data.success) return data;
                else throw new Error(data.message);
            });
        }

        document.getElementById('bulkRewardBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.reward-checkbox');
            const selectedCheckboxes = Array.from(checkboxes)
                .filter(cb => cb.checked);

            if (selectedCheckboxes.length === 0) {
                showCustomAlert("Selection Required", "Please select at least one student session to reward.", "warning");
                return;
            }

            const tbody = document.getElementById('bulkRewardTableBody');
            tbody.innerHTML = '';
            
            selectedCheckboxes.forEach(cb => {
                const sitinId = cb.value;
                const idno = cb.getAttribute('data-idno');
                const fullName = cb.getAttribute('data-fullname');
                const duration = cb.getAttribute('data-duration');
                const pcNum = cb.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
                
                const row = document.createElement('tr');
                row.className = 'hover:bg-white/5 transition border-b border-white/5';
                row.innerHTML = `
                    <td class="py-3 px-3">
                        <div class="font-bold text-white">${fullName}</div>
                        <div class="text-[10px] text-gray-500">${idno}</div>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-300 font-semibold text-[11px]">${duration}</td>
                    <td class="py-3 px-3 text-center text-orange-400 font-bold text-[11px]">${pcNum}</td>
                    <td class="py-3 px-3 text-center">
                        <label class="relative inline-flex items-center cursor-pointer select-none">
                            <input type="checkbox" class="sr-only bulk-task-toggle" data-sitin-id="${sitinId}">
                            <div class="toggle-bg">
                                <span class="text-[8px] font-extrabold text-gray-500 label-no">NO</span>
                                <div class="toggle-handle"></div>
                                <span class="text-[8px] font-extrabold text-green-400 opacity-0 label-yes">YES</span>
                            </div>
                        </label>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Open task confirmation modal (themed popup)
            document.getElementById('bulkRewardModal').classList.remove('hidden');
            document.getElementById('bulkRewardModal').style.display = 'flex';
        });

        document.getElementById('btnConfirmBulkReward').addEventListener('click', function() {
            const bulkBtn = this;
            const originalText = bulkBtn.innerHTML;
            bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            bulkBtn.disabled = true;

            const checkboxes = document.querySelectorAll('.reward-checkbox');
            const selectedCheckboxes = Array.from(checkboxes)
                .filter(cb => cb.checked);

            const promises = selectedCheckboxes.map(cb => {
                const sitinId = cb.value;
                const idno = cb.getAttribute('data-idno');
                const fullName = cb.getAttribute('data-fullname');
                
                // Find matching switch in bulkRewardTableBody
                const toggleInput = document.querySelector(`.bulk-task-toggle[data-sitin-id="${sitinId}"]`);
                const isCompleted = (toggleInput && toggleInput.checked) ? 1 : 0;
                
                return giveSingleReward(sitinId, idno, fullName, isCompleted);
            });

            Promise.allSettled(promises).then(results => {
                const successes = results.filter(r => r.status === 'fulfilled');
                showCustomAlert("Bulk Reward Complete", `Successfully rewarded ${successes.length} out of ${selectedCheckboxes.length} students.`, "success").then(() => {
                    closeBulkRewardModal();
                    location.reload();
                });
            });
        });

        document.getElementById('btnConfirmReward').addEventListener('click', function() {
            if (!activeRewardSitinId) return;

            const sitinId = activeRewardSitinId;
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            this.disabled = true;

            const nameParts = activeStudentName.split(',');
            const lastname = nameParts[0].trim();
            const firstname = nameParts[1] ? nameParts[1].trim() : '';
            const idno = document.getElementById('modIdno').textContent;

            const formData = new URLSearchParams();
            formData.append('sitin_id', sitinId);
            formData.append('idno', idno);
            formData.append('lastname', lastname);
            formData.append('firstname', firstname);
            formData.append('task_completed', taskCompletedChoice);

            fetch('give_reward.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeRewardModal();
                    showToast(activeStudentName);
                    
                    const cell = document.getElementById('action-cell-' + sitinId);
                    if (cell) {
                        cell.innerHTML = `
                            <button disabled class="btn-rewarded">
                                <i class="fas fa-check-circle"></i> Rewarded
                            </button>
                        `;
                    }
                    // Disable the checkbox for selection mode
                    const cb = document.querySelector(`.reward-checkbox[value="${sitinId}"]`);
                    if (cb) {
                        cb.disabled = true;
                        cb.checked = false;
                    }
                } else {
                    showCustomAlert("Reward Failed", data.message, "error");
                }
            })
            .catch(err => {
                console.error(err);
                showCustomAlert("Connection Error", "An error occurred while communicating with the server.", "error");
            })
            .finally(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });

        // Toast Logic
        let toastTimeout;
        function showToast(studentName) {
            const toast = document.getElementById('rewardToast');
            document.getElementById('toastName').textContent = studentName;
            
            toast.classList.remove('translate-x-[150%]');
            toast.classList.add('translate-x-0');

            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                closeToast();
            }, 4000);
        }

        function closeToast() {
            const toast = document.getElementById('rewardToast');
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-[150%]');
        }
    </script>
</body>
</html>
