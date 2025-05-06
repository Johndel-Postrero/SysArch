<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../../config/db.php';

// Fetch pending reservations
$pendingSql = "SELECT r.reservation_id, r.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, 
               r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, r.created_at
        FROM reservations r
        JOIN users u ON r.idno = u.idno
        WHERE r.status = 'pending'
        ORDER BY r.reservation_date DESC, r.time_in DESC";

$pendingResult = $conn->query($pendingSql);
$pendingReservations = [];
if ($pendingResult->num_rows > 0) {
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingReservations[] = $row;
    }
}

// Fetch approved reservations that haven't been timed in yet
$approvedSql = "SELECT r.reservation_id, r.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, 
               r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, r.created_at
        FROM reservations r
        JOIN users u ON r.idno = u.idno
        WHERE r.status = 'approved' AND r.time_in_status = 'pending'
        ORDER BY r.reservation_date DESC, r.time_in DESC";

$approvedResult = $conn->query($approvedSql);
$approvedReservations = [];
if ($approvedResult->num_rows > 0) {
    while ($row = $approvedResult->fetch_assoc()) {
        $approvedReservations[] = $row;
    }
}

// Fetch reservation logs (declined/sit-inned)
// Fetch reservation logs (declined/sit-inned/completed)
$logsSql = "SELECT r.reservation_id, r.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, 
           r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, 
           CASE 
               WHEN r.status = 'declined' THEN 'declined'
               WHEN r.time_in_status = 'sit-inned' AND 
                    EXISTS (SELECT 1 FROM sitin s WHERE s.idno = r.idno AND s.lab_number = r.lab_number 
                            AND s.sitin_date = r.reservation_date AND s.time_out IS NULL) THEN 'sit-inned'
               WHEN r.time_in_status = 'completed' OR 
                    EXISTS (SELECT 1 FROM sitin s WHERE s.idno = r.idno AND s.lab_number = r.lab_number 
                            AND s.sitin_date = r.reservation_date AND s.time_out IS NOT NULL) THEN 'completed'
               WHEN r.status = 'approved' THEN 'approved'
               ELSE r.status
           END as status,
           r.created_at
    FROM reservations r
    JOIN users u ON r.idno = u.idno
    WHERE r.status != 'pending'
    ORDER BY r.reservation_date DESC, r.time_in DESC";

