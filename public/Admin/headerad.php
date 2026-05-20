<?php
// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../../config/db.php';

// Ensure notifications table exists
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    notification_type VARCHAR(50) DEFAULT 'admin',
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$toastMessage = '';
$toastType = '';
if (isset($_SESSION['success'])) {
    $toastMessage = $_SESSION['success'];
    $toastType = 'success';
    unset($_SESSION['success']);
    
    // Auto-create a notification if it's an important success action
    $msgLower = strtolower($toastMessage);
    if (strpos($msgLower, 'success') !== false || strpos($msgLower, 'approv') !== false || strpos($msgLower, 'log') !== false || strpos($msgLower, 'add') !== false || strpos($msgLower, 'creat') !== false) {
        $stmt = $conn->prepare("INSERT INTO notifications (notification_type, message) VALUES ('admin', ?)");
        if ($stmt) {
            $stmt->bind_param("s", $toastMessage);
            $stmt->execute();
            $stmt->close();
        }
    }
} else if (isset($_SESSION['error'])) {
    $toastMessage = $_SESSION['error'];
    $toastType = 'error';
    unset($_SESSION['error']);
}

// Mark notification as read (AJAX request handling)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark single notification as read
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        header('Content-Type: application/json');
        $notificationId = intval($_POST['notification_id']);
        
        $markReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        if ($markReadQuery) {
            $markReadQuery->bind_param("i", $notificationId);
            
            if ($markReadQuery->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            
            $markReadQuery->close();
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        
        $conn->close();
        exit;
    }
}

// Get the current page filename
$page = basename($_SERVER['PHP_SELF'], ".php");

// Define a title based on the page
$titles = [
    "adminIndex" => "Dashboard",
    "search_results" => "Search Results",
    "profilead" => "Profile Settings",
    "sitin" => "Sit-In Rules",
    "laboratory" => "Lab Rules",
    "reservation" => "Reservations",
    "current_sit" => "Current Sit-In",
    "day_sit" => "Sit-In Records",
    "Cannouncement" => "Announcements",
    "feedbackad" => "Feedback",
    "generate" => "Reports",
    "student" => "Students",
    "resourcesad" => "Resources",
    "leaderboard" => "Analytics",
    "labsched" => "Lab Schedule",
    "reservationad" => "Reservations",
    "rewards" => "Rewards & Leaderboards"
];

$pageTitle = isset($titles[$page]) ? $titles[$page] : "Dashboard";

// Function to get notifications from database
function getAdminNotifications($conn, $limit = 5) {
    $notifications = [];
    $query = "SELECT notification_id, message, is_read, created_at FROM notifications WHERE notification_type = 'admin' ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
    }
    return $notifications;
}

// Function to count unread admin notifications
function countUnreadAdminNotifications($conn) {
    $count = 0;
    $query = "SELECT COUNT(*) as unread_count FROM notifications WHERE is_read = 0 AND notification_type = 'admin'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['unread_count'];
    }
    return $count;
}

$unreadCount = countUnreadAdminNotifications($conn);
$notifications = getAdminNotifications($conn, 5);
$conn->close();
?>

