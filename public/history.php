<?php
// Prevent caching
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

// Function to check for foul words
function containsFoulWords($message, $foulWords) {
    foreach ($foulWords as $word) {
        if (stripos($message, $word) !== false) {
            return $word; // Return the foul word found
        }
    }
    return false; // No foul words found
}

// Function to save a notification
// Function to save a notification specifically for admins
function saveAdminNotification($message, $conn) {
    // Get admin user ID (assuming admin user_id is 1)
    $admin_id = 1;
    
    $stmt = $conn->prepare("INSERT INTO notifications (message, notification_type, user_id) VALUES (?, 'admin', ?)");
    $stmt->bind_param("si", $message, $admin_id);
    $stmt->execute();
    $stmt->close();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitFeedback'])) {
    $userId = $_SESSION['user_id']; // Assuming the user ID is stored in the session
    $sitinId = intval($_POST['sitin_id']); // Get sitin_id from the form
    $rating = intval($_POST['rating']);
    $message = $conn->real_escape_string($_POST['message']);

    // Validate rating (must be between 1 and 5)
    if ($rating < 1 || $rating > 5) {
        die("Invalid rating value.");
    }

    // Validate sitin_id (check if it exists in the sitin table)
    $checkSitinQuery = "SELECT sitin_id FROM sitin WHERE sitin_id = '$sitinId'";
    $checkSitinResult = $conn->query($checkSitinQuery);

    if ($checkSitinResult->num_rows === 0) {
        die("Invalid sitin_id: The provided sitin_id does not exist in the sitin table.");
    }

    // Define foul words
    $foulWords = [
        //Tagalog
        "putang ina",
        "putangina",
        "tang ina",
        "tangina",
        "tang ina mo",
        "tangina mo",
        "puta",
        "pota",
        "gago",
        "gaga",
        "bobo",
        "boba",
        "ulol",
        "ulul",
        "tarantado",
        "tanga",
        "tengene",
        "bwisit",
        "bwisit ka",
        "leche",
        "letse",
        "lintik",
        "punyeta",
        "pakyu",
        "pakyo",
        "pakshet",
        "putragis",
        "putang inamo",
        "putangina mo",
        "hayop",
        "ulol",
        "pucha",
        "pakshet",
        "pakyu",

        // Mispelled versions of existing words
        "ptang ina",
        "ptangina",
        "put@ng in@",
        "put@ngin@",
        "putang1na",
        "putang!na",
        "tang ina mo",
        "tang1na",
        "t@ng in@",
        "t@ngin@",
        "tangena",
        "tang1n@ mo",
        "put@",
        "p0ta",
        "put4",
        "putah",
        "g@go",
        "g@ga",
        "gag0",
        "g@gu",
        "b0b0",
        "b0ba",
        "bub0",
        "bubu",
        "ul0l",
        "u1ol",
        "ulul",
        "tarantadu",
        "tarantad0",
        "t@rantado",
        "tanga mo",
        "tengene",
        "teng3n3",
        "t3ng3n3",
        "bwisit",
        "bw1s1t",
        "bwesit",
        "bwiset",
        "lech3",
        "letse",
        "l3tse",
        "lint1k",
        "lint!k",
        "punyeta",
        "pnyeta",
        "p@nyeta",
        "pakyu",
        "p@kyu",
        "pakyo",
        "p@kyo",
        "pakshet",
        "p@kshet",
        "paksyet",
        "putragis",
        "putrag!s",
        "putrag1s",
        "putang inamo",
        "putang1namo",
        "put@nginamo",
        "hayop",
        "h@yop",
        "hay0p",
        "ul0l",
        "ulul",
        "pucha",
        "puch@",
        "puche",
        "pakshet",
        "p@kshet",
        "pakyu",
        "p@kyu",

        //Bisaya
        "giatay",
        "atay",
        "minatay",
        "pisti",
        "piste",
        "yawa",
        "yawaa",
        "buang",
        "buanga",
        "tanga",
        "pisting yawa",

        //English 
        "f you",
        "fuck you",
        "fuck",
        "shit",
        "bitch", 
        "motherfucker",
        "asshole",
        "nigger",
        "dipshit",
        "nigga",
        "niga".
        "bulshit",
        "fucker"

    ];
        
        
    // Check for foul words
    $detectedFoulWord = containsFoulWords($message, $foulWords);

    if ($detectedFoulWord) {
        // Notify admin and handle foul word detection
    // Notify admin and handle foul word detection
    $notificationMessage = "Foul language detected in feedback from id no: " . $_SESSION['idno'] . ". Detected word: " . $detectedFoulWord;
    saveAdminNotification($notificationMessage, $conn); // Save as admin notification

        // Insert feedback into the database even if foul words are detected
        $sql = "INSERT INTO feedback (user_id, sitin_id, message, rating) VALUES ('$userId', '$sitinId', '$message', '$rating')";
        if ($conn->query($sql)) {
            echo "<script>alert('Your feedback contains inappropriate language. Please revise your message.');</script>";
            $_SESSION['feedback_success'] = true;
            header("Location: history.php");
            exit();
        } else {
            echo "<script>alert('Error submitting feedback: " . $conn->error . "');</script>";
        }
    } else {
        // Insert feedback into the database if no foul words are detected
        $sql = "INSERT INTO feedback (user_id, sitin_id, message, rating) VALUES ('$userId', '$sitinId', '$message', '$rating')";
        if ($conn->query($sql)) {
            $_SESSION['feedback_success'] = true;
            header("Location: history.php");
            exit();
        } else {
            echo "<script>alert('Error submitting feedback: " . $conn->error . "');</script>";
        }
    }
}

