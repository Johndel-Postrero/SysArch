<?php
// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Assume $user is fetched from the database after verifying login credentials.
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['middlename'] = $user['middlename'];
$_SESSION['lastname'] = $user['lastname'];
require __DIR__ . '/../config/db.php';

// Debug: output session idno value
// var_dump($_SESSION['idno']);

// Get the current page filename
$page = basename($_SERVER['PHP_SELF'], ".php");

// Define a title based on the page
$titles = [
    "index" => "Dashboard",
    "profile" => "Profile Settings",
    "sitin" => "Sit-In Rules and Regulations",
    "laboratory" => "Laboratory Rules and Regulations",
    "reservation" => "Reservations",
    "history" => "Sit-in History"
];

// Set the page title dynamically (default to 'Dashboard' if not found)
$pageTitle = isset($titles[$page]) ? $titles[$page] : "Dashboard";
// Ensure session variables exist before using them
$firstname = isset($_SESSION['firstname']) ? $_SESSION['firstname'] : "Guest";
$lastname = isset($_SESSION['lastname']) ? $_SESSION['lastname'] : "";
$middlename = isset($_SESSION['middlename']) ? $_SESSION['middlename'] : "";
$initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
$role= isset($user['role']) ? $user['role'] : "";
$profile_picture = isset($user['profile_picture']) ? $user['profile_picture'] : "default-profile.png";

// Function to get notifications from database in chronological order
function getNotifications($conn, $limit = 5) {
    $notifications = [];
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'student';
    
    if ($role === 'student') {
        $query = "SELECT id, message, is_read, created_at 
                 FROM notifications 
                 WHERE (user_id = ? AND notification_type = 'student')
                 ORDER BY created_at DESC LIMIT ?";
    } else {
        $query = "SELECT id, message, is_read, created_at 
                 FROM notifications 
                 WHERE notification_type = 'admin'
                 ORDER BY created_at DESC LIMIT ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        if ($role === 'student') {
            $stmt->bind_param("ii", $user_id, $limit);
        } else {
            $stmt->bind_param("i", $limit);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
    }
    
    return $notifications;
}

// Function to count unread notifications
function countUnreadNotifications($conn) {
    $count = 0;
    $user_id = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? 'student';
    
    if ($role === 'student') {
        $query = "SELECT COUNT(*) as unread_count 
                 FROM notifications 
                 WHERE is_read = 0 
                 AND (user_id = ? AND notification_type = 'student')";
    } else {
        $query = "SELECT COUNT(*) as unread_count 
                 FROM notifications 
                 WHERE is_read = 0 
                 AND notification_type = 'admin'";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        if ($role === 'student') {
            $stmt->bind_param("i", $user_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = $row['unread_count'];
        }
        
        $stmt->close();
    }
    
    return $count;
}

// Mark notification as read (AJAX request handling)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Mark single notification as read
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);
        
        $markReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
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
    
    // Mark all notifications as read
    if (isset($_POST['mark_all_read'])) {
        $markAllReadQuery = $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        
        if ($markAllReadQuery) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        
        $conn->close();
        exit;
    }
}

// Get notifications and unread count
$notifications = getNotifications($conn, 5); // Get in chronological order
$unreadCount = countUnreadNotifications($conn);
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
        .header{
            position:sticky;
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
    </style>
</head>
<body>
<header class="header flex items-center justify-between bg-white p-6">
    <h2 class="text-2xl font-semibold"><?php echo $pageTitle; ?></h2>
    <div class="flex items-center">
        <!-- Notification Bell -->
        <div class="relative mr-6">
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
                                    <p class="text-sm <?php echo $notification['is_read'] ? 'text-gray-600' : 'text-gray-900 font-medium'; ?>">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                    <button class="mark-read text-xs text-blue-500 hover:text-blue-700 ml-2">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                </p>
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
                <!-- Profile Initials -->
                <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
                    <?php 
                    if($profile_picture && file_exists(__DIR__ . '/../public/upload/' . $profile_picture)){
                        echo '<img src="upload/' . htmlspecialchars($profile_picture) . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
                    }else{
                        echo $initials;
                    }
                    ?>
                </div>
                <div>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' .$_SESSION['middlename'] . ' '. $_SESSION['lastname']); ?> </p>
                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars(ucfirst($role)); ?></p>
                </div>
            </div>
            <!-- Dropdown Menu -->
            <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-50">
                <a href="profile.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-200">
                    <i class="fas fa-user mr-3"></i> Profile
                </a>
                <a href="logout.php" class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-200">
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
        document.getElementById('notificationDropdown').classList.add('hidden');
    });

    // Toggle notification dropdown
    document.getElementById('notificationBell').addEventListener('click', function() {
        document.getElementById('notificationDropdown').classList.toggle('hidden');
        document.getElementById('profileDropdown').classList.add('hidden');
    });

    // Function to properly style a notification as read
    function styleNotificationAsRead(notificationItem) {
        notificationItem.classList.remove('unread');
        const textElement = notificationItem.querySelector('p:first-of-type');
        if (textElement) {
            textElement.classList.remove('text-gray-900', 'font-medium');
            textElement.classList.add('text-gray-600');
        }
        const markReadBtn = notificationItem.querySelector('.mark-read');
        if (markReadBtn) markReadBtn.remove();
    }

    // Mark single notification as read
    document.querySelectorAll('.mark-read').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const notificationItem = this.closest('.notification-item');
            const notificationId = notificationItem.dataset.id;
            
            styleNotificationAsRead(notificationItem);
            updateNotificationBadge();
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mark_read=1&notification_id=' + notificationId
            }).catch(error => {
                console.error('Error:', error);
            });
        });
    });

    // Mark all notifications as read
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                styleNotificationAsRead(item);
            });
            
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.remove();
            
            this.remove();
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mark_all_read=1'
            }).catch(error => {
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