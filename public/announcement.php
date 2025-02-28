<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
?>
<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}
?>
<html>
<head>
    <title>Announcements</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
    <style>
body {
    font-family: "Poppins-Regular";
    color: #333;
    font-size: 16px;
    margin: 0; }
    header{
        z-index: 1;
    }
    .sidebar {
        width: 5rem; /* Default width */
        transition: all 0.3s ease-in-out;
    }
    .sidebar:hover {
        width: 16rem; /* Expanded width */
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
        justify-content: center; /* Centers the icons */
        padding: 1rem;
    }
    .sidebar:hover a {
        justify-content: flex-start; /* Aligns text to the left on hover */
    }
    .sidebar i {
        font-size: 1.5rem; /* Slightly larger icons */
    }
    .dropdown-content {
        display: none;
        margin-left: 2rem;
    }
    .dropdown:hover .dropdown-content {
        display: block;
    }
    body {
    margin: 0;
}

.main-content {
    margin-left: 5rem; /* Adjust based on the sidebar width */
    transition: margin-left 0.3s ease-in-out; /* Smooth transition */
}

.sidebar:hover + .main-content {
    margin-left: 16rem; /* Adjust content when sidebar expands */

}
.dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-content a:hover {background-color: #f1f1f1}
        .dropdown:hover .dropdown-content {display: block;}
</style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Include Header -->
            <?php include 'header.php'; ?>
            <div class="flex-1 p-6 flex justify-center items-center">
                <div class="main-con p-6 max-w-4xl w-full">
                    <!-- Search and Filter -->
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="relative flex-1">
                            <input class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="relative dropdown">
                            <button class="flex items-center space-x-2 text-gray-600 relative">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                </svg>
                                <span>Sort</span>
                            </button>
                            <!-- Dropdown menu -->
                            <div class="dropdown-content absolute left-1/2 transform -translate-x-1/2 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 w-32">
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
                            </div>
                        </div>
                    </div>
                    <!-- Announcement Card -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center mb-4">
                            <img alt="Admin Avatar" class="w-10 h-10 rounded-full" src="https://placehold.co/40x40" />
                            <div class="ml-4">
                                <p class="font-semibold">John Wayne · Admin</p>
                                <p class="text-sm text-gray-500">5h</p>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold mb-2">New Sit-In Rules Implemented</h2>
                        <p class="text-gray-700 mb-4">Sitin Rules have been changed due to szbn rtyua dfghlz bnm werty uiopa szbn rtyua dfghlz bnm werty uiopa szbn rtyua dfghlz bnm werty uiopa szbn rtyua.</p>
                        <img alt="People planting trees" class="w-full rounded-lg mb-4" src="https://placehold.co/600x300" />
                        <button class="flex items-center mt-5 space-x-2 text-gray-600">
                            <i class="fas fa-comment"></i>
                            <span>Comment</span>
                        </button>
                    </div>
                </div>
            </div>
 
        </div>
    </div>
</body>
</html>