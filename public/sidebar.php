<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/index.css">
    <title>Sidebar</title>
    <style>
        body {
            font-family: "Poppins-Regular";
            margin: 0;
        }
        .sidebar {
            width: 5rem;
            transition: all 0.3s ease-in-out;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .sidebar::-webkit-scrollbar {
            display: none;
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
            transition: all 0.2s ease;
        }
        .sidebar:hover a {
            justify-content: flex-start;
        }
        .sidebar i {
            font-size: 1.25rem;
            min-width: 1.5rem;
            text-align: center;
        }
        .dropdown-content {
            display: none;
            margin-left: 1.5rem;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
    </style>
</head>
<body>
<!-- sidebar.php -->
<div class="sidebar bg-[#002044] text-white flex flex-col items-center py-8">
    <img alt="CCS Sit-In Monitoring System Logo" class="mb-4" height="70" src="inc/CCS_LOGO.png" width="70"/>
    <h1 class="text-center text-sm sidebar-text">CCS Sit-In Monitoring System</h1>
    
    <nav class="mt-10 w-full px-2">
        <!-- Dashboard -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="index.php">
            <i class="fas fa-home mr-3"></i> 
            <span class="sidebar-text">Home</span>
        </a>
        
        <!-- Announcements -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="announcement.php">
            <i class="fas fa-bullhorn mr-3"></i> 
            <span class="sidebar-text">Announcements</span>
        </a>

        <!-- Rules & Regulations -->
        <div class="dropdown group">
            <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg transition-colors">
                <i class="fas fa-clipboard-list mr-3"></i> 
                <span class="sidebar-text">Rules & Regulations</span>
                <i class="fas fa-chevron-down ml-auto sidebar-text text-xs transform group-hover:rotate-180 transition-transform"></i>
            </a>
            <div class="dropdown-content">
                <a class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg text-sm" href="sitin.php">
                    <i class="fas fa-chair mr-3"></i> 
                    <span class="sidebar-text">Sit-In</span>
                </a>
                <a class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg text-sm" href="laboratory.php">
                    <i class="fas fa-flask mr-3"></i> 
                    <span class="sidebar-text">Laboratory</span>
                </a>
            </div>
        </div>

        <!-- History -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="history.php">
            <i class="fas fa-history mr-3"></i> 
            <span class="sidebar-text">History</span>
        </a> 
        
        <!-- Resources 
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="resources.php">
            <i class="fas fa-boxes mr-3"></i> 
            <span class="sidebar-text">Resources</span>
        </a> -->

        <!-- Lab Schedule -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="lab.php">
            <i class="fas fa-calendar-check mr-3"></i> 
            <span class="sidebar-text">Lab Schedule</span>
        </a> 

        <!--Reservations (Highlighted)-->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="reservation.php">
            <i class="fas fa-calendar-check mr-3"></i> 
            <span class="sidebar-text">Reservations</span>
        </a> 
        
        <!-- Leaderboard -->
         <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg" href="leader.php">
            <i class="fas fa-trophy mr-3"></i> 
            <span class="sidebar-text">Leaderboard</span>
        </a>

    </nav>
</div>
</body>
</html>