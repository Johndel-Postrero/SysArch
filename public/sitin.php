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
    <title>Sit-In</title>
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
                    <h4 class="text-lg font-bold mb-4">SIT-IN RULES AND REGULATIONS</h4>
                    <p class="mb-2">To ensure a conducive and respectful learning environment for all, please adhere to the following Sit-In rules:</p>
                    <ol class="list-decimal list-inside space-y-2">
                        <li>Only authorized sit-in students with prior approval from the instructor are allowed.</li>
                        <li>Sit-in students must not disrupt the class or engage in side conversations.</li>
                        <li>Mobile phones and other electronic devices must be set to silent mode during the session.</li>
                        <li>Sit-in students must not use laboratory computers unless explicitly permitted by the instructor.</li>
                        <li>Seats are prioritized for officially enrolled students. Sit-in students should occupy vacant seats only.</li>
                        <li>Participation in discussions or activities is allowed only if the instructor permits.</li>
                        <li>Eating, drinking, or any form of littering is strictly prohibited inside the classroom.</li>
                        <li>Sit-in students must follow the instructor’s guidelines and classroom rules at all times.</li>
                        <li>Disruptive behavior, including excessive talking, arguing, or any form of distraction, will not be tolerated.</li>
                        <li>Failure to follow these rules may result in the immediate removal from the class and possible restrictions on future sit-in requests.</li>
                    </ol>
                    <h4 class="text-lg font-bold mt-4 mb-2">DISCIPLINARY ACTION</h4>
                    <ol class="list-inside space-y-2">
                        <li>First Offense - A verbal warning will be issued by the instructor.</li>
                        <li>Second Offense - The student will be asked to leave and reported to the administration.</li>
                        <li>Third Offense - A formal complaint may be filed, leading to further disciplinary actions.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
