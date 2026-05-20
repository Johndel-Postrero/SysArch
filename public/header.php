<?php
// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/db.php';

// Get the current page filename
$page = basename($_SERVER['PHP_SELF'], ".php");

// Define a title based on the page
$titles = [
    "index"        => "Dashboard",
    "profile"      => "Profile Settings",
    "lab_schedule" => "Computer and Lab",
    "announcement" => "Announcements",
    "leader"       => "Rewards & Leaderboards",
    "sitin"        => "Sit-In Rules",
    "laboratory"   => "Laboratory Rules",
    "resources"    => "Resources",
    "reservation"  => "Reservations",
    "history"      => "Sit-In History",
    "rules"        => "Rules & Regulations",
    "lab"          => "Computer and Lab",
    "student_sc"   => "Student Dashboard",
];
$pageTitle = isset($titles[$page]) ? $titles[$page] : "Dashboard";

// ===== Fetch user info =====
$firstname       = $_SESSION['firstname'] ?? "Student";
$lastname        = $_SESSION['lastname']  ?? "";
$profile_picture = $_SESSION['profile_picture'] ?? "default-profile.png";
$role            = $_SESSION['role'] ?? "student";

// Refresh user info from DB to catch any profile updates
if (isset($_SESSION['login_user'])) {
    $username = $_SESSION['login_user'];
    $q = $conn->prepare("SELECT firstname, lastname, profile_picture, role FROM users WHERE idno = ?");
    if ($q) {
        $q->bind_param("s", $username);
        $q->execute();
        $r = $q->get_result();
        if ($r && $r->num_rows > 0) {
            $u = $r->fetch_assoc();
            $_SESSION['firstname']       = $u['firstname'];
            $_SESSION['lastname']        = $u['lastname'];
            $_SESSION['profile_picture'] = $u['profile_picture'] ?? 'default-profile.png';
            $_SESSION['role']            = $u['role'];
            $firstname       = $u['firstname'];
            $lastname        = $u['lastname'];
            $profile_picture = $_SESSION['profile_picture'];
            $role            = $u['role'];
        }
        $q->close();
    }
}

// ===== Notification AJAX handling =====
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);
        $mq = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        if ($mq) {
            $mq->bind_param("i", $notificationId);
            echo json_encode(['success' => $mq->execute()]);
            $mq->close();
        } else {
            echo json_encode(['success' => false]);
        }
        $conn->close();
        exit;
    }

    if (isset($_POST['mark_all_read'])) {
        $user_id = $_SESSION['user_id'] ?? 0;
        $mqa = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND notification_type = 'student' AND is_read = 0");
        if ($mqa) {
            $mqa->bind_param("i", $user_id);
            echo json_encode(['success' => $mqa->execute()]);
            $mqa->close();
        } else {
            echo json_encode(['success' => false]);
        }
        $conn->close();
        exit;
    }
}

// ===== Fetch notifications =====
$notifications = [];
$unreadCount   = 0;
$user_id       = $_SESSION['user_id'] ?? 0;

// Get total unread count for badge
$countQuery = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND notification_type = 'student' AND is_read = 0");
if ($countQuery) {
    $countQuery->bind_param("i", $user_id);
    $countQuery->execute();
    $unreadCount = $countQuery->get_result()->fetch_assoc()['cnt'] ?? 0;
    $countQuery->close();
}

$nq = $conn->prepare(
    "SELECT notification_id, message, is_read, created_at
     FROM notifications
     WHERE user_id = ? AND notification_type = 'student'
     ORDER BY created_at DESC LIMIT 5"
);
if ($nq) {
    $nq->bind_param("i", $user_id);
    $nq->execute();
    $nr = $nq->get_result();
    while ($row = $nr->fetch_assoc()) {
        $notifications[] = $row;
    }
    $nq->close();
}
$conn->close();

// Determine if profile picture exists
$hasProfile = ($profile_picture && $profile_picture !== 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $profile_picture));
?>

