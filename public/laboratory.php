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
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Laboratory</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        .main-content-expanded {
            width: 100%;
        }
        .hidden {
            display: none;
        }

        .dropdown-hidden {
            display: none;
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
                <div class="main-con bg-white p-6 rounded-lg shadow-lg border border-gray-200 max-w-3xl w-full">
                    <h2 class="text-xl font-semibold text-center mb-4">University of Cebu</h2>
                    <h3 class="text-lg font-medium text-center mb-4">COLLEGE OF INFORMATION & COMPUTER STUDIES</h3>
                    <h4 class="text-lg font-bold mb-4">LABORATORY RULES AND REGULATIONS</h4>
                    <p class="mb-2">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>
                    <ol class="list-decimal list-inside space-y-2">
                        <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                        <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                        <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                        <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                        <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                        <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                        <li>Observe proper decorum while inside the laboratory.</li>
                        <li>Do not get inside the lab unless the instructor is present.</li>
                        <li>All bags, knapsacks, and the likes must be deposited at the counter.</li>
                        <li>Follow the seating arrangement of your instructor.</li>
                        <li>At the end of class, all software programs must be closed.</li>
                        <li>Return all chairs to their proper places after using.</li>
                        <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                        <li>Anyone causing a continual disturbance will be asked to leave the lab.</li>
                        <li>Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                        <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                        <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                        <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
                    </ol>
                    <h4 class="text-lg font-bold mt-4 mb-2">DISCIPLINARY ACTION</h4>
                    <ol class="list-inside space-y-2">
                        <li>First Offense - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</li>
                        <li>Second and Subsequent Offenses - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>