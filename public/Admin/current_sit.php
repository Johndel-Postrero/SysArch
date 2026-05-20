<?php
date_default_timezone_set('Asia/Manila'); // Set to Philippine time

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../../config/db.php';

// Handle active sit-in alert from global search
if (isset($_GET['active_alert']) && $_GET['active_alert'] == 1) {
    $_SESSION['error'] = "This student is already currently sitting in!";
    $searchStr = isset($_GET['search']) ? "?search=" . urlencode($_GET['search']) : "";
    header("Location: current_sit.php" . $searchStr);
    exit();
}

// Process Manual Log Sit-In from the High-Fidelity Modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_sitin'])) {
    $idno = intval($_POST['idno']);
    $lab_number = intval($_POST['lab_number']);
    $pc_number = intval($_POST['pc_number']);
    $purpose = trim($_POST['purpose']);
    $sitin_date = date("Y-m-d");
    $time_in = date("H:i:s");

    // Check if user exists and has remaining sessions
    $checkUser = $conn->prepare("SELECT session FROM users WHERE idno = ? AND role = 'student'");
    if ($checkUser) {
        $checkUser->bind_param("i", $idno);
        $checkUser->execute();
        $userRes = $checkUser->get_result();
        if ($userRow = $userRes->fetch_assoc()) {
            if ($userRow['session'] > 0) {
                // Check if not already sitting in today
                $checkActive = $conn->prepare("SELECT sitin_id FROM sitin WHERE idno = ? AND time_out IS NULL");
                $checkActive->bind_param("i", $idno);
                $checkActive->execute();
                if ($checkActive->get_result()->num_rows === 0) {
                    // Check if PC in the same lab is occupied by another active sit-in
                    $checkPc = $conn->prepare("SELECT sitin_id FROM sitin WHERE lab_number = ? AND pc_number = ? AND time_out IS NULL");
                    $checkPc->bind_param("ii", $lab_number, $pc_number);
                    $checkPc->execute();
                    $pcRes = $checkPc->get_result();
                    $isPcOccupied = ($pcRes->num_rows > 0);
                    $checkPc->close();

                    if ($isPcOccupied) {
                        $_SESSION['error'] = "PC {$pc_number} in Lab {$lab_number} is already occupied by another student!";
                    } else {
                        $insertSitin = $conn->prepare("INSERT INTO sitin (idno, lab_number, pc_number, sitin_date, time_in, purpose) VALUES (?, ?, ?, ?, ?, ?)");
                        $insertSitin->bind_param("iiisss", $idno, $lab_number, $pc_number, $sitin_date, $time_in, $purpose);
                        if ($insertSitin->execute()) {
                            $_SESSION['success'] = "Sit-in session successfully logged for PC {$pc_number}!";
                        } else {
                            $_SESSION['error'] = "Failed to log sit-in: " . $conn->error;
                        }
                        $insertSitin->close();
                    }
                } else {
                    $_SESSION['error'] = "Student is already currently sitting in!";
                }
                $checkActive->close();
            } else {
                $_SESSION['error'] = "Student has no remaining sessions!";
            }
        } else {
            $_SESSION['error'] = "Student ID Number not found!";
        }
        $checkUser->close();
    }
    header("Location: current_sit.php");
    exit();
}

// Process Logout (Time Out)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['logout_idno'])) {
    $idno = intval($_POST['logout_idno']);
    $time_out = date("H:i:s");

    $conn->begin_transaction();
    try {
        // Find active sit-in to get lab and PC before closing
        $activeQuery = $conn->prepare("SELECT s.lab_number, COALESCE(s.pc_number, (SELECT r.pc_number FROM reservations r WHERE r.idno = s.idno AND r.lab_number = s.lab_number AND r.reservation_date = s.sitin_date AND r.time_in_status = 'sit-inned' ORDER BY r.reservation_id DESC LIMIT 1)) as pc_num FROM sitin s WHERE s.idno = ? AND s.time_out IS NULL");
        $labNum = 0; $pcNum = 0;
        if ($activeQuery) {
            $activeQuery->bind_param("i", $idno);
            $activeQuery->execute();
            $res = $activeQuery->get_result();
            if ($row = $res->fetch_assoc()) {
                $labNum = intval($row['lab_number']);
                $pcNum = intval($row['pc_num']);
            }
            $activeQuery->close();
        }

        $logoutQuery = $conn->prepare("UPDATE sitin SET time_out = ? WHERE idno = ? AND time_out IS NULL");
        if (!$logoutQuery) throw new Exception("Prepare failed: " . $conn->error);
        
        $logoutQuery->bind_param("si", $time_out, $idno);
        if (!$logoutQuery->execute()) throw new Exception("Error logging out: " . $logoutQuery->error);
        
        $affectedRows = $conn->affected_rows;
        $logoutQuery->close();

        if ($affectedRows > 0) {
            $updateReservation = $conn->prepare("UPDATE reservations SET time_in_status = 'completed' WHERE idno = ? AND time_in_status = 'sit-inned'");
            if ($updateReservation) {
                $updateReservation->bind_param("i", $idno);
                $updateReservation->execute();
                $updateReservation->close();
            }

            // Free up PC in lab_pcs
            if ($labNum > 0 && $pcNum > 0) {
                $freePc = $conn->prepare("UPDATE lab_pcs SET status = 'available' WHERE lab_number = ? AND pc_number = ?");
                if ($freePc) {
                    $freePc->bind_param("ii", $labNum, $pcNum);
                    $freePc->execute();
                    $freePc->close();
                }
            }

            $deductSessionQuery = $conn->prepare("UPDATE users SET session = GREATEST(session - 1, 0) WHERE idno = ?");
            if ($deductSessionQuery) {
                $deductSessionQuery->bind_param("i", $idno);
                $deductSessionQuery->execute();
                $deductSessionQuery->close();
            }
        }
        $conn->commit();
        $_SESSION['success'] = "Student successfully timed out! Session deducted.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: current_sit.php");
    exit();
}

