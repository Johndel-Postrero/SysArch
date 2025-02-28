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
    "admimIndex" => "Dashboard",
    "announcement" => "Announcements",
    "profile" => "Profile Settings",
    "sitin" => "Sit-In Rules and Regulations",
    "laboratory" => "Laboratory Rules and Regulations",
    "reservation" => "Reservations",
    "history" => "Sit-in History"
];

// Set the page title dynamically (default to 'Dashboard' if not found)
$pageTitle = isset($titles[$page]) ? $titles[$page] : "Dashboard";

// Fetch user info from session or database
$firstname = "Guest";
$middlename = "";
$lastname = "";
$initials = "G";

function updateUserSession($conn, &$firstname, &$middlename, &$lastname, &$profile_picture, &$role, &$initials) {
    if (isset($_SESSION['login_user'])) {
        $username = $_SESSION['login_user'];
        $query = $conn->prepare("SELECT firstname, middlename, lastname, profile_picture, role FROM users WHERE username = ?");
        if (!$query) {
            die("Prepare failed: " . $conn->error);
        }
        $query->bind_param("s", $username);
        if (!$query->execute()) {
            die("Execute failed: " . $query->error);
        }
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Update session variables
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['middlename'] = $user['middlename']; // Middlename is stored here
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_picture'] = $user['profile_picture'] ?? "default-profile.png";
        
            // Update the passed variables
            $firstname = $_SESSION['firstname'];
            $middlename = $_SESSION['middlename']; // Middlename is passed here
            $lastname = $_SESSION['lastname'];
            $profile_picture = $_SESSION['profile_picture'];
            $role = $_SESSION['role'];
            $initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
        }
        $query->close();
    }
}
// Define default values
$firstname = "Guest";
$middlename = "";
$lastname = "";
$profile_picture = "default-profile.png";
$role = "Guest";
$initials = "G"; // Default initials

// Call the function and pass variables by reference
updateUserSession($conn, $firstname, $middlename, $lastname, $profile_picture, $role, $initials);

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
        }
        
    </style>
</head>
<body>
<header class="header flex items-center justify-between bg-white p-6">
    <h2 class="text-2xl font-semibold"><?php echo $pageTitle; ?></h2>
    <div class="flex items-center">
        <!-- Bell Icon -->
        <i class="fas fa-bell text-xl mr-6"></i>
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
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars("$firstname $lastname"); ?></p>
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
    document.getElementById('profileDropdownBtn').addEventListener('click', function() {
        document.getElementById('profileDropdown').classList.toggle('hidden');
    });

    // Close the dropdown if clicked outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('profileDropdown');
        const button = document.getElementById('profileDropdownBtn');
        if (!button.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>
</body>
</html>