// Fetch data from the sitin table (or any other relevant table)
$loggedInUserIdno = $_SESSION['idno']; // Assuming the user's idno is stored in the session

$sql = "SELECT sitin.sitin_id, sitin.idno, users.lastname, users.firstname, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at,
               feedback.feedback_id AS feedback_id
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        LEFT JOIN feedback ON sitin.sitin_id = feedback.sitin_id AND feedback.user_id = '{$_SESSION['user_id']}'
        WHERE sitin.time_out IS NOT NULL AND sitin.idno = '$loggedInUserIdno'"; // Filter by logged-in user's idno

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
    <title>History</title>
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 100;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
        }
        .star-rating {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .star-rating .star {
            cursor: pointer;
            font-size: 24px;
            color: #ccc;
            margin: 0 5px;
        }
        .star-rating .star.selected {
            color: #ffcc00;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <?php
    if (isset($_SESSION['feedback_success'])) {
        echo "<script>alert('Feedback submitted successfully!');</script>";
        unset($_SESSION['feedback_success']); // Clear the session variable
    }
    ?>
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Include Header -->
            <?php include 'header.php'; ?>
            <div class="flex-1 p-6 flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <div class="flex justify-between items-center mb-4">
                        <!-- Entries Selection (Left) -->
                        <div class="flex items-center space-x-2">
                            <label class="text-gray-600" for="entries">
                                Entries per page
                            </label>
                            <select class="border border-gray-300 rounded-md p-2" id="entries">
                                <option value="all" selected>All</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>

                        <!-- Search and Sort (Right) -->
                        <div class="flex items-center space-x-4">
                            <!-- Search Input -->
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <!-- Filter Button and Dropdown -->
                            <div class="relative dropdown">
                                <button id="filterButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <i class="fas fa-filter"></i>
                                    <span>Filter</span>
                                </button>
                                <div id="filterDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" data-filter="all" class="block px-4 py-2 hover:bg-gray-100">All</a>
                                    <a href="#" data-filter="done" class="block px-4 py-2 hover:bg-gray-100">Done</a>
                                    <a href="#" data-filter="not-done" class="block px-4 py-2 hover:bg-gray-100">Not Done</a>
                                </div>
                            </div>

                            <!-- Sort Button and Dropdown -->
                            <div class="relative dropdown">
                                <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <div id="sortDropdown" class="dropdown-content absolute left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="asc">A-Z</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="desc">Z-A</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="newest">Newest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="oldest">Oldest</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">LABORATORY</th>
                                    <th class="py-4 px-4 text-center">PURPOSE</th>
                                    <th class="py-4 px-4 text-center">LOGIN</th>
                                    <th class="py-4 px-4 text-center">LOGOUT</th>
                                    <th class="py-4 px-4 text-center">DATE</th>
                                    <th class="py-4 px-4 text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sitinData)): ?>
                                    <?php foreach ($sitinData as $index => $sitin): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('h:i A', strtotime($sitin['time_in']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('h:i A', strtotime($sitin['time_out']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('Y-m-d', strtotime($sitin['created_at']))); ?></td>
                                            <td class="py-4 px-4 text-center">
                                                <?php if (!empty($sitin['feedback_id'])): ?>
                                                    <span class="text-gray-500">Done</span>
                                                <?php else: ?>
                                                    <a href="#" class="feedback-link text-blue-500" data-id="<?php echo $sitin['sitin_id']; ?>">Feedback</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center">No data found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-4">
                        <div class="text-gray-600" id="paginationInfo"></div>
                        <div class="flex space-x-2" id="paginationControls"></div>
                    </div>
                </div>
            </div>      
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <h2 class="text-xl font-bold mb-4">Leave Feedback</h2>
            <form method="POST" action="">
                <input type="hidden" name="sitin_id" id="sitinIdInput">
                <div class="star-rating">
                    <span class="star" data-value="1">&#9733;</span>
                    <span class="star" data-value="2">&#9733;</span>
                    <span class="star" data-value="3">&#9733;</span>
                    <span class="star" data-value="4">&#9733;</span>
                    <span class="star" data-value="5">&#9733;</span>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">
                <textarea class="w-full p-2 border border-gray-300 rounded-md mt-4" name="message" placeholder="Your message..." rows="4" required></textarea>
                <div class="flex justify-end mt-4">
                    <button type="submit" name="submitFeedback" id="submitFeedback" class="bg-blue-500 text-white px-4 py-2 rounded-md">Submit</button>
                    <button type="button" id="closeModal" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md">Close</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables for pagination
        let currentPage = 1;
        let totalPages = 1;
        let currentSort = 'newest'; // Default sort
        let currentFilter = 'all'; // Default filter

        // Initialize dropdown functionality
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdownContent = this.querySelector('.dropdown-content');
                if (dropdownContent) {
                    dropdownContent.classList.toggle('hidden');
                }
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });

        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            currentPage = 1;
            filterTable();
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
                        const aDate = new Date(a.querySelector('td:nth-child(5)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(5)').textContent);
                        return bDate - aDate;
                    } else if (sortType === 'oldest') {
                        const aDate = new Date(a.querySelector('td:nth-child(5)').textContent);
                        const bDate = new Date(b.querySelector('td:nth-child(5)').textContent);
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

        // Filter Functionality
        document.querySelectorAll('#filterDropdown a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                currentFilter = this.getAttribute('data-filter');
                currentPage = 1;
                filterTable();
                document.getElementById('filterDropdown').classList.add('hidden');
            });
        });

        // Main filter function with pagination
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const entriesPerPage = document.getElementById('entries').value;
            
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            let visibleRows = [];
            let totalVisible = 0;

            // First pass: filter rows by search and filter
            rows.forEach((row) => {
                const cells = row.querySelectorAll('td');
                let match = searchValue === '';
                
                if (searchValue !== '') {
                    cells.forEach(cell => {
                        if (cell.textContent.toLowerCase().includes(searchValue)) {
                            match = true;
                        }
                    });
                }

                // Apply filter
                if (match) {
                    const statusCell = row.querySelector('td:last-child');
                    const status = statusCell.textContent.trim().toLowerCase();
                    
                    if (currentFilter === 'all' || 
                        (currentFilter === 'done' && status === 'done') ||
                        (currentFilter === 'not-done' && status !== 'done')) {
                        visibleRows.push(row);
                        totalVisible++;
                    }
                }
            });

            // Sort the visible rows
            sortVisibleRows(visibleRows);

            // Show all rows if "All" is selected
            if (entriesPerPage === "all") {
                rows.forEach(row => row.style.display = 'none');
                visibleRows.forEach(row => row.style.display = '');
                updatePaginationControls(totalVisible, true);
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
            updatePaginationControls(totalVisible, false);
        }

        // Sort visible rows based on current sort type
        function sortVisibleRows(rows) {
            rows.sort((a, b) => {
                const aPurpose = a.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const bPurpose = b.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const aDate = a.querySelector('td:nth-child(5)').textContent;
                const bDate = b.querySelector('td:nth-child(5)').textContent;

                switch (currentSort) {
                    case 'az':
                        return aPurpose.localeCompare(bPurpose);
                    case 'za':
                        return bPurpose.localeCompare(aPurpose);
                    case 'newest':
                        return new Date(bDate) - new Date(aDate);
                    case 'oldest':
                        return new Date(aDate) - new Date(bDate);
                    default:
                        return 0;
                }
            });
        }

        // Update pagination controls
        function updatePaginationControls(totalVisible, showAll) {
            const entriesPerPage = document.getElementById('entries').value;
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');
            
            if (entriesPerPage === "all" || showAll) {
                paginationInfo.textContent = `Showing all ${totalVisible} entries`;
                paginationControls.innerHTML = '';
                return;
            }
            
            const entriesNum = parseInt(entriesPerPage);
            const startEntry = totalVisible === 0 ? 0 : (currentPage - 1) * entriesNum + 1;
            const endEntry = Math.min(currentPage * entriesNum, totalVisible);
            
            paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalVisible} entries`;
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

        // Entries per page functionality
        document.getElementById('entries').addEventListener('change', function() {
            currentPage = 1;
            filterTable();
        });

        // Initialize table on page load
        filterTable();

        // Feedback Modal Functionality
        const modal = document.getElementById('feedbackModal');
        const closeModal = document.getElementById('closeModal');
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('ratingInput');
        const submitFeedback = document.getElementById('submitFeedback');

        // Open modal when clicking feedback link
        document.querySelectorAll('.feedback-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sitinId = this.getAttribute('data-id');
                document.getElementById('sitinIdInput').value = sitinId;
                modal.style.display = 'flex';
            });
        });

        // Close modal
        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
            resetRating();
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
                resetRating();
            }
        });

        // Star rating functionality
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                ratingInput.value = value;
                updateStars(value);
            });

            star.addEventListener('mouseover', function() {
                const value = this.getAttribute('data-value');
                updateStars(value);
            });
        });

        // Reset stars when mouse leaves the rating container
        document.querySelector('.star-rating').addEventListener('mouseleave', function() {
            const currentRating = ratingInput.value;
            updateStars(currentRating);
        });

        function updateStars(value) {
            stars.forEach(star => {
                const starValue = star.getAttribute('data-value');
                if (starValue <= value) {
                    star.style.color = '#ffcc00';
                } else {
                    star.style.color = '#ccc';
                }
            });
        }

        function resetRating() {
            ratingInput.value = '0';
            stars.forEach(star => {
                star.style.color = '#ccc';
            });
        }

        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (ratingInput.value === '0') {
                e.preventDefault();
                alert('Please select a rating before submitting.');
            }
        });
    </script>
</body>
</html>