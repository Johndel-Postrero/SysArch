<?php
// Determine active page
$currentPage = basename($_SERVER['PHP_SELF']);

// Determine if profile picture is available for the sidebar user section
$sidebarProfileImg = $_SESSION['profile_picture'] ?? 'default-profile.png';
$sidebarFirstname  = $_SESSION['firstname'] ?? 'Student';
$sidebarLastname   = $_SESSION['lastname'] ?? '';
$sidebarHasProfile = ($sidebarProfileImg && $sidebarProfileImg !== 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $sidebarProfileImg));
?>
<style>
    /* ===== STUDENT SIDEBAR — DARK ACADEMIC THEME ===== */
    :root {
        --bg-sidebar:    #0D0B1A;
        --purple-glow:   #8B3FD9;
        --purple-hover:  rgba(139, 63, 217, 0.15);
        --text-main:     #ffffff;
        --text-dim:      #9A8FB0;
        --font-h:        'Orbitron', sans-serif;
        --font-b:        'Inter', sans-serif;
    }

    /* Prevent flash of unstyled transitions on load */
    .preload-transitions * { transition: none !important; }

    /* ===== SIDEBAR SHELL ===== */
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
        overflow: visible;
    }

    /* ===== MINIMIZED STATE ===== */
    body.sidebar-minimized .sidebar                          { width: 80px; padding: 24px 15px; }
    body.sidebar-minimized .sidebar .logo-text,
    body.sidebar-minimized .sidebar .nav-item span,
    body.sidebar-minimized .sidebar .sub-nav-item span,
    body.sidebar-minimized .sidebar .user-info,
    body.sidebar-minimized .sidebar .logout-btn span,
    body.sidebar-minimized .sidebar .nav-dropdown i.fa-chevron-right { display: none; }

    body.sidebar-minimized .sidebar .logo-section            { padding-bottom: 30px; justify-content: center; }
    body.sidebar-minimized .sidebar .nav-item                { justify-content: center; padding: 14px; }
    body.sidebar-minimized .sidebar .user-profile            { justify-content: center; }
    body.sidebar-minimized .nav-dropdown.active .dropdown-content,
    body.sidebar-minimized .sidebar .dropdown-content        { display: none !important; }
    body.sidebar-minimized .sidebar .nav-item .flex          { gap: 0 !important; justify-content: center; }
    body.sidebar-minimized .sub-nav-item                     { justify-content: center; padding: 14px; }

    /* Main wrapper responsive margin */
    .main-wrapper { margin-left: 280px; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
    body.sidebar-minimized .main-wrapper { margin-left: 80px; }

    /* ===== LOGO SECTION ===== */
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
        font-size: 14px;
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

    /* ===== TOGGLE BUTTON ===== */
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
        font-size: 11px;
    }
    body.sidebar-minimized .toggle-sidebar {
        transform: translateY(-50%) rotate(180deg);
    }

    /* ===== NAV SECTION ===== */
    .nav-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: none;
    }
    .nav-section::-webkit-scrollbar { display: none; }

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
        font-family: var(--font-b);
        white-space: nowrap;
    }
    .nav-item i {
        font-size: 18px;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
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

    /* ===== DROPDOWN (Rules & Regulations) ===== */
    .nav-dropdown { position: relative; }
    .nav-dropdown .dropdown-trigger { cursor: pointer; justify-content: space-between; }
    body.sidebar-minimized .nav-dropdown .dropdown-trigger { justify-content: center; padding: 14px; }
    body.sidebar-minimized .nav-dropdown .dropdown-trigger .flex { justify-content: center; gap: 0; }

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
    .nav-dropdown.active .fa-chevron-right { transform: rotate(90deg); }

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
        border-radius: 8px;
        white-space: nowrap;
        font-family: var(--font-b);
    }
    .sub-nav-item:hover { color: var(--purple-glow); background: var(--purple-hover); }
    .sub-nav-item.active { color: var(--purple-glow); }

    .nav-section-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: rgba(154, 143, 176, 0.4);
        padding: 16px 18px 4px;
        font-family: var(--font-b);
        white-space: nowrap;
    }
    body.sidebar-minimized .nav-section-label { display: none; }

    /* ===== USER SECTION ===== */
    .user-section {
        padding-top: 20px;
        margin-top: auto;
        border-top: 1px solid rgba(139, 63, 217, 0.1);
    }
    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid transparent;
    }
    .user-profile:hover {
        background: rgba(26, 20, 56, 0.8);
        border-color: rgba(139, 63, 217, 0.15);
    }
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid var(--purple-glow);
        padding: 2px;
        flex-shrink: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .user-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    .user-info h4 { color: #fff; font-size: 13px; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
    .user-info p  { color: var(--text-dim); font-size: 11px; margin: 0; }
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
        font-family: var(--font-b);
        white-space: nowrap;
    }
    .logout-btn:hover { background: #ef4444; color: #fff; }

    /* ===== GLOBAL BODY + SCROLLBAR OVERRIDES ===== */
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
    .student-content,
    .main-content-scroll {
        flex: 1 !important;
        overflow-y: auto !important;
        position: relative;
        padding-bottom: 40px !important;
    }
    .student-content::-webkit-scrollbar,
    .main-content-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .student-content::-webkit-scrollbar-track,
    .main-content-scroll::-webkit-scrollbar-track { background: rgba(15, 10, 25, 0.4); }
    .student-content::-webkit-scrollbar-thumb,
    .main-content-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #8B3FD9 0%, #C084FC 100%);
        border-radius: 4px;
    }
