<?php
date_default_timezone_set('Asia/Manila'); // Set to Philippine time

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../../config/db.php';

// Process Logout
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['logout_idno'])) {
    $idno = $_POST['logout_idno'];
    $time_out = date("H:i:s");

    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update the sitin table to log out the user
        $logoutQuery = $conn->prepare("UPDATE sitin SET time_out = ? WHERE idno = ? AND sitin_date = CURDATE() AND time_out IS NULL");
        if (!$logoutQuery) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $logoutQuery->bind_param("si", $time_out, $idno);

        if (!$logoutQuery->execute()) {
            throw new Exception("Error logging out: " . $logoutQuery->error);
        }
        $affectedRows = $conn->affected_rows;
        $logoutQuery->close();

        if ($affectedRows > 0) {
            // 2. Update the corresponding reservation to mark as completed
            $updateReservation = $conn->prepare("UPDATE reservations 
                                               SET time_in_status = 'completed' 
                                               WHERE idno = ? 
                                               AND reservation_date = CURDATE() 
                                               AND time_in_status = 'sit-inned'");
            if (!$updateReservation) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $updateReservation->bind_param("i", $idno);
            
            if (!$updateReservation->execute()) {
                throw new Exception("Failed to update reservation status: " . $updateReservation->error);
            }
            $updateReservation->close();

            // 3. Deduct one session from the user's session count
            $deductSessionQuery = $conn->prepare("UPDATE users SET session = GREATEST(session - 1, 0) WHERE idno = ?");
            if (!$deductSessionQuery) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $deductSessionQuery->bind_param("i", $idno);
            if (!$deductSessionQuery->execute()) {
                throw new Exception("Failed to deduct session: " . $deductSessionQuery->error);
            }
            $deductSessionQuery->close();
        }

        // Commit transaction
        $conn->commit();

        $_SESSION['success'] = "User successfully logged out and session deducted!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    // Redirect to refresh the page
    header("Location: current_sit.php");
    exit();
}

// Fetch data from the sitin table for students who are currently sitting in
$sql = "SELECT sitin.sitin_id, sitin.idno, users.lastname, users.firstname, users.middlename, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, users.session 
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        WHERE sitin.time_out IS NULL";
$result = $conn->query($sql);

$sitinData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Determine the status based on time_out
        $row['status'] = ($row['time_out'] === null) ? 'Sit-in' : 'Not Sit-in';
        $sitinData[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Current Sit-In</title>
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
            margin: 0;
        }
        .sidebar {
            width: 5rem;
            transition: all 0.3s ease-in-out;
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
        }
        .sidebar:hover a {
            justify-content: flex-start;
        }
        .sidebar i {
            font-size: 1.5rem;
        }
        .main-content {
            margin-left: 5rem;
            transition: margin-left 0.3s ease-in-out;
        }
        .sidebar:hover + .main-content {
            margin-left: 16rem; /* Adjust content when sidebar expands */
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebarad.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Include Header -->
            <?php include 'headerad.php'; ?>
            <div class="flex-1 p-6 flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <!-- Controls (Entries, Search, Sort) -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Entries Selection (Left) -->
                        <div class="flex items-center space-x-2">
                            <label class="text-gray-600" for="entries">
                                Entries per page
                            </label>
                            <select class="border border-gray-300 rounded-md p-2" id="entries">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>

                        <!-- Search and Sort (Right) -->
                        <div class="flex items-center space-x-4">
                            <div class="relative">
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
                                <div class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">SIT ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">NAME</th>
                                    <th class="py-4 px-4 text-center">PURPOSE</th>
                                    <th class="py-4 px-4 text-center">LAB</th>
                                    <th class="py-4 px-4 text-center">SESSION</th>
                                    <th class="py-4 px-4 text-center">STATUS</th>
                                    <th class="py-4 px-4 text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sitinData)): ?>
                                    <?php foreach ($sitinData as $index => $sitin): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['sitin_id']); ?></td>
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['firstname'] . ' ' . $sitin['middlename'] . ' ' . $sitin['lastname']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['session']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['status']); ?></td>
                                            <td class="py-4 px-4 text-center">
                                                <form method="POST" action="current_sit.php">
                                                    <input type="hidden" name="logout_idno" value="<?php echo $sitin['idno']; ?>">
                                                    <button class="bg-red-500 text-white px-4 py-2 rounded logout-btn">
                                                        Time Out
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="py-4 px-4 text-center">No students currently sitting in.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>      
        </div>
    </div>
    <script>
        function toggleDropdown() {
            let dropdown = document.getElementById("sortDropdown");
            dropdown.classList.toggle("hidden");
        }

        // Close dropdown when clicking outside
        document.addEventListener("click", function(event) {
            let dropdown = document.getElementById("sortDropdown");
            let button = dropdown.previousElementSibling; // Get the button
            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add("hidden");
            }
        });
                // Entries per page functionality
                document.getElementById('entries').addEventListener('change', function() {
        const selectedValue = parseInt(this.value); // Get the selected value (10, 25, or 50)
        const rows = document.querySelectorAll('#sitinTable tbody tr'); // Get all table rows

        rows.forEach((row, index) => {
            if (index < selectedValue) {
                row.style.display = ''; // Show rows up to the selected value
            } else {
                row.style.display = 'none'; // Hide the rest
            }
        });
    });

    // Initialize table with default entries per page
    function initializeTable() {
        const defaultEntries = 5; // Default number of entries
        const rows = document.querySelectorAll('#sitinTable tbody tr'); // Get all table rows

        rows.forEach((row, index) => {
            if (index < defaultEntries) {
                row.style.display = ''; // Show rows up to the default value
            } else {
                row.style.display = 'none'; // Hide the rest
            }
        });
    }

    // Call the initialize function on page load
    initializeTable();
    </script>
</body>
</html>