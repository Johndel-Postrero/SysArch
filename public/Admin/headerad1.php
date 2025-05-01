<?php
// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../../config/db.php';



// Get user data from session
$firstname = $_SESSION['firstname'] ?? "Guest";
$middlename = $_SESSION['middlename'] ?? "";
$lastname = $_SESSION['lastname'] ?? "";
$role = $_SESSION['role'] ?? "";
$profile_picture = $_SESSION['profile_picture'] ?? "default-profile.png";

$initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));

// Get the current page filename
$page = basename($_SERVER['PHP_SELF'], ".php");

// Define a title based on the page
$titles = [
    "admimIndex" => "Dashboard",
    "search_results" => "Search Results",
    "profilead" => "Profile Settings",
    "sitin" => "Sit-In Rules and Regulations",
    "laboratory" => "Laboratory Rules and Regulations",
    "reservation" => "Reservations",
    "current_sit" => "Current Sit-In",
    "day_sit" => "Current Sit-In Records",
    "Cannouncement" => "Announcements",
    "feedbackad" => "Feedback Report",
    "generate" => "Generate Report",
    "student" => "Student Records",
    "resourcesad" => "Resources",
    "leaderboard" => "Leaderboard",
    "labsched" => "Lab Schedule",
    "reservationad" => "Reservations"
];

// Set the page title dynamically (default to 'Dashboard' if not found)
$pageTitle = $titles[$page] ?? "Dashboard";

// Function to get notifications from database (admin-specific)
function getAdminNotifications($conn, $limit = 5) {
    $notifications = [];
    
    $query = "SELECT id, message, is_read, created_at 
              FROM notifications 
              WHERE notification_type = 'admin'
              ORDER BY created_at DESC 
              LIMIT ?";
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
    
    $query = "SELECT COUNT(*) as unread_count 
              FROM notifications 
              WHERE is_read = 0 AND notification_type = 'admin'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $count = $row['unread_count'];
    }
    
    return $count;
}

// Get notifications
$notifications = getAdminNotifications($conn, 5); // Get last 5 admin notifications
$unreadCount = countUnreadAdminNotifications($conn);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header</title>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background-color: white;
        }
        #profileDropdown, #notificationDropdown {
            position: absolute;
            z-index: 1000;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #EF4444;
            color: white;
            font-size: 10px;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-item {
            transition: background-color 0.3s;
        }
        .notification-item:hover {
            background-color: #F3F4F6;
        }
        .notification-item.unread {
            background-color: #EFF6FF;
        }
        @media (max-width: 570px) {
            .search-bar {
                display: none;
            }
        }
    </style>
</head>
<body>
<header class="header flex items-center justify-between bg-white py-6 px-6">
    <h2 class="text-2xl font-semibold"><?php echo $pageTitle; ?></h2>

    <div class="flex items-center space-x-4">
        <!-- Search Bar -->
        <form action="search_results.php" method="GET" class="relative w-80 flex items-center search-bar sm:flex hidden">
            <div class="relative w-full">
                <input type="text" name="query" placeholder="Search by Name or ID" 
                    class="w-full py-2 pl-5 pr-14 h-10 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500">
                <button type="submit" class="absolute top-1/2 -translate-y-1/2 right-4 w-10 flex items-center justify-center">
                    <i class="fas fa-search text-gray-400"></i>
                </button>
            </div>
        </form>
        
        <!-- Notification Bell -->
        <div class="relative">
            <div class="cursor-pointer" id="notificationBell">
                <i class="fas fa-bell text-xl"></i>
                <?php if ($unreadCount > 0): ?>
                <div class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="font-semibold">Notifications</h3>
                    <?php if ($unreadCount > 0): ?>
                    <button id="markAllRead" class="text-xs text-blue-500 hover:text-blue-700">Mark All as Read</button>
                    <?php endif; ?>
                </div>
                
                <div class="max-h-80 overflow-y-auto">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> p-4 border-b" data-id="<?php echo $notification['id']; ?>">
                                <div class="flex justify-between">
                                    <p class="text-sm <?php echo $notification['is_read'] ? 'text-gray-600' : 'text-gray-900 font-medium'; ?>"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                    <button class="mark-read text-xs text-blue-500 hover:text-blue-700 ml-2">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-1"><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-gray-500">No notifications</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="relative">
            <div class="flex items-center cursor-pointer" id="profileDropdownBtn">
                <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
                    <?php 
                        if ($profile_picture && file_exists(__DIR__ . '/../upload/' . $profile_picture)) {
                            echo '<img src="../upload/' . htmlspecialchars($profile_picture) . '?t=' . time() . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
                        } else {
                            echo $initials;
                        }
                    ?>
                </div>
                <div>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($firstname . ' ' . $middlename . ' ' . $lastname); ?></p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($role)); ?></p>
                </div>
            </div>
            <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-50">
                <a href="profilead.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-user mr-3"></i> Profile
                </a>
                <a href="../logout.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<script>
