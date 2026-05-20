<style>
        :root {
            --bg-sidebar: #0D0B1A;
            --purple-glow: #8B3FD9;
            --purple-hover: rgba(139, 63, 217, 0.15);
            --text-main: #ffffff;
            --text-dim: #9A8FB0;
            --font-h: 'Orbitron', sans-serif;
            --font-b: 'Inter', sans-serif;
        }

        /* Prevent transitions during page load */
        .preload-transitions * {
            transition: none !important;
        }
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            border-right: 1px solid rgba(139, 63, 217, 0.2);
            display: flex;
            flex-direction: column;
            padding: 24px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Minimized State applied to body */
        body.sidebar-minimized .sidebar {
            width: 80px;
            padding: 24px 15px;
        }

        body.sidebar-minimized .sidebar .logo-text, 
        body.sidebar-minimized .sidebar .nav-item span, 
        body.sidebar-minimized .sidebar .sub-nav-item span,
        body.sidebar-minimized .sidebar .user-info, 
        body.sidebar-minimized .sidebar .logout-btn span,
        body.sidebar-minimized .sidebar .nav-dropdown i.fa-chevron-right { 
            display: none; 
        }

        body.sidebar-minimized .sidebar .logo-section {
            padding-bottom: 30px;
            justify-content: center;
        }

        body.sidebar-minimized .sidebar .nav-item {
            justify-content: center;
            padding: 14px;
        }

        body.sidebar-minimized .sidebar .user-profile {
            justify-content: center;
        }

        body.sidebar-minimized .nav-dropdown.active .dropdown-content,
        body.sidebar-minimized .sidebar .dropdown-content {
            display: none !important;
        }

        body.sidebar-minimized .sidebar .nav-item .flex {
            gap: 0 !important;
            justify-content: center;
        }

        body.sidebar-minimized .sub-nav-item {
            justify-content: center;
            padding: 14px;
        }

        /* Body Responsive Margin */
        .main-wrapper {
            margin-left: 280px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-minimized .main-wrapper {
            margin-left: 80px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 40px;
            position: relative;
        }

        .logo-box {
            width: 42px;
            height: 42px;
            background: rgba(139, 63, 217, 0.15);
            border: 1px solid rgba(139, 63, 217, 0.4);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.4);
            flex-shrink: 0;
        }

        .logo-text h2 {
            font-family: var(--font-h);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .logo-text p {
            color: var(--text-dim);
            font-size: 10px;
            margin: 0;
            letter-spacing: 1px;
        }

        /* Minimize Button */
        .toggle-sidebar {
            position: absolute;
            right: -14px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            background: var(--purple-glow);
            border: 2px solid var(--bg-sidebar);
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.6);
            z-index: 1001;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-minimized .toggle-sidebar {
            transform: translateY(-50%) rotate(180deg);
        }

        .nav-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 18px;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            border-left: 4px solid transparent;
        }

        .nav-item i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .nav-item:hover {
            color: #fff;
            background: var(--purple-hover);
        }

        .nav-item.active {
            color: #fff;
            background: linear-gradient(90deg, rgba(139, 63, 217, 0.2) 0%, rgba(139, 63, 217, 0.05) 100%);
            border-left: 4px solid var(--purple-glow);
            box-shadow: -10px 0 20px -5px rgba(139, 63, 217, 0.3);
        }

        .nav-item.active i {
            color: var(--purple-glow);
            text-shadow: 0 0 10px rgba(139, 63, 217, 0.5);
        }

        /* Dropdown Logic (for Communication) */
        .nav-dropdown {
            position: relative;
        }

        .nav-dropdown .dropdown-trigger {
            cursor: pointer;
            justify-content: space-between;
        }

        /* When minimized, the dropdown trigger should look like a regular centered nav icon */
        body.sidebar-minimized .nav-dropdown .dropdown-trigger {
            justify-content: center;
            padding: 14px;
        }

        body.sidebar-minimized .nav-dropdown .dropdown-trigger .flex {
            justify-content: center;
            gap: 0;
        }

        .dropdown-content {
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease-out;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0 0 12px 12px;
            margin-top: -8px;
            padding-left: 20px;
        }

        .nav-dropdown.active .dropdown-content {
            max-height: 200px;
            padding-top: 12px;
            padding-bottom: 12px;
            margin-top: 4px;
        }

        .nav-dropdown.active .fa-chevron-right {
            transform: rotate(90deg);
        }

        .sub-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            color: var(--text-dim);
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sub-nav-item:hover {
            color: var(--purple-glow);
        }

        /* User Section */
        .user-section {
            padding-top: 24px;
            margin-top: auto;
            border-top: 1px solid rgba(139, 63, 217, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--purple-glow);
            padding: 2px;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-info h4 {
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            margin: 0;
        }

        .user-info p {
            color: var(--text-dim);
            font-size: 11px;
            margin: 0;
        }

        .logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: #ef4444;
            color: #fff;
        }

        /* --- Global Scrollbar Limitation Overrides --- */
        body {
            height: 100vh !important;
            overflow: hidden !important;
        }
        
        .main-wrapper {
            height: 100vh !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        .admin-header {
            flex-shrink: 0 !important;
        }
        
        /* Core scrollable content divs in all pages */
        .dashboard-content, 
        .student-content, 
        .analytics-content,
        .main-content-scroll {
            flex: 1 !important;
            overflow-y: auto !important;
            position: relative;
            padding-bottom: 40px !important;
        }
        
        /* Custom scrollbars inside scrollable content containers */
        .dashboard-content::-webkit-scrollbar,
        .student-content::-webkit-scrollbar,
        .analytics-content::-webkit-scrollbar,
        .main-content-scroll::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .dashboard-content::-webkit-scrollbar-track,
        .student-content::-webkit-scrollbar-track,
        .analytics-content::-webkit-scrollbar-track,
        .main-content-scroll::-webkit-scrollbar-track {
            background: rgba(15, 10, 25, 0.4);
        }
        .dashboard-content::-webkit-scrollbar-thumb,
        .student-content::-webkit-scrollbar-thumb,
        .analytics-content::-webkit-scrollbar-thumb,
        .main-content-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8B3FD9 0%, #C084FC 100%);
            border-radius: 4px;
        }
        .dashboard-content::-webkit-scrollbar-thumb:hover,
        .student-content::-webkit-scrollbar-thumb:hover,
        .analytics-content::-webkit-scrollbar-thumb:hover,
        .main-content-scroll::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #7C2D12 0%, #D4870A 100%);
        }
    </style>
