<?php
// Only start session if one isn't active already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Assume $user is fetched from the database after verifying login credentials.
$_SESSION['firstname'] = $user['firstname'];
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
$initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
$role = isset($user['role']) ? $user['role'] : "";
$profile_picture = isset($user['profile_picture']) ? $user['profile_picture'] : "default-profile.png";
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
        }
        #search-button {
    position: relative;
    z-index: 9999;
}


    </style>
</head>
<body>
<header class="header flex items-center justify-between bg-white p-6">
    <h2 class="text-2xl font-semibold"><?php echo $pageTitle; ?></h2>
    
    <!-- Search Bar (Visible on Larger Screens) -->
    <form action="search_results.php" method="GET" class="relative w-80 flex items-center sm:flex hidden">
        <div class="relative w-full">
            <button type="submit" class="absolute inset-y-0 right-3 flex items-center">
                <i class="fas fa-search text-gray-400"></i>
            </button>
            <input type="text" name="query" placeholder="Search by Name or ID" 
                class="w-full py-2 pl-5 pr-10 h-10 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
    </form>
    
    <div class="flex items-center">
        <!-- Bell Icon -->
        <i class="fas fa-bell text-xl mr-6"></i>
        <!-- Profile Dropdown -->
        <div class="relative">
            <div class="flex items-center cursor-pointer" id="profileDropdownBtn">
                <!-- Profile Initials -->
                <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
                    <?php 
                    if ($profile_picture && file_exists(__DIR__ . '/../public/upload/' . $profile_picture)) {
                        echo '<img src="upload/' . htmlspecialchars($profile_picture) . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
                    } else {
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
    $(document).ready(function() {
    $("#search").on("input", function() {
        $.ajax({
            url: "search.php",
            method: "POST",
            data: { query: $(this).val() },
            success: function(response) {
                $("#search-results").html(response);
            }
        });
    });
});

</script>
</body>
</html>
