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

$query = "SELECT 
            u.idno, 
            u.firstname, 
            u.lastname, 
            u.profile_picture,
            u.course,
            SEC_TO_TIME(SUM(TIME_TO_SEC(TIMEDIFF(s.time_out, s.time_in)))) AS total_time,
            SUM(TIME_TO_SEC(TIMEDIFF(s.time_out, s.time_in))) AS total_seconds
          FROM users u
          JOIN sitin s ON u.idno = s.idno
          WHERE s.time_out IS NOT NULL
          GROUP BY u.idno
          ORDER BY total_seconds DESC
          LIMIT 10";

$result = $conn->query($query);
$topUsers = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $topUsers[] = $row;
    }
}

function getInitials($firstname, $lastname) {
    return strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Leaderboard</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        header {
            z-index: 1;
        }
        .sidebar {
            width: 5rem;
            transition: all 0.3s ease-in-out;
        }
        .sidebar:hover {
            width: 16rem;
        }
        .sidebar:hover .sidebar-text {
            display: inline;
        }
        .sidebar-text {
            display: none;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .sidebar:hover a {
            justify-content: flex-start;
        }
        .sidebar i {
            font-size: 1.5rem;
        }
        .main-content {
            margin-left: 5rem;
            transition: margin-left 0.3s ease-in-out;
        }
        .sidebar:hover + .main-content {
            margin-left: 16rem;
        }
        
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            margin: 0;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4f0fb 100%);
        }
        
        /* Animated background for the leaderboard */
        .leaderboard-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4f0fb 100%);
            overflow: hidden;
        }
        
        .leaderboard-bg::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="rgba(79, 70, 229, 0.03)" d="M0,0 L100,0 L100,100 L0,100 Z"></path></svg>');
            animation: rotate 120s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Floating confetti elements */
        .confetti {
            position: absolute;
            width: 15px;
            height: 15px;
            opacity: 0;
            animation: confetti-fall 5s linear infinite;
        }
        
        @keyframes confetti-fall {
            0% { transform: translateY(-100px) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        
        /* Header with game-like styling */
        .leaderboard-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .leaderboard-title {
            font-size: 3rem;
            font-weight: 800;
            color: #4F46E5;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            text-shadow: 3px 3px 0 rgba(0,0,0,0.1);
            position: relative;
            display: inline-block;
        }
        
        .leaderboard-title::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #4F46E5, #A855F7, #4F46E5);
            border-radius: 5px;
        }
        
        .leaderboard-subtitle {
            font-size: 1.2rem;
            color: #666;
            font-weight: 600;
        }
        
        /* Updated podium styles */
        .podium-container {
            margin-bottom: 10px;
        }
        
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            height: 300px;
            gap: 20px;
        }
        
        .podium-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 180px;
        }
        
        .podium-1, .podium-2, .podium-3 {
            position: relative;
            width: 100%;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .podium-1 {
            height: 240px;
            background: linear-gradient(to bottom, #FFD700, #FFC600);
        }
        
        .podium-2 {
            height: 200px;
            background: linear-gradient(to bottom, #C0C0C0, #B0B0B0);
        }
        
        .podium-3 {
            height: 160px;
            background: linear-gradient(to bottom, #CD7F32, #B87333);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        /* Align names horizontally */
        .podium-names {
            display: flex;
            justify-content: center;
            gap: 20px;
            width: 100%;
            margin-top: 20px;
        }
        
        .podium-name-container {
            width: 180px;
            text-align: center;
        }
        
        .podium-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #4F46E5;
            font-size: 1.1rem;
        }
        
        .podium-time {
            font-size: 1rem;
            color: #666;
            font-weight: 600;
        }
        
        /* Adjust avatar positioning */
        .podium-avatar-container {
            position: absolute;
            top: -50px;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .podium-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4F46E5, #A855F7);
            color: white;
            font-weight: bold;
            font-size: 28px;
            z-index: 10;
            transition: all 0.3s ease;
        }
        
        .podium-number {
            font-size: 3rem;
            font-weight: bold;
            color: white;
            position: absolute;
            top: -70px;
            width: 100%;
            text-align: center;
            text-shadow: 2px 2px 0 rgba(0,0,0,0.2);
            z-index: 2;
        }
        
        /* Crown for 1st place */
        .crown {
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2.5rem;
            color: #FFD700;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
            z-index: 5;
            animation: float 2s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-10px); }
        }
        
        /* Leaderboard list with individual cards */
        .leaderboard-list {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .leaderboard-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(79, 70, 229, 0.1);
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .leaderboard-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            border-color: rgba(79, 70, 229, 0.3);
        }
        
        .leaderboard-item::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #4F46E5, #A855F7);
        }
        
        .rank {
            font-size: 1.5rem;
            font-weight: bold;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            margin-right: 15px;
            flex-shrink: 0;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .rank-1 {
            background: linear-gradient(135deg, #FFD700, #FFC600);
        }
        
        .rank-2 {
            background: linear-gradient(135deg, #C0C0C0, #B0B0B0);
        }
        
        .rank-3 {
            background: linear-gradient(135deg, #CD7F32, #B87333);
        }
        
        .rank-4, .rank-5, .rank-6, .rank-7, .rank-8, .rank-9, .rank-10 {
            background: linear-gradient(135deg, #4F46E5, #A855F7);
        }
        
        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #4F46E5, #A855F7);
            color: white;
            font-weight: bold;
            font-size: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .user-info {
            flex-grow: 1;
        }
        
        .user-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #4F46E5;
            font-size: 1.1rem;
        }
        
        .user-course {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .time-spent {
            font-weight: bold;
            color: #4F46E5;
            font-size: 1.1rem;
            text-align: right;
        }
        
        .time-spent::before {
            content: "⏱️ ";
        }
        
        .progress-bar {
            height: 8px;
            background: #eee;
            border-radius: 4px;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4F46E5, #A855F7);
            border-radius: 4px;
        }
        
        /* Badges for top performers */
        .badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #FFD700;
            color: #333;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .no-data {
            text-align: center;
            padding: 40px 0;
            color: #666;
        }
        
        /* Trophy animation */
        @keyframes shine {
            0% { filter: brightness(1); }
            50% { filter: brightness(1.2); }
            100% { filter: brightness(1); }
        }
        
        .trophy-icon {
            animation: shine 2s infinite;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .podium {
                flex-direction: column;
                align-items: center;
                height: auto;
                gap: 80px;
            }
            
            .podium-step {
                width: 120px;
            }
            
            .podium-names {
                flex-direction: column;
                align-items: center;
                gap: 40px;
            }
            
            .leaderboard-list {
                padding: 0 10px;
            }
            
            .leaderboard-item {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            .rank, .avatar {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .time-spent {
                text-align: center;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="leaderboard-bg"></div>
    
    <!-- Generate confetti elements -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#4F46E5', '#A855F7', '#FFD700', '#C0C0C0', '#CD7F32'];
            const container = document.querySelector('.leaderboard-bg');
            
            for (let i = 0; i < 30; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.animationDelay = Math.random() * 5 + 's';
                confetti.style.animationDuration = 3 + Math.random() * 7 + 's';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.width = 10 + Math.random() * 10 + 'px';
                confetti.style.height = 10 + Math.random() * 10 + 'px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                container.appendChild(confetti);
            }
        });
    </script>

    <div class="flex h-screen">
        <?php include 'sidebarad.php'; ?>

        <div class="main-content flex-1 flex flex-col">
            <?php include 'headerad.php'; ?>
            
            <div class="flex-1 overflow-auto">
                <div class="leaderboard-container p-6">
                    <div class="leaderboard-header">
                        <h1 class="leaderboard-title">Lab Champions</h1>
                        <p class="leaderboard-subtitle">Top performers based on dedication and time spent</p>
                    </div>
                    
                    <?php if (!empty($topUsers)): ?>
                        <div class="podium-container">
                            <div class="podium">
                                <!-- 2nd place -->
                                <div class="podium-step">
                                    <div class="podium-avatar-container">
                                        <?php if (isset($topUsers[1])): ?>
                                            <?php if ($topUsers[1]['profile_picture'] && $topUsers[1]['profile_picture'] != 'default-profile.png'): ?>
                                                <img src="upload/<?php echo htmlspecialchars($topUsers[1]['profile_picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($topUsers[1]['firstname']); ?>" 
                                                     class="podium-avatar">
                                            <?php else: ?>
                                                <div class="podium-avatar">
                                                    <?php echo getInitials($topUsers[1]['firstname'], $topUsers[1]['lastname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-2"></div>
                                    <div class="podium-number">2</div>
                                </div>
                                
                                <!-- 1st place -->
                                <div class="podium-step">
                                    <div class="podium-avatar-container">
                                        <div class="crown trophy-icon">👑</div>
                                        <?php if (isset($topUsers[0])): ?>
                                            <?php if ($topUsers[0]['profile_picture'] && $topUsers[0]['profile_picture'] != 'default-profile.png'): ?>
                                                <img src="upload/<?php echo htmlspecialchars($topUsers[0]['profile_picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($topUsers[0]['firstname']); ?>" 
                                                     class="podium-avatar">
                                            <?php else: ?>
                                                <div class="podium-avatar">
                                                    <?php echo getInitials($topUsers[0]['firstname'], $topUsers[0]['lastname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-1"></div>
                                    <div class="podium-number">1</div>
                                </div>
                                
                                <!-- 3rd place -->
                                <div class="podium-step">
                                    <div class="podium-avatar-container">
                                        <?php if (isset($topUsers[2])): ?>
                                            <?php if ($topUsers[2]['profile_picture'] && $topUsers[2]['profile_picture'] != 'default-profile.png'): ?>
                                                <img src="upload/<?php echo htmlspecialchars($topUsers[2]['profile_picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($topUsers[2]['firstname']); ?>" 
                                                     class="podium-avatar">
                                            <?php else: ?>
                                                <div class="podium-avatar">
                                                    <?php echo getInitials($topUsers[2]['firstname'], $topUsers[2]['lastname']); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="podium-3"></div>
                                    <div class="podium-number">3</div>
                                </div>
                            </div>
                            
                            <!-- Names aligned horizontally below the podium -->
                            <div class="podium-names">
                                <div class="podium-name-container">
                                    <?php if (isset($topUsers[1])): ?>
                                        <div class="podium-name"><?php echo htmlspecialchars($topUsers[1]['firstname'] . ' ' . $topUsers[1]['lastname']); ?></div>
                                        <div class="podium-time"><?php echo htmlspecialchars($topUsers[1]['total_time']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="podium-name-container">
                                    <?php if (isset($topUsers[0])): ?>
                                        <div class="podium-name"><?php echo htmlspecialchars($topUsers[0]['firstname'] . ' ' . $topUsers[0]['lastname']); ?></div>
                                        <div class="podium-time"><?php echo htmlspecialchars($topUsers[0]['total_time']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="podium-name-container">
                                    <?php if (isset($topUsers[2])): ?>
                                        <div class="podium-name"><?php echo htmlspecialchars($topUsers[2]['firstname'] . ' ' . $topUsers[2]['lastname']); ?></div>
                                        <div class="podium-time"><?php echo htmlspecialchars($topUsers[2]['total_time']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="leaderboard-list">
                            <?php 
                            // Find max time for progress bars
                            $maxTime = !empty($topUsers) ? $topUsers[0]['total_seconds'] : 0;
                            ?>
                            
                            <?php foreach ($topUsers as $index => $user): ?>
                                <?php if ($index >= 3): ?>
                                    <div class="leaderboard-item">
                                        <div class="rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                                        <?php if ($user['profile_picture'] && $user['profile_picture'] != 'default-profile.png'): ?>
                                            <img src="upload/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                                 alt="<?php echo htmlspecialchars($user['firstname']); ?>" 
                                                 class="avatar">
                                        <?php else: ?>
                                            <div class="avatar">
                                                <?php echo getInitials($user['firstname'], $user['lastname']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="user-info">
                                            <div class="user-name"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></div>
                                            <div class="user-course"><?php echo htmlspecialchars($user['course']); ?></div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo ($user['total_seconds'] / $maxTime) * 100; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="time-spent"><?php echo htmlspecialchars($user['total_time']); ?></div>
                                        <?php if ($index < 6): ?>
                                            <div class="badge">Top Performer</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-trophy text-4xl mb-4 text-gray-400"></i>
                            <p class="text-xl">No champions yet!</p>
                            <p class="text-gray-500">Be the first to log your lab hours and appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // GSAP animations for podium entries
        document.addEventListener('DOMContentLoaded', function() {
            gsap.from(".podium-step", {
                duration: 1,
                y: 50,
                opacity: 0,
                stagger: 0.2,
                ease: "back.out(1.7)"
            });
            
            gsap.from(".podium-names", {
                duration: 0.8,
                y: 30,
                opacity: 0,
                delay: 0.5,
                ease: "power2.out"
            });
            
            gsap.from(".leaderboard-item", {
                duration: 0.8,
                y: 30,
                stagger: 0.1,
                delay: 0.8,
                ease: "power2.out"
            });
            
            // Add hover effect to podium avatars
            const podiumAvatars = document.querySelectorAll('.podium-avatar');
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