</head>
<body>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    ?>

    <script>
        // Execute immediately to prevent flashing before DOM finishes loading
        if (localStorage.getItem('sidebarMinimized') === 'true') {
            document.documentElement.classList.add('sidebar-minimized');
            if (document.body) {
                document.body.classList.add('sidebar-minimized');
                document.body.classList.add('preload-transitions');
                setTimeout(() => document.body.classList.remove('preload-transitions'), 100);
            }
        }
    </script>

    <aside class="sidebar" id="sidebar">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="logo-section">
            <div class="logo-box">
                <img src="../resources/ccslogo.png" alt="CCS Logo" class="w-8 h-8 object-contain">
            </div>
            <div class="logo-text">
                <h2>SIT-IN</h2>
                <p>MONITORING</p>
            </div>
        </div>

        <nav class="nav-section">
            <a href="adminIndex.php" class="nav-item <?php echo $currentPage == 'adminIndex.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="student.php" class="nav-item <?php echo $currentPage == 'student.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-viewfinder"></i>
                <span>Students</span>
            </a>

            <a href="reservationad.php" class="nav-item <?php echo $currentPage == 'reservationad.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Reservation</span>
            </a>

            <a href="current_sit.php" class="nav-item <?php echo $currentPage == 'current_sit.php' ? 'active' : ''; ?>">
                <i class="fas fa-couch"></i>
                <span>Current Sit-In</span>
            </a>



            <a href="leaderboard.php" class="nav-item <?php echo $currentPage == 'leaderboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>

            <a href="rewards.php" class="nav-item <?php echo $currentPage == 'rewards.php' ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i>
                <span>Rewards & Leaderboards</span>
            </a>

            <div class="nav-dropdown <?php echo in_array($currentPage, ['Cannouncement.php', 'feedbackad.php', 'labsched.php']) ? 'active' : ''; ?>">
                <div class="nav-item dropdown-trigger">
                    <div class="flex items-center gap-4">
                        <i class="fas fa-message"></i>
                        <span>Communication</span>
                    </div>
                    <i class="fas fa-chevron-right text-xs transition-transform duration-300"></i>
                </div>
                <div class="dropdown-content">
                    <a href="Cannouncement.php" class="sub-nav-item">
                        <i class="fas fa-bullhorn text-xs"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="feedbackad.php" class="sub-nav-item">
                        <i class="fas fa-comment-alt text-xs"></i>
                        <span>Feedback</span>
                    </a>
                    <a href="labsched.php" class="sub-nav-item">
                        <i class="fas fa-desktop text-xs"></i>
                        <span>Computer & Lab</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="user-section">
            <div class="user-profile cursor-pointer hover:bg-[#1a1438] rounded-xl p-2 transition-all duration-300 border border-transparent hover:border-[rgba(139,63,217,0.15)]" onclick="window.location.href='profilead.php'">
                <div class="user-avatar">
                    <?php 
                    $profileImgName = $_SESSION['profile_picture'] ?? "default-profile.png";
                    $hasProfile = ($profileImgName && $profileImgName !== 'default-profile.png' && file_exists("../upload/" . $profileImgName));
                    if ($hasProfile):
                    ?>
                        <img src="../upload/<?php echo htmlspecialchars($profileImgName); ?>" alt="Admin">
                    <?php else: ?>
                        <div class="w-full h-full rounded-full bg-white/5 flex items-center justify-center overflow-hidden">
                            <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 4px;">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?></h4>
                    <p><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></p>
                </div>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('toggleSidebar');
            
            // Ensure body has the class if initialized via html element
            if(document.documentElement.classList.contains('sidebar-minimized')) {
                document.body.classList.add('sidebar-minimized');
            }

            toggleBtn.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-minimized');
                document.documentElement.classList.toggle('sidebar-minimized');
                const isMinimized = document.body.classList.contains('sidebar-minimized');
                localStorage.setItem('sidebarMinimized', isMinimized);
            });
        });

        document.querySelectorAll('.dropdown-trigger').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const parent = trigger.parentElement;
                parent.classList.toggle('active');
            });
        });
    </script>