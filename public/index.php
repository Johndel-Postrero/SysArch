<?php
date_default_timezone_set('Asia/Manila');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../config/db.php';

$username = $_SESSION['login_user'];

// Fetch user session and points
$query = $conn->prepare(
    "SELECT u.session, u.firstname, u.lastname, u.course, u.idno,
            COALESCE(SUM(r.points), 0) AS total_points,
            COALESCE(SUM(r.leaderboard_score), 0) AS total_score
     FROM users u
     LEFT JOIN rewards r ON u.idno = r.idno
     WHERE u.idno = ?
     GROUP BY u.idno"
);
$query->bind_param("s", $username);
$query->execute();
$result = $query->get_result();
$user   = $result->fetch_assoc();
$query->close();

$sessionsLeft       = $user['session']      ?? 0;
$pointsAccumulated  = $user['total_points'] ?? 0;
$userFirstname      = $user['firstname']    ?? '';
$userLastname       = $user['lastname']     ?? '';
$userCourse         = $user['course']       ?? '';
$userIdno           = $user['idno']         ?? '';

// Fetch leaderboard rank for this user
$rankQuery = $conn->prepare(
    "SELECT COUNT(*) + 1 AS my_rank
     FROM (
         SELECT u2.idno, COALESCE(SUM(r2.leaderboard_score), 0) as sc
         FROM users u2
         LEFT JOIN rewards r2 ON u2.idno = r2.idno
         WHERE u2.role = 'student'
         GROUP BY u2.idno
         HAVING sc > 0
     ) ranked
     WHERE sc > ?
    "
);
$rankQuery->bind_param("d", $user['total_score']);
$rankQuery->execute();
$rankResult = $rankQuery->get_result()->fetch_assoc();
$myRank = ($user['total_score'] > 0) ? ($rankResult['my_rank'] ?? '-') : '-';
$rankQuery->close();

// Fetch the latest 4 announcements
$announcementsQuery = $conn->prepare(
    "SELECT a.announcement_id, a.title, a.description, a.created_at, a.attachment,
            (SELECT COUNT(*) FROM comments c WHERE c.announcement_id = a.announcement_id) AS comment_count
     FROM announcements a
     ORDER BY a.created_at DESC
     LIMIT 4"
);
$announcementsQuery->execute();
$announcementsResult = $announcementsQuery->get_result();
$announcements = [];
while ($row = $announcementsResult->fetch_assoc()) {
    $createdAt = new DateTime($row['created_at']);
    $now       = new DateTime();
    $interval  = $createdAt->diff($now);
    if ($interval->y > 0)      $timeAgo = $interval->y . "y ago";
    elseif ($interval->m > 0)  $timeAgo = $interval->m . "mo ago";
    elseif ($interval->d > 0)  $timeAgo = $interval->d . "d ago";
    elseif ($interval->h > 0)  $timeAgo = $interval->h . "h ago";
    elseif ($interval->i > 0)  $timeAgo = $interval->i . "m ago";
    else                        $timeAgo = "Just now";
    $announcements[] = [
        'announcement_id' => $row['announcement_id'],
        'title'           => $row['title'],
        'description'     => $row['description'],
        'timeAgo'         => $timeAgo,
        'timeFormatted'   => date('h:i A', strtotime($row['created_at'])),
        'attachment'      => $row['attachment'],
        'comment_count'   => $row['comment_count']
    ];
}
$announcementsQuery->close();

// Fetch lab usage data (last 30 days, for this student)
$labUsageQuery = $conn->prepare(
    "SELECT DATE(created_at) as sitin_date, COUNT(*) as sitin_count
     FROM sitin
     WHERE idno = ? AND time_out IS NOT NULL
     GROUP BY DATE(created_at)
     ORDER BY sitin_date DESC
     LIMIT 30"
);
$labUsageQuery->bind_param("s", $username);
$labUsageQuery->execute();
$labUsageResult = $labUsageQuery->get_result();
$labUsageData   = [];
while ($row = $labUsageResult->fetch_assoc()) {
    $labUsageData[$row['sitin_date']] = $row['sitin_count'];
}
$labUsageQuery->close();