<style>
    /* ===== STUDENT HEADER — DARK ACADEMIC ===== */
    .student-header {
        background: rgba(13, 11, 26, 0.85);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border-bottom: 1px solid rgba(139, 63, 217, 0.2);
        padding: 14px 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 900;
        flex-shrink: 0;
    }

    .student-header .header-left h1 {
        font-family: 'Orbitron', sans-serif;
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 14px;
        letter-spacing: 1px;
    }

    .student-header .header-left h1::after {
        content: '';
        width: 36px;
        height: 3px;
        background: linear-gradient(90deg, #8B3FD9, transparent);
        border-radius: 2px;
        box-shadow: 0 0 10px rgba(139, 63, 217, 0.6);
    }

    .student-header .header-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    /* Notification Bell Button */
    .sh-notif-btn {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(139, 63, 217, 0.25);
        border-radius: 10px;
        color: #fff;
        position: relative;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 15px;
    }
    .sh-notif-btn:hover {
        background: rgba(139, 63, 217, 0.12);
        border-color: #8B3FD9;
        box-shadow: 0 0 12px rgba(139, 63, 217, 0.2);
    }
    .sh-notif-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #ef4444;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        min-width: 16px;
        height: 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #0D0B1A;
    }

    /* Notification Dropdown */
    .sh-notif-dropdown {
        position: absolute;
        right: 80px;
        top: 70px;
        width: 340px;
        background: #161326;
        border: 1px solid rgba(139, 63, 217, 0.3);
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        display: none;
        overflow: hidden;
        z-index: 2000;
    }
    .sh-notif-head {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(139, 63, 217, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .sh-notif-head h3 {
        margin: 0;
        color: #fff;
        font-size: 14px;
        font-family: 'Orbitron', sans-serif;
        letter-spacing: 0.5px;
    }
    .sh-notif-head button {
        background: none;
        border: none;
        color: #8B3FD9;
        font-size: 11px;
        cursor: pointer;
        font-weight: 600;
        transition: color 0.2s;
    }
    .sh-notif-head button:hover { color: #C084FC; }

    .sh-notif-item {
        padding: 13px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        transition: background 0.2s;
    }
    .sh-notif-item:hover { background: rgba(139, 63, 217, 0.06); }
    .sh-notif-item.unread { background: rgba(139, 63, 217, 0.1); }
    .sh-notif-item p { margin: 0 0 4px; font-size: 13px; color: #fff; line-height: 1.4; }
    .sh-notif-item span { font-size: 11px; color: #9A8FB0; }
    .sh-notif-item-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .sh-mark-btn {
        background: none; border: none; color: #8B3FD9; font-size: 10px;
        cursor: pointer; padding: 2px 6px; border-radius: 4px; font-weight: 600;
        transition: all 0.2s; flex-shrink: 0;
    }
    .sh-mark-btn:hover { background: rgba(139, 63, 217, 0.15); color: #C084FC; }

    /* Profile Area */
    .sh-profile-wrap { position: relative; }
    .sh-profile-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        padding: 6px 12px 6px 6px;
        border-radius: 12px;
        border: 1px solid rgba(139, 63, 217, 0.2);
        background: rgba(255, 255, 255, 0.04);
        transition: all 0.3s;
    }
    .sh-profile-btn:hover {
        background: rgba(139, 63, 217, 0.1);
        border-color: rgba(139, 63, 217, 0.4);
    }
    .sh-profile-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 2px solid rgba(139, 63, 217, 0.5);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .sh-profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .sh-profile-name {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sh-profile-role {
        font-size: 10px;
        color: #9A8FB0;
        text-transform: capitalize;
    }
    .sh-profile-chevron { color: #9A8FB0; font-size: 11px; transition: transform 0.3s; }
    .sh-profile-btn.open .sh-profile-chevron { transform: rotate(180deg); }

    /* Profile Dropdown */
    .sh-profile-dropdown {
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        width: 190px;
        background: #161326;
        border: 1px solid rgba(139, 63, 217, 0.3);
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        display: none;
        z-index: 2000;
    }
    .sh-profile-dropdown a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: #D1C7E0;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .sh-profile-dropdown a:last-child { border-bottom: none; }
    .sh-profile-dropdown a:hover { background: rgba(139, 63, 217, 0.1); color: #fff; }
    .sh-profile-dropdown a i { width: 16px; text-align: center; color: #9A8FB0; font-size: 14px; }
    .sh-profile-dropdown a:hover i { color: #C084FC; }
    .sh-profile-dropdown .logout-link { color: #ef4444 !important; }
    .sh-profile-dropdown .logout-link i { color: #ef4444 !important; }
    .sh-profile-dropdown .logout-link:hover { background: rgba(239, 68, 68, 0.1) !important; }
</style>

<header class="student-header">
    <div class="header-left">
        <h1><?php echo strtoupper($pageTitle); ?></h1>
    </div>

    <div class="header-right">
        <!-- Notification Bell -->
        <div class="sh-notif-btn" id="shNotifBtn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
                <div class="sh-notif-badge" id="shNotifBadge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></div>
            <?php endif; ?>
        </div>

        <!-- Profile Section Removed -->
    </div>
</header>

<!-- Notification Dropdown (positioned relative to fixed header) -->
<div class="sh-notif-dropdown" id="shNotifDropdown">
    <div class="sh-notif-head">
        <h3>NOTIFICATIONS</h3>
        <?php if ($unreadCount > 0): ?>
            <button id="shMarkAllRead">Mark all read</button>
        <?php endif; ?>
    </div>
    <div id="shNotifList" style="max-height: 340px; overflow-y: auto;">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $n): ?>
                <div class="sh-notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>"
                     data-id="<?php echo $n['notification_id']; ?>">
                    <div class="sh-notif-item-top">
                        <p><?php echo htmlspecialchars($n['message']); ?></p>
                        <?php if (!$n['is_read']): ?>
                            <button class="sh-mark-btn" onclick="shMarkRead(<?php echo $n['notification_id']; ?>, this)">✓</button>
                        <?php endif; ?>
                    </div>
                    <span><?php echo date('M d, h:i A', strtotime($n['created_at'])); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 32px; text-align: center; color: #9A8FB0; font-size: 13px;">No notifications yet</div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const notifBtn      = document.getElementById('shNotifBtn');
    const notifDropdown = document.getElementById('shNotifDropdown');

    // Notification toggle
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = notifDropdown.style.display === 'block';
            notifDropdown.style.display = isOpen ? 'none' : 'block';
        });
    }

    // Close on outside click
    document.addEventListener('click', function() {
        if (notifDropdown) notifDropdown.style.display = 'none';
    });

    // Prevent dropdown close when clicking inside
    if (notifDropdown) {
        notifDropdown.addEventListener('click', e => e.stopPropagation());
    }
})();

// Mark single notification as read
function shMarkRead(id, element) {
    const item = element.closest('.sh-notif-item') || element;
    let wasUnread = false;

    // Optimistic UI Update: change style immediately!
    if (item.classList.contains('unread')) {
        item.classList.remove('unread');
        const btn = item.querySelector('.sh-mark-btn');
        if (btn) btn.remove();
        shUpdateBadge(-1);
        wasUnread = true;
    }

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_read=1&notification_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            // Revert on failure
            if (wasUnread) {
                item.classList.add('unread');
                shUpdateBadge(1);
            }
        }
    }).catch(err => {
        console.error(err);
        if (wasUnread) {
            item.classList.add('unread');
            shUpdateBadge(1);
        }
    });
}

// Add click listeners to student notification items themselves
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.sh-notif-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (this.classList.contains('unread')) {
                if (e.target.classList.contains('sh-mark-btn')) return;
                const id = this.dataset.id;
                const btn = this.querySelector('.sh-mark-btn');
                shMarkRead(id, btn || this);
            }
        });
    });
});

