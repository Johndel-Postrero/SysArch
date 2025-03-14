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

// Fetch feedback data from the feedback table
$sql = "SELECT feedback.id, users.idno, users.lastname, users.firstname, sitin.lab_number, feedback.message, feedback.rating, feedback.created_at 
        FROM feedback 
        JOIN users ON feedback.user_id = users.id
        JOIN sitin ON feedback.sitin_id = sitin.id
        ORDER BY feedback.created_at DESC"; // Fetch feedback data sorted by creation date
$result = $conn->query($sql);

$feedbackData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $feedbackData[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feedback</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
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
        .star-rating {
            color: #ffc107; /* Yellow color for stars */
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
                        <!-- Entries Selection and Print Button (Left) -->
                        <div class="flex items-center space-x-4">
                            <!-- Entries Selection -->
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

                            <!-- Print Button -->
                            <button id="printButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-print"></i>
                                <span>Print</span>
                            </button>
                        </div>

                        <!-- Search and Sort (Right) -->
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <div class="relative dropdown">
                                <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <!-- Dropdown menu -->
                                <div id="sortDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" data-sort="az" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                    <a href="#" data-sort="za" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                    <a href="#" data-sort="newest" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                    <a href="#" data-sort="oldest" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="feedbackTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">LABORATORY</th>
                                    <th class="py-4 px-4 text-center">DATE</th>
                                    <th class="py-4 px-4 text-center">MESSAGE</th>
                                    <th class="py-4 px-4 text-center">RATING</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($feedbackData)): ?>
                                    <?php foreach ($feedbackData as $index => $feedback): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($feedback['idno']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($feedback['lab_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($feedback['created_at']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($feedback['message']); ?></td>
                                            <td class="py-4 px-4 text-center">
                                                <div class="star-rating">
                                                    <?php
                                                    $rating = $feedback['rating'];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-4 px-4 text-center">No feedback found.</td>
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
        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase(); // Get the search input value
            const rows = document.querySelectorAll('#feedbackTable tbody tr'); // Get all table rows

            rows.forEach(row => {
                const cells = row.querySelectorAll('td'); // Get all cells in the row
                let match = false;

                // Check if any cell in the row matches the search value
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                    }
                });

                // Show or hide the row based on the match
                row.style.display = match ? '' : 'none';
            });
        });

        // Sort Functionality
        const sortDropdown = document.getElementById('sortDropdown');
        const sortButton = document.getElementById('sortButton');
        const feedbackTable = document.getElementById('feedbackTable');

        // Toggle dropdown visibility
        sortButton.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent the click from closing the dropdown immediately
            sortDropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!sortDropdown.contains(event.target) && !sortButton.contains(event.target)) {
                sortDropdown.classList.add('hidden');
            }
        });

        // Handle sort selection
        sortDropdown.addEventListener('click', function(event) {
            if (event.target.tagName === 'A') {
                const sortType = event.target.getAttribute('data-sort');
                sortTable(sortType);
                sortDropdown.classList.add('hidden'); // Hide dropdown after selection
            }
        });

        // Sort table function
        function sortTable(sortType) {
            const rows = Array.from(feedbackTable.querySelectorAll('tbody tr'));

            rows.sort((a, b) => {
                const aValue = a.querySelector('td:nth-child(4)').textContent.toLowerCase(); // Message column
                const bValue = b.querySelector('td:nth-child(4)').textContent.toLowerCase();

                switch (sortType) {
                    case 'az':
                        return aValue.localeCompare(bValue); // A-Z
                    case 'za':
                        return bValue.localeCompare(aValue); // Z-A
                    case 'newest':
                        return new Date(b.querySelector('td:nth-child(3)').textContent) - new Date(a.querySelector('td:nth-child(3)').textContent); // Newest
                    case 'oldest':
                        return new Date(a.querySelector('td:nth-child(3)').textContent) - new Date(b.querySelector('td:nth-child(3)').textContent); // Oldest
                    default:
                        return 0;
                }
            });

            // Re-append sorted rows to the table
            const tbody = feedbackTable.querySelector('tbody');
            tbody.innerHTML = ''; // Clear existing rows
            rows.forEach(row => tbody.appendChild(row));
        }

        //print
        document.getElementById('printButton').addEventListener('click', function() {
            printJS({
                printable: 'feedbackTable', // Correct table ID
                type: 'html',
                style: `
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        font-family: "Poppins-Regular", sans-serif; 
                        font-size: 16px; 
                        color: #000; 
                    }
                    th, td { 
                        border: 1px solid #000; 
                        padding: 8px; 
                        text-align: center; 
                    }
                    th { 
                        font-weight: bold; 
                    }
                `,
            });
        });
    // Initialize table with default entries per page
    function initializeTable() {
        const defaultEntries = 5; // Default number of entries
        const rows = document.querySelectorAll('tbody tr'); // Get all table rows

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

    // Entries per page functionality
    document.getElementById('entries').addEventListener('change', function() {
        const selectedValue = parseInt(this.value); // Get the selected value (5, 10, 25, or 50)
        const rows = document.querySelectorAll('tbody tr'); // Get all table rows

        rows.forEach((row, index) => {
            if (index < selectedValue) {
                row.style.display = ''; // Show rows up to the selected value
            } else {
                row.style.display = 'none'; // Hide the rest
            }
        });
    });
    </script>
</body>
</html>