// Fetch active sit-in status
$activeQuery = $conn->prepare(
    "SELECT lab_number, purpose, time_in FROM sitin WHERE idno = ? AND time_out IS NULL LIMIT 1"
);
$activeQuery->bind_param("s", $username);
$activeQuery->execute();
$activeSitin = $activeQuery->get_result()->fetch_assoc();
$activeQuery->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard – CCS Sit-In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Prevent page cache flashing back to old state */
        window.onpageshow = function(event) { if (event.persisted) window.location.reload(); };

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px 24px;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(139, 63, 217, 0.15);
        }
        .stat-card .icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 14px;
        }
        .stat-card .stat-value {
            font-family: var(--font-h);
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-card .stat-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-dim);
        }
        .stat-card .glow-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            border-radius: 0 0 20px 20px;
        }

        /* Dashboard specific grid */
        .dash-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        .dash-main-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 20px;
        }
        @media (max-width: 1024px) {
            .dash-main-grid { grid-template-columns: 1fr; }
        }

        /* Content cards */
        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 22px 24px;
            backdrop-filter: blur(10px);
        }
        .glass-card .card-title {
            font-family: var(--font-h);
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }

        /* Active Sit-In Banner */
        .active-banner {
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.3); }
            50%       { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        }
        .active-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
            animation: blink 1.2s infinite;
            flex-shrink: 0;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; } 50% { opacity: 0.3; }
        }

        /* Announcement cards */
        .announce-card {
            background: rgba(26, 21, 48, 0.35);
            border: 1px solid rgba(139, 63, 217, 0.12);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 12px;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        .announce-card:hover {
            transform: translateY(-2px);
            border-color: rgba(139, 63, 217, 0.35);
            background: rgba(26, 21, 48, 0.5);
            box-shadow: 0 8px 24px rgba(139, 63, 217, 0.12);
        }
        .announce-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .announce-author {
            font-family: var(--font-b);
            font-size: 14px;
            font-weight: 700;
            color: #C084FC;
        }
        .announce-role-badge {
            background: rgba(212, 135, 10, 0.12);
            border: 1px solid rgba(212, 135, 10, 0.3);
            color: #D4870A;
            border-radius: 4px;
            padding: 1px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .announce-body {
            font-size: 13px;
            color: #D1C7E0;
            line-height: 1.4;
            margin-bottom: 8px;
            word-break: break-word;
        }
        .announce-time {
            font-size: 11px;
            color: #6b7280;
            font-weight: 500;
        }

        /* Rank badge */
        .rank-display {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(212, 135, 10, 0.1), rgba(212, 135, 10, 0.03));
            border: 1px solid rgba(212, 135, 10, 0.25);
            border-radius: 14px;
            margin-top: 14px;
        }
        .rank-number {
            font-family: var(--font-h);
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, #D4870A, #E8A020);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 4px;
        }
        .rank-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'header.php'; ?>

        <div class="student-content">
            <!-- Active Sit-In Banner -->
            <?php if ($activeSitin): ?>
            <div class="active-banner">
                <div class="active-dot"></div>
                <div>
                    <span style="font-size:13px;font-weight:700;color:#10b981;">ACTIVE SIT-IN</span>
                    <span style="font-size:13px;color:#D1C7E0;margin-left:8px;">
                        Lab <?php echo htmlspecialchars($activeSitin['lab_number']); ?> •
                        <?php echo htmlspecialchars($activeSitin['purpose']); ?> •
                        In since <?php echo date('h:i A', strtotime($activeSitin['time_in'])); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="dash-grid" style="grid-template-columns: repeat(3, 1fr);">
                <!-- Sessions Left -->
                <a href="leader.php" class="stat-card block" style="text-decoration: none;">
                    <div class="icon-wrap" style="background: rgba(139, 63, 217, 0.15); color: #C084FC;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo htmlspecialchars($sessionsLeft); ?></div>
                    <div class="stat-label">Sessions Remaining</div>
                    <div class="glow-bar" style="background: linear-gradient(90deg, #8B3FD9, transparent);"></div>
                </a>

                <!-- Points Accumulated -->
                <a href="leader.php" class="stat-card block" style="text-decoration: none;">
                    <div class="icon-wrap" style="background: rgba(212, 135, 10, 0.15); color: #D4870A;">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value" style="color: #D4870A;"><?php echo number_format((float)$pointsAccumulated, 0); ?></div>
                    <div class="stat-label">Points Accumulated</div>
                    <div class="glow-bar" style="background: linear-gradient(90deg, #D4870A, transparent);"></div>
                </a>

                <!-- Leaderboard Rank -->
                <a href="leader.php?tab=leaderboard" class="stat-card block" style="text-decoration: none;">
                    <div class="icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-value" style="color: #10b981;"><?php echo $myRank !== '-' ? '#' . $myRank : '—'; ?></div>
                    <div class="stat-label">Leaderboard Rank</div>
                    <div class="glow-bar" style="background: linear-gradient(90deg, #10b981, transparent);"></div>
                </a>
            </div>

            <!-- Main Grid: Chart + Announcements -->
            <div class="dash-main-grid">
                <!-- Lab Usage Chart -->
                <div class="glass-card" style="display:flex;flex-direction:column;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                        <div class="card-title" style="margin-bottom:0;">
                            <i class="fas fa-chart-area" style="color: #8B3FD9;"></i> MY LAB ACTIVITY
                        </div>
                        <select id="timeRange" class="dark-select" style="padding: 6px 30px 6px 12px; border-radius: 8px; font-size: 12px;">
                            <option value="7">Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30">Last 30 Days</option>
                        </select>
                    </div>
                    <div style="flex:1;min-height:220px;position:relative;">
                        <canvas id="labUsageChart"></canvas>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="glass-card" style="display:flex;flex-direction:column;overflow:hidden;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <div class="card-title" style="margin-bottom:0;">
                            <i class="fas fa-bullhorn" style="color: #D4870A;"></i> ANNOUNCEMENTS
                        </div>
                        <a href="announcement.php" style="font-size:11px;color:#8B3FD9;text-decoration:none;font-weight:600;transition:color 0.2s;" onmouseover="this.style.color='#C084FC'" onmouseout="this.style.color='#8B3FD9'">View All →</a>
                    </div>
                    <div style="flex:1;overflow-y:auto;">
                        <?php if (!empty($announcements)): ?>
                            <?php foreach ($announcements as $ann): ?>
                            <a href="announcement.php" style="text-decoration:none; display:block;">
                                <div class="announce-card">
                                    <div class="announce-header">
                                        <span class="announce-author">Admin</span>
                                        <?php if (!empty($ann['attachment'])): ?>
                                        <span class="announce-file-indicator" title="Has attachment" style="color: #D4870A; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; background: rgba(212, 135, 10, 0.12); border: 1px solid rgba(212, 135, 10, 0.3); padding: 2px 6px; border-radius: 4px;">
                                            <i class="fas fa-paperclip"></i> <span style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">File</span>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="announce-body">
                                        <?php echo nl2br(htmlspecialchars($ann['description'])); ?>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div class="announce-time">
                                            <?php echo htmlspecialchars($ann['timeFormatted']); ?>
                                        </div>
                                        <div class="announce-comments-count" style="color: #9A8FB0; font-size: 11px; display: inline-flex; align-items: center; gap: 6px;" title="Comments">
                                            <i class="far fa-comment"></i>
                                            <span style="font-weight: 600;"><?php echo intval($ann['comment_count']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;padding:40px 0;color:var(--text-dim);">
                                <i class="fas fa-bullhorn" style="font-size:28px;opacity:0.3;margin-bottom:8px;display:block;"></i>
                                <p style="font-size:13px;">No announcements yet</p>
                            </div>
                        <?php endif; ?>
                    </div>


                </div>
            </div>
        </div><!-- end .student-content -->
    </div><!-- end .main-wrapper -->

    <!-- Star Canvas Animation -->
    <script>
    (function() {
        const canvas = document.getElementById('star-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let W, H, stars = [], shoots = [];

        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize);
        resize();

        for (let i = 0; i < 140; i++) {
            stars.push({ x: Math.random()*9999, y: Math.random()*9999, r: Math.random()*1.2+0.3,
                         a: Math.random(), da: (Math.random()*0.004+0.001) * (Math.random()<.5?1:-1) });
        }
        function spawnShoot() {
            shoots.push({ x: Math.random()*W*1.2, y: Math.random()*H*0.5,
                          len: Math.random()*100+50, speed: Math.random()*5+3,
                          angle: Math.PI/4, alpha: 1 });
        }
        setInterval(spawnShoot, 3500);
        function draw() {
            ctx.clearRect(0,0,W,H);
            stars.forEach(s => {
                s.a += s.da;
                if (s.a<=0||s.a>=1) s.da*=-1;
                ctx.beginPath();
                ctx.arc(s.x%W, s.y%H, s.r, 0, Math.PI*2);
                ctx.fillStyle = `rgba(200,180,255,${s.a.toFixed(2)})`;
                ctx.fill();
            });
            shoots.forEach((s,i) => {
                s.x += Math.cos(s.angle)*s.speed;
                s.y += Math.sin(s.angle)*s.speed;
                s.alpha -= 0.015;
                const g = ctx.createLinearGradient(
                    s.x-Math.cos(s.angle)*s.len, s.y-Math.sin(s.angle)*s.len, s.x, s.y);
                g.addColorStop(0,`rgba(212,135,10,0)`);
                g.addColorStop(1,`rgba(200,160,255,${s.alpha.toFixed(2)})`);
                ctx.beginPath();
                ctx.moveTo(s.x-Math.cos(s.angle)*s.len, s.y-Math.sin(s.angle)*s.len);
                ctx.lineTo(s.x, s.y);
                ctx.strokeStyle = g; ctx.lineWidth = 1; ctx.stroke();
                if (s.alpha<=0) shoots.splice(i,1);
            });
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>

    <!-- Lab Usage Chart -->
    <script>
    const labUsageData = <?php echo json_encode($labUsageData); ?>;

    function prepareChartData(range) {
        const labels = [], data = [];
        const today = new Date();
        for (let i = range - 1; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            const formatted = date.toISOString().split('T')[0];
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            data.push(labUsageData[formatted] ? parseInt(labUsageData[formatted]) : 0);
        }
        return { labels, data };
    }

    const ctxChart = document.getElementById('labUsageChart').getContext('2d');
    const gradient = ctxChart.createLinearGradient(0, 0, 0, 250);
    gradient.addColorStop(0, 'rgba(139, 63, 217, 0.35)');
    gradient.addColorStop(1, 'rgba(139, 63, 217, 0)');

    let labChart = new Chart(ctxChart, {
        type: 'line',
        data: {
            labels: prepareChartData(7).labels,
            datasets: [{
                label: 'Sit-ins',
                data:  prepareChartData(7).data,
                borderColor: '#8B3FD9',
                borderWidth: 2.5,
                pointBackgroundColor: '#8B3FD9',
                pointBorderColor: '#0D0B1A',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: true,
                backgroundColor: gradient,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(22,19,38,0.95)',
                    borderColor: 'rgba(139,63,217,0.4)',
                    borderWidth: 1,
                    titleColor: '#C084FC',
                    bodyColor: '#D1C7E0',
                    callbacks: { label: ctx => ` ${ctx.raw} sit-in${ctx.raw !== 1 ? 's' : ''}` }
                }
            },
            scales: {
                y: {
                    min: 0,
                    suggestedMax: 3,
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: { color: '#9A8FB0', font: { size: 11 }, precision: 0, stepSize: 1 },
                    border: { color: 'transparent' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#9A8FB0', font: { size: 11 } },
                    border: { color: 'transparent' }
                }
            }
        }
    });

    document.getElementById('timeRange').addEventListener('change', function() {
        const range = parseInt(this.value);
        const { labels, data } = prepareChartData(range);
        labChart.data.labels = labels;
        labChart.data.datasets[0].data = data;
        labChart.update();
    });
    </script>
</body>
</html>