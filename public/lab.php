<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../config/db.php';

// Get all labs
$labs = [524, 526, 528, 530, 542, 544];
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get current lab from query parameters
$current_lab = $_GET['lab'] ?? $labs[0];
if (!in_array($current_lab, $labs)) {
    $current_lab = $labs[0];
}

// --- TAB 1: COMPUTER AVAILABILITY ---
$pcs = [];
for ($i = 1; $i <= 50; $i++) {
    $pcs[$i] = 'available';
}

// 1. Check lab_pcs table for unavailable PCs
$stmt1 = $conn->prepare("SELECT pc_number, status FROM lab_pcs WHERE lab_number = ? AND pc_number <= 50");
if ($stmt1) {
    $stmt1->bind_param("i", $current_lab);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($row = $res1->fetch_assoc()) {
        $p = intval($row['pc_number']);
        if ($p >= 1 && $p <= 50) {
            if ($row['status'] === 'unavailable') {
                $pcs[$p] = 'unavailable';
            }
        }
    }
    $stmt1->close();
}

// 2. Check active sit-ins and reservations currently sitting in (matching admin logic perfectly)
$stmt2 = $conn->prepare("
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
if ($stmt2) {
    $stmt2->bind_param("i", $current_lab);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $p = intval($row['pc_num']);
        if ($p >= 1 && $p <= 50) {
            $pcs[$p] = 'sitined';
        }
    }
    $stmt2->close();
}

$available_count = 0;
$sitined_count = 0;
$unavailable_count = 0;
for ($i = 1; $i <= 50; $i++) {
    if ($pcs[$i] === 'available') $available_count++;
    elseif ($pcs[$i] === 'sitined') $sitined_count++;
    elseif ($pcs[$i] === 'unavailable') $unavailable_count++;
}


// --- TAB 2: LAB SCHEDULE ---
// Get schedule for the selected lab
$schedule_query = $conn->prepare("SELECT * FROM lab_schedules WHERE lab_number = ? ORDER BY day_of_week, start_time");
$schedule_query->bind_param("i", $current_lab);
$schedule_query->execute();
$schedule_result = $schedule_query->get_result();
$all_schedules = $schedule_result->fetch_all(MYSQLI_ASSOC);
$schedule_query->close();

// Organize schedules by day and time
$organized_schedules = [];
foreach ($all_schedules as $schedule) {
    $organized_schedules[$schedule['day_of_week']][$schedule['start_time']] = [
        'end_time' => $schedule['end_time'],
        'status' => $schedule['status'],
        'notes' => $schedule['notes']
    ];
}

// Generate time slots
$time_slots = [];
$start = strtotime('7:30 AM');
$end = strtotime('8:00 PM');
$interval = 30 * 60; // 30 minutes in seconds