</style>

<?php
// Inline script to apply sidebar state BEFORE paint to prevent flash
?>
<script>
    if (localStorage.getItem('studentSidebarMinimized') === 'true') {
        document.documentElement.classList.add('sidebar-minimized');
        if (document.body) {
            document.body.classList.add('sidebar-minimized', 'preload-transitions');
            setTimeout(() => document.body.classList.remove('preload-transitions'), 100);
        }
    }
</script>

<aside class="sidebar" id="studentSidebar">
    <button class="toggle-sidebar" id="toggleStudentSidebar" title="Toggle sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <!-- Logo -->
    <div class="logo-section">
        <div class="logo-box">
            <img src="inc/CCS_LOGO.png" alt="CCS Logo" style="width:28px;height:28px;object-fit:contain;">
        </div>
        <div class="logo-text">
            <h2>SIT-IN</h2>
            <p>STUDENT PORTAL</p>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="nav-section">
        <div class="nav-section-label">Main</div>

        <a href="index.php" class="nav-item <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>

        <a href="announcement.php" class="nav-item <?php echo $currentPage === 'announcement.php' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>

        <div class="nav-section-label">Lab Access</div>

        <a href="reservation.php" class="nav-item <?php echo $currentPage === 'reservation.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Reservations</span>
        </a>

        <a href="history.php" class="nav-item <?php echo $currentPage === 'history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Sit-in History&Feedback</span>
        </a>

        <a href="lab.php" class="nav-item <?php echo $currentPage === 'lab.php' ? 'active' : ''; ?>">
            <i class="fas fa-desktop"></i>
            <span>Computer and Lab</span>
        </a>

        <div class="nav-section-label">Info</div>

        <!-- Rules & Regulations Dropdown -->
        <div class="nav-dropdown <?php echo in_array($currentPage, ['sitin.php', 'laboratory.php']) ? 'active' : ''; ?>">
            <div class="nav-item dropdown-trigger">
                <div class="flex items-center gap-4">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Rules & Regs</span>
                </div>
                <i class="fas fa-chevron-right text-xs transition-transform duration-300"></i>
            </div>
            <div class="dropdown-content">
                <a href="sitin.php" class="sub-nav-item <?php echo $currentPage === 'sitin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chair text-xs"></i>
                    <span>Sit-In Rules</span>
                </a>
                <a href="laboratory.php" class="sub-nav-item <?php echo $currentPage === 'laboratory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-flask text-xs"></i>
                    <span>Laboratory</span>
                </a>
            </div>
        </div>

        <a href="leader.php" class="nav-item <?php echo $currentPage === 'leader.php' ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i>
            <span>Rewards & Leaderboards</span>
        </a>
    </nav>

    <!-- User Section -->
    <div class="user-section">
        <div class="user-profile" onclick="window.location.href='profile.php'">
            <div class="user-avatar">
                <?php if ($sidebarHasProfile): ?>
                    <img src="upload/<?php echo htmlspecialchars($sidebarProfileImg); ?>" alt="Profile">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:rgba(139,63,217,0.15);display:flex;align-items:center;justify-content:center;border-radius:50%;">
                        <svg class="fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:24px;height:24px;color:#C084FC;">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($sidebarFirstname . ' ' . $sidebarLastname); ?></h4>
                <p>Student</p>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('toggleStudentSidebar');

        // Sync html class → body on load
        if (document.documentElement.classList.contains('sidebar-minimized')) {
            document.body.classList.add('sidebar-minimized');
        }

        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-minimized');
            document.documentElement.classList.toggle('sidebar-minimized');
            const isMinimized = document.body.classList.contains('sidebar-minimized');
            localStorage.setItem('studentSidebarMinimized', isMinimized);
        });

        // Rules & Regulations dropdown toggle
        document.querySelectorAll('.dropdown-trigger').forEach(trigger => {
            trigger.addEventListener('click', () => {
                trigger.parentElement.classList.toggle('active');
            });
        });
    });
</script>