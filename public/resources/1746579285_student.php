<?php
date_default_timezone_set('Asia/Manila'); // Set to Philippine time

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../config/db.php';

// Fetch data from the users table for students
$sql = "SELECT idno, lastname, firstname, username, middlename, course, level, email, session
        FROM users
        WHERE role = 'student'";
$result = $conn->query($sql);

$sitinData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
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
  <link rel="stylesheet" href="fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
  <link rel="stylesheet" href="css/add.css">
  <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <style>
        /* Modal Styles */
        .fixed {

            position: fixed;
            top: 95px;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .inset-0 {
            top: 10%;
            right: 0;
            bottom: 0;
            left: 50%;
        }
        .bg-black {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .bg-opacity-50 {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .flex {
            display: flex;
        }
        .items-center {
            align-items: center;
        }
        .justify-center {
            justify-content: center;
        }
        .hidden {
            display: none;
        }
        .rounded-lg {
            border-radius: 0.5rem;
        }
        .p-6 {
            padding: 1.5rem;
        }
        .w-full {
            width: 100%;
        }
        .max-w-2xl {
            max-width: 42rem;
        }
        .grid {
            display: grid;
        }
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        .md\:grid-cols-2 {
            @media (min-width: 768px) {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .gap-4 {
            gap: 1rem;
        }
        .mt-6 {
            margin-top: 1.5rem;
        }
        .space-x-4 {
            margin-right: 1rem;
        }
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
        input::placeholder {
            font-family: poppins-regular !important; /* Adjust the font family */
            font-size: 16px !important; /* Adjust the size as needed */
        }
        .inner form { width: 100%; }
        .div-button1 { height: 51px; border-radius: 6px; border: 1px solid #951313; }
        .div-button2 { height: 51px; color: white; background-color: #7952b3; border-radius: 6px; }
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
                                <option value="all">All</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
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
                                        <label class="block text-sm font-medium text-gray-700">Course</label>
                                        <select id="courseFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Courses</option>
                                            <option value="BSIT">BSIT</option>
                                            <option value="BSCS">BSCS</option>
                                            <option value="HM">HM</option>
                                            <option value="CRIM">CRIM</option>
                                            <option value="CBA">CBA</option>
                                        </select>
                                    </div>
                                    <div class="p-2">
                                        <label class="block text-sm font-medium text-gray-700">Level</label>
                                        <select id="levelFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Levels</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Sort Dropdown -->
                            <div class="relative dropdown">
                                <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <!-- Sort Dropdown Menu -->
                                <div id="sortDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="asc">A-Z</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="desc">Z-A</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <div class="flex space-x-2">
                        <button onclick="openAddModal()" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
    <i class="fas fa-plus"></i>
    <span>Add Student</span>
</button>
                            <button id="resetSession" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-clock"></i>
                                <span>Reset Session</span>
                            </button>
                        </div>
                        <!-- Export Buttons -->
                        <div class="flex space-x-2">
                            <button id="exportCSV" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-csv"></i>
                                <span>CSV</span>
                            </button>
                            <button id="exportExcel" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-excel"></i>
                                <span>Excel</span>
                            </button>
                            <button id="exportPDF" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-pdf"></i>
                                <span>PDF</span>
                            </button>
                            <button id="printButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-print"></i>
                                <span>Print</span>
                            </button>
                        </div>
                    </div>
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">FULL NAME</th>
                                    <th class="py-4 px-4 text-center">COURSE</th>
                                    <th class="py-4 px-4 text-center">LEVEL</th>
                                    <th class="py-4 px-4 text-center">EMAIL</th>
                                    <th class="py-4 px-4 text-center">SESSION</th>
                                    <th class="py-4 px-4 text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sitinData)): ?>
                                    <?php foreach ($sitinData as $index => $sitin): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname'] . ' ' . $sitin['middlename']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['course']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['level']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['email']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['session']); ?></td>
                                            <td class="py-4 px-4 text-center flex align-center justify-center space-x-2">
                                            <button onclick="openEditModal('<?php echo $sitin['idno']; ?>')" class="bg-blue-500 text-white px-2 py-2 rounded-md flex items-center space-x-2">
    <i class="fas fa-pen"></i>
</button>
                                                <button onclick="deleteStudent('<?php echo $sitin['idno']; ?>')" class="bg-red-500 text-white px-2 py-2 rounded-md flex items-center space-x-2">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
<!-- Add Student Modal -->
<div id="addStudentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-auto">
        <h2 class="text-xl font-semibold mb-4">Add Student</h2>
        <form id="addStudentForm" method="post" enctype="multipart/form-data">
            <div class="flex justify-center items-center w-full">
                <label for="add-profile-picture-upload" class="cursor-pointer relative">
                    <img id="add-profile-picture-preview" 
                         src="images/default-profile.png" 
                         alt="Profile Picture" 
                         class="rounded-full w-24 h-24 mx-auto object-cover border-2 border-gray-300"/>
                    <i class="zmdi zmdi-camera absolute bottom-2 right-2 bg-gray-700 text-white p-1 rounded-full"></i>
                </label>
                <input type="file" id="add-profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
            </div>
            <div class="form-wrapper">
                <input class="form-control mt-10" id="addIdNo" name="idno" placeholder="ID Number" type="number" required/>
                <i class="zmdi zmdi-card"></i>
            </div>
            <div class="form-group gap-6" style="background-color: transparent !important;">
                <input class="form-control" id="addLastName" name="lastname" placeholder="Last Name" type="text" required/>
                <input class="form-control" id="addFirstName" name="firstname" placeholder="First Name" type="text" required/>
                <input class="form-control" id="addMiddleName" name="middlename" placeholder="Middle Name" type="text"/>
            </div>
            <div class="form-group">
                <div class="form-wrapper" style="width: 50%; margin-right: 25px;">
                    <select id="addCourse" name="course" class="course form-control" required>
                        <option value="BSIT">BSIT</option>
                        <option value="BSCS">BSCS</option>
                        <option value="HM">HM</option>
                        <option value="CRIM">CRIM</option>
                        <option value="CBA">CBA</option>
                    </select>
                    <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                </div>
                <div class="form-wrapper" style="width: 50%;">
                    <select id="addLevel" name="level" class="level form-control" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                    <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                </div>
            </div>
            <div class="form-wrapper">
                <input class="form-control" id="addEmail" name="email" placeholder="Email Address" type="email" required/>
                <i class="zmdi zmdi-email"></i>
            </div>
            <div class="form-wrapper">
                <input class="form-control" id="addUser" name="username" placeholder="Username" type="text" required/>
                <i class="zmdi zmdi-account"></i>
            </div>
            <div class="div-button flex text-center justify-center gap-16 mt-6">
                <button class="div-button1" type="button" onclick="closeAddModal()">Cancel</button>
                <button class="div-button2" type="submit">Add Student</button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Student Modal -->
<div id="editStudentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-auto">
        <h2 class="text-xl font-semibold mb-4">Edit Student</h2>
        <form id="editStudentForm" method="post" enctype="multipart/form-data">
            <div class="flex justify-center items-center w-full">
                <label for="profile-picture-upload" class="cursor-pointer relative">
                    <img id="profile-picture-preview" 
                         src="images/default-profile.png" 
                         alt="Profile Picture" 
                         class="rounded-full w-24 h-24 mx-auto object-cover border-2 border-gray-300"/>
                    <i class="zmdi zmdi-camera absolute bottom-2 right-2 bg-gray-700 text-white p-1 rounded-full"></i>
                </label>
                <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
            </div>
            <div class="form-wrapper">
                <input class="form-control mt-10" id="editIdNo" name="idno" placeholder="ID Number" type="number"/>
                <input type="hidden" id="oldIdNo" name="oldIdNo" value="" />
                <i class="zmdi zmdi-card"></i>
            </div>
            <div class="form-group gap-6" style="background-color: transparent !important;">
                <input class="form-control" id="editLastName" name="lastname" placeholder="Last Name" type="text"/>
                <input class="form-control" id="editFirstName" name="firstname" placeholder="First Name" type="text"/>
                <input class="form-control" id="editMiddleName" name="middlename" placeholder="Middle Name" type="text"/>
            </div>
            <div class="form-group">
                <div class="form-wrapper" style="width: 50%; margin-right: 25px;">
                    <select id="editCourse" name="course" class="course form-control">
                        <option value="BSIT">BSIT</option>
                        <option value="BSCS">BSCS</option>
                        <option value="HM">HM</option>
                        <option value="CRIM">CRIM</option>
                        <option value="CBA">CBA</option>
                    </select>
                    <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                </div>
                <div class="form-wrapper" style="width: 50%;">
                    <select id="editLevel" name="level" class="level form-control">
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                    <i class="zmdi zmdi-caret-down" style="font-size: 17px; bottom: 30px;"></i>
                </div>
            </div>
            <div class="form-wrapper">
                <input class="form-control" id="editEmail" name="email" placeholder="Email Address" type="email"/>
                <i class="zmdi zmdi-email"></i>
            </div>
            <div class="form-wrapper">
                <input class="form-control" id="editUser" name="username" placeholder="Username" type="text"/>
                <i class="zmdi zmdi-account"></i>
            </div>
            <!-- Add a password field for updating the password -->
            <div class="form-wrapper">
                <input class="form-control" id="editPassword" name="password" placeholder="New Password" type="password"/>
                <i class="zmdi zmdi-lock absolute right-3 top-1/2 transform -translate-y-1/2 cursor-pointer" id="toggleEditPassword"></i>
            </div>
            <div class="div-button flex text-center justify-center gap-16 mt-6">
                <button class="div-button1" type="button" onclick="closeEditModal()">Cancel</button>
                <button class="div-button2" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

                </div>
            </div>      
        </div>
    </div>
    <script>
        // Initialize table with all entries visible by default
        function initializeTable() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            rows.forEach(row => {
                row.style.display = ''; // Ensure all rows are visible
            });
        }

        // Call the initialize function on page load
        initializeTable();

        // Entries per page functionality
        document.getElementById('entries').addEventListener('change', function() {
            const selectedValue = this.value;
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            if (selectedValue === "all") {
                rows.forEach(row => {
                    row.style.display = ''; // Show all rows
                });
            } else {
                const numEntries = parseInt(selectedValue);
                rows.forEach((row, index) => {
                    if (index < numEntries) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;

                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                    }
                });

                row.style.display = match ? '' : 'none';
            });
        });

        // Filter functionality
        const courseFilter = document.getElementById('courseFilter');
        const levelFilter = document.getElementById('levelFilter');

        function filterTable() {
            const courseValue = courseFilter.value.toLowerCase();
            const levelValue = levelFilter.value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            rows.forEach(row => {
                const courseCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const levelCell = row.querySelector('td:nth-child(4)').textContent.toLowerCase();

                const matchesCourse = courseValue ? courseCell.includes(courseValue) : true;
                const matchesLevel = levelValue ? levelCell.includes(levelValue) : true;

                row.style.display = matchesCourse && matchesLevel ? '' : 'none';
            });
        }

        courseFilter.addEventListener('change', filterTable);
        levelFilter.addEventListener('change', filterTable);

        // Toggle Filter Dropdown
        document.getElementById('filterButton').addEventListener('click', function() {
            const dropdown = document.getElementById('filterDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close Filter Dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const filterButton = document.getElementById('filterButton');
            const filterDropdown = document.getElementById('filterDropdown');
            if (!filterButton.contains(event.target) && !filterDropdown.contains(event.target)) {
                filterDropdown.classList.add('hidden');
            }
        });

        // Sort functionality
        document.getElementById('sortButton').addEventListener('click', function() {
            const dropdown = document.getElementById('sortDropdown');
            dropdown.classList.toggle('hidden');
        });

        document.querySelectorAll('#sortDropdown a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sortType = this.getAttribute('data-sort');
                const rows = Array.from(document.querySelectorAll('#sitinTable tbody tr'));

                rows.sort((a, b) => {
                    if (sortType === 'asc') {
                        const aText = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const bText = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        return aText.localeCompare(bText);
                    } else if (sortType === 'desc') {
                        const aText = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        const bText = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                        return bText.localeCompare(aText);
                    } else if (sortType === 'newest') {
                        const aDate = new Date(a.querySelector('td:nth-child(7)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(7)').textContent);
                        return bDate - aDate;
                    } else if (sortType === 'oldest') {
                        const aDate = new Date(a.querySelector('td:nth-child(7)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(7)').textContent);
                        return aDate - bDate;
                    }
                });

                const tbody = document.querySelector('#sitinTable tbody');
                tbody.innerHTML = '';
                rows.forEach(row => tbody.appendChild(row));
            });
        });

        // Close Sort Dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const sortButton = document.getElementById('sortButton');
            const sortDropdown = document.getElementById('sortDropdown');
            if (!sortButton.contains(event.target) && !sortDropdown.contains(event.target)) {
                sortDropdown.classList.add('hidden');
            }
        });

        // Export to CSV
        // Export to CSV
        document.getElementById('exportCSV').addEventListener('click', function() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            let csvContent = "data:text/csv;charset=utf-8,";
            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent)
                .join(',');
            csvContent += headers + "\n";

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent)
                        .join(',');
                    csvContent += rowData + "\n";
                }
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "student_records.csv");
            document.body.appendChild(link);
            link.click();
        });

        // Export to Excel
        // Export to Excel
        document.getElementById('exportExcel').addEventListener('click', function() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            const data = [];
            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent);
            data.push(headers);

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent);
                    data.push(rowData);
                }
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
            XLSX.writeFile(wb, "student_records.xlsx");
        });

        // Export to PDF
        // Export to PDF
        document.getElementById('exportPDF').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');

            const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(th => th.textContent);
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            const data = [];

            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const rowData = Array.from(row.querySelectorAll('td'))
                        .slice(0, -1) // Exclude the last column (Action)
                        .map(td => td.textContent);
                    data.push(rowData);
                }
            });

            doc.autoTable({
                head: [headers],
                body: data,
                startY: 20,
                margin: { top: 20 },
                styles: {
                    fontSize: 10,
                    cellPadding: 5,
                    valign: 'middle',
                    halign: 'center',
                    lineColor: [0, 0, 0],
                    lineWidth: 0.1,
                },
                headStyles: {
                    fillColor: false,
                    textColor: [0, 0, 0],
                    fontStyle: 'bold',
                    lineWidth: 0.1,
                },
                bodyStyles: {
                    fillColor: false,
                    textColor: [0, 0, 0],
                    lineWidth: 0.1,
                },
                alternateRowStyles: {
                    fillColor: false,
                },
                columnStyles: {
                    0: { cellWidth: 'auto' },
                    1: { cellWidth: 'auto' },
                    2: { cellWidth: 'auto' },
                    3: { cellWidth: 'auto' },
                    4: { cellWidth: 'auto' },
                    5: { cellWidth: 'auto' },
                },
            });

            doc.save("student_records.pdf");
        });

        // Print Table
        // Print Table
        document.getElementById('printButton').addEventListener('click', function() {
            // Clone the table to avoid modifying the original
            const table = document.getElementById('sitinTable').cloneNode(true);

            // Remove the last column (Action) from the cloned table
            const rows = table.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                if (cells.length > 0) {
                    row.removeChild(cells[cells.length - 1]); // Remove the last cell
                }
            });

            // Use printJS to print the modified table
            printJS({
                printable: table,
                type: 'html',
                style: 'table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #000; padding: 8px; text-align: center; }'
            });
        });


        // Reset Session Button
        document.getElementById('resetSession').addEventListener('click', function() {
            if (confirm("Are you sure you want to reset the session for all students?")) {
                fetch('reset_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Session reset successfully!");
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert("Error resetting session: " + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });

        // Edit Student Modal Functions
 // Preview image on file select
document.getElementById("profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById("profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Open Edit Modal with current profile picture
function openEditModal(idno) {
    fetch(`get_student.php?idno=${idno}`)
        .then(response => response.json())
        .then(data => {
            // Populate the form fields
            document.getElementById('oldIdNo').value = data.idno; // Old ID (for WHERE clause)
            document.getElementById('editIdNo').value = data.idno; // New ID (for SET clause)
            document.getElementById('editUser').value = data.username;
            document.getElementById('editFirstName').value = data.firstname;
            document.getElementById('editMiddleName').value = data.middlename;
            document.getElementById('editLastName').value = data.lastname;
            document.getElementById('editCourse').value = data.course;
            document.getElementById('editLevel').value = data.level;
            document.getElementById('editEmail').value = data.email;

            // Populate the profile picture preview
            const profilePicturePreview = document.getElementById('profile-picture-preview');
            if (data.profile_picture) {
                profilePicturePreview.src = data.profile_picture;
            } else {
                profilePicturePreview.src = 'images/default-profile.png'; // Default image if no profile picture
            }

            // Show the modal
            document.getElementById('editStudentModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editStudentModal').classList.add('hidden');
}

// Handle form submission
// Handle form submission
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    console.log("Form submitted"); // Debugging: Check if the form submission is triggered

    const formData = new FormData(this);
    console.log("FormData:", formData); // Debugging: Check the form data being sent

    fetch('update_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log("Response received:", response); // Debugging: Check the response
        return response.json();
    })
    .then(data => {
        console.log("Data:", data); // Debugging: Check the parsed JSON response
        if (data.success) {
            alert("Student updated successfully!");
            location.reload(); // Reload the page to reflect changes
        } else {
            alert("Error updating student: " + data.error);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debugging: Check for fetch errors
        alert("An unexpected error occurred.");
    });
});

// Delete Student Function
function deleteStudent(idno) {
    if (confirm("Are you sure you want to delete this student?")) {
        fetch(`delete_student.php?idno=${idno}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Student deleted successfully!");
                location.reload(); // Reload the page to reflect changes
            } else {
                alert("Error deleting student: " + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An unexpected error occurred.");
        });
    }
}

// Open Add Modal
function openAddModal() {
    document.getElementById('addStudentModal').classList.remove('hidden');
}

// Close Add Modal
function closeAddModal() {
    document.getElementById('addStudentModal').classList.add('hidden');
}

// Handle Add Student Form Submission
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Get form data
    const formData = new FormData(this);

    // Generate default password: first 4 letters of last name + first 4 digits of ID number
    const lastName = formData.get('lastname').substring(0, 4).toLowerCase();
    const idNo = formData.get('idno').toString().substring(0, 4);
    const defaultPassword = lastName + idNo;

    // Add the default password to the form data
    formData.append('password', defaultPassword);

    // Send the form data to the server
    fetch('add_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Student added successfully!");
            location.reload(); // Reload the page to reflect changes
        } else {
            alert("Error adding student: " + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert("An unexpected error occurred.");
    });
});
// Preview image on file select for Add Student Modal
document.getElementById("add-profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Update the preview image source
            document.getElementById("add-profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file); // Read the file as a data URL
    }
});

// Toggle password visibility in the edit modal
document.getElementById("toggleEditPassword").addEventListener("click", function () {
    const passwordInput = document.getElementById("editPassword");
    const icon = this;

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("zmdi-lock");
        icon.classList.add("zmdi-lock-open"); // Change icon to "open lock"
    } else {
        passwordInput.type = "password";
        icon.classList.remove("zmdi-lock-open");
        icon.classList.add("zmdi-lock"); // Change icon back to "lock"
    }
});
    </script>
</body>
</html>