for ($time = $start; $time <= $end; $time += $interval) {
    $time_slots[] = date('H:i', $time);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer and Lab – CCS Sit-In</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .lab-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            backdrop-filter: blur(10px);
            width: 100%;
            display: flex;
            flex-direction: column;
            overflow: visible;
        }
        /* Lab Pill Tabs */
        .pill-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .pill-tab {
            padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: all 0.3s; border: 1px solid rgba(139,63,217,0.15);
            background: rgba(255,255,255,0.03); color: #9A8FB0;
        }
        .pill-tab.active { background: rgba(139,63,217,0.15); color: #C084FC; border-color: rgba(139,63,217,0.4); }
        .pill-tab:hover:not(.active) { background: rgba(139,63,217,0.08); color: #D1C7E0; }
        .table-container {
            flex: 1;
            overflow: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(13, 11, 26, 0.4);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--font-b);
        }
        th {
            position: sticky;
            top: 0;
            background: #141124;
            color: var(--purple-light);
            font-family: var(--font-h);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 14px 16px;
            border-bottom: 2px solid var(--border);
            z-index: 10;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 13px;
            color: var(--text-body);
            text-align: center;
        }
        tr:hover td {
            background: rgba(139, 63, 217, 0.04);
        }
        .time-cell {
            font-weight: 600;
            color: var(--gold);
            text-align: left !important;
            border-right: 1px solid rgba(255, 255, 255, 0.04);
            background: rgba(13, 11, 26, 0.2);
            white-space: nowrap;
        }
        .status-cell {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .status-available {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .status-unavailable {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        /* Dual Main Tabs Styles */
        .analytics-tabs {
            display: flex;
            border-bottom: 1px solid rgba(139, 63, 217, 0.15);
            margin-bottom: 24px;
            gap: 30px;
        }
        .analytics-tab-btn {
            background: none;
            border: none;
            color: var(--text-dim);
            font-family: var(--font-b);
            font-size: 15px;
            font-weight: 600;
            padding: 0 10px 15px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .analytics-tab-btn:hover {
            color: #fff;
        }
        .analytics-tab-btn.active {
            color: #fff;
        }
        .analytics-tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--purple-glow), var(--purple-light));
            border-radius: 3px 3px 0 0;
            box-shadow: 0 -2px 10px rgba(139, 63, 217, 0.5);
        }

        /* Tab Content Display */
        .main-tab-content {
            display: none;
        }
        .main-tab-content.active {
            display: block;
        }

        /* PC Grid Styles */
        .pc-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 10px; margin-top: 16px; }
        .pc-item {
            background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.06);
            padding: 14px 8px; text-align: center; border-radius: 12px;
            cursor: default; transition: all 0.3s; font-size: 13px; color: #D1C7E0;
            display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
            user-select: none; min-height: 70px;
        }
        .pc-item.available { background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.3); }
        .pc-item.unavailable { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.3); }
        .pc-item.sitinned { background: rgba(234,179,8,0.08); border-color: rgba(234,179,8,0.4); }
        .pc-item .pc-label { font-weight: 700; font-size: 14px; color: #fff; }
        .pc-status-badge {
            display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;
        }
        .pc-status-badge.available { background: rgba(16,185,129,0.15); color: #10b981; }
        .pc-status-badge.unavailable { background: rgba(239,68,68,0.15); color: #ef4444; }
        .pc-status-badge.sitinned { background: rgba(234,179,8,0.15); color: #eab308; }

        @media (max-width: 1024px) {
            .pc-grid { grid-template-columns: repeat(5, 1fr); }
        }
        @media (max-width: 640px) {
            .pc-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* Stats Row inside tab */
        .availability-stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .avail-stat-card {
            flex: 1;
            min-width: 150px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avail-stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        /* Beautiful Custom Scrollbars */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(13, 11, 26, 0.4);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8B3FD9 0%, #C084FC 100%);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #C084FC 0%, #eab308 100%);
            box-shadow: 0 0 10px rgba(139, 63, 217, 0.5);
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        
        <div class="student-content">
            <div class="lab-card">
                <!-- Lab Tabs Selection -->
                <div class="lab-section" style="border: none; padding: 0; background: transparent; margin-bottom: 20px;">
                    <h3 style="font-family: var(--font-h); font-size: 15px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: #fff;">
                        <i class="fas fa-server" style="color:#C084FC; margin-right:8px;"></i> Select Laboratory
                    </h3>
                    <div class="pill-tabs">
                        <?php foreach ($labs as $lab): ?>
                            <div class="pill-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>" data-lab="<?php echo $lab; ?>">
                                Lab <?php echo $lab; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Dual Main Tabs -->
                <div class="analytics-tabs">
                    <button id="availTabBtn" class="analytics-tab-btn active" onclick="switchMainTab('avail')">
                        <i class="fas fa-desktop"></i>
                        <span>Computer Availability</span>
                    </button>
                    <button id="schedTabBtn" class="analytics-tab-btn" onclick="switchMainTab('sched')">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Labsched</span>
                    </button>
                </div>

                <!-- TAB 1: COMPUTER AVAILABILITY -->
                <div id="availContent" class="main-tab-content active">
                    <!-- Stats summary cards -->
                    <div class="availability-stats">
                        <div class="avail-stat-card">
                            <div class="avail-stat-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-dim); font-weight: 600; text-transform: uppercase;">Available</div>
                                <div style="font-size: 18px; color: #fff; font-weight: 700; font-family: var(--font-h);"><?php echo $available_count; ?> <span style="font-size: 12px; color: var(--text-dim); font-weight: 500;">/ 50</span></div>
                            </div>
                        </div>
                        <div class="avail-stat-card">
                            <div class="avail-stat-icon" style="background: rgba(234, 179, 8, 0.12); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.2);">
                                <i class="fas fa-user-lock"></i>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-dim); font-weight: 600; text-transform: uppercase;">Sit-Inned</div>
                                <div style="font-size: 18px; color: #fff; font-weight: 700; font-family: var(--font-h);"><?php echo $sitined_count; ?> <span style="font-size: 12px; color: var(--text-dim); font-weight: 500;">/ 50</span></div>
                            </div>
                        </div>
                        <div class="avail-stat-card">
                            <div class="avail-stat-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2);">
                                <i class="fas fa-ban"></i>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--text-dim); font-weight: 600; text-transform: uppercase;">Unavailable</div>
                                <div style="font-size: 18px; color: #fff; font-weight: 700; font-family: var(--font-h);"><?php echo $unavailable_count; ?> <span style="font-size: 12px; color: var(--text-dim); font-weight: 500;">/ 50</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- PC Grid layout -->
                    <div style="background: rgba(0, 0, 0, 0.2); border: 1px solid var(--border); border-radius: 16px; padding: 20px;">
                        <h3 style="font-family: var(--font-h); font-size: 13px; font-weight: 700; color: #fff; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-desktop" style="color: var(--purple-light);"></i> PC Grid – Lab <?php echo $current_lab; ?>
                        </h3>
                        <div class="pc-grid">
                            <?php for ($i = 1; $i <= 50; $i++): 
                                $status = $pcs[$i]; // 'available', 'sitined', 'unavailable'
                                $status_class = ($status === 'sitined') ? 'sitinned' : $status;
                                $status_label = ($status === 'sitined') ? 'Sitinned' : ucfirst($status);
                            ?>
                                <div class="pc-item <?php echo $status_class; ?>" title="PC <?php echo $i; ?> is <?php echo $status_label; ?>">
                                    <span class="pc-label">PC <?php echo $i; ?></span>
                                    <div class="pc-status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_label; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LAB SCHEDULE (LABSCHED) -->
                <div id="schedContent" class="main-tab-content">
                    <!-- Title for Active Lab -->
                    <div style="margin-bottom:16px;">
                        <h3 style="font-family:var(--font-h); font-size:16px; font-weight:700; color:var(--purple-light); display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-calendar-alt"></i> Weekly Timetable for Lab <?php echo $current_lab; ?>
                        </h3>
                    </div>

                    <!-- Schedule Table -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="time-cell">Time</th>
                                    <?php foreach ($days_of_week as $day): ?>
                                        <th><?php echo $day; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($time_slots as $time): ?>
                                    <tr>
                                        <td class="time-cell">
                                            <i class="far fa-clock" style="margin-right:6px; opacity:0.6;"></i>
                                            <?php echo date('g:i A', strtotime($time)); ?>
                                        </td>
                                        <?php foreach ($days_of_week as $day): 
                                            $status = 'available';
                                            $notes = '';
                                            
                                            // Check if this time slot is covered by any schedule
                                            foreach ($organized_schedules[$day] ?? [] as $start_t => $schedule) {
                                                if ($time >= $start_t && $time < $schedule['end_time']) {
                                                    $status = $schedule['status'];
                                                    $notes = $schedule['notes'];
                                                    break;
                                                }
                                            }
                                        ?>
                                            <td>
                                                <div class="status-cell <?php echo 'status-' . $status; ?>" 
                                                     title="<?php echo htmlspecialchars($notes); ?>">
                                                    <i class="fas <?php echo $status === 'available' ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                                                    <?php echo ucfirst($status); ?>
                                                    <?php if (!empty($notes)): ?>
                                                        <i class="fas fa-info-circle ml-1" style="font-size:10px; opacity:0.8;"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Switch between Availability and Labsched tabs
        function switchMainTab(tabName) {
            document.querySelectorAll('.main-tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.analytics-tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabName + 'Content').classList.add('active');
            document.getElementById(tabName + 'TabBtn').classList.add('active');
            
            localStorage.setItem('activeLabTab', tabName);
        }

        // Change lab
        function changeLab(labNumber) {
            const url = new URL(window.location.href);
            url.searchParams.set('lab', labNumber);
            window.location.href = url.toString();
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Restore last active main tab
            const lastTab = localStorage.getItem('activeLabTab') || 'avail';
            switchMainTab(lastTab);

            // Add click handlers for lab tabs
            document.querySelectorAll('.pill-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.getAttribute('data-lab');
                    changeLab(labNumber);
                });
            });
        });
    </script>
    <script>
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();
    </script>
</body>
</html>