<style>
    .admin-header {
        background: rgba(13, 11, 26, 0.7);
        backdrop-filter: blur(15px);
        border-bottom: 1px solid rgba(139, 63, 217, 0.2);
        padding: 16px 40px; /* Shortened height */
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 900;
    }

    .header-left h1 {
        font-family: 'Orbitron', sans-serif;
        font-size: 22px; /* Slightly smaller */
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        letter-spacing: 1px;
    }

    .header-left h1::after {
        content: '';
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #8B3FD9, transparent);
        border-radius: 2px;
        box-shadow: 0 0 10px rgba(139, 63, 217, 0.6);
    }

    .header-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .search-container {
        position: relative;
        width: 280px;
    }

    .search-container input {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(139, 63, 217, 0.3);
        border-radius: 10px;
        padding: 8px 16px 8px 38px;
        color: #fff;
        font-size: 13px;
        transition: all 0.3s;
    }

    .search-container input:focus {
        outline: none;
        border-color: #8B3FD9;
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 15px rgba(139, 63, 217, 0.2);
    }

    .search-container i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9A8FB0;
        font-size: 14px;
    }

    .notification-btn {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(139, 63, 217, 0.2);
        border-radius: 10px;
        color: #fff;
        position: relative;
        cursor: pointer;
        transition: all 0.3s;
    }

    .notification-btn:hover {
        background: rgba(139, 63, 217, 0.1);
        border-color: #8B3FD9;
    }

    .notif-badge {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #ef4444;
        color: #fff;
        font-size: 9px;
        font-weight: 700;
        min-width: 16px;
        height: 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #0D0B1A;
    }

    #notificationDropdown {
        position: absolute;
        right: 40px;
        top: 70px;
        width: 350px;
        background: #161326;
        border: 1px solid rgba(139, 63, 217, 0.3);
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        display: none;
        overflow: hidden;
    }

    .notif-header {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(139, 63, 217, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notif-header h3 {
        margin: 0;
        color: #fff;
        font-size: 15px;
    }

    .notif-item {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s;
    }

    .notif-item:hover {
        background: rgba(139, 63, 217, 0.05);
    }

    .notif-item.unread {
        background: rgba(139, 63, 217, 0.1);
    }

    .notif-item p {
        margin: 0 0 4px;
        font-size: 13px;
        color: #fff;
    }

    .notif-item span {
        font-size: 11px;
        color: #9A8FB0;
    }

    .notif-mark-btn {
        background: none; border: none; color: #8B3FD9; font-size: 10px;
        cursor: pointer; padding: 2px 6px; border-radius: 4px; transition: all 0.2s;
        font-weight: 600; margin-left: 6px;
    }
    .notif-mark-btn:hover { background: rgba(139,63,217,0.15); color: #C084FC; }
    .notif-item-top { display: flex; justify-content: space-between; align-items: flex-start; }

    /* Search Suggestions Dropdown */
    .nav-suggestions {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 320px;
        background: rgba(13, 11, 26, 0.95);
        border: 1px solid rgba(139, 63, 217, 0.4);
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.8);
        z-index: 1000;
        overflow: hidden;
        backdrop-filter: blur(20px);
    }
    .sug-section {
        padding: 8px 14px;
        font-size: 10px;
        font-weight: 800;
        color: #8B3FD9;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        background: rgba(0,0,0,0.4);
    }
    .sug-item {
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .sug-item:hover {
        background: rgba(139, 63, 217, 0.15);
    }
    .sug-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .sug-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(139, 63, 217, 0.2);
        color: #c084fc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 13px;
        border: 1px solid rgba(139, 63, 217, 0.4);
        flex-shrink: 0;
    }
    .sug-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 2px; }
    .sug-sub { font-size: 11px; color: #9A8FB0; }
    .sug-badge {
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 10px;
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
        font-weight: 600;
    }
    .sug-badge-sitin {
        background: rgba(6, 182, 212, 0.2);
        color: #06b6d4;
        border: 1px solid rgba(6, 182, 212, 0.3);
    }
</style>

<header class="admin-header">
    <div class="header-left">
        <h1><?php echo strtoupper($pageTitle); ?></h1>
    </div>

    <div class="header-right">
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="navGlobalSearch" placeholder="Search students, sit-ins...">
            <div id="navSuggestionsBox" class="nav-suggestions hidden"></div>
        </div>

        <div class="notification-btn" id="notifBtn">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
                <div class="notif-badge" id="notifBadge"><?php echo $unreadCount; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="notificationDropdown">
        <div class="notif-header">
            <h3>Notifications</h3>
            <?php if ($unreadCount > 0): ?>
                <button id="markAllRead" class="text-xs text-purple-400 hover:text-purple-300">Mark all read</button>
            <?php endif; ?>
        </div>
        <div class="max-h-[350px] overflow-y-auto" id="notifList">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $n['notification_id']; ?>">
                        <div class="notif-item-top">
                            <p><?php echo htmlspecialchars($n['message']); ?></p>
                            <?php if (!$n['is_read']): ?>
                                <button class="notif-mark-btn" onclick="markNotifRead(<?php echo $n['notification_id']; ?>, this)">Mark read</button>
                            <?php endif; ?>
                        </div>
                        <span><?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500 text-sm">No new notifications</div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notificationDropdown');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', () => {
            notifDropdown.style.display = 'none';
        });

        notifDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Mark single notification as read
    function markNotifRead(id, element) {
        fetch('mark_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_read=1&notification_id=' + id
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const item = element.closest('.notif-item') || element;
                if (item.classList.contains('unread')) {
                    item.classList.remove('unread');
                    const btn = item.querySelector('.notif-mark-btn');
                    if (btn) btn.remove();
                    updateBadge(-1);
                }
            }
        }).catch(err => console.error(err));
    }

    // Add click listeners to items themselves
    document.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.classList.contains('unread')) {
                if (e.target.classList.contains('notif-mark-btn')) return;
                const id = this.dataset.id;
                const btn = this.querySelector('.notif-mark-btn');
                markNotifRead(id, btn || this);
            }
        });
    });

    // Mark all notifications as read
    const markAllBtn = document.getElementById('markAllRead');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', () => {
            fetch('mark_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=1'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notif-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const btn = item.querySelector('.notif-mark-btn');
                        if (btn) btn.remove();
                    });
                    const badge = document.getElementById('notifBadge');
                    if (badge) badge.remove();
                    markAllBtn.style.display = 'none';
                }
            }).catch(err => console.error(err));
        });
    }

    // Update badge count
    function updateBadge(delta) {
        const badge = document.getElementById('notifBadge');
        if (!badge) return;
        let count = parseInt(badge.textContent) + delta;
        if (count <= 0) {
            badge.remove();
            const markAll = document.getElementById('markAllRead');
            if (markAll) markAll.remove();
        } else {
            badge.textContent = count > 99 ? '99+' : count;
        }
    }

    // Nav Global Search Live Suggestions
    const navSearchInput = document.getElementById('navGlobalSearch');
    const navSugBox = document.getElementById('navSuggestionsBox');
    let navSearchTimer;

    if (navSearchInput && navSugBox) {
        navSearchInput.addEventListener('input', function() {
            clearTimeout(navSearchTimer);
            const query = this.value.trim();

            if (query === '') {
                navSugBox.classList.add('hidden');
                return;
            }

            navSearchTimer = setTimeout(() => {
                fetch(`get_search_suggestions.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        let html = '';
                        
                        if (data.sitins && data.sitins.length > 0) {
                            html += `<div class="sug-section">Active Sit-Ins</div>`;
                            data.sitins.forEach(s => {
                                const initials = (s.firstname[0] + s.lastname[0]).toUpperCase();
                                html += `
                                    <div class="sug-item" onclick="window.location.href='current_sit.php?search=${s.idno}&active_alert=1'">
                                        <div class="sug-left">
                                            <div class="sug-avatar" style="background: rgba(6, 182, 212, 0.2); color: #06b6d4; border-color: rgba(6, 182, 212, 0.4);">${initials}</div>
                                            <div>
                                                <div class="sug-title">${s.firstname} ${s.lastname}</div>
                                                <div class="sug-sub">Lab ${s.lab_number} • ${s.purpose}</div>
                                            </div>
                                        </div>
                                        <span class="sug-badge sug-badge-sitin">Active</span>
                                    </div>
                                `;
                            });
                        }

                        if (data.students && data.students.length > 0) {
                            html += `<div class="sug-section">Registered Students</div>`;
                            data.students.forEach(st => {
                                const initials = (st.firstname[0] + st.lastname[0]).toUpperCase();
                                html += `
                                    <div class="sug-item" onclick="window.location.href='current_sit.php?log_idno=${st.idno}'">
                                        <div class="sug-left">
                                            <div class="sug-avatar">${initials}</div>
                                            <div>
                                                <div class="sug-title">${st.firstname} ${st.lastname}</div>
                                                <div class="sug-sub">${st.idno} • ${st.course}-${st.level}</div>
                                            </div>
                                        </div>
                                        <span class="sug-badge">${st.session} sessions</span>
                                    </div>
                                `;
                            });
                        }

                        if (html === '') {
                            html = `<div class="p-4 text-center text-xs text-gray-500">No matching suggestions</div>`;
                        }

                        navSugBox.innerHTML = html;
                        navSugBox.classList.remove('hidden');
                    })
                    .catch(err => console.error(err));
            }, 250);
        });

        document.addEventListener('click', (e) => {
            if (!navSearchInput.contains(e.target) && !navSugBox.contains(e.target)) {
                navSugBox.classList.add('hidden');
            }
        });

        navSearchInput.addEventListener('focus', function() {
            if (this.value.trim() !== '' && navSugBox.innerHTML !== '') {
                navSugBox.classList.remove('hidden');
            }
        });
    }
</script>


<?php if ($toastMessage !== ''): ?>
    <style>
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 99999;
            transform: translateY(120%);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .toast-container.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-card {
            background: rgba(13, 11, 26, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 320px;
            max-width: 450px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .toast-card.success { border-left-color: #10b981; }
        .toast-card.error { border-left-color: #ef4444; }
        .toast-icon { font-size: 20px; margin-top: 2px; }
        .toast-card.success .toast-icon { color: #10b981; }
        .toast-card.error .toast-icon { color: #ef4444; }
        .toast-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .toast-msg {
            font-size: 13px;
            color: #9A8FB0;
            line-height: 1.4;
            font-weight: 500;
        }
        .toast-close {
            margin-left: auto;
            color: #9A8FB0;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
            padding: 2px;
        }
        .toast-close:hover { color: #fff; }
    </style>
    
    <div id="globalToast" class="toast-container">
        <div class="toast-card <?php echo $toastType; ?>">
            <div class="toast-icon">
                <i class="fas <?php echo $toastType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            </div>
            <div>
                <div class="toast-title"><?php echo $toastType === 'success' ? 'SUCCESS' : 'ERROR'; ?></div>
                <div class="toast-msg"><?php echo htmlspecialchars($toastMessage); ?></div>
            </div>
            <div class="toast-close" onclick="closeToast()">
                <i class="fas fa-times"></i>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('globalToast');
            if (toast) {
                setTimeout(() => { toast.classList.add('show'); }, 100);
                setTimeout(() => { closeToast(); }, 5000);
            }
        });
        function closeToast() {
            const toast = document.getElementById('globalToast');
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => { toast.remove(); }, 400);
            }
        }
    </script>
<?php endif; ?>