$logsResult = $conn->query($logsSql);
$reservationLogs = [];
if ($logsResult->num_rows > 0) {
    while ($row = $logsResult->fetch_assoc()) {
        $reservationLogs[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reservations - Admin</title>
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
            margin-left: 16rem;
        }
        .status-pending { color: orange; }
        .status-approved { color: green; }
        .status-declined { color: red; }
        .tab-active {
            border-bottom: 3px solid #002044;
            color: #002044;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
                    <!-- Tabs -->
                    <div class="flex border-b mb-4">
                        <button id="pendingTab" class="px-4 py-2 tab-active" onclick="switchTab('pending')">
                            <i class="fas fa-hourglass-half mr-2"></i> Pending
                        </button>
                        <button id="approvedTab" class="px-4 py-2" onclick="switchTab('approved')">
                            <i class="fas fa-check-circle mr-2"></i> Approved
                        </button>
                        <button id="logsTab" class="px-4 py-2" onclick="switchTab('logs')">
                            <i class="fas fa-history mr-2"></i> Logs
                        </button>
                    </div>

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
                                <option value="all" selected>All</option>
                            </select>
                        </div>

                        <!-- Search, Filter, and Sort (Right) -->
                        <div class="flex items-center space-x-4">
                            <!-- Search -->
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <!-- Filter Dropdown -->
                            <div class="relative dropdown">
                                <button id="filterButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <i class="fas fa-filter"></i>
                                    <span>Filter</span>
                                </button>
                                <!-- Filter Dropdown Menu -->
                                <div id="filterDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-48 hidden">
                                    <div class="p-2">
                                        <label class="block text-sm font-medium text-gray-700">Laboratory</label>
                                        <select id="labFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Labs</option>
                                            <option value="524">524</option>
                                            <option value="526">526</option>
                                            <option value="528">528</option>
                                            <option value="530">530</option>
                                            <option value="542">542</option>
                                            <option value="544">544</option>
                                        </select>
                                    </div>
                                    <div id="statusFilterContainer" class="p-2 hidden">
                                        <label class="block text-sm font-medium text-gray-700">Status</label>
                                        <select id="statusFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Status</option>
                                            <option value="approved">Approved</option>
                                            <option value="declined">Declined</option>
                                            <option value="sit-inned">Sit-Inned</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Sort Dropdown -->
                            <div class="relative dropdown">
                                <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <!-- Dropdown menu -->
                                <div id="sortDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" data-sort="name-asc" class="block px-4 py-2 hover:bg-gray-100">Name (A-Z)</a>
                                    <a href="#" data-sort="name-desc" class="block px-4 py-2 hover:bg-gray-100">Name (Z-A)</a>
                                    <a href="#" data-sort="date-asc" class="block px-4 py-2 hover:bg-gray-100">Date (Oldest)</a>
                                    <a href="#" data-sort="date-desc" class="block px-4 py-2 hover:bg-gray-100">Date (Newest)</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Reservations Tab Content -->
                    <div id="pendingContent" class="tab-content active">
                        <div class="overflow-x-auto">
                            <table id="pendingTable" class="min-w-full bg-white shadow-md rounded-lg">
                                <thead>
                                    <tr class="bg-[#002044] text-white">
                                        <th class="py-4 px-4 text-center">ID Number</th>
                                        <th class="py-4 px-4 text-center">Student Name</th>
                                        <th class="py-4 px-4 text-center">Lab</th>
                                        <th class="py-4 px-4 text-center">PC</th>
                                        <th class="py-4 px-4 text-center">Date</th>
                                        <th class="py-4 px-4 text-center">Time In</th>
                                        <th class="py-4 px-4 text-center">Purpose</th>
                                        <th class="py-4 px-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingReservations)): ?>
                                        <tr>
                                            <td colspan="9" class="py-4 px-4 text-center">No pending reservations</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingReservations as $index => $reservation): ?>
                                            <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['idno']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars($reservation['lastname'] . ', ' . $reservation['firstname'] . ' ' . substr($reservation['middlename'], 0, 1) . '.'); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['lab_number']); ?></td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['pc_number']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['reservation_date']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('g:i A', strtotime($reservation['time_in']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <div class="flex justify-center space-x-2">
                                                        <button onclick="approveReservation(<?php echo $reservation['reservation_id']; ?>)" 
                                                            class="bg-blue-500 text-white px-4 py-2 rounded">
                                                            Approve
                                                        </button>
                                                        <button onclick="declineReservation(<?php echo $reservation['reservation_id']; ?>)" 
                                                            class="bg-red-500 text-white px-4 py-2 rounded">
                                                            Decline
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Pending -->
                        <div class="flex justify-between items-center mt-4">
                            <div class="text-gray-600" id="pendingPaginationInfo"></div>
                            <div class="flex space-x-2" id="pendingPaginationControls"></div>
                        </div>
                    </div>

                    <!-- Approved Reservations Tab Content -->
                    <div id="approvedContent" class="tab-content">
                        <div class="overflow-x-auto">
                            <table id="approvedTable" class="min-w-full bg-white shadow-md rounded-lg">
                                <thead>
                                    <tr class="bg-[#002044] text-white">
                                        <th class="py-4 px-4 text-center">ID Number</th>
                                        <th class="py-4 px-4 text-center">Student Name</th>
                                        <th class="py-4 px-4 text-center">Lab</th>
                                        <th class="py-4 px-4 text-center">PC</th>
                                        <th class="py-4 px-4 text-center">Date</th>
                                        <th class="py-4 px-4 text-center">Time In</th>
                                        <th class="py-4 px-4 text-center">Purpose</th>
                                        <th class="py-4 px-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($approvedReservations)): ?>
                                        <tr>
                                            <td colspan="9" class="py-4 px-4 text-center">No approved reservations</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($approvedReservations as $index => $reservation): ?>
                                            <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['idno']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars($reservation['lastname'] . ', ' . $reservation['firstname'] . ' ' . substr($reservation['middlename'], 0, 1) . '.'); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['lab_number']); ?></td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['pc_number']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['reservation_date']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('g:i A', strtotime($reservation['time_in']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <button onclick="timeInReservation(<?php echo $reservation['reservation_id']; ?>)" 
                                                        class="bg-blue-500 text-white px-4 py-2 rounded">
                                                        Time In
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Approved -->
                        <div class="flex justify-between items-center mt-4">
                            <div class="text-gray-600" id="approvedPaginationInfo"></div>
                            <div class="flex space-x-2" id="approvedPaginationControls"></div>
                        </div>
                    </div>

                    <!-- Logs Tab Content -->
                    <div id="logsContent" class="tab-content">
                        <div class="overflow-x-auto">
                            <table id="logsTable" class="min-w-full bg-white shadow-md rounded-lg">
                                <thead>
                                    <tr class="bg-[#002044] text-white">
                                        <th class="py-4 px-4 text-center">ID Number</th>
                                        <th class="py-4 px-4 text-center">Student Name</th>
                                        <th class="py-4 px-4 text-center">Lab</th>
                                        <th class="py-4 px-4 text-center">PC</th>
                                        <th class="py-4 px-4 text-center">Date</th>
                                        <th class="py-4 px-4 text-center">Time In</th>
                                        <th class="py-4 px-4 text-center">Purpose</th>
                                        <th class="py-4 px-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reservationLogs)): ?>
                                        <tr>
                                            <td colspan="9" class="py-4 px-4 text-center">No reservation logs found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($reservationLogs as $index => $log): ?>
                                            <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($log['idno']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars($log['lastname'] . ', ' . $log['firstname'] . ' ' . substr($log['middlename'], 0, 1) . '.'); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($log['lab_number']); ?></td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($log['pc_number']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('M j, Y', strtotime($log['reservation_date']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <?php echo htmlspecialchars(date('g:i A', strtotime($log['time_in']))); ?>
                                                </td>
                                                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($log['purpose']); ?></td>
                                                <td class="py-4 px-4 text-center">
                                                    <span class="px-2 py-1 rounded-full text-xs 
                                                        <?php 
                                                            if ($log['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                            elseif ($log['status'] == 'declined') echo 'bg-red-100 text-red-800';
                                                            elseif ($log['status'] == 'sit-inned') echo 'bg-violet-100 text-violet-800';
                                                            elseif ($log['status'] == 'completed') echo 'bg-blue-100 text-blue-800';
                                                            else echo 'bg-yellow-100 text-yellow-800';
                                                        ?>">
                                                        <?php 
                                                            if ($log['status'] == 'sit-inned') {
                                                                echo 'Sit-Inned';
                                                            } elseif ($log['status'] == 'completed') {
                                                                echo 'Completed';
                                                            } else {
                                                                echo htmlspecialchars(ucfirst($log['status']));
                                                            }
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Logs -->
                        <div class="flex justify-between items-center mt-4">
                            <div class="text-gray-600" id="logsPaginationInfo"></div>
                            <div class="flex space-x-2" id="logsPaginationControls"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables for pagination
        let currentPage = 1;
        let totalPages = 1;

        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('[id$="Tab"]').forEach(tab => {
                tab.classList.remove('tab-active');
            });
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName + 'Content').classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('tab-active');
            
            // Reset search and filters when switching tabs
            document.getElementById('searchInput').value = '';
            document.getElementById('labFilter').value = '';
            document.getElementById('statusFilter').value = '';
            
            // Show/hide status filter based on tab
            const statusFilterContainer = document.getElementById('statusFilterContainer');
            if (tabName === 'logs') {
                statusFilterContainer.classList.remove('hidden');
            } else {
                statusFilterContainer.classList.add('hidden');
            }
            
            // Reset to first page when switching tabs
            currentPage = 1;
            
            // Reset table display and update pagination
            filterTable();
        }

        // Main filter function with pagination
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const labValue = document.getElementById('labFilter').value.toLowerCase();
            const statusValue = document.getElementById('statusFilter').value.toLowerCase();
            const entriesPerPage = document.getElementById('entries').value;
            const activeTab = document.querySelector('.tab-content.active').id;
            
            const activeTable = document.querySelector('.tab-content.active').querySelector('table');
            const rows = activeTable.querySelectorAll('tbody tr');
            let visibleRows = [];
            let totalVisible = 0;

            // First pass: count all matching rows
            rows.forEach((row) => {
                const cells = row.querySelectorAll('td');
                const labCell = cells[2].textContent.toLowerCase();
                let matchesSearch = searchValue ? 
                    Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(searchValue)) : true;
                let matchesLab = labValue ? labCell.includes(labValue) : true;
                let matchesStatus = true;

                if (activeTab === 'logsContent' && statusValue) {
                    const statusCell = cells[7].textContent.toLowerCase();
                    matchesStatus = statusCell.includes(statusValue);
                }

                if (matchesSearch && matchesLab && matchesStatus) {
                    visibleRows.push(row);
                    totalVisible++;
                }
            });

            // Show all rows if "All" is selected
            if (entriesPerPage === "all") {
                rows.forEach(row => row.style.display = 'none');
                visibleRows.forEach(row => row.style.display = '');
                updatePaginationControls(totalVisible, true, activeTab);
                return;
            }

            // Calculate total pages for paginated results
            const entriesNum = parseInt(entriesPerPage);
            totalPages = Math.ceil(totalVisible / entriesNum);
            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            } else if (totalPages === 0) {
                currentPage = 1;
            }

            // Second pass: show/hide rows based on pagination
            const startIndex = (currentPage - 1) * entriesNum;
            const endIndex = startIndex + entriesNum;

            rows.forEach(row => row.style.display = 'none');
            visibleRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

            // Update pagination controls
            updatePaginationControls(totalVisible, false, activeTab);
        }

        function updatePaginationControls(totalVisible, showAll, activeTab) {
            const entriesPerPage = document.getElementById('entries').value;
            const tabName = activeTab.replace('Content', '');
            const paginationInfo = document.getElementById(tabName + 'PaginationInfo');
            const paginationControls = document.getElementById(tabName + 'PaginationControls');
            
            if (entriesPerPage === "all" || showAll) {
                // Show all entries - hide pagination controls
                paginationInfo.textContent = `Showing all ${totalVisible} entries`;
                paginationControls.innerHTML = '';
                return;
            }
            
            // Show paginated results
            const entriesNum = parseInt(entriesPerPage);
            const startEntry = totalVisible === 0 ? 0 : (currentPage - 1) * entriesNum + 1;
            const endEntry = Math.min(currentPage * entriesNum, totalVisible);
            
            // Update pagination info
            paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalVisible} entries`;
            
            // Update pagination controls
            paginationControls.innerHTML = '';
            
            // Previous button
            const prevButton = document.createElement('button');
            prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevButton.className = `px-3 py-1 rounded-md border ${currentPage === 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    filterTable();
                }
            });
            paginationControls.appendChild(prevButton);
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            if (startPage > 1) {
                const firstPageButton = document.createElement('button');
                firstPageButton.textContent = '1';
                firstPageButton.className = 'px-3 py-1 rounded-md border bg-white text-[#002044] hover:bg-gray-100';
                firstPageButton.addEventListener('click', () => {
                    currentPage = 1;
                    filterTable();
                });
                paginationControls.appendChild(firstPageButton);
                
                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.textContent = '...';
                    ellipsis.className = 'px-2 py-1';
                    paginationControls.appendChild(ellipsis);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = `px-3 py-1 rounded-md border ${i === currentPage ? 'bg-[#002044] text-white' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
                pageButton.addEventListener('click', () => {
                    currentPage = i;
                    filterTable();
                });
                paginationControls.appendChild(pageButton);
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.textContent = '...';
                    ellipsis.className = 'px-2 py-1';
                    paginationControls.appendChild(ellipsis);
                }
                
                const lastPageButton = document.createElement('button');
                lastPageButton.textContent = totalPages;
                lastPageButton.className = 'px-3 py-1 rounded-md border bg-white text-[#002044] hover:bg-gray-100';
                lastPageButton.addEventListener('click', () => {
                    currentPage = totalPages;
                    filterTable();
                });
                paginationControls.appendChild(lastPageButton);
            }
            
            // Next button
            const nextButton = document.createElement('button');
            nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextButton.className = `px-3 py-1 rounded-md border ${currentPage === totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
            nextButton.disabled = currentPage === totalPages;
            nextButton.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    filterTable();
                }
            });
            paginationControls.appendChild(nextButton);
        }

        // Event listeners for all filters
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('labFilter').addEventListener('change', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('entries').addEventListener('change', function() {
            currentPage = 1; // Reset to first page when changing entries per page
            filterTable();
        });

        // Initialize table with default filters and pagination
        filterTable();

        // Approve/Decline reservation functions
        function approveReservation(reservationId) {
            if (confirm("Are you sure you want to approve this reservation?")) {
                updateReservationStatus(reservationId, 'approved');
            }
        }

        function declineReservation(reservationId) {
            if (confirm("Are you sure you want to decline this reservation?")) {
                updateReservationStatus(reservationId, 'declined');
            }
        }

        function updateReservationStatus(reservationId, status) {
    fetch('update_reservation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `reservation_id=${reservationId}&status=${status}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json().catch(() => {
            throw new Error('Invalid JSON response');
        });
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function timeInReservation(reservationId) {
    if (confirm("Are you sure you want to mark this reservation as timed in?")) {
        fetch('time_in_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `reservation_id=${reservationId}`
        })
        .then(response => {
            // First check if the response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error(`Invalid response: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error: ' + error.message);
        });
    }
}
    </script>
</body>
</html>