// Fetch stats
$todayQuery = $conn->query("SELECT COUNT(*) as cnt FROM sitin WHERE DATE(sitin_date) = CURDATE()");
$totalToday = $todayQuery ? $todayQuery->fetch_assoc()['cnt'] : 0;

$labQuery = $conn->query("SELECT lab_number, COUNT(*) as cnt FROM sitin WHERE DATE(sitin_date) = CURDATE() GROUP BY lab_number ORDER BY cnt DESC LIMIT 1");
$mostUsedLab = ($labQuery && $labQuery->num_rows > 0) ? "Lab " . $labQuery->fetch_assoc()['lab_number'] : "None";

// Fetch active sit-ins
$sql = "SELECT s.sitin_id, s.idno, u.lastname, u.firstname, u.middlename, s.purpose, s.lab_number, s.pc_number, s.time_in, s.time_out, u.session, s.sitin_date,
               (SELECT r.pc_number FROM reservations r WHERE r.idno = s.idno AND r.lab_number = s.lab_number AND r.reservation_date = s.sitin_date AND r.time_in_status = 'sit-inned' LIMIT 1) as res_pc
        FROM sitin s
        JOIN users u ON s.idno = u.idno
        WHERE s.time_out IS NULL
        ORDER BY s.time_in DESC";
$result = $conn->query($sql);

$sitinData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['pc_number'] = $row['pc_number'] ? $row['pc_number'] : ($row['res_pc'] ? $row['res_pc'] : (($row['sitin_id'] % 30) + 1));
        $sitinData[] = $row;
    }
}

// Fetch all registered students for Modal 1
$stuQuery = $conn->query("SELECT idno, firstname, lastname, middlename, course, level, session, profile_picture FROM users WHERE role = 'student' ORDER BY firstname ASC");
$allStudents = [];
if ($stuQuery && $stuQuery->num_rows > 0) {
    while ($row = $stuQuery->fetch_assoc()) {
        $allStudents[] = $row;
    }
}
$conn->close();

