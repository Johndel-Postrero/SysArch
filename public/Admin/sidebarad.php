<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/index.css">
    <title>sidebar</title>
    <style>
body { font-family: "Poppins-Regular"; color: #333; font-size: 16px; margin: 0; }
.sidebar {
    width: 5rem;
    transition: all 0.3s ease-in-out;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto; /* Allows scrolling */
    scrollbar-width: none; /* Hides scrollbar in Firefox */
}

.sidebar::-webkit-scrollbar {
    display: none; /* Hides scrollbar in Chrome, Safari, and Edge */
}

    i.fas.fa-home.mr-3, i.fas.fa-bullhorn.mr-3, i.fas.fa-file-alt.mr-3, i.fas.fa-chair.mr-3, i.fas.fa-flask.mr-3, i.fas.fa-calendar-alt.mr-3, i.fas.fa-clock.mr-3 {
        font-size: 16px;
    }
    .sidebar:hover {
        width: 16rem; /* Expanded width */
    }
    .sidebar:hover .sidebar-text {
        display: inline;
    }
    .sidebar-text {
        display: none;
        margin-bottom: 0;
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
        font-size: 16px; /* Slightly larger icons */
    }
    .dropdown-content {
        display: none;
        margin-left: 2rem;
    }
    .dropdown:hover .dropdown-content {
        display: block;
    }

        .dropdown-hidden {
            display: none;
        }
        .sidebar {
    width: 5rem;
    transition: all 0.3s ease-in-out;
    height: 100vh;
    position: fixed; /* Fixed position */
    top: 0;
    left: 0;
    overflow-y: auto; /* Allows scrolling if content is longer */
}

    </style>
</head>
<body>
<!-- sidebar.php -->
<div class="sidebar bg-[#002044] text-white w-300 hover:w-100 flex flex-col items-center py-8 transition-all duration-300">
    <img alt="CCS Sit-In Monitoring System Logo" class="mb-4" height="70" src="../inc/CCS_LOGO.png" width="70"/>
    <h1 class="text-center text-sm sidebar-text">CCS Sit-In Monitoring System</h1>
    
    <nav class="mt-10 w-full space-y-1">
        <!-- Dashboard -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors" href="adminIndex.php">
            <i class="fas fa-tachometer-alt mr-3"></i> 
            <span class="sidebar-text">Dashboard</span>
        </a>
        
        <!-- Reservations (Highlighted) -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors" href="reservationad.php">
            <i class="fas fa-calendar-check mr-3"></i> 
            <span class="sidebar-text">Reservations</span>
        </a>
        
        <!-- Sit-In Management -->
        <div class="dropdown group">
            <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors">
                <i class="fas fa-chair mr-3"></i> 
                <span class="sidebar-text">Sit-In</span>
                <i class="fas fa-chevron-down ml-auto sidebar-text text-xs transform group-hover:rotate-180 transition-transform"></i>
            </a>
            <div class="dropdown-content pl-2 mt-1">
                <a href="current_sit.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-eye mr-3"></i> Current Sit-In
                </a>
                <a href="day_sit.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-archive mr-3"></i> Records
                </a>
            </div>
        </div>
        
        <!-- Resources -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors" href="resourcesad.php">
            <i class="fas fa-boxes mr-3"></i> 
            <span class="sidebar-text">Resources</span>
        </a>
        
        <!-- Reports & Analytics -->
        <div class="dropdown group">
            <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors">
                <i class="fas fa-chart-pie mr-3"></i> 
                <span class="sidebar-text">Analytics</span>
                <i class="fas fa-chevron-down ml-auto sidebar-text text-xs transform group-hover:rotate-180 transition-transform"></i>
            </a>
            <div class="dropdown-content pl-2 mt-1">
                <a href="generate.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-file-export mr-3"></i> Generate Reports
                </a>
                <a href="leaderboard.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-trophy mr-3"></i> Leaderboard
                </a>
            </div>
        </div>
        
        <!-- Student Management -->
        <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors" href="student.php">
            <i class="fas fa-user-graduate mr-3"></i> 
            <span class="sidebar-text">Students</span>
        </a>
        
        <!-- Communication -->
        <div class="dropdown group">
            <a class="flex items-center py-3 px-4 text-white hover:bg-blue-700 rounded-lg mx-2 transition-colors">
                <i class="fas fa-comments mr-3"></i> 
                <span class="sidebar-text">Communication</span>
                <i class="fas fa-chevron-down ml-auto sidebar-text text-xs transform group-hover:rotate-180 transition-transform"></i>
            </a>
            <div class="dropdown-content pl-2 mt-1">
                <a href="Cannouncement.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-bullhorn mr-3"></i> Announcements
                </a>
                <a href="feedbackad.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-comment-dots mr-3"></i> Feedback
                </a>
                <a href="labsched.php" class="flex items-center py-2 px-4 text-white hover:bg-blue-600 rounded-lg mx-2 text-sm">
                    <i class="fas fa-comment-dots mr-3"></i> Lab Schedule
                </a>
            </div>
        </div>
    </nav>
</div>
</html>