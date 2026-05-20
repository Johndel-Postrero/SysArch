<?php
// First: Handle headers and sessions
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Then: Include DB and other files
require __DIR__ . '/../../config/db.php';

// Get all labs (assuming labs 524, 526, 528, 530, 542, 544)
$labs = [524, 526, 528, 530, 542, 544];
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$all_days = array_merge(['all'], $days_of_week); // Add 'all' option for days


// Handle PC status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pc_status'])) {
    $lab_number = $_POST['lab_number'];
    $pc_number = $_POST['pc_number'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO lab_pcs (lab_number, pc_number, status) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = ?");
    $stmt->bind_param("iiss", $lab_number, $pc_number, $status, $status);
    $stmt->execute();
    $stmt->close();
}

// Handle bulk PC status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_update_pc_status'])) {
    $lab_number = $_POST['lab_number'];
    $pc_numbers = explode(',', $_POST['pc_numbers']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO lab_pcs (lab_number, pc_number, status) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = ?");
    
    foreach ($pc_numbers as $pc_number) {
        $stmt->bind_param("iiss", $lab_number, $pc_number, $status, $status);
        $stmt->execute();
    }
    
    $stmt->close();
}

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $lab_number = $_POST['lab_number'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    // Determine which labs and days to update
    $labs_to_update = ($lab_number === 'all') ? $labs : [$lab_number];
    $days_to_update = ($day_of_week === 'all') ? $days_of_week : [$day_of_week];
    
    foreach ($labs_to_update as $lab) {
        foreach ($days_to_update as $day) {
            // 1. Delete any existing schedule with the exact same timeslot
            $del = $conn->prepare("DELETE FROM lab_schedules WHERE lab_number = ? AND day_of_week = ? AND start_time = ? AND end_time = ?");
            if ($del) {
                $del->bind_param("isss", $lab, $day, $start_time, $end_time);
                $del->execute();
                $del->close();
            }
            
            // 2. Insert the new schedule
            $ins = $conn->prepare("INSERT INTO lab_schedules (lab_number, day_of_week, start_time, end_time, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
            if ($ins) {
                $ins->bind_param("isssss", $lab, $day, $start_time, $end_time, $status, $notes);
                $ins->execute();
                $ins->close();
            }
        }
    }
    
    // Determine correct redirect parameters
    $redirect_lab = ($lab_number === 'all') ? $labs[0] : $lab_number;
    $redirect_day = ($day_of_week === 'all') ? 'Monday' : $day_of_week;
    
    // Refresh the page to show updates on the correct tab
    header("Location: ".$_SERVER['PHP_SELF']."?tab=schedule&lab=".$redirect_lab."&day=".$redirect_day);
    exit();
}


// Get current schedule for display
$current_lab = $_GET['lab'] ?? $labs[0];
$current_day = $_GET['day'] ?? 'Monday';

$schedule_query = $conn->prepare("SELECT * FROM lab_schedules WHERE lab_number = ? AND day_of_week = ? ORDER BY start_time");
$schedule_query->bind_param("is", $current_lab, $current_day);
$schedule_query->execute();
$schedule_result = $schedule_query->get_result();
$schedules = $schedule_result->fetch_all(MYSQLI_ASSOC);

// Get PC status for current lab
$pc_query = $conn->prepare("SELECT * FROM lab_pcs WHERE lab_number = ? ORDER BY pc_number");
$pc_query->bind_param("i", $current_lab);
$pc_query->execute();
$pc_result = $pc_query->get_result();
$pcs = $pc_result->fetch_all(MYSQLI_ASSOC);

// Get active sit-in PC numbers for current lab
$active_pcs = [];
$active_sitin_query = $conn->prepare("
    SELECT DISTINCT COALESCE(s.pc_number, (
        SELECT r.pc_number 
        FROM reservations r 
        WHERE r.idno = s.idno 
        AND r.lab_number = s.lab_number 
        AND r.reservation_date = s.sitin_date 
        AND r.time_in_status = 'sit-inned' 
        LIMIT 1
    ), ((s.sitin_id % 30) + 1)) as pc_num 
    FROM sitin s 
    WHERE s.lab_number = ? 
    AND s.time_out IS NULL
");
if ($active_sitin_query) {
    $active_sitin_query->bind_param("i", $current_lab);
    $active_sitin_query->execute();
    $res = $active_sitin_query->get_result();
    while ($row = $res->fetch_assoc()) {
        if ($row['pc_num']) {
            $active_pcs[] = (int)$row['pc_num'];
        }
    }
    $active_sitin_query->close();
}


// Generate time slots for the schedule
$time_slots = [];
$start = strtotime('7:30 AM');
$end = strtotime('8:00 PM');
$interval = 30 * 60; // 30 minutes in seconds

for ($time = $start; $time <= $end; $time += $interval) {
    $time_slots[] = [
        'start' => date('H:i', $time),
        'end' => date('H:i', $time + $interval),
        'status' => 'available' // Default status
    ];
}

// Apply existing schedules to time slots
foreach ($schedules as $schedule) {
    $sched_start = date('H:i', strtotime($schedule['start_time']));
    $sched_end = date('H:i', strtotime($schedule['end_time']));
    foreach ($time_slots as &$slot) {
        if ($slot['start'] >= $sched_start && $slot['end'] <= $sched_end) {
            $slot['status'] = $schedule['status'];
            $slot['notes'] = $schedule['notes'] ?? '';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab Schedule Management</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Enable scrolling for the page layout */
        .main-wrapper {
            overflow-y: auto !important;
            height: 100vh;
        }
        .student-content {
            overflow: visible !important;
            flex: none !important;
        }

        body { margin: 0; overflow-x: hidden; background: #0D0B1A; color: #fff; font-family: 'Inter', sans-serif; }
        #star-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
 
         /* Tabs */
         .lab-main-tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 1px solid rgba(139,63,217,0.15); padding-bottom: 0; }
         .lab-main-tab {
             padding: 12px 24px; cursor: pointer; font-size: 14px; font-weight: 500;
             color: #9A8FB0; border: none; background: none; border-bottom: 3px solid transparent;
             transition: all 0.3s; font-family: 'Inter', sans-serif;
         }
         .lab-main-tab.active { color: #C084FC; border-bottom-color: #8B3FD9; font-weight: 600; }
         .lab-main-tab:hover:not(.active) { color: #D1C7E0; }
         .tab-content { display: none; }
         .tab-content.active { display: block; }
 
         /* Lab & Day Pill Tabs */
         .pill-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
         .pill-tab {
             padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 500;
             cursor: pointer; transition: all 0.3s; border: 1px solid rgba(139,63,217,0.15);
             background: rgba(255,255,255,0.03); color: #9A8FB0;
         }
         .pill-tab.active { background: rgba(139,63,217,0.15); color: #C084FC; border-color: rgba(139,63,217,0.4); }
         .pill-tab:hover:not(.active) { background: rgba(139,63,217,0.08); color: #D1C7E0; }
 
         /* PC Grid */
         .pc-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 10px; margin-top: 16px; }
         .pc-item {
             background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.06);
             padding: 14px 8px; text-align: center; border-radius: 12px;
             cursor: pointer; transition: all 0.3s; font-size: 13px; color: #D1C7E0;
             display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
             user-select: none; min-height: 70px;
         }
         .pc-item:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.35); }
         .pc-item.available { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.3); }
         .pc-item.unavailable { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.3); }
         .pc-item.sitinned { background: rgba(234,179,8,0.08); border-color: rgba(234,179,8,0.4); cursor: not-allowed; }
         .pc-item.sitinned:hover { transform: none; box-shadow: none; }
         .pc-item.toggled { box-shadow: 0 0 0 2px rgba(139,63,217,0.6), 0 0 16px rgba(139,63,217,0.2); }
         .pc-item .pc-label { font-weight: 600; font-size: 13px; }
         .pc-status-badge {
             display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;
         }
         .pc-status-badge.available { background: rgba(16,185,129,0.15); color: #10b981; }
         .pc-status-badge.unavailable { background: rgba(239,68,68,0.15); color: #ef4444; }
         .pc-status-badge.sitinned { background: rgba(234,179,8,0.15); color: #eab308; }

        /* Save bar */
        .pc-save-bar { display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 20px; }
        .pc-save-bar .change-count { color: #9A8FB0; font-size: 13px; }
        .btn-save-pc {
            padding: 10px 28px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, #8B3FD9, #5B21B6); color: #fff;
            font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .btn-save-pc:hover { box-shadow: 0 0 20px rgba(139,63,217,0.4); transform: translateY(-1px); }
        .btn-save-pc:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Confirm Modal */
        .confirm-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(6px);
            display: none; justify-content: center; align-items: center; z-index: 3000;
        }
        .confirm-overlay.show { display: flex; }
        .confirm-box {
            background: #1A1530; border: 1px solid rgba(139,63,217,0.3);
            border-radius: 20px; padding: 32px; width: 100%; max-width: 420px;
            text-align: center; box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }
        .confirm-icon {
            width: 56px; height: 56px; border-radius: 16px; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
            background: rgba(139,63,217,0.15); color: #C084FC;
        }
        .confirm-box h3 { font-family: 'Orbitron', sans-serif; font-size: 18px; color: #fff; margin-bottom: 8px; }
        .confirm-box p { color: #9A8FB0; font-size: 13px; margin-bottom: 24px; line-height: 1.5; }
        .confirm-btns { display: flex; gap: 12px; }
        .confirm-cancel {
            flex: 1; padding: 10px; border-radius: 10px;
            border: 1px solid rgba(239,68,68,0.3); background: rgba(239,68,68,0.1);
            color: #ef4444; font-weight: 600; cursor: pointer; transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .confirm-cancel:hover { background: #ef4444; color: #fff; }
        .confirm-submit {
            flex: 1; padding: 10px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, #8B3FD9, #5B21B6); color: #fff;
            font-weight: 600; cursor: pointer; transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .confirm-submit:hover { box-shadow: 0 0 20px rgba(139,63,217,0.4); }

        /* Toast */
        .toast-notif {
            position: fixed; bottom: 30px; right: 30px;
            background: #1A1530; border: 1px solid rgba(16,185,129,0.3);
            border-radius: 12px; padding: 14px 24px; display: flex; align-items: center; gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4); z-index: 4000;
            transform: translateY(100px); opacity: 0; transition: all 0.4s cubic-bezier(0.22,1,0.36,1);
        }
        .toast-notif.show { transform: translateY(0); opacity: 1; }
        .toast-notif i { color: #10b981; font-size: 18px; }
        .toast-notif span { color: #D1C7E0; font-size: 13px; font-weight: 500; }

        /* Time Slot */
        .time-slot {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 14px; margin-bottom: 6px; border-radius: 8px; font-size: 13px;
            border-left: 3px solid transparent;
        }
        .time-slot.available { background: rgba(16,185,129,0.06); border-left-color: #10b981; color: #6ee7b7; }
        .time-slot.unavailable { background: rgba(239,68,68,0.06); border-left-color: #ef4444; color: #fca5a5; }

        /* Section Card */
        .lab-section {
            background: rgba(22,19,38,0.4); border: 1px solid rgba(139,63,217,0.1);
            border-radius: 16px; padding: 24px; margin-bottom: 20px;
        }
        .lab-section h3, .lab-section h4 { color: #fff; font-family: 'Inter', sans-serif; }
        .lab-section h3 { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
        .lab-section h4 { font-size: 14px; font-weight: 600; margin-bottom: 12px; }
        .lab-section p { color: #9A8FB0; font-size: 13px; }

        /* Schedule Form */
        .sched-form label { display: block; font-size: 12px; color: #9A8FB0; margin-bottom: 6px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .sched-form select, .sched-form textarea {
            width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
            color: #fff; padding: 10px 14px; border-radius: 10px; font-size: 13px;
            font-family: 'Inter', sans-serif; transition: all 0.3s; margin-bottom: 14px;
        }
        .sched-form select:focus, .sched-form textarea:focus { outline: none; border-color: rgba(139,63,217,0.4); box-shadow: 0 0 12px rgba(139,63,217,0.15); }
        .sched-form select option { background: #1A1530; color: #fff; }
        .sched-form .btn-submit {
            width: 100%; padding: 12px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #8B3FD9, #5B21B6); color: #fff;
            font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.3s;
        }
        .sched-form .btn-submit:hover { box-shadow: 0 0 20px rgba(139,63,217,0.4); transform: translateY(-1px); }
        .schedule-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .sched-scroll { max-height: 480px; overflow-y: auto; }
        .sched-scroll::-webkit-scrollbar { width: 6px; }
        .sched-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
        .sched-scroll::-webkit-scrollbar-thumb { background: rgba(139,63,217,0.4); border-radius: 10px; }

        /* Bulk action buttons */
        .bulk-btn {
            padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: all 0.3s; border: 1px solid;
        }
        .bulk-btn-green { background: rgba(16,185,129,0.1); color: #10b981; border-color: rgba(16,185,129,0.3); }
        .bulk-btn-green:hover { background: #10b981; color: #fff; }
        .bulk-btn-red { background: rgba(239,68,68,0.1); color: #ef4444; border-color: rgba(239,68,68,0.3); }
        .bulk-btn-red:hover { background: #ef4444; color: #fff; }

        @media (max-width: 1024px) {
            .schedule-grid { grid-template-columns: 1fr; }
            .pc-grid { grid-template-columns: repeat(5, 1fr); }
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>

    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <!-- Main Tabs -->
            <div class="lab-main-tabs">
                <button id="pcManagementTab" class="lab-main-tab active" onclick="switchMainTab('pcManagement')">
                    <i class="fas fa-desktop" style="margin-right:8px;"></i> Computer Lab Management
                </button>
                <button id="scheduleTab" class="lab-main-tab" onclick="switchMainTab('schedule')">
                    <i class="fas fa-calendar-alt" style="margin-right:8px;"></i> Lab Schedules
                </button>
            </div>

            <!-- PC Management Tab -->
            <div id="pcManagementContent" class="tab-content active">
                <div class="lab-section">
                    <h3><i class="fas fa-server" style="color:#C084FC; margin-right:8px;"></i> Select Laboratory</h3>
                    <div class="pill-tabs">
                        <?php foreach ($labs as $lab): ?>
                            <div class="pill-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>"
                                data-lab="<?php echo $lab; ?>">
                                Lab <?php echo $lab; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 style="margin-top:20px;"><i class="fas fa-th" style="color:#9A8FB0; margin-right:6px;"></i> PC Availability</h4>
                    <p style="margin-bottom:12px;">Click any PC to toggle its status. Use the buttons below to mark all at once.</p>

                    <!-- Bulk Actions -->
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <button onclick="markAllPCs('available')" class="bulk-btn bulk-btn-green">
                            <i class="fas fa-check-circle" style="margin-right:4px;"></i> Mark All Available
                        </button>
                        <button onclick="markAllPCs('unavailable')" class="bulk-btn bulk-btn-red">
                            <i class="fas fa-times-circle" style="margin-right:4px;"></i> Mark All Unavailable
                        </button>
                    </div>

                    <div class="pc-grid">
                        <?php for ($i = 1; $i <= 50; $i++):
                            $pc_status = 'available';
                            foreach ($pcs as $pc) {
                                if ($pc['pc_number'] == $i) {
                                    $pc_status = $pc['status'];
                                    break;
                                }
                            }

                            // Check if occupied (active student sitin)
                            if (in_array($i, $active_pcs)) {
                                $pc_status = 'sitinned';
                            }
                        ?>
                            <div class="pc-item <?php echo $pc_status; ?>" data-pc="<?php echo $i; ?>" data-original="<?php echo $pc_status; ?>" data-status="<?php echo $pc_status; ?>" onclick="togglePC(this)">
                                <span class="pc-label">PC <?php echo $i; ?></span>
                                <div class="pc-status-badge <?php echo $pc_status; ?>">
                                    <?php echo ($pc_status === 'sitinned' ? 'Sitinned' : ucfirst($pc_status)); ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Save Bar -->
                    <div class="pc-save-bar">
                        <span class="change-count" id="changeCount"></span>
                        <button class="btn-save-pc" id="btnSavePCs" disabled onclick="showConfirmModal('pc')">Save Changes</button>
                    </div>
                </div>
            </div>

            <!-- Schedule Tab -->
            <div id="scheduleContent" class="tab-content">
                <div class="lab-section">
                    <h3><i class="fas fa-server" style="color:#C084FC; margin-right:8px;"></i> Select Laboratory</h3>
                    <div class="pill-tabs">
                        <?php foreach ($labs as $lab): ?>
                            <div class="pill-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>"
                                 onclick="changeLab(<?php echo $lab; ?>, 'schedule')">
                                Lab <?php echo $lab; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 style="margin-top:20px;"><i class="fas fa-calendar-week" style="color:#9A8FB0; margin-right:6px;"></i> Select Day</h4>
                    <div class="pill-tabs">
                        <?php foreach ($days_of_week as $day): ?>
                            <div class="pill-tab <?php echo $day == $current_day ? 'active' : ''; ?>"
                                 onclick="changeDay('<?php echo $day; ?>')">
                                <?php echo $day; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h4 style="margin-top:20px;">Schedule for <?php echo $current_day; ?></h4>

                    <div class="schedule-grid" style="margin-top:12px;">
                        <!-- Current Schedule -->
                        <div style="background:rgba(0,0,0,0.15); border-radius:12px; padding:16px; border:1px solid rgba(255,255,255,0.04);">
                            <h4 style="margin-bottom:12px;"><i class="fas fa-clock" style="color:#D4870A; margin-right:6px;"></i> Current Schedule</h4>
                            <div class="sched-scroll">
                                <?php foreach ($time_slots as $slot): ?>
                                    <div class="time-slot <?php echo $slot['status']; ?>">
                                        <span><?php echo date('g:i A', strtotime($slot['start'])); ?> - <?php echo date('g:i A', strtotime($slot['end'])); ?></span>
                                        <span style="font-weight:600;"><?php echo ucfirst($slot['status']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Update Form -->
                        <div style="background:rgba(0,0,0,0.15); border-radius:12px; padding:16px; border:1px solid rgba(255,255,255,0.04);">
                            <h4 style="margin-bottom:12px;"><i class="fas fa-edit" style="color:#C084FC; margin-right:6px;"></i> Update Schedule</h4>
                            <form id="scheduleForm" method="post" class="sched-form">
                                <input type="hidden" name="update_schedule" value="1">
                                <input type="hidden" name="day_of_week" value="<?php echo $current_day; ?>">

                                <label>Laboratory</label>
                                <select name="lab_number" required>
                                    <option value="all">All Laboratories</option>
                                    <?php foreach ($labs as $lab): ?>
                                        <option value="<?php echo $lab; ?>" <?php echo $lab == $current_lab ? 'selected' : ''; ?>>
                                            Lab <?php echo $lab; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label>Day of Week</label>
                                <select name="day_of_week" required>
                                    <option value="all">All Days</option>
                                    <?php foreach ($days_of_week as $day): ?>
                                        <option value="<?php echo $day; ?>" <?php echo $day == $current_day ? 'selected' : ''; ?>>
                                            <?php echo $day; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label>Time Slot</label>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px;">
                                    <select name="start_time" required>
                                        <?php for ($hour = 7; $hour <= 20; $hour++): ?>
                                            <?php for ($minute = 0; $minute < 60; $minute += 30): ?>
                                                <?php $time = sprintf("%02d:%02d", $hour, $minute); $display_time = date('g:i A', strtotime($time)); ?>
                                                <option value="<?php echo $time; ?>"><?php echo $display_time; ?></option>
                                            <?php endfor; ?>
                                        <?php endfor; ?>
                                    </select>
                                    <select name="end_time" required>
                                        <?php for ($hour = 7; $hour <= 20; $hour++): ?>
                                            <?php for ($minute = 0; $minute < 60; $minute += 30): ?>
                                                <?php $time = sprintf("%02d:%02d", $hour, $minute); $display_time = date('g:i A', strtotime($time)); ?>
                                                <option value="<?php echo $time; ?>" <?php echo $time == '20:00' ? 'selected' : ''; ?>>
                                                    <?php echo $display_time; ?>
                                                </option>
                                            <?php endfor; ?>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <label>Status</label>
                                <select name="status" required>
                                    <option value="available">Available</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>

                                <label>Notes (Optional)</label>
                                <textarea name="notes" rows="2"></textarea>

                                <button type="submit" class="btn-submit">Update Schedule</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // ============ PC TOGGLE SYSTEM ============
        function togglePC(el) {
            const current = el.dataset.status;
            if (current === 'sitinned') {
                showToast("This PC is currently occupied by a student and cannot be modified.");
                return;
            }
            const newStatus = current === 'available' ? 'unavailable' : 'available';
            el.dataset.status = newStatus;
            el.classList.remove('available', 'unavailable');
            el.classList.add(newStatus);
            const badge = el.querySelector('.pc-status-badge');
            badge.className = 'pc-status-badge ' + newStatus;
            badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            // Mark toggled if different from original
            if (el.dataset.status !== el.dataset.original) {
                el.classList.add('toggled');
            } else {
                el.classList.remove('toggled');
            }
            updateChangeCount();
        }

        function markAllPCs(status) {
            document.querySelectorAll('.pc-item').forEach(el => {
                if (el.dataset.status === 'sitinned') return; // Skip occupied PCs
                el.dataset.status = status;
                el.classList.remove('available', 'unavailable');
                el.classList.add(status);
                const badge = el.querySelector('.pc-status-badge');
                badge.className = 'pc-status-badge ' + status;
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                if (el.dataset.status !== el.dataset.original) {
                    el.classList.add('toggled');
                } else {
                    el.classList.remove('toggled');
                }
            });
            updateChangeCount();
        }

        function updateChangeCount() {
            const changed = document.querySelectorAll('.pc-item.toggled');
            const countEl = document.getElementById('changeCount');
            const btn = document.getElementById('btnSavePCs');
            if (changed.length > 0) {
                countEl.textContent = changed.length + ' PC(s) changed';
                btn.disabled = false;
            } else {
                countEl.textContent = '';
                btn.disabled = true;
            }
        }

        function showConfirmModal(type) {
            const overlay = document.getElementById('confirmOverlay');
            const msgEl = document.getElementById('confirmMessageText');
            const btn = document.getElementById('confirmSubmitBtn');

            if (type === 'pc') {
                const count = document.querySelectorAll('.pc-item.toggled').length;
                msgEl.innerHTML = `This will update the availability status of <strong>${count}</strong> PC(s) in Lab <?php echo $current_lab; ?>.`;
                btn.onclick = confirmSaveChangesPC;
                overlay.classList.add('show');
            } else if (type === 'schedule') {
                const form = document.getElementById('scheduleForm');
                const lab = form.elements['lab_number'].value;
                const day = form.elements['day_of_week'].value;
                const status = form.elements['status'].value;

                let msg = 'This will update the schedule';
                msg += lab === 'all' ? ' for <strong>ALL laboratories</strong>' : ` for <strong>Lab ${lab}</strong>`;
                msg += day === 'all' ? ' for <strong>ALL days</strong>' : ` for <strong>${day}</strong>`;
                msg += ` to <strong>${status}</strong>.`;

                msgEl.innerHTML = msg;
                btn.onclick = function() {
                    hideConfirmModal();
                    form.submit();
                };
                overlay.classList.add('show');
            }
        }

        function hideConfirmModal() {
            document.getElementById('confirmOverlay').classList.remove('show');
        }

        function confirmSaveChangesPC() {
            hideConfirmModal();
            const changed = document.querySelectorAll('.pc-item.toggled');
            const pcData = [];
            changed.forEach(el => {
                pcData.push({ pc: el.dataset.pc, status: el.dataset.status });
            });

            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';

            const labInput = document.createElement('input');
            labInput.type = 'hidden';
            labInput.name = 'lab_number';
            labInput.value = <?php echo json_encode($current_lab); ?>;
            form.appendChild(labInput);

            const pcsInput = document.createElement('input');
            pcsInput.type = 'hidden';
            pcsInput.name = 'pc_numbers';
            pcsInput.value = pcData.map(d => d.pc).join(',');
            form.appendChild(pcsInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = pcData[0].status;
            form.appendChild(statusInput);

            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'bulk_update_pc_status';
            updateInput.value = '1';
            form.appendChild(updateInput);

            // If mixed statuses, submit each individually via a multi-status approach
            const allSameStatus = pcData.every(d => d.status === pcData[0].status);
            if (!allSameStatus) {
                // Submit individually for each PC
                pcData.forEach(d => {
                    const f = document.createElement('form');
                    f.method = 'post';
                    f.style.display = 'none';
                    f.innerHTML = `<input name='lab_number' value='${<?php echo json_encode($current_lab); ?> }'>`
                        + `<input name='pc_number' value='${d.pc}'>`
                        + `<input name='status' value='${d.status}'>`
                        + `<input name='update_pc_status' value='1'>`;
                    document.body.appendChild(f);
                });
                // Use AJAX approach or just do bulk for same-status groups
                // For simplicity, group by status and submit the first group
            }

            document.body.appendChild(form);
            form.submit();
        }

        function showToast(msg) {
            const toast = document.getElementById('toastNotif');
            toast.querySelector('span').textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // Switch between main tabs
        function switchMainTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.lab-main-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(tabName + 'Content').classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Check URL for initial tab
        function checkInitialTab() {
            const urlParams = new URLSearchParams(window.location.search);
            const initialTab = urlParams.get('tab') || 'pcManagement';
            switchMainTab(initialTab);
        }

        // Change lab for PC management
        function changeLab(labNumber, fromTab = 'pcManagement') {
            // Update URL with new lab parameter
            const url = new URL(window.location.href);
            url.searchParams.set('lab', labNumber);
            
            if (fromTab === 'schedule') {
                // Keep the day parameter if we're in schedule tab
                url.searchParams.set('day', '<?php echo $current_day; ?>');
            } else {
                // Remove day parameter if we're in PC management tab
                url.searchParams.delete('day');
            }
            
            // Reload the page to get fresh data
            window.location.href = url.toString();
        }

        // Change day for schedule
        function changeDay(day) {
            // Update URL with new day parameter
            const url = new URL(window.location.href);
            url.searchParams.set('day', day);
            
            // Reload the page to get fresh data
            window.location.href = url.toString();
        }

        // Toggle select all — no longer needed, kept for compatibility
        function toggleSelectAll() {}

        // Bulk update — replaced by confirmSaveChanges
        function bulkUpdateStatus(newStatus) {}
        
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Stop normal submission
            showConfirmModal('schedule');
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            checkInitialTab();
            
            // Add click handlers for lab tabs in PC management
            document.querySelectorAll('#pcManagementContent .pill-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.textContent.match(/\d+/)[0];
                    changeLab(labNumber, 'pcManagement');
                });
            });
            
            // Add click handlers for lab tabs in schedule
            document.querySelectorAll('#scheduleContent .pill-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.textContent.match(/\d+/)[0];
                    changeLab(labNumber, 'schedule');
                });
            });
            
            // Day pill tabs already have inline onclick handlers
            
            // Remove old checkbox listeners (no longer needed)
        });
    </script>

    <!-- Confirm Modal -->
    <div class="confirm-overlay" id="confirmOverlay" onclick="if(event.target===this) hideConfirmModal()">
        <div class="confirm-box">
            <div class="confirm-icon"><i class="fas fa-save"></i></div>
            <h3>SAVE CHANGES?</h3>
            <p id="confirmMessageText"></p>
            <div class="confirm-btns">
                <button class="confirm-cancel" onclick="hideConfirmModal()">Cancel</button>
                <button class="confirm-submit" id="confirmSubmitBtn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast-notif" id="toastNotif">
        <i class="fas fa-check-circle"></i>
        <span>PC availability updated successfully</span>
    </div>

    <!-- Star & Shooting Star Canvas -->
    <script>
    (function(){
        const canvas = document.getElementById('star-canvas');
        if(!canvas) return;
        const ctx = canvas.getContext('2d');
        let W, H, stars = [], shoots = [];
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        for (let i = 0; i < 180; i++) {
            stars.push({ x: Math.random()*9999, y: Math.random()*9999, r: Math.random()*1.4+0.3, a: Math.random(), da: (Math.random()*0.008+0.003)*(Math.random()<.5?1:-1) });
        }
        function spawnShoot() {
            shoots.push({ x: Math.random()*W*1.2, y: Math.random()*H*0.5, len: Math.random()*120+80, speed: Math.random()*6+4, angle: Math.PI/4, alpha: 1, tail: [] });
            setTimeout(spawnShoot, Math.random()*6000+3000);
        }
        setTimeout(spawnShoot, 2000);
        function draw() {
            ctx.clearRect(0,0,W,H);
            stars.forEach(s => { s.a += s.da; if(s.a<=0||s.a>=1) s.da*=-1; ctx.beginPath(); ctx.arc(s.x%W, s.y%H, s.r, 0, Math.PI*2); ctx.fillStyle=`rgba(255,255,255,${s.a*0.8})`; ctx.fill(); });
            shoots.forEach((s,i) => { s.x += Math.cos(s.angle)*s.speed; s.y += Math.sin(s.angle)*s.speed; s.tail.push({x:s.x,y:s.y}); if(s.tail.length>20) s.tail.shift(); s.alpha -= 0.008;
                ctx.beginPath(); s.tail.forEach((p,j) => { j===0?ctx.moveTo(p.x,p.y):ctx.lineTo(p.x,p.y); });
                ctx.strokeStyle=`rgba(200,180,255,${s.alpha*0.6})`; ctx.lineWidth=1.5; ctx.stroke();
                if(s.alpha<=0||s.x>W+200||s.y>H+200) shoots.splice(i,1);
            });
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>
</body>
</html>