// Mark all notifications as read
const shMarkAllBtn = document.getElementById('shMarkAllRead');
if (shMarkAllBtn) {
    shMarkAllBtn.addEventListener('click', function() {
        // Optimistic UI Update: clear immediately!
        const unreadItems = document.querySelectorAll('.sh-notif-item.unread');
        
        unreadItems.forEach(item => {
            item.classList.remove('unread');
            const b = item.querySelector('.sh-mark-btn');
            if (b) b.remove();
        });
        const badge = document.getElementById('shNotifBadge');
        if (badge) badge.remove();
        this.remove();

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_all_read=1'
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                location.reload();
            }
        }).catch(() => location.reload());
    });
}

// Update notification badge count
function shUpdateBadge(delta) {
    const badge = document.getElementById('shNotifBadge');
    if (!badge) return;
    let count = parseInt(badge.textContent) + delta;
    if (count <= 0) {
        badge.remove();
        const markAll = document.getElementById('shMarkAllRead');
        if (markAll) markAll.remove();
    } else {
        badge.textContent = count > 99 ? '99+' : count;
    }
}

// Reservation reminder polling
function checkUpcomingReservations() {
    fetch('check_reservation.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.notifications_sent > 0) {
                data.reservations.forEach(reservation => {
                    const msg = `Your reservation for Lab ${reservation.lab_number}, PC ${reservation.pc_number} starts in 30 min.`;
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Reservation Reminder', { body: msg });
                    }
                });
            }
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', function() {
    checkUpcomingReservations();
    setInterval(checkUpcomingReservations, 60000);
    if ('Notification' in window) Notification.requestPermission();
});
</script>