function getPurposeClass($purpose) {
    $p = strtolower($purpose);
    if (strpos($p, 'c#') !== false) return 'purp-purple';
    if (strpos($p, 'java') !== false) return 'purp-orange';
    if (strpos($p, 'php') !== false) return 'purp-cyan';
    if (strpos($p, 'asp') !== false) return 'purp-orange';
    if (strpos($p, 'c prog') !== false || $p === 'c') return 'purp-pink';
    if (strpos($p, 'web') !== false) return 'purp-blue';
    return 'purp-emerald';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Sit-In</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/student-dark.css">
    <style>
        .btn-log-sitin {
            background: linear-gradient(135deg, #8B3FD9, #D4870A);
            color: white;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.4);
            border: none;
            transition: all 0.3s;
            font-family: var(--font-b);
        }
        .btn-log-sitin:hover {
            box-shadow: 0 0 25px rgba(212, 135, 10, 0.6);
            transform: translateY(-1px);
        }

        /* Custom Modal Styles matching High-Fidelity Pictures */
        .modal-container-custom {
            width: 100%;
            max-width: 520px;
            background: #0D0B1A;
            border: 1px solid rgba(139, 63, 217, 0.5);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.8), 0 0 40px rgba(139, 63, 217, 0.2);
            overflow: hidden;
            backdrop-filter: blur(20px);
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-select-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .modal-select-card:hover {
            background: rgba(139, 63, 217, 0.1);
            border-color: rgba(139, 63, 217, 0.4);
        }
        .modal-select-card.zero-sessions {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.2);
            opacity: 0.8;
        }
        .stu-avatar-lg {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #8B3FD9, #5B21B6);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.4);
            flex-shrink: 0;
        }
        .btn-select-stu {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(139, 63, 217, 0.2);
            color: #c084fc;
            border: 1px solid rgba(139, 63, 217, 0.4);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-select-stu:hover {
            background: #8B3FD9;
            color: #fff;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.6);
        }

        /* PC Grid Custom Styles */
        .pc-grid-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px;
            margin-top: 6px;
        }
        .pc-grid-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 14px 0;
            max-height: 260px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .pc-tile {
            aspect-ratio: 1.2;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #9A8FB0;
        }
        .pc-tile i { font-size: 16px; }
        .pc-tile:hover:not(.occupied):not(.selected) {
            border-color: rgba(139, 63, 217, 0.4);
            color: #fff;
            background: rgba(255, 255, 255, 0.07);
        }
        .pc-tile.occupied {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
            cursor: not-allowed;
        }
        .pc-tile.selected {
            background: rgba(139, 63, 217, 0.25);
            border-color: #8B3FD9;
            color: #fff;
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.5);
            transform: scale(1.04);
        }
        .quick-select-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #9A8FB0;
            transition: all 0.2s;
        }
        .quick-select-pill:hover, .quick-select-pill.active {
            background: #8B3FD9;
            color: #fff;
            border-color: #8B3FD9;
            box-shadow: 0 0 12px rgba(139, 63, 217, 0.4);
        }
        .rem-session-card {
            background: linear-gradient(135deg, rgba(212, 135, 10, 0.1), rgba(139, 63, 217, 0.05));
            border: 1px solid rgba(212, 135, 10, 0.3);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
        }

        /* Beautiful Custom Checkbox */
        .dark-checkbox {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(139, 63, 217, 0.4);
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.3);
            cursor: pointer;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .dark-checkbox:hover {
            border-color: #8B3FD9;
            box-shadow: 0 0 8px rgba(139, 63, 217, 0.3);
        }
        .dark-checkbox:checked {
            background: #8B3FD9;
            border-color: #8B3FD9;
            box-shadow: 0 0 10px rgba(139, 63, 217, 0.5);
        }
        .dark-checkbox:checked::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: white;
            font-size: 10px;
            position: absolute;
        }

        /* Beautiful Custom Select Box */
        select.w-full {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23C084FC' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 40px !important;
            background-color: rgba(22, 19, 38, 0.8) !important;
            border: 1px solid rgba(139, 63, 217, 0.3) !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            color: #fff;
            cursor: pointer;
        }

        select.w-full:focus {
            border-color: #8B3FD9 !important;
            box-shadow: 0 0 12px rgba(139, 63, 217, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }

        select.w-full option {
            background-color: #161326;
            color: #D1C7E0;
            padding: 12px;
            font-size: 14px;
        }
        
        select.w-full option:hover, select.w-full option:focus, select.w-full option:active {
            background-color: #8B3FD9 !important;
            color: #white !important;
        }

        /* Hug Table Height and prevent void spaces */
        .student-content {
            overflow-y: auto !important;
        }
        .content-card {
            flex: 1 !important;
        }
        .dark-table-wrap {
            height: 370px !important;
            min-height: 370px !important;
            max-height: 370px !important;
            overflow: hidden !important;
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon-box icon-green">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Currently Sitting In</p>
                        <h3 class="stat-value">
                            <?php echo count($sitinData); ?>
                            <span class="status-dot animate-pulse"></span>
                        </h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-orange">
                        <i class="fas fa-calendar-days"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Total Sessions Today</p>
                        <h3 class="stat-value val-orange">
                            <?php echo $totalToday; ?>
                        </h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-box icon-cyan">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <p class="stat-label">Most Used Lab Today</p>
                        <h3 class="stat-value val-cyan">
                            <?php echo htmlspecialchars($mostUsedLab); ?>
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Controls Row -->
            <div class="controls-row">
                <div class="controls-left">
                    <button id="selectStudentBtn" class="filter-btn" style="background: rgba(139,63,217,0.15); border: 1px solid var(--purple); color: var(--purple-light);">
                        <i class="fas fa-list-check"></i> <span>Select Student</span>
                    </button>
                    <div id="bulkActions" style="display: none; gap: 8px; margin-left: 8px;">
                        <button id="bulkTimeoutBtn" class="btn-timeout" style="padding: 6px 14px;" title="Time Out Selected">
                            <i class="fas fa-clock"></i> <span>Time Out</span>
                        </button>
                    </div>
                    <!-- Hidden entries select to keep pagination logic working if needed, defaulting to 5 -->
                    <select id="entries" style="display:none;">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">all</option>
                    </select>
                </div>
                <div class="controls-right">
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" placeholder="Search..." type="text"/>
                    </div>
                    <div style="position:relative;">
                        <button id="sortButton" class="filter-btn">
                            <i class="fas fa-sort"></i><span>Sort</span>
                        </button>
                        <div id="sortDropdown" class="filter-dropdown hidden">
                            <button class="sort-opt" data-sort="id-asc">ID: Low to High</button>
                            <button class="sort-opt" data-sort="id-desc">ID: High to Low</button>
                            <button class="sort-opt" data-sort="name-asc">Name: A to Z</button>
                            <button class="sort-opt" data-sort="name-desc">Name: Z to A</button>
                            <button class="sort-opt" data-sort="time-desc">Latest Time In</button>
                            <button class="sort-opt" data-sort="time-asc">Earliest Time In</button>
                        </div>
                    </div>
                    <button class="btn-log-sitin" onclick="openSelectModal()">
                        <i class="fas fa-plus"></i><span>Log Sit-In</span>
                    </button>
                </div>
            </div>

            <div class="content-card">
            <!-- Table Wrap -->
            <div class="dark-table-wrap">
                <table class="dark-table" id="currentSitTable">
                    <thead>
                        <tr>
                            <th class="select-col" style="display:none; width: 40px;"><input type="checkbox" id="selectAllSitins" class="dark-checkbox"></th>
                            <th>ID Number</th>
                            <th>Student Name</th>
                            <th>Lab</th>
                            <th>PC</th>
                            <th>Date</th>
                            <th>Time in</th>
                            <th>Purpose</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sitinData)): ?>
                            <?php foreach ($sitinData as $sitin): ?>
                                <tr>
                                    <td class="select-col" style="display:none;"><input type="checkbox" class="sitin-checkbox dark-checkbox" value="<?php echo $sitin['idno']; ?>"></td>
                                    <td class="font-semibold" style="color: var(--gold);"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                    <td class="font-semibold text-white"><?php echo htmlspecialchars(trim($sitin['firstname'] . ' ' . $sitin['middlename'] . ' ' . $sitin['lastname'])); ?></td>
                                    <td><span class="badge-lab"><?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                    <td class="text-gray-300 font-medium"><?php echo htmlspecialchars($sitin['pc_number']); ?></td>
                                    <td class="text-gray-400"><?php echo date('M d, Y', strtotime($sitin['sitin_date'])); ?></td>
                                    <td class="font-medium text-gray-200" data-timestamp="<?php echo strtotime($sitin['sitin_date'].' '.$sitin['time_in']); ?>"><?php echo date('g:i A', strtotime($sitin['time_in'])); ?></td>
                                    <td><span class="purpose-badge <?php echo getPurposeClass($sitin['purpose']); ?>"><?php echo htmlspecialchars($sitin['purpose']); ?></span></td>
                                    <td>
                                        <form method="POST" action="current_sit.php" onsubmit="return confirm('Time out student <?php echo htmlspecialchars($sitin['firstname'].' '.$sitin['lastname']); ?>?');">
                                            <input type="hidden" name="logout_idno" value="<?php echo $sitin['idno']; ?>">
                                            <button type="submit" class="btn-timeout">
                                                <i class="fas fa-clock"></i><span>Time Out</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="not-record" id="dbEmptyRow">
                                <td colspan="9">
                                    <div class="text-center py-12">
                                        <i class="fas fa-couch text-4xl mb-3 text-purple-500/40"></i>
                                        <p class="text-gray-400 font-medium">No students currently sitting in.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="not-record hidden" id="noMatchRow">
                            <td colspan="9">
                                <div class="text-center py-12">
                                    <i class="fas fa-search text-4xl mb-3 text-purple-500/40"></i>
                                    <p class="text-gray-400 font-medium">No matching sit-in records found.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Row -->
            <div class="pagination-row">
                <div class="pagination-info" id="paginationInfo">Showing 0 entries</div>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
            </div><!-- end content-card -->
        </div>
    </div>

    <!-- Step 1 Modal: Select Student -->
    <div id="modalSelectStudent" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div class="modal-container-custom p-6 flex flex-col max-h-[85vh] w-full max-w-[520px]">
            <div class="flex items-center justify-between pb-4 border-b border-white/10 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white tracking-wide">Select Student</h2>
                    <p class="text-xs text-gray-400">Choose a registered student to log a sit-in session</p>
                </div>
                <button type="button" onclick="closeSelectModal()" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 text-gray-400 hover:text-white flex items-center justify-center transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <div class="relative mb-3">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="modalStuSearch" placeholder="Search student name or ID..." class="w-full bg-white/5 border border-purple-500/30 rounded-xl pl-10 pr-10 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition">
                <button type="button" onclick="document.getElementById('modalStuSearch').value=''; filterModalStudents();" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>

            <div class="flex items-center justify-between text-xs text-gray-400 px-1 mb-2 font-semibold">
                <span id="modalStuCount">Showing 0 of 0 registered students</span>
            </div>

            <div class="flex items-center justify-between text-[10px] font-bold text-purple-400 tracking-wider px-4 py-2 bg-black/40 rounded-lg mb-3">
                <span>STUDENT</span>
                <span>SESSIONS</span>
            </div>

            <div class="flex-1 overflow-y-auto pr-1 space-y-2 mb-4" id="modalStudentList">
                <!-- Dynamically rendered students -->
            </div>

            <div class="pt-4 border-t border-white/10 flex justify-center">
                <button type="button" onclick="closeSelectModal()" class="w-full py-3 bg-white/5 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white font-semibold rounded-xl transition text-sm flex items-center justify-center gap-2">
                    <i class="fas fa-times"></i><span>Cancel</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Step 2 Modal: Log Sit-In Form with PC Grid -->
    <div id="modalLogSitinForm" class="modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1rem;">
        <div class="modal-container-custom p-6 flex flex-col max-h-[90vh] w-full max-w-[520px]">
            <div class="flex items-center justify-between pb-4 border-b border-white/10 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/40 text-purple-400 flex items-center justify-center text-lg">
                        <i class="fas fa-desktop"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-white tracking-wide">SIT-IN</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">Log Session</span>
                        </div>
                        <p class="text-xs text-gray-400">Fill in the required fields to register a sit-in session</p>
                    </div>
                </div>
                <button type="button" onclick="closeFormModal()" class="w-8 h-8 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 text-gray-400 hover:text-white flex items-center justify-center transition">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <form method="POST" action="current_sit.php" id="sitinSessionForm" class="flex-1 overflow-y-auto pr-2 space-y-4">
                <input type="hidden" name="log_sitin" value="1">
                <input type="hidden" name="idno" id="formIdno">
                <input type="hidden" name="pc_number" id="formPcNumber">

                <!-- ID No -->
                <div>
                    <div class="flex items-center justify-between text-xs text-gray-400 font-semibold mb-1.5 px-1">
                        <span class="text-amber-500"><i class="fas fa-hashtag mr-1.5"></i>ID NO.</span>
                        <span class="text-gray-500 italic">Auto-filled</span>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 bg-black/40 border border-white/10 rounded-xl">
                        <div class="flex items-center gap-3 text-sm font-bold text-amber-500">
                            <i class="fas fa-lock text-gray-500"></i>
                            <span id="displayIdno">21-10345</span>
                            <span class="text-xs text-gray-500 font-normal">Read-only</span>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shadow-[0_0_8px_#f59e0b]"></span>
                    </div>
                </div>

                <!-- Student Name -->
                <div>
                    <div class="flex items-center justify-between text-xs text-gray-400 font-semibold mb-1.5 px-1">
                        <span class="text-purple-400"><i class="fas fa-user mr-1.5"></i>STUDENT NAME</span>
                        <span class="text-gray-500 italic">Auto-filled</span>
                    </div>
                    <div class="flex items-center gap-3 px-4 py-3 bg-black/40 border border-white/10 rounded-xl text-sm font-semibold text-white">
                        <i class="fas fa-lock text-gray-500"></i>
                        <span id="displayName">Juan Andres • BSCS - 2nd Year</span>
                    </div>
                </div>

                <!-- Laboratory -->
                <div>
                    <label class="block text-xs font-semibold text-purple-400 mb-1.5 px-1"><i class="fas fa-flask mr-1.5"></i>LABORATORY *</label>
                    <select name="lab_number" id="formLabSelect" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition">
                        <option value="524" selected>Lab 524</option>
                        <option value="526">Lab 526</option>
                        <option value="528">Lab 528</option>
                        <option value="530">Lab 530</option>
                        <option value="542">Lab 542</option>
                        <option value="544">Lab 544</option>
                    </select>
                </div>

                <!-- Purpose -->
                <div>
                    <label class="block text-xs font-semibold text-purple-400 mb-1.5 px-1"><i class="fas fa-code mr-1.5"></i>PURPOSE / LANGUAGE *</label>
                    <select name="purpose" id="formPurposeSelect" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-purple-500 transition mb-2">
                        <option value="" selected disabled>Select purpose...</option>
                        <option value="C Programming">C Programming</option>
                        <option value="C# Programming">C# Programming</option>
                        <option value="Java Programming">Java Programming</option>
                        <option value="PHP Programming">PHP Programming</option>
                        <option value="Python Programming">Python Programming</option>
                        <option value="JavaScript / Web Dev">JavaScript / Web Dev</option>
                        <option value="Research / Documentation">Research / Documentation</option>
                        <option value="Database">Database</option>
                        <option value="Project Management">Project Management</option>
                        <option value="Others">Others</option>
                    </select>
                    <div class="flex items-center flex-wrap gap-1.5 px-1">
                        <span class="text-[11px] text-gray-500 mr-1">Quick select:</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('C# Programming', this)">C#</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('Java Programming', this)">Java</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('PHP Programming', this)">PHP</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('Python Programming', this)">Python</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('JavaScript / Web Dev', this)">JS</span>
                        <span class="quick-select-pill" onclick="setQuickPurpose('Research / Documentation', this)">Research</span>
                    </div>
                </div>

                <!-- PC Grid -->
                <div>
                    <div class="flex items-center justify-between text-xs font-semibold text-purple-400 px-1 mb-1.5">
                        <span><i class="fas fa-desktop mr-1.5"></i>PC NUMBER *</span>
                        <span id="selectedPcBadge" class="hidden px-2 py-0.5 rounded-lg text-[11px] font-bold bg-purple-500/20 border border-purple-500/40 text-purple-300">
                            <i class="fas fa-check mr-1"></i>PC <span id="badgePcVal">12</span> Selected
                        </span>
                    </div>
                    <div class="pc-grid-box">
                        <div class="flex items-center justify-between text-xs pb-3 border-b border-white/10">
                            <span class="font-bold text-white tracking-wider uppercase" id="gridHeaderLabel">PC GRID — LAB 524</span>
                            <div class="flex items-center gap-3 text-[11px] font-semibold">
                                <span class="flex items-center gap-1.5 text-gray-400"><span class="w-2.5 h-2.5 rounded bg-white/20 border border-white/30"></span>Available</span>
                                <span class="flex items-center gap-1.5 text-red-400"><span class="w-2.5 h-2.5 rounded bg-red-500/20 border border-red-500/40"></span>Occupied</span>
                                <span class="flex items-center gap-1.5 text-purple-300"><span class="w-2.5 h-2.5 rounded bg-purple-500/30 border border-purple-500/50"></span>Selected</span>
                            </div>
                        </div>

                        <div class="pc-grid-container" id="pcGridContainer">
                            <!-- Dynamically loaded 35 PCs -->
                            <div class="py-8 col-span-5 text-center text-xs text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading PC grid...</div>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-white/10 text-xs text-gray-400">
                            <div class="flex items-center gap-3 font-semibold">
                                <span id="countAvail">0 Available</span>
                                <span>•</span>
                                <span id="countOcc" class="text-red-400">0 Occupied</span>
                                <span>•</span>
                                <span id="countSel" class="text-purple-300">0 Selected</span>
                            </div>
                            <span class="italic text-[11px]">Tap a tile to select</span>
                        </div>
                    </div>
                </div>

                <!-- Remaining Sessions -->
                <div>
                    <div class="flex items-center justify-between text-xs text-gray-400 font-semibold mb-1.5 px-1">
                        <span class="text-amber-500"><i class="fas fa-history mr-1.5"></i>REMAINING SESSIONS</span>
                        <span class="text-gray-500 italic">Auto-filled</span>
                    </div>
                    <div class="rem-session-card">
                        <div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-3xl font-extrabold text-amber-500 tracking-tight" id="remSessionVal">28</span>
                                <span class="text-xs font-bold text-amber-500/80">sessions left</span>
                            </div>
                            <p class="text-[11px] text-gray-400">out of 30 allocated per semester</p>
                        </div>
                        <div class="w-36">
                            <div class="flex justify-between text-[11px] font-bold text-amber-400 mb-1">
                                <span>Progress</span>
                                <span id="remSessionPercent">93% remaining</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-black/40 border border-white/10 overflow-hidden">
                                <div id="remSessionBar" class="h-full bg-gradient-to-r from-amber-500 to-purple-500 rounded-full transition-all duration-500" style="width: 93%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Status -->
                <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded-xl flex items-center justify-between text-xs text-purple-300 mt-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-info-circle text-purple-400"></i>
                        <span>Lab, Purpose, and PC Number are required to save</span>
                    </div>
                    <div class="flex items-center gap-2 font-bold bg-purple-500/20 px-2.5 py-1 rounded-lg border border-purple-500/30">
                        <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                        <span id="formStatusBadge">1/3 filled</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="pt-4 border-t border-white/10 flex items-center gap-4">
                    <button type="button" onclick="closeFormModal()" class="flex-1 py-3.5 bg-white/5 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white font-bold rounded-xl transition text-sm flex items-center justify-center gap-2">
                        <i class="fas fa-times"></i><span>Cancel</span>
                    </button>
                    <button type="submit" id="btnSubmitSession" disabled class="flex-1 py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold rounded-xl shadow-[0_0_20px_rgba(139,63,217,0.5)] hover:shadow-[0_0_30px_rgba(139,63,217,0.8)] disabled:opacity-40 disabled:pointer-events-none transition text-sm flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i><span>Log Sit-In Session</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const allRegisteredStudents = <?php echo json_encode($allStudents); ?>;
        const activeSitinIdnos = <?php echo json_encode(array_column($sitinData, 'idno')); ?>;
        let selectedStudentObj = null;

        // Modal 1 Logic
        function openSelectModal() {
            document.getElementById('modalSelectStudent').style.display = 'flex';
            filterModalStudents();
        }
        function closeSelectModal() {
            document.getElementById('modalSelectStudent').style.display = 'none';
        }

        function filterModalStudents() {
            const q = document.getElementById('modalStuSearch').value.toLowerCase().trim();
            const list = document.getElementById('modalStudentList');
            list.innerHTML = '';

            const filtered = allRegisteredStudents.filter(st => {
                const searchStr = `${st.firstname} ${st.lastname} ${st.idno}`.toLowerCase();
                return searchStr.includes(q);
            });

            document.getElementById('modalStuCount').textContent = `Showing ${filtered.length} of ${allRegisteredStudents.length} registered students`;

            if (filtered.length === 0) {
                list.innerHTML = `<div class="p-8 text-center text-gray-500 text-sm">No registered students found matching your search</div>`;
                return;
            }

            filtered.forEach(st => {
                const sessions = parseInt(st.session);
                const isZero = sessions === 0;
                const isSittingIn = activeSitinIdnos.includes(st.idno);
                const initials = (st.firstname[0] + st.lastname[0]).toUpperCase();

                const card = document.createElement('div');
                card.className = `modal-select-card ${isZero ? 'zero-sessions' : ''}`;
                if (isSittingIn) {
                    card.style.opacity = '0.45';
                    card.style.filter = 'grayscale(0.85)';
                    card.style.pointerEvents = 'none';
                    card.style.cursor = 'not-allowed';
                }
                
                let rightHtml = '';
                if (isSittingIn) {
                    rightHtml = `
                        <div class="flex items-center gap-3 font-bold text-gray-400">
                            <span class="px-2.5 py-1 rounded-lg bg-gray-500/10 border border-gray-500/30 text-[11px] flex items-center gap-1.5"><i class="fas fa-couch"></i>Sitting In</span>
                        </div>
                    `;
                } else if (isZero) {
                    rightHtml = `
                        <div class="flex items-center gap-3 font-bold text-red-500">
                            <span class="px-2.5 py-1 rounded-lg bg-red-500/20 border border-red-500/40 text-[11px] flex items-center gap-1.5"><i class="fas fa-exclamation-triangle"></i>No Sessions</span>
                            <span class="text-xl">0</span>
                        </div>
                    `;
                } else {
                    const countColor = sessions <= 10 ? 'text-orange-500' : 'text-emerald-500';
                    rightHtml = `
                        <div class="flex items-center gap-4">
                            <div class="text-right font-bold">
                                <div class="text-lg ${countColor}">${sessions}</div>
                                <div class="text-[10px] text-gray-500 tracking-wider uppercase">remaining</div>
                            </div>
                            <button onclick='selectStudentForSitin(${JSON.stringify(st)})' class="btn-select-stu">
                                <span>Select</span><i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    `;
                }

                card.innerHTML = `
                    <div class="flex items-center gap-3.5 pr-2">
                        <div class="stu-avatar-lg">${initials}</div>
                        <div>
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="font-bold text-white text-sm">${st.firstname} ${st.lastname}</span>
                                <span class="badge-course-sm font-mono">${st.idno}</span>
                                <span class="badge-course-sm">${st.course}</span>
                            </div>
                            <div class="text-xs text-gray-400 flex items-center gap-1.5">
                                <i class="fas fa-graduation-cap text-purple-400"></i>
                                <span>${st.level === '1' ? '1st' : st.level === '2' ? '2nd' : st.level === '3' ? '3rd' : '4th'} Year</span>
                            </div>
                        </div>
                    </div>
                    ${rightHtml}
                `;

                list.appendChild(card);
            });
        }

        // Modal 2 Logic
        function selectStudentForSitin(st) {
            selectedStudentObj = st;
            closeSelectModal();

            // Populate form
            document.getElementById('formIdno').value = st.idno;
            document.getElementById('displayIdno').textContent = st.idno;
            document.getElementById('displayName').textContent = `${st.firstname} ${st.lastname} • ${st.course} - ${st.level === '1' ? '1st' : st.level === '2' ? '2nd' : st.level === '3' ? '3rd' : '4th'} Year`;
            
            const sessionVal = parseInt(st.session);
            document.getElementById('remSessionVal').textContent = sessionVal;
            const pct = Math.round((sessionVal / 30) * 100);
            document.getElementById('remSessionPercent').textContent = `${pct}% remaining`;
            document.getElementById('remSessionBar').style.width = `${pct}%`;

            // Reset Purpose and PC
            document.getElementById('formPurposeSelect').value = '';
            document.getElementById('formPcNumber').value = '';
            document.getElementById('selectedPcBadge').classList.add('hidden');
            document.querySelectorAll('.quick-select-pill').forEach(p => p.classList.remove('active'));

            document.getElementById('modalLogSitinForm').style.display = 'flex';
            loadPcGrid(document.getElementById('formLabSelect').value);
            checkRequiredFields();
        }

        function closeFormModal() {
            document.getElementById('modalLogSitinForm').style.display = 'none';
        }

        document.getElementById('formLabSelect').addEventListener('change', function() {
            document.getElementById('gridHeaderLabel').textContent = `PC GRID — LAB ${this.value}`;
            document.getElementById('formPcNumber').value = '';
            document.getElementById('selectedPcBadge').classList.add('hidden');
            loadPcGrid(this.value);
            checkRequiredFields();
        });

        document.getElementById('formPurposeSelect').addEventListener('change', function() {
            document.querySelectorAll('.quick-select-pill').forEach(p => p.classList.remove('active'));
            checkRequiredFields();
        });

        function setQuickPurpose(val, btn) {
            document.getElementById('formPurposeSelect').value = val;
            document.querySelectorAll('.quick-select-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            checkRequiredFields();
        }

        function loadPcGrid(lab) {
            const container = document.getElementById('pcGridContainer');
            container.innerHTML = `<div class="py-8 col-span-5 text-center text-xs text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Loading PC grid...</div>`;

            fetch(`get_lab_pcs.php?lab=${lab}`)
                .then(res => res.json())
                .then(data => {
                    container.innerHTML = '';
                    let avail = 0; let occ = 0; let sel = document.getElementById('formPcNumber').value ? 1 : 0;

                    data.pcs.forEach(p => {
                        const isOcc = p.status === 'occupied';
                        const isSel = p.pc_number.toString() === document.getElementById('formPcNumber').value;

                        if (isOcc) occ++;
                        else avail++;

                        const tile = document.createElement('div');
                        tile.className = `pc-tile ${isOcc ? 'occupied' : ''} ${isSel ? 'selected' : ''}`;
                        tile.dataset.pc = p.pc_number;
                        
                        tile.innerHTML = `
                            <i class="fas ${isOcc ? 'fa-ban' : isSel ? 'fa-check-circle' : 'fa-desktop'}"></i>
                            <span>${p.pc_number}</span>
                        `;

                        if (!isOcc) {
                            tile.addEventListener('click', function() {
                                document.querySelectorAll('.pc-tile').forEach(t => t.classList.remove('selected', '!border-purple-500'));
                                this.classList.add('selected');
                                document.getElementById('formPcNumber').value = p.pc_number;
                                document.getElementById('badgePcVal').textContent = p.pc_number;
                                document.getElementById('selectedPcBadge').classList.remove('hidden');
                                document.getElementById('countSel').textContent = '1 Selected';
                                checkRequiredFields();
                            });
                        }

                        container.appendChild(tile);
                    });

                    document.getElementById('countAvail').textContent = `${avail} Available`;
                    document.getElementById('countOcc').textContent = `${occ} Occupied`;
                    document.getElementById('countSel').textContent = `${sel} Selected`;
                })
                .catch(err => {
                    container.innerHTML = `<div class="py-8 col-span-5 text-center text-xs text-red-400">Failed to load PC grid</div>`;
                });
        }

        function checkRequiredFields() {
            const lab = document.getElementById('formLabSelect').value;
            const purpose = document.getElementById('formPurposeSelect').value;
            const pc = document.getElementById('formPcNumber').value;

            let count = 0;
            if (lab) count++;
            if (purpose) count++;
            if (pc) count++;

            document.getElementById('formStatusBadge').textContent = `${count}/3 filled`;
            document.getElementById('btnSubmitSession').disabled = count < 3;
        }

        // Sort Dropdown Handler
        const sortBtn = document.getElementById('sortButton');
        const sortDropdown = document.getElementById('sortDropdown');
        if (sortBtn && sortDropdown) {
            sortBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sortDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', (e) => {
                if (!sortDropdown.contains(e.target)) {
                    sortDropdown.classList.add('hidden');
                }
            });
        }

        // Pagination & Search & Sort Logic
        let currentPage = 1;
        let rowsPerPage = 5;
        let currentSort = 'time-desc';

        const tableBody = document.querySelector('#currentSitTable tbody');
        const searchInput = document.getElementById('searchInput');
        const entriesSelect = document.getElementById('entries');
        const noMatchRow = document.getElementById('noMatchRow');
        const dbEmptyRow = document.getElementById('dbEmptyRow');

        function updateTable() {
            const query = searchInput.value.toLowerCase().trim();
            const rows = Array.from(tableBody.querySelectorAll('tr:not(.not-record)'));
            
            if (rows.length === 0) return;

            // Search filter
            let visibleRows = rows.filter(row => {
                const text = row.textContent.toLowerCase();
                return text.includes(query);
            });

            // Sorting
            visibleRows.sort((a, b) => {
                if (currentSort === 'id-asc') {
                    return parseInt(a.cells[1].textContent) - parseInt(b.cells[1].textContent);
                } else if (currentSort === 'id-desc') {
                    return parseInt(b.cells[1].textContent) - parseInt(a.cells[1].textContent);
                } else if (currentSort === 'name-asc') {
                    return a.cells[2].textContent.localeCompare(b.cells[2].textContent);
                } else if (currentSort === 'name-desc') {
                    return b.cells[2].textContent.localeCompare(a.cells[2].textContent);
                } else if (currentSort === 'time-asc') {
                    return parseInt(a.cells[6].dataset.timestamp) - parseInt(b.cells[6].dataset.timestamp);
                } else { // time-desc
                    return parseInt(b.cells[6].dataset.timestamp) - parseInt(a.cells[6].dataset.timestamp);
                }
            });

            // Reorder DOM rows to match sort order
            rows.forEach(r => r.style.display = 'none');
            visibleRows.forEach(r => tableBody.appendChild(r));

            // Show/Hide no match row
            if (visibleRows.length === 0 && query !== '') {
                if (noMatchRow) noMatchRow.classList.remove('hidden');
            } else {
                if (noMatchRow) noMatchRow.classList.add('hidden');
            }

            // Pagination calculations
            const total = visibleRows.length;
            const totalPages = rowsPerPage === Infinity ? 1 : Math.ceil(total / rowsPerPage);
            if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

            const start = rowsPerPage === Infinity ? 0 : (currentPage - 1) * rowsPerPage;
            const end = rowsPerPage === Infinity ? total : start + rowsPerPage;

            visibleRows.slice(start, end).forEach(r => r.style.display = '');

            // Update Info
            const s = total === 0 ? 0 : start + 1;
            const e = Math.min(end, total);
            document.getElementById('paginationInfo').textContent = rowsPerPage === Infinity ? `Showing all ${total} entries` : `Showing ${s} to ${e} of ${total} entries`;

            // Update Controls
            const controls = document.getElementById('paginationControls');
            controls.innerHTML = '';

            if (rowsPerPage !== Infinity && totalPages > 1) {
                const prev = document.createElement('button');
                prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
                prev.className = 'page-btn'; prev.disabled = currentPage === 1;
                prev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; updateTable(); } });
                controls.appendChild(prev);

                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement('button');
                    btn.textContent = i;
                    btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
                    btn.addEventListener('click', () => { currentPage = i; updateTable(); });
                    controls.appendChild(btn);
                }

                const next = document.createElement('button');
                next.innerHTML = '<i class="fas fa-chevron-right"></i>';
                next.className = 'page-btn'; next.disabled = currentPage === totalPages;
                next.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; updateTable(); } });
                controls.appendChild(next);
            }
        }

        // Attach listeners
        if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
        if (entriesSelect) entriesSelect.addEventListener('change', function() {
            rowsPerPage = this.value === 'all' ? Infinity : parseInt(this.value);
            currentPage = 1;
            updateTable();
        });

        document.querySelectorAll('.sort-opt').forEach(btn => {
            btn.addEventListener('click', function() {
                currentSort = this.dataset.sort;
                sortDropdown.classList.add('hidden');
                updateTable();
            });
        });

        // Bulk Selection and Time Out Logic
        let selectionMode = false;
        const selectStudentBtn = document.getElementById('selectStudentBtn');
        const bulkActions = document.getElementById('bulkActions');
        const selectCols = document.querySelectorAll('.select-col');
        const selectAllSitins = document.getElementById('selectAllSitins');

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
                    this.style.borderColor = 'var(--purple)';
                    this.style.color = 'var(--purple-light)';
                    bulkActions.style.display = 'none';
                    selectCols.forEach(col => col.style.display = 'none');
                    // Uncheck all
                    if (selectAllSitins) selectAllSitins.checked = false;
                    document.querySelectorAll('.sitin-checkbox').forEach(cb => cb.checked = false);
                }
            });
        }

        if (selectAllSitins) {
            selectAllSitins.addEventListener('change', function() {
                const isChecked = this.checked;
                document.querySelectorAll('.sitin-checkbox').forEach(cb => {
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = isChecked;
                    }
                });
            });
        }

        const bulkTimeoutBtn = document.getElementById('bulkTimeoutBtn');
        if (bulkTimeoutBtn) {
            bulkTimeoutBtn.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.sitin-checkbox:checked');
                const selectedIds = Array.from(checkedBoxes)
                    .filter(cb => cb.closest('tr').style.display !== 'none')
                    .map(cb => cb.value);

                if (selectedIds.length === 0) {
                    alert("Please select at least one student session to Time Out.");
                    return;
                }

                if (!confirm(`Are you sure you want to Time Out ${selectedIds.length} selected student(s)?`)) {
                    return;
                }

                // Show loading state
                bulkTimeoutBtn.disabled = true;
                bulkTimeoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Timing Out...';

                const promises = selectedIds.map(id => {
                    return fetch('current_sit.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `logout_idno=${id}`
                    }).then(res => {
                        if (!res.ok) throw new Error("HTTP error");
                        return res.text();
                    });
                });

                Promise.allSettled(promises).then(results => {
                    const successes = results.filter(r => r.status === 'fulfilled');
                    alert(`Successfully timed out ${successes.length} out of ${selectedIds.length} students.`);
                    location.reload();
                });
            });
        }

        // Background Starfield Animation
        const canvas = document.getElementById('star-canvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let stars = [];
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                initStars();
            }
            function initStars() {
                stars = [];
                const count = Math.floor((canvas.width * canvas.height) / 3000);
                for (let i = 0; i < count; i++) {
                    stars.push({
                        x: Math.random() * canvas.width,
                        y: Math.random() * canvas.height,
                        radius: Math.random() * 1.5 + 0.5,
                        vx: (Math.random() - 0.5) * 0.2,
                        vy: Math.random() * 0.3 + 0.1,
                        alpha: Math.random(),
                        dAlpha: (Math.random() - 0.5) * 0.02,
                        hue: Math.random() > 0.8 ? Math.floor(Math.random() * 60) + 260 : 240
                    });
                }
            }
            function animateStars() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                stars.forEach(star => {
                    star.x += star.vx;
                    star.y += star.vy;
                    if (star.y > canvas.height) star.y = 0;
                    if (star.x < 0) star.x = canvas.width;
                    if (star.x > canvas.width) star.x = 0;
                    star.alpha += star.dAlpha;
                    if (star.alpha > 1 || star.alpha < 0.2) star.dAlpha = -star.dAlpha;
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `hsla(${star.hue}, 80%, 75%, ${star.alpha})`;
                    ctx.fill();
                });
                requestAnimationFrame(animateStars);
            }
            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();
            animateStars();
        }

        // Initial table load & Check URL Search Params
        document.addEventListener('DOMContentLoaded', () => {
            if (entriesSelect) entriesSelect.value = '5';
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam && searchInput) {
                searchInput.value = searchParam;
            }
            updateTable();

            <?php if (isset($_GET['log_idno'])): ?>
            const targetId = <?php echo json_encode($_GET['log_idno']); ?>;
            const student = allRegisteredStudents.find(s => s.idno == targetId);
            if (student) {
                selectStudentForSitin(student);
            } else {
                openSelectModal();
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>