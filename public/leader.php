<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../config/db.php';

// 1. Fetch all student standings to calculate exact ranks and find the current student
$query = "SELECT 
            u.idno, 
            u.firstname, 
            u.lastname, 
            u.profile_picture,
            u.course,
            COALESCE(rp.reward_count, 0) AS reward_points,
            COALESCE(rp.total_hours, 0.00) AS total_hours,
            COALESCE(rp.tasks_completed, 0) AS tasks_completed,
            COALESCE(rp.total_score, 0.00) AS total_score
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
          ORDER BY total_score DESC, u.lastname ASC";

$result = $conn->query($query);
$allStandings = [];
$loggedInStudentStanding = null;
$loggedInStudentRank = -1;

$rank_counter = 1;
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row['rank'] = $rank_counter;
        $allStandings[] = $row;
        if ($row['idno'] == $_SESSION['login_user']) {
            $loggedInStudentStanding = $row;
            $loggedInStudentRank = $rank_counter;
        }
        $rank_counter++;
    }
}
$topUsers = array_slice($allStandings, 0, 10);

// 2. Fetch logged-in student's own completed sit-in sessions and points for the Rewards tab
$sitinSql = "SELECT s.sitin_id, s.lab_number, s.purpose, s.time_in, s.time_out, s.created_at,
                    ABS(TIMESTAMPDIFF(MINUTE, s.time_in, s.time_out)) as duration_mins,
                    r.reward_id, r.leaderboard_score as xp, r.task_completed,
                    (SELECT res.pc_number FROM reservations res WHERE res.idno = s.idno AND res.lab_number = s.lab_number AND res.reservation_date = DATE(s.created_at) ORDER BY res.reservation_id DESC LIMIT 1) as res_pc
             FROM sitin s
             LEFT JOIN rewards r ON s.sitin_id = r.sitin_id
             WHERE s.idno = ? AND s.time_out IS NOT NULL
             ORDER BY s.created_at DESC";

$stmt = $conn->prepare($sitinSql);
$stmt->bind_param("s", $_SESSION['login_user']);
$stmt->execute();
$sitinResult = $stmt->get_result();
$sitinData = [];
if ($sitinResult && $sitinResult->num_rows > 0) {
    while ($row = $sitinResult->fetch_assoc()) {
        $row['pc_number'] = $row['res_pc'] ? $row['res_pc'] : (($row['sitin_id'] % 30) + 1);
        $sitinData[] = $row;
    }
}
$stmt->close();

// Fetch unconverted reward points for the logged-in student
$pointsQuery = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS unconverted_points FROM rewards WHERE idno = ? AND points > 0");
$pointsQuery->bind_param("s", $_SESSION['login_user']);
$pointsQuery->execute();
$pointsRes = $pointsQuery->get_result()->fetch_assoc();
$unconvertedPoints = (int)($pointsRes['unconverted_points'] ?? 0);
$pointsQuery->close();

// Fetch current session count for the logged-in student
$sessionQuery = $conn->prepare("SELECT session FROM users WHERE idno = ?");
$sessionQuery->bind_param("s", $_SESSION['login_user']);
$sessionQuery->execute();
$sessionRes = $sessionQuery->get_result()->fetch_assoc();
$currentSessions = (int)($sessionRes['session'] ?? 0);
$sessionQuery->close();

// Fetch total reward points ever earned by the logged-in student (overall count of rewards)
$totalRewardsQuery = $conn->prepare("SELECT COUNT(*) AS total_rewards FROM rewards WHERE idno = ?");
$totalRewardsQuery->bind_param("s", $_SESSION['login_user']);
$totalRewardsQuery->execute();
$totalRewardsRes = $totalRewardsQuery->get_result()->fetch_assoc();
$totalRewardsEarned = (int)($totalRewardsRes['total_rewards'] ?? 0);
$totalRewardsQuery->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards & Leaderboards – CCS Sit-In</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            font-family: var(--font-b);
        }
        
        .leaderboard-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }
        
        .confetti {
            position: absolute;
            width: 12px;
            height: 12px;
            opacity: 0;
            animation: confetti-fall 6s linear infinite;
        }
        
        @keyframes confetti-fall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 0.8; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        
        .leaderboard-header {
            text-align: center;
            margin-bottom: 24px;
            position: relative;
        }
        
        .leaderboard-title {
            font-family: var(--font-h);
            font-size: 2.2rem;
            font-weight: 900;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 6px;
            text-shadow: 0 0 20px rgba(139, 63, 217, 0.4);
            display: inline-block;
            position: relative;
        }
        
        .leaderboard-title::after {
            content: "";
            position: absolute;
            bottom: -6px;
            left: 10%;
            width: 80%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--purple-glow), var(--purple-light), var(--purple-glow), transparent);
            border-radius: 5px;
        }
        
        .leaderboard-subtitle {
            font-size: 13px;
            color: var(--text-dim);
            font-weight: 500;
            margin-top: 8px;
        }
        
        /* Tab Navigation Styling */
        .analytics-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }
        .analytics-tab-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-dim);
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: var(--font-h);
        }
        .analytics-tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.02);
        }
        .analytics-tab-btn.active {
            color: var(--purple-light);
            background: rgba(139, 63, 217, 0.12);
            border-color: rgba(139, 63, 217, 0.3);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.1);
        }

        .tab-content {
            display: none;
            flex: 1;
        }
        .tab-content.active {
            display: flex;
            flex-direction: column;
        }

        /* Controls Box Styling */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .controls-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .controls-right {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-left: auto;
        }
        .dark-search {
            position: relative;
            display: flex;
            align-items: center;
        }
        .dark-search i {
            position: absolute;
            left: 14px;
            color: var(--text-dim);
            font-size: 14px;
        }
        .dark-search input {
            background: rgba(22, 19, 38, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 16px 10px 40px;
            color: #fff;
            font-size: 13px;
            width: 240px;
            outline: none;
            transition: all 0.3s ease;
        }
        .dark-search input:focus {
            border-color: var(--purple-light);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.25);
        }
        .filter-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 16px;
            color: var(--text-dim);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .filter-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--border-strong);
        }
        .filter-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 100;
            background: #161326;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            width: 200px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }
        .filter-dropdown label {
            display: block;
            color: var(--text-dim);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .dark-select {
            background: rgba(22, 19, 38, 0.6);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: #fff;
            font-size: 12px;
            outline: none;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }
        .dark-select:focus {
            border-color: var(--purple-light);
        }

        /* Leaderboard Podium Styling (3D pedestal columns, no bounding box) */
        .leaderboard-podium {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            max-width: 680px;
            margin: 40px auto 45px auto;
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
        
        /* Ensure all leaderboard profile pictures (both podium and table rows) are perfectly circle */
        .avatar-ring img, 
        .avatar-placeholder,
        table img,
        tr img {
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
        
        /* Highlighting Logged-in Student Podium Ring */
        .podium-column.highlight-you-podium .avatar-ring img, 
        .podium-column.highlight-you-podium .avatar-placeholder {
            border-color: #C084FC !important;
            animation: youGlow 2.5s ease-in-out infinite !important;
        }
        
        .podium-names {
            display: flex;
            justify-content: center;
            gap: 16px;
            width: 100%;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 16px;
        }
        
        .podium-name-container {
            width: 180px;
            text-align: center;
        }
        
        .podium-name {
            font-weight: 700;
            color: #fff;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }
        
        .podium-points {
            font-size: 12px;
            color: var(--gold-light);
            font-weight: 700;
            font-family: var(--font-h);
        }
        
        .podium-number {
            font-family: var(--font-h);
            font-size: 2.2rem;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.15);
            position: absolute;
            top: 20px;
            width: 100%;
            text-align: center;
            z-index: 2;
        }
        
        .podium-1 .podium-number { color: rgba(212, 135, 10, 0.25); }
        .podium-2 .podium-number { color: rgba(139, 63, 217, 0.25); }
        .podium-3 .podium-number { color: rgba(191, 122, 78, 0.25); }
        
        .crown {
            position: absolute;
            top: -54px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2rem;
            color: var(--gold);
            text-shadow: 0 0 10px rgba(212, 135, 10, 0.5);
            z-index: 5;
            animation: float 2s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-6px); }
        }

        /* Fixed Table Height & Custom Scrollbar styling matching Admin */
        .table-container {
            height: 430px !important;
            min-height: 430px !important;
            max-height: 430px !important;
            overflow-y: auto !important;
            overflow-x: auto !important;
            position: relative;
            border-radius: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 0 10px 10px 10px;
        }

        #rewardsContent .table-container {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
        }

        .table-wrapper {
            width: 100%;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .custom-table th {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #110e1d; /* Solid color matching student theme background */
            text-align: left;
            color: var(--text-dim);
            font-weight: 600;
            font-size: 11px;
            padding: 14px 20px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
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
            color: #e2e8f0 !important;
            vertical-align: middle;
            transition: all 0.3s ease;
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

        .custom-table tr.row-highlight-you td {
            background: linear-gradient(90deg, rgba(139, 63, 217, 0.25) 0%, rgba(212, 135, 10, 0.15) 100%) !important;
            color: #fff !important;
            border-top: 1.5px solid rgba(139, 63, 217, 0.4) !important;
            border-bottom: 1.5px solid rgba(139, 63, 217, 0.4) !important;
        }

        .custom-table tr.row-highlight-you td:first-child {
            border-left: 1.5px solid rgba(139, 63, 217, 0.4) !important;
            border-radius: 12px 0 0 12px;
        }

        .custom-table tr.row-highlight-you td:last-child {
            border-right: 1.5px solid rgba(139, 63, 217, 0.4) !important;
            border-radius: 0 12px 12px 0;
        }

        .rank {
            font-family: var(--font-h);
            font-size: 13px;
            font-weight: 900;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            margin: 0 auto;
        }
        
        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFC600); border: 1px solid #FFD700; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #B0B0B0); border: 1px solid #C0C0C0; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #B87333); border: 1px solid #CD7F32; }
        .rank-other { background: rgba(139, 63, 217, 0.15); border: 1px solid var(--border); color: var(--purple-light); }
        .rank-you-highlight {
            background: linear-gradient(135deg, #8B3FD9, #D4870A) !important;
            border: 1px solid #C084FC !important;
            box-shadow: 0 0 10px rgba(192, 132, 252, 0.5) !important;
        }
        
        .no-data {
            text-align: center;
            padding: 48px 24px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            backdrop-filter: blur(10px);
        }
        
        .badge-top {
            background: rgba(212, 135, 10, 0.15);
            color: var(--gold);
            border: 1px solid rgba(212, 135, 10, 0.25);
            font-family: var(--font-h);
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .badge-contender {
            background: rgba(139, 63, 217, 0.15);
            color: var(--purple-light);
            border: 1px solid var(--border);
            font-family: var(--font-h);
            font-size: 9px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .badge-you {
            background: linear-gradient(135deg, #8B3FD9, #D4870A);
            color: white;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            box-shadow: 0 0 12px rgba(139, 63, 217, 0.5);
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
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

        .purpose-badge {
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            display: inline-block;
        }

        .lab-badge {
            padding: 6px 12px !important;
            border-radius: 20px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            background: rgba(59, 130, 246, 0.1) !important;
            color: #60A5FA !important;
            border: 1px solid rgba(59, 130, 246, 0.2) !important;
            display: inline-block;
        }

        /* Pagination Controls styling matching Admin */
        .pagination-row {
            margin-top: 16px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }
        .pagination-info {
            font-size: 12px;
            color: var(--text-dim);
        }
        .pagination-controls {
            display: flex;
            gap: 6px;
        }
        .page-btn {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            color: var(--text-dim);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .page-btn:hover:not(:disabled) {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--border-strong);
        }
        .page-btn.active {
            background: var(--purple-glow);
            color: #fff;
            border-color: var(--purple-light);
            box-shadow: 0 0 10px rgba(139, 63, 217, 0.3);
        }
        .page-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* Spring animations */
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
        @keyframes youGlow {
            0%, 100% { 
                box-shadow: 0 0 20px rgba(139, 63, 217, 0.5), inset 0 0 10px rgba(139, 63, 217, 0.25); 
                border-color: #C084FC;
            }
            50% { 
                box-shadow: 0 0 35px rgba(139, 63, 217, 0.8), inset 0 0 15px rgba(139, 63, 217, 0.5); 
                border-color: #fff;
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
    <div class="leaderboard-bg"></div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#8B3FD9', '#C084FC', '#D4870A', '#E09B1A'];
            const container = document.querySelector('.leaderboard-bg');
            
            for (let i = 0; i < 20; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDelay = Math.random() * 4 + 's';
                confetti.style.animationDuration = 4 + Math.random() * 6 + 's';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = 6 + Math.random() * 6 + 'px';
                confetti.style.height = 6 + Math.random() * 6 + 'px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                container.appendChild(confetti);
            }
        });
    </script>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        
        <div class="student-content overflow-auto">
            <div class="leaderboard-header">
                <h1 class="leaderboard-title">Rewards & Leaderboards</h1>
                <p class="leaderboard-subtitle">Track your earned sit-in rewards and view the top lab contenders</p>
            </div>

            <!-- Tabs Navigation matching Admin exactly -->
            <div class="analytics-tabs">
                <button id="rewardsTab" class="analytics-tab-btn active" onclick="switchTab('rewards')">
                    <i class="fas fa-gift"></i>
                    <span>My Rewards</span>
                </button>
                <button id="leaderboardTab" class="analytics-tab-btn" onclick="switchTab('leaderboard')">
                    <i class="fas fa-trophy"></i>
                    <span>Leaderboards</span>
                </button>
            </div>

            <!-- Controls row (search / filter) which will show/hide exactly like Admin -->
            <div class="controls-row">
                <div class="controls-left">
                    <div class="entries-select-wrapper" style="display: flex; align-items: center; gap: 8px; color: var(--text-dim); font-size: 13px;">
                        <input type="hidden" id="entries" value="6">
                        <div class="total-rewards-badge" style="display: flex; align-items: center; gap: 8px; background: rgba(139, 63, 217, 0.15); border: 1.5px solid rgba(139, 63, 217, 0.35); border-radius: 12px; padding: 6px 14px; color: #fff; font-size: 13px; font-weight: 600; box-shadow: 0 4px 15px rgba(139, 63, 217, 0.15); backdrop-filter: blur(10px);">
                            <i class="fas fa-trophy" style="color: #FBBF24; font-size: 13px;"></i>
                            <span>Total Rewards Earned Overall: <strong style="color: #fff; font-size: 14px; font-family: var(--font-h); font-weight: 900; margin-left: 2px;"><?php echo $totalRewardsEarned; ?></strong></span>
                        </div>
                    </div>
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
                            <select id="labFilter" class="dark-select" onchange="filterTable()">
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

            <!-- MY REWARDS CONTENT TAB -->
            <div id="rewardsContent" class="tab-content active">
                <div class="content-card">
                    
                    <!-- Rewards Summary Panel -->
                    <div class="flex flex-wrap gap-4 mb-6">
                        <!-- Card 1: Available Reward Points -->
                        <div class="flex-1 min-w-[200px] p-5 rounded-2xl border border-[rgba(139,63,217,0.2)] bg-[rgba(22,19,38,0.6)] backdrop-blur-md flex items-center justify-between shadow-xl">
                            <div>
                                <span class="text-xs font-semibold text-[var(--text-dim)] uppercase tracking-wider block mb-1">Unconverted Reward Points</span>
                                <span class="text-3xl font-black text-white flex items-center gap-2" id="valUnconvertedPoints">
                                    <i class="fas fa-gift text-purple-400"></i>
                                    <span><?php echo $unconvertedPoints; ?></span>
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] text-purple-300/80 block bg-purple-500/10 border border-purple-500/20 px-2 py-0.5 rounded-full">3 pts = 1 session</span>
                            </div>
                        </div>

                        <!-- Card 2: Current Sessions Remaining -->
                        <div class="flex-1 min-w-[200px] p-5 rounded-2xl border border-[rgba(139,63,217,0.2)] bg-[rgba(22,19,38,0.6)] backdrop-blur-md flex items-center justify-between shadow-xl">
                            <div>
                                <span class="text-xs font-semibold text-[var(--text-dim)] uppercase tracking-wider block mb-1">Current Sessions Remaining</span>
                                <span class="text-3xl font-black text-white flex items-center gap-2" id="valCurrentSessions">
                                    <i class="fas fa-clock text-blue-400"></i>
                                    <span><?php echo $currentSessions; ?> <span class="text-xs text-[var(--text-dim)] font-normal">/ 30 max</span></span>
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] text-blue-300/80 block bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 rounded-full">Max Limit: 30</span>
                            </div>
                        </div>

                        <!-- Card 3: Action Panel -->
                        <div class="flex-1 min-w-[240px] p-5 rounded-2xl border border-[rgba(139,63,217,0.2)] bg-[rgba(22,19,38,0.6)] backdrop-blur-md flex flex-col justify-center shadow-xl">
                            <button id="btnConvert" onclick="convertRewardsToSessions()" class="w-full py-3 px-4 rounded-xl font-bold text-sm tracking-wider uppercase text-white bg-gradient-to-r from-[#8B3FD9] to-[#C084FC] hover:from-[#9d52eb] hover:to-[#cb96ff] shadow-lg shadow-purple-900/30 transition-all duration-300 flex items-center justify-center gap-2" <?php echo $unconvertedPoints < 3 ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                                <i class="fas fa-exchange-alt"></i>
                                <span>Convert to Session</span>
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <div class="table-wrapper">
                            <table id="rewardsTable" class="custom-table">
                                <thead>
                                    <tr>
                                        <th>PC NUMBER</th>
                                        <th>LAB</th>
                                        <th>PURPOSE</th>
                                        <th>LOGIN</th>
                                        <th>LOGOUT</th>
                                        <th>DATE</th>
                                        <th>AWARDED XP</th>
                                        <th>TASK STATUS</th>
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
                                    ?>
                                        <tr>
                                            <td class="font-medium text-white">PC <?php echo htmlspecialchars($sitin['pc_number']); ?></td>
                                            <td><span class="lab-badge">Lab <?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                            <td><span class="purpose-badge <?php echo $pColor; ?>"><?php echo htmlspecialchars($sitin['purpose']); ?></span></td>
                                            <td class="text-gray-300"><?php echo date('h:i A', strtotime($sitin['time_in'])); ?></td>
                                            <td class="text-gray-300"><?php echo date('h:i A', strtotime($sitin['time_out'])); ?></td>
                                            <td class="text-gray-300"><?php echo date('Y-m-d', strtotime($sitin['created_at'])); ?></td>
                                            <td>
                                                <?php if ($isRewarded): ?>
                                                    <span class="xp-badge">
                                                        <i class="fas fa-star"></i>
                                                        <span>+<?php echo number_format($sitin['xp'], 2); ?> XP</span>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-gray-500/10 text-gray-400 border border-white/5 px-2 py-0.5 rounded-md text-[10px] font-bold">
                                                        <i class="fas fa-spinner fa-spin mr-1"></i>Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isRewarded): ?>
                                                    <?php if ($sitin['task_completed'] == 1): ?>
                                                        <span class="bg-green-500/10 text-green-400 border border-green-500/25 px-2 py-0.5 rounded-md text-[10px] font-bold">
                                                            <i class="fas fa-check-circle mr-1"></i>Completed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="bg-gray-500/10 text-gray-400 border border-white/5 px-2 py-0.5 rounded-md text-[10px] font-bold">
                                                            <i class="fas fa-times-circle mr-1"></i>Incomplete
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="bg-gray-500/10 text-gray-400 border border-white/5 px-2 py-0.5 rounded-md text-[10px] font-bold">
                                                        <i class="fas fa-clock mr-1"></i>Pending Verification
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <tr class="not-record">
                                            <td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;">
                                                <i class="fas fa-gift mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>
                                                You have not earned any sit-in rewards yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="not-record" id="rewardsNoMatch" style="display:none;">
                                        <td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;">
                                            <i class="fas fa-search mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>
                                            No matching rewards history found
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="pagination-row">
                        <div class="pagination-info" id="rewardsPaginationInfo"></div>
                        <div class="pagination-controls" id="rewardsPaginationControls"></div>
                    </div>
                </div>
            </div>

            <!-- LEADERBOARDS CONTENT TAB -->
            <div id="leaderboardContent" class="tab-content">
                <?php if (!empty($topUsers)): ?>
                    <!-- Podium -->
                    <div class="leaderboard-podium">
                        <!-- 2nd Place -->
                        <?php if (isset($topUsers[1])): 
                            $p2 = $topUsers[1];
                            $p2Avatar = $p2['profile_picture'] && $p2['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p2['profile_picture']) ? "upload/".$p2['profile_picture'] : "";
                            $p2IsYou = ($p2['idno'] == $_SESSION['login_user']);
                        ?>
                        <div class="podium-column second <?php echo $p2IsYou ? 'highlight-you-podium' : ''; ?>">
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
                                        <img src="<?php echo $p2Avatar; ?>" alt="2nd Place" class="rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 6px;">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($p2IsYou): ?>
                                    <div class="flex justify-center mb-1"><span class="badge-you">YOU</span></div>
                                <?php endif; ?>
                                <div class="font-bold text-white text-base text-center truncate w-40 <?php echo $p2IsYou ? 'text-purple-300 font-black' : ''; ?>"><?php echo htmlspecialchars($p2['firstname'] . ' ' . $p2['lastname']); ?></div>
                            </div>
                            <div class="pedestal">
                                <i class="fas fa-trophy text-gray-400 text-xl mb-1.5"></i>
                                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">2nd Place</span>
                                <span class="text-white font-extrabold text-lg mt-1"><?php echo number_format($p2['total_score'], 2); ?> XP</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="podium-column second opacity-0 pointer-events-none select-none"></div>
                        <?php endif; ?>

                        <!-- 1st Place -->
                        <?php if (isset($topUsers[0])): 
                            $p1 = $topUsers[0];
                            $p1Avatar = $p1['profile_picture'] && $p1['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p1['profile_picture']) ? "upload/".$p1['profile_picture'] : "";
                            $p1IsYou = ($p1['idno'] == $_SESSION['login_user']);
                        ?>
                        <div class="podium-column first <?php echo $p1IsYou ? 'highlight-you-podium' : ''; ?>">
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
                                <i class="fas fa-crown text-yellow-400 text-3xl mb-1 drop-shadow-[0_0_8px_rgba(251,191,36,0.7)] rank-1-crown"></i>
                                <div class="avatar-ring mb-2">
                                    <?php if($p1Avatar): ?>
                                        <img src="<?php echo $p1Avatar; ?>" alt="1st Place" class="rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 8px;">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($p1IsYou): ?>
                                    <div class="flex justify-center mb-1"><span class="badge-you">YOU</span></div>
                                <?php endif; ?>
                                <div class="font-bold text-white text-lg text-center truncate w-44 <?php echo $p1IsYou ? 'text-amber-400 font-black' : ''; ?>"><?php echo htmlspecialchars($p1['firstname'] . ' ' . $p1['lastname']); ?></div>
                            </div>
                            <div class="pedestal">
                                <i class="fas fa-trophy text-yellow-400 text-2xl mb-1.5 drop-shadow-[0_0_10px_rgba(251,191,36,0.4)]"></i>
                                <span class="text-[10px] text-yellow-400 font-bold uppercase tracking-wider">Champion</span>
                                <span class="text-yellow-400 font-black text-xl mt-1"><?php echo number_format($p1['total_score'], 2); ?> XP</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="podium-column first opacity-0 pointer-events-none select-none"></div>
                        <?php endif; ?>

                        <!-- 3rd Place -->
                        <?php if (isset($topUsers[2])): 
                            $p3 = $topUsers[2];
                            $p3Avatar = $p3['profile_picture'] && $p3['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p3['profile_picture']) ? "upload/".$p3['profile_picture'] : "";
                            $p3IsYou = ($p3['idno'] == $_SESSION['login_user']);
                        ?>
                        <div class="podium-column third <?php echo $p3IsYou ? 'highlight-you-podium' : ''; ?>">
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
                                        <img src="<?php echo $p3Avatar; ?>" alt="3rd Place" class="rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 5px;">
                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($p3IsYou): ?>
                                    <div class="flex justify-center mb-1"><span class="badge-you">YOU</span></div>
                                <?php endif; ?>
                                <div class="font-bold text-white text-sm text-center truncate w-36 <?php echo $p3IsYou ? 'text-amber-600 font-black' : ''; ?>"><?php echo htmlspecialchars($p3['firstname'] . ' ' . $p3['lastname']); ?></div>
                            </div>
                            <div class="pedestal">
                                <i class="fas fa-trophy text-amber-700 text-lg mb-1.5"></i>
                                <span class="text-[10px] text-amber-600 font-bold uppercase tracking-wider">3rd Place</span>
                                <span class="text-white font-extrabold text-base mt-1"><?php echo number_format($p3['total_score'], 2); ?> XP</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="podium-column third opacity-0 pointer-events-none select-none"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Leaderboard Table Container (Matches Admin Heights exactly) -->
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="custom-table" id="leaderboardTable">
                                <thead>
                                    <tr>
                                        <th style="width: 80px; text-align: center;">Rank</th>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th style="text-align: center;">Total Hours</th>
                                        <th style="text-align: center;">Total Rewards</th>
                                        <th style="text-align: center;">Tasks Completed</th>
                                        <th style="text-align: right;">Weighted Score</th>
                                        <th style="width: 160px; text-align: center;">Award</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $hasRanks4to10 = false;
                                    foreach ($topUsers as $index => $user): 
                                        if ($index >= 3): 
                                            $hasRanks4to10 = true;
                                            $isYou = ($user['idno'] == $_SESSION['login_user']);
                                    ?>
                                        <tr class="<?php echo $isYou ? 'row-highlight-you' : ''; ?>">
                                            <td>
                                                <div class="rank rank-other <?php echo $isYou ? 'rank-you-highlight' : ''; ?>"><?php echo $index + 1; ?></div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <?php if ($user['profile_picture'] && $user['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $user['profile_picture'])): ?>
                                                        <img src="upload/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                                             alt="<?php echo htmlspecialchars($user['firstname']); ?>" 
                                                             class="w-9 h-9 rounded-full object-cover shadow-md border border-purple-500/30">
                                                    <?php else: ?>
                                                        <div class="w-9 h-9 rounded-full flex items-center justify-center overflow-hidden border border-purple-500/30 shadow-md" style="background: rgba(139, 63, 217, 0.15);">
                                                            <svg class="w-full h-full text-purple-400 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="padding: 2px;">
                                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span style="font-weight:600; color:#fff;" class="flex items-center gap-2">
                                                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                                        <?php if ($isYou): ?>
                                                            <span class="badge-you">YOU</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight:500; color:var(--text-dim);"><?php echo htmlspecialchars($user['course']); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo number_format((float)$user['total_hours'], 2); ?> hrs</span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo (int)$user['reward_points']; ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo (int)$user['tasks_completed']; ?></span>
                                            </td>
                                            <td style="text-align: right;">
                                                <span style="font-weight:700; color:var(--gold-light);">🏆 <?php echo number_format((float)$user['total_score'], 2); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($index < 6): ?>
                                                    <span class="badge-top">Top Performer</span>
                                                <?php else: ?>
                                                    <span class="badge-contender">Contender</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    
                                    // Extra row if the current student is ranked BELOW rank 10
                                    if ($loggedInStudentRank > 10 && $loggedInStudentStanding):
                                        $avatar = $loggedInStudentStanding['profile_picture'] && $loggedInStudentStanding['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $loggedInStudentStanding['profile_picture']) ? "upload/".$loggedInStudentStanding['profile_picture'] : "";
                                    ?>
                                        <!-- Separator dots row -->
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 10px 0; color: var(--text-dim); opacity: 0.6;">
                                                <i class="fas fa-ellipsis-v text-sm"></i>
                                            </td>
                                        </tr>
                                        <!-- Logged in Student row -->
                                        <tr class="row-highlight-you">
                                            <td>
                                                <div class="rank rank-you-highlight"><?php echo $loggedInStudentRank; ?></div>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <?php if ($avatar): ?>
                                                        <img src="<?php echo $avatar; ?>" 
                                                             alt="<?php echo htmlspecialchars($loggedInStudentStanding['firstname']); ?>" 
                                                             class="w-9 h-9 rounded-full object-cover shadow-md border border-purple-500/30">
                                                    <?php else: ?>
                                                        <div class="w-9 h-9 rounded-full flex items-center justify-center overflow-hidden border border-purple-500/30 shadow-md" style="background: rgba(139, 63, 217, 0.15);">
                                                            <svg class="w-full h-full text-purple-400 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="padding: 2px;">
                                                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span style="font-weight:600; color:#fff;" class="flex items-center gap-2">
                                                        <?php echo htmlspecialchars($loggedInStudentStanding['firstname'] . ' ' . $loggedInStudentStanding['lastname']); ?>
                                                        <span class="badge-you">YOU</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight:500; color:var(--text-dim);"><?php echo htmlspecialchars($loggedInStudentStanding['course']); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo number_format((float)$loggedInStudentStanding['total_hours'], 2); ?> hrs</span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo (int)$loggedInStudentStanding['reward_points']; ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="color:#fff; font-weight: 600;"><?php echo (int)$loggedInStudentStanding['tasks_completed']; ?></span>
                                            </td>
                                            <td style="text-align: right;">
                                                <span style="font-weight:700; color:var(--gold-light);">🏆 <?php echo number_format((float)$loggedInStudentStanding['total_score'], 2); ?></span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="badge-contender">Contender</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if (!$hasRanks4to10): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                                <i class="fas fa-trophy text-3xl mb-2" style="opacity: 0.3;"></i>
                                                <p class="text-sm font-medium">No other ranked students yet</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-trophy text-4xl mb-4 text-gray-500"></i>
                        <p class="text-xl">No champions yet!</p>
                        <p class="text-gray-500">Be the first to earn points and appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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

        // Tab Switching Logic matching Admin exactly
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.analytics-tab-btn').forEach(b => b.classList.remove('active'));
            
            if (tabId === 'rewards') {
                document.getElementById('rewardsContent').classList.add('active');
                document.getElementById('rewardsTab').classList.add('active');
                
                // Show the entire controls row for rewards page (Search and Filter)
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
            
            localStorage.setItem('studentRewardsActiveTab', tabId);
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
            
            if (!activeTable) return;
            const rows = activeTable.querySelectorAll('tbody tr:not(.not-record)');
            
            let visibleRows = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 2) return; // ignore helper rows
                
                const labCell = cells[1] ? cells[1].textContent.toLowerCase() : '';
                
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
                return;
            }

            const entriesPerPage = document.getElementById('entries').value;
            if (entriesPerPage === 'all') {
                visibleRows.forEach(r => r.style.display = '');
                updatePagination(visibleRows.length, true);
                return;
            }

            const num = parseInt(entriesPerPage) || 6;
            totalPages = Math.ceil(visibleRows.length / num);
            
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
            else if (totalPages === 0) currentPage = 1;

            const start = (currentPage - 1) * num;
            visibleRows.forEach(r => r.style.display = 'none');
            visibleRows.slice(start, start + num).forEach(r => r.style.display = '');
            
            updatePagination(visibleRows.length, false);
        }

        function updatePagination(total, showAll) {
            const info = document.getElementById('rewardsPaginationInfo');
            const controls = document.getElementById('rewardsPaginationControls');
            const epp = document.getElementById('entries').value;

            if (epp === 'all' || showAll) {
                info.textContent = `Showing ${total} entries`;
                controls.innerHTML = '';
                return;
            }

            const num = parseInt(epp) || 6;
            const s = total === 0 ? 0 : (currentPage - 1) * num + 1;
            const e = Math.min(currentPage * num, total);
            info.textContent = `Showing ${s} to ${e} of ${total} entries`;
            controls.innerHTML = '';

            // Prev button
            const prev = document.createElement('button');
            prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prev.className = 'page-btn'; 
            prev.disabled = currentPage === 1;
            prev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; filterTable(); } });
            controls.appendChild(prev);

            // Page buttons
            const displayPages = Math.max(1, totalPages);
            const max = 5;
            let sp = Math.max(1, currentPage - Math.floor(max / 2));
            let ep = Math.min(displayPages, sp + max - 1);
            if (ep - sp + 1 < max) sp = Math.max(1, ep - max + 1);

            for (let i = sp; i <= ep; i++) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
                if (totalPages <= 1) {
                    btn.disabled = true;
                }
                btn.addEventListener('click', () => { currentPage = i; filterTable(); });
                controls.appendChild(btn);
            }

            // Next button
            const next = document.createElement('button');
            next.innerHTML = '<i class="fas fa-chevron-right"></i>';
            next.className = 'page-btn'; 
            next.disabled = currentPage === totalPages || totalPages <= 1;
            next.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; filterTable(); } });
            controls.appendChild(next);
        }

        // Convert reward points to sessions with beautiful confirmation and error trapping
        function convertRewardsToSessions() {
            Swal.fire({
                title: 'Convert Rewards?',
                text: 'Would you like to convert 3 reward points to 1 session?',
                icon: 'question',
                iconColor: '#C084FC',
                background: 'rgba(22, 19, 38, 0.95)',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#8B3FD9',
                cancelButtonColor: 'rgba(255, 255, 255, 0.1)',
                confirmButtonText: 'Yes, Convert!',
                cancelButtonText: 'Cancel',
                backdrop: 'rgba(15, 10, 30, 0.7)'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Converting your points...',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        background: 'rgba(22, 19, 38, 0.95)',
                        color: '#fff',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('convert_rewards.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: data.message,
                                icon: 'success',
                                iconColor: '#22C55E',
                                background: 'rgba(22, 19, 38, 0.95)',
                                color: '#fff',
                                confirmButtonColor: '#8B3FD9'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Conversion Rejected',
                                text: data.message,
                                icon: 'error',
                                iconColor: '#EF4444',
                                background: 'rgba(22, 19, 38, 0.95)',
                                color: '#fff',
                                confirmButtonColor: '#8B3FD9'
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({
                            title: 'Error',
                            text: 'An unexpected system error occurred.',
                            icon: 'error',
                            iconColor: '#EF4444',
                            background: 'rgba(22, 19, 38, 0.95)',
                            color: '#fff',
                            confirmButtonColor: '#8B3FD9'
                        });
                    });
                }
            });
        }

        // DOM Initializer
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam === 'leaderboard') {
                switchTab('leaderboard');
            } else if (tabParam === 'rewards') {
                switchTab('rewards');
            } else {
                // Restore last active tab if present
                const lastTab = localStorage.getItem('studentRewardsActiveTab');
                if (lastTab === 'leaderboard') {
                    switchTab('leaderboard');
                } else {
                    switchTab('rewards');
                }
            }
            
            // GSAP animations for entry
            gsap.from(".podium-column", {
                duration: 1,
                y: 50,
                opacity: 0,
                stagger: 0.2,
                ease: "back.out(1.7)"
            });
            
            // Slide rows up beautifully on entry, keeping full opacity to prevent dimming bugs
            gsap.from(".custom-table tbody tr", {
                duration: 0.8,
                y: 30,
                stagger: 0.1,
                delay: 0.3,
                ease: "power2.out"
            });
            
            // Add hover effect to podium avatars
            const podiumAvatars = document.querySelectorAll('.avatar-ring img, .avatar-placeholder');
            podiumAvatars.forEach(avatar => {
                avatar.addEventListener('mouseenter', () => {
                    gsap.to(avatar, {
                        duration: 0.3,
                        scale: 1.1,
                        rotate: Math.random() > 0.5 ? 5 : -5,
                        y: -5,
                        ease: "power2.out"
                    });
                });
                
                avatar.addEventListener('mouseleave', () => {
                    gsap.to(avatar, {
                        duration: 0.3,
                        scale: 1,
                        rotate: 0,
                        y: 0,
                        ease: "power2.out"
                    });
                });
            });
        });
    </script>
</body>
</html>