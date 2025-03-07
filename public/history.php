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
    $checkSitinQuery = "SELECT id FROM sitin WHERE id = '$sitinId'";
    $checkSitinResult = $conn->query($checkSitinQuery);

    if ($checkSitinResult->num_rows === 0) {
        die("Invalid sitin_id: The provided sitin_id does not exist in the sitin table.");
    }

    // Insert feedback into the database
    $sql = "INSERT INTO feedback (user_id, sitin_id, message, rating) VALUES ('$userId', '$sitinId', '$message', '$rating')";
    if ($conn->query($sql)) {
        // Set a session variable for the success message
        $_SESSION['feedback_success'] = true;
        // Redirect to the same page to prevent form resubmission
        header("Location: history.php");
        exit();
    } else {
        echo "<script>alert('Error submitting feedback: " . $conn->error . "');</script>";
    }
}

// Fetch data from the sitin table (or any other relevant table)
$sql = "SELECT sitin.id, sitin.idno, users.lastname, users.firstname, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at,
               feedback.id AS feedback_id
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        LEFT JOIN feedback ON sitin.id = feedback.sitin_id AND feedback.user_id = '{$_SESSION['user_id']}'
        WHERE sitin.time_out IS NOT NULL
        AND sitin.idno = '{$_SESSION['login_user']}'"; // Add this line to filter by the logged-in user

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
                        <table class="min-w-full bg-white shadow-md rounded-lg">
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
                                                    <a href="#" class="feedback-link text-blue-500" data-id="<?php echo $sitin['id']; ?>">Feedback</a>
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
                <textarea class="w-full p-2 border border-gray-300 rounded-md mt-4" name="message" placeholder="Your message..." rows="4"></textarea>
                <div class="flex justify-end mt-4">
                    <button type="submit" name="submitFeedback" id="submitFeedback" class="bg-blue-500 text-white px-4 py-2 rounded-md">Submit</button>
                    <button type="button" id="closeModal" class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md">Close</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Feedback Modal Logic
        const feedbackLinks = document.querySelectorAll('.feedback-link');
        const modal = document.getElementById('feedbackModal');
        const closeModal = document.getElementById('closeModal');
        const stars = document.querySelectorAll('.star-rating .star');
        const ratingInput = document.getElementById('ratingInput');

        feedbackLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const sitinId = link.getAttribute('data-id');
                document.getElementById('sitinIdInput').value = sitinId; // Set the hidden input value
                modal.style.display = 'flex';
            });
        });

        closeModal.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        stars.forEach(star => {
            star.addEventListener('click', () => {
                const value = star.getAttribute('data-value');
                ratingInput.value = value; // Set the hidden input value
                stars.forEach(s => s.classList.remove('selected'));
                for (let i = 0; i < value; i++) {
                    stars[i].classList.add('selected');
                }
            });
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
    </script>
 <script>
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

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchValue = this.value.toLowerCase(); // Get the search input value
        const rows = document.querySelectorAll('tbody tr'); // Get all table rows

        rows.forEach(row => {
            const cells = row.querySelectorAll('td'); // Get all cells in the row
            let match = false;

            cells.forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchValue)) {
                    match = true;
                }
            });

            row.style.display = match ? '' : 'none'; // Show or hide the row based on the match
        });
    });

    // Sort functionality
    const sortDropdown = document.getElementById('sortDropdown');
    const sortButton = document.getElementById('sortButton');

// Toggle dropdown visibility
sortButton.addEventListener('click', function(event) {
    event.stopPropagation(); // Stop event propagation
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
        const rows = Array.from(document.querySelectorAll('tbody tr'));

        rows.sort((a, b) => {
            const aValue = a.querySelector('td:nth-child(2)').textContent.toLowerCase(); // Sort by Purpose column
            const bValue = b.querySelector('td:nth-child(2)').textContent.toLowerCase();

            switch (sortType) {
                case 'az':
                    return aValue.localeCompare(bValue); // A-Z
                case 'za':
                    return bValue.localeCompare(aValue); // Z-A
                case 'newest':
                    return new Date(b.querySelector('td:nth-child(5)').textContent) - new Date(a.querySelector('td:nth-child(5)').textContent); // Newest
                case 'oldest':
                    return new Date(a.querySelector('td:nth-child(5)').textContent) - new Date(b.querySelector('td:nth-child(5)').textContent); // Oldest
                default:
                    return 0;
            }
        });

        // Re-append sorted rows to the table
        const tbody = document.querySelector('tbody');
        tbody.innerHTML = ''; // Clear existing rows
        rows.forEach(row => tbody.appendChild(row));
    }

// Filter functionality
const filterDropdown = document.getElementById('filterDropdown');
const filterButton = document.getElementById('filterButton');

// Toggle dropdown visibility
filterButton.addEventListener('click', function(event) {
    event.stopPropagation(); // Stop event propagation
    filterDropdown.classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!filterDropdown.contains(event.target) && !filterButton.contains(event.target)) {
        filterDropdown.classList.add('hidden');
    }
});

    // Handle filter selection
    filterDropdown.addEventListener('click', function(event) {
        if (event.target.tagName === 'A') {
            const filterType = event.target.getAttribute('data-filter');
            filterTable(filterType);
            filterDropdown.classList.add('hidden'); // Hide dropdown after selection
        }
    });

    // Filter table function
    function filterTable(filterType) {
        const rows = document.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const actionCell = row.querySelector('td:nth-child(6)').textContent.toLowerCase(); // Action column
            const isDone = actionCell.includes('done');

            switch (filterType) {
                case 'done':
                    row.style.display = isDone ? '' : 'none'; // Show only "Done" rows
                    break;
                case 'not-done':
                    row.style.display = !isDone ? '' : 'none'; // Show only "Not Done" rows
                    break;
                default:
                    row.style.display = ''; // Show all rows
            }
        });
    }
</script>
</body>
</html>