// Toggle profile dropdown
document.getElementById('profileDropdownBtn').addEventListener('click', function() {
    document.getElementById('profileDropdown').classList.toggle('hidden');
    // Hide notification dropdown if open
    document.getElementById('notificationDropdown').classList.add('hidden');
});

// Toggle notification dropdown
document.getElementById('notificationBell').addEventListener('click', function() {
    document.getElementById('notificationDropdown').classList.toggle('hidden');
    // Hide profile dropdown if open
    document.getElementById('profileDropdown').classList.add('hidden');
});

// Function to properly style a notification as read
function styleNotificationAsRead(notificationItem) {
    // Remove unread class from the container
    notificationItem.classList.remove('unread');
    
    // Update text styling
    const textElement = notificationItem.querySelector('p:first-of-type');
    if (textElement) {
        textElement.classList.remove('text-gray-900', 'font-medium');
        textElement.classList.add('text-gray-600');
    }
    
    // Remove mark-read button
    const markReadBtn = notificationItem.querySelector('.mark-read');
    if (markReadBtn) markReadBtn.remove();
}

// Mark single notification as read
document.querySelectorAll('.mark-read').forEach(button => {
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        const notificationItem = this.closest('.notification-item');
        const notificationId = notificationItem.dataset.id;
        
        // Update UI immediately
        styleNotificationAsRead(notificationItem);
        
        // Update badge count
        updateNotificationBadge();
        
        // Send request to server in background
        fetch('mark_notification_read.php', {  // Changed this line
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'mark_read=1&notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error marking notification as read:', data.error);
                // Optionally revert UI changes if the request failed
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});

// Mark all notifications as read
const markAllReadBtn = document.getElementById('markAllRead');
if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Update UI immediately for all unread notifications
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            styleNotificationAsRead(item);
        });
        
        // Remove badge
        const badge = document.querySelector('.notification-badge');
        if (badge) badge.remove();
        
        // Remove the "Mark All as Read" button
        this.remove();
        
        // Send request to server in background
        fetch('mark_notification_read.php', {  // Changed this line
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'mark_all_read=1'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error marking all notifications as read:', data.error);
                // Optionally revert UI changes if the request failed
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
}

// Function to update notification badge
function updateNotificationBadge() {
    const unreadItems = document.querySelectorAll('.notification-item.unread').length;
    const badge = document.querySelector('.notification-badge');
    const markAllBtn = document.getElementById('markAllRead');
    
    if (unreadItems === 0) {
        if (badge) badge.remove();
        if (markAllBtn) markAllBtn.remove();
    } else if (badge) {
        badge.textContent = unreadItems > 99 ? '99+' : unreadItems;
    }
}

// Close dropdowns if clicked outside
document.addEventListener('click', function(event) {
    const profileDropdown = document.getElementById('profileDropdown');
    const profileBtn = document.getElementById('profileDropdownBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBtn = document.getElementById('notificationBell');
    
    if (profileDropdown && profileBtn && !profileBtn.contains(event.target) && !profileDropdown.contains(event.target)) {
        profileDropdown.classList.add('hidden');
    }
    
    if (notificationDropdown && notificationBtn && !notificationBtn.contains(event.target) && !notificationDropdown.contains(event.target)) {
        notificationDropdown.classList.add('hidden');
    }
});
</script>
</body>
</html>