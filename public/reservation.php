<?php
// Start session at the very top
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

// Fetch user's details
$username = $_SESSION['login_user'];
$sql = "SELECT idno, lastname, firstname, session FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "User not found"]);
    exit();
}

// Process reservation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reserve'])) {
    header('Content-Type: application/json');
    
    try {
        $idno = $user['idno'];
        $lab_number = $_POST['lab_number'];
        $purpose = ($_POST['purpose'] === 'Others') ? $_POST['other_reason'] : $_POST['purpose'];
        $reservation_date = $_POST['reservation_date'];
        $time_in = $_POST['time_in'];
        
        // Validate inputs
        if (empty($lab_number) || empty($purpose) || empty($reservation_date) || empty($time_in)) {
            throw new Exception("All fields are required");
        }

        // Convert to 24-hour format
        $time_24hr = date("H:i", strtotime($time_in));
        if (!$time_24hr) {
            throw new Exception("Invalid time format");
        }
        
        $hour = (int)date('H', strtotime($time_24hr));

        // Validation checks
        if ($user['session'] <= 0) {
            throw new Exception("You don't have enough sessions left");
        }
        
        if (date('w', strtotime($reservation_date)) == 0) {
            throw new Exception("Reservations are not allowed on Sundays");
        }
        
        if ($hour < 7 || $hour >= 20) {
            throw new Exception("Reservations allowed between 7am-8pm only");
        }
        
        // Check existing reservations
        $checkQuery = $conn->prepare("SELECT id FROM reservations WHERE idno = ? AND reservation_date = ? AND time_in = ?");
        $checkQuery->bind_param("iss", $idno, $reservation_date, $time_24hr);
        $checkQuery->execute();
        $checkQuery->store_result();
        
        if ($checkQuery->num_rows > 0) {
            throw new Exception("You already have a reservation at this time");
        }
        
        // Check lab availability
        $labCheckQuery = $conn->prepare("SELECT id FROM reservations WHERE lab_number = ? AND reservation_date = ? AND time_in = ?");
        $labCheckQuery->bind_param("iss", $lab_number, $reservation_date, $time_24hr);
        $labCheckQuery->execute();
        $labCheckQuery->store_result();
        
        if ($labCheckQuery->num_rows > 0) {
            throw new Exception("Lab already reserved at this time");
        }
        
        // Create reservation
        $insertQuery = $conn->prepare("INSERT INTO reservations (idno, lab_number, reservation_date, time_in, purpose) VALUES (?, ?, ?, ?, ?)");
        $insertQuery->bind_param("iisss", $idno, $lab_number, $reservation_date, $time_24hr, $purpose);
        
        if ($insertQuery->execute()) {
            echo json_encode(["success" => true, "message" => "Reservation created successfully!"]);
        } else {
            throw new Exception("Database error: " . $conn->error);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit();
}

// Modify your reservations query in the main page to:
$reservationsQuery = $conn->prepare("
    SELECT r.id, r.lab_number, r.reservation_date, r.time_in, r.purpose, r.status, 
           (SELECT COUNT(*) FROM sitin s 
            WHERE s.idno = r.idno 
            AND s.lab_number = r.lab_number 
            AND s.sitin_date = r.reservation_date 
            AND s.time_in = r.time_in) AS has_sitin,
           (SELECT COUNT(*) FROM sitin s 
            WHERE s.idno = r.idno 
            AND s.time_out IS NULL) AS has_active_sitin,
           (CURRENT_TIMESTAMP >= TIMESTAMP(r.reservation_date, r.time_in) - INTERVAL 30 MINUTE) AS button_active,
           (CURRENT_TIMESTAMP BETWEEN TIMESTAMP(r.reservation_date, r.time_in) - INTERVAL 30 MINUTE 
            AND TIMESTAMP(r.reservation_date, r.time_in) + INTERVAL 15 MINUTE) AS within_grace_period,
           (CURRENT_TIMESTAMP > TIMESTAMP(r.reservation_date, r.time_in) + INTERVAL 15 MINUTE) AS too_late
    FROM reservations r 
    WHERE r.idno = ? 
    ORDER BY r.reservation_date DESC, r.time_in DESC
");
$reservationsQuery->bind_param("i", $user['idno']);
$reservationsQuery->execute();
$reservationsResult = $reservationsQuery->get_result();
$sitinData = $reservationsResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reservations</title>
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .status-approved { color: green; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal {
            background: white;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .sidebar {
            width: 5rem;
            transition: all 0.3s ease-in-out;
        }
        .sidebar:hover { width: 16rem; }
        .sidebar:hover .sidebar-text { display: inline; }
        .sidebar-text { display: none; }
        .sidebar a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .sidebar:hover a { justify-content: flex-start; }
        .sidebar i { font-size: 1.5rem; }
        .main-content {
            margin-left: 5rem;
            transition: margin-left 0.3s ease-in-out;
        }
        .sidebar:hover + .main-content { margin-left: 16rem; }
        input[type="time"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem;
            width: 100%;
        }
        .header { z-index: 100; position: relative; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="main-content flex-1 flex flex-col">
            <?php include 'header.php'; ?>
            
            <div class="flex-1 p-6">
                <div class="max-w-6xl mx-auto">
                    <!-- Controls (Entries, Search, Filter) -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Entries Selection -->
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
                                        <label class="block text-sm font-medium text-gray-700">Lab</label>
                                        <select id="courseFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Laboratory</option>
                                            <option value="524">524</option>
                                            <option value="526">526</option>
                                            <option value="528">528</option>
                                            <option value="530">530</option>
                                            <option value="542">542</option>
                                            <option value="544">544</option>
                                        </select>
                                    </div>
                                    <div class="p-2">
                                        <label class="block text-sm font-medium text-gray-700">Purpose</label>
                                        <select id="levelFilter" class="w-full border border-gray-300 rounded-md p-2 mt-1">
                                            <option value="">All Purpose</option>
                                            <option value="C Programming">C Programming</option>
                                            <option value="C# Programming">C# Programming</option>
                                            <option value="Java Programming">Java Programming</option>
                                            <option value="PHP Programming">PHP Programming</option>
                                            <option value="ASP Net">ASP Net</option>
                                            <option value="Others">Others</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add Reservation Button -->
                            <button onclick="openModal()" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-plus"></i>
                                <span>Add Reservation</span>
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">Lab Number</th>
                                    <th class="py-4 px-4 text-center">Date</th>
                                    <th class="py-4 px-4 text-center">Time</th>
                                    <th class="py-4 px-4 text-center">Purpose</th>
                                    <th class="py-4 px-4 text-center">Status</th>
                                    <th class="py-4 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php if (empty($sitinData)): ?>
        <tr>
            <td colspan="6" class="py-4 px-4 text-center">No reservations found</td>
        </tr>
    <?php else: ?>
        <?php foreach ($sitinData as $index => $sitin): ?>
            <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('F j, Y', strtotime($sitin['reservation_date']))); ?></td>
                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('g:i A', strtotime($sitin['time_in']))); ?></td>
                <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                <td class="py-4 px-4 text-center">
                    <span class="px-2 py-1 rounded-full text-xs 
                        <?php 
                            if ($sitin['status'] == 'approved') echo 'bg-green-100 text-green-800';
                            elseif ($sitin['status'] == 'declined') echo 'bg-red-100 text-red-800';
                            else echo 'bg-yellow-100 text-yellow-800';
                        ?>">
                        <?php echo htmlspecialchars(ucfirst($sitin['status'])); ?>
                    </span>
                </td>
                <td class="py-4 px-4 text-center">
                    <?php if ($sitin['status'] == 'pending'): ?>
                        <!-- Edit and Delete buttons for pending reservations -->
                        <button onclick="editReservation(<?php echo $sitin['id']; ?>)" class="text-blue-500 hover:text-blue-700 mx-1">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteReservation(<?php echo $sitin['id']; ?>)" class="text-red-500 hover:text-red-700 mx-1">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    <?php elseif ($sitin['status'] == 'approved'): ?>
                        <!-- Sit-in button for approved reservations -->
                        <?php if ($sitin['has_sitin'] > 0): ?>
                            <button class="bg-gray-300 text-gray-600 px-3 py-1 rounded cursor-not-allowed">
                                Sit-In
                            </button>
                        <?php elseif ($sitin['too_late']): ?>
                            <button class="bg-gray-300 text-gray-600 px-3 py-1 rounded cursor-not-allowed" title="Sit-in period has expired (15 minutes grace period passed)">
                                Sit-In
                            </button>
                        <?php elseif ($sitin['button_active']): ?>
                            <button onclick="recordSitin(<?php echo $sitin['id']; ?>)" class="bg-[#002044] text-white px-3 py-1 rounded hover:bg-[#003366]">
                                Sit-In
                            </button>
                        <?php else: ?>
                            <button class="bg-gray-300 text-gray-600 px-3 py-1 rounded cursor-not-allowed" title="Sit-in available starting <?php echo date('g:i A', strtotime($sitin['reservation_date'].' '.$sitin['time_in'].' - 30 minutes')); ?>">
                                Sit-In
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- No action for declined reservations -->
                        <span class="text-gray-400">No action</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
                        </table>
                    </div>
                    <?php include 'pagination.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Modal -->
    <div id="reservationModal" class="overlay hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="modal bg-white p-6 rounded-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">New Reservation</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="reservationForm" class="space-y-4">
                <div class="flex flex-col space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">ID Number:</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['idno']); ?>" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
                        </div>
                        
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">Student Name:</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
                        </div>
                    </div>
                    
                    <div class="flex flex-col text-left">
                        <label class="font-semibold">Sessions Left:</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['session']); ?>" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
                    </div>
                    
                    <div class="flex flex-col text-left">
                        <label class="font-semibold">Lab Number:</label>
                        <select name="lab_number" class="w-full border px-3 py-2 rounded bg-white" required>
                            <option value="">Select Lab</option>
                            <option value="524">524</option>
                            <option value="526">526</option>
                            <option value="528">528</option>
                            <option value="530">530</option>
                            <option value="542">542</option>
                            <option value="544">544</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">Date:</label>
                            <input type="text" id="reservation_date" name="reservation_date" class="w-full border px-3 py-2 rounded bg-white" placeholder="Select date" required>
                        </div>
                        
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">Time:</label>
                            <input type="time" name="time_in" class="w-full border px-3 py-2 rounded bg-white" required>
                        </div>
                    </div>
                    
                    <div class="flex flex-col text-left">
                        <label class="font-semibold">Purpose:</label>
                        <select name="purpose" class="w-full border px-3 py-2 rounded bg-white" onchange="toggleOtherReason()" required>
                            <option value="">Select Purpose</option>
                            <option value="C Programming">C Programming</option>
                            <option value="C# Programming">C# Programming</option>
                            <option value="Java Programming">Java Programming</option>
                            <option value="PHP Programming">PHP Programming</option>
                            <option value="ASP Net">ASP Net</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    
                    <div id="otherReasonDiv" class="hidden flex flex-col text-left">
                        <label class="font-semibold">Specify Purpose:</label>
                        <input type="text" name="other_reason" class="w-full border px-3 py-2 rounded bg-white">
                    </div>
                </div>

                <div class="flex justify-center gap-4 pt-4">
                    <button type="button" onclick="closeModal()" class="w-40 h-12 border border-red-700 text-red-700 font-semibold rounded-lg hover:bg-red-700 hover:text-white transition duration-300">
                        Cancel
                    </button>
                    <button type="submit" class="w-40 h-12 bg-purple-700 text-white font-semibold rounded-lg hover:bg-purple-800 transition duration-300">
                        Reserve
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize date picker
        const datePicker = flatpickr("#reservation_date", {
            minDate: "today",
            maxDate: new Date().fp_incr(14), // 14 days from now
            disable: [
                function(date) {
                    // Disable Sundays
                    return (date.getDay() === 0);
                }
            ],
            dateFormat: "Y-m-d"
        });
        
        function openModal() {
            document.getElementById('reservationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('reservationModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function toggleOtherReason() {
            const purposeSelect = document.querySelector('select[name="purpose"]');
            const otherReasonDiv = document.getElementById('otherReasonDiv');
            
            if (purposeSelect.value === 'Others') {
                otherReasonDiv.classList.remove('hidden');
                document.querySelector('input[name="other_reason"]').required = true;
            } else {
                otherReasonDiv.classList.add('hidden');
                document.querySelector('input[name="other_reason"]').required = false;
            }
        }
        
        // Handle form submission with fetch API
        document.getElementById('reservationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                const form = e.target;
                const formData = new FormData(form);
                
                // Explicitly add the reserve field
                formData.append('reserve', '1');
                
                // Format the date properly before submission
                const selectedDate = datePicker.selectedDates[0];
                if (selectedDate) {
                    const formattedDate = datePicker.formatDate(selectedDate, 'Y-m-d');
                    formData.set('reservation_date', formattedDate);
                }
                
                const response = await fetch('reservation.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('reservationModal');
            if (event.target === modal) {
                closeModal();
            }
        };

        // Initialize table with all entries visible by default
        function initializeTable() {
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            rows.forEach(row => {
                row.style.display = '';
            });
        }
        initializeTable();

        // Entries per page functionality
        document.getElementById('entries').addEventListener('change', function() {
            const selectedValue = this.value;
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            
            rows.forEach(row => row.style.display = '');
            
            if (selectedValue !== "all") {
                const numEntries = parseInt(selectedValue);
                rows.forEach((row, index) => {
                    if (index >= numEntries) {
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
                let match = false;
                for (let i = 0; i < row.cells.length - 1; i++) {
                    if (row.cells[i].textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                        break;
                    }
                }
                row.style.display = match ? '' : 'none';
            });
        });

        // Filter functionality
        document.getElementById('courseFilter').addEventListener('change', filterTable);
        document.getElementById('levelFilter').addEventListener('change', filterTable);

        function filterTable() {
            const labValue = document.getElementById('courseFilter').value.toLowerCase();
            const purposeValue = document.getElementById('levelFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            
            rows.forEach(row => {
                const labCell = row.cells[0].textContent.toLowerCase();
                const purposeCell = row.cells[3].textContent.toLowerCase();
                
                const matchesLab = labValue ? labCell.includes(labValue) : true;
                const matchesPurpose = purposeValue ? purposeCell.includes(purposeValue) : true;
                
                row.style.display = matchesLab && matchesPurpose ? '' : 'none';
            });
        }

        // Toggle dropdowns
        document.getElementById('filterButton').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('filterDropdown').classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('filterDropdown').classList.add('hidden');
        });

        // Edit reservation
        function editReservation(reservationId) {
            console.log("Edit reservation:", reservationId);
            // Implement edit functionality here
            alert("Edit functionality will be implemented here for reservation ID: " + reservationId);
        }

        // Delete reservation
        function deleteReservation(reservationId) {
            if (confirm("Are you sure you want to delete this reservation?")) {
                fetch('delete_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${reservationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Reservation deleted successfully!");
                        location.reload();
                    } else {
                        alert("Error: " + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("An error occurred while deleting the reservation.");
                });
            }
        }

// Record sit-in
// Record sit-in
function recordSitin(reservationId) {
    if (confirm("Are you ready to sit-in? This will record your attendance.")) {
        fetch('record_sitin.php', {
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
                    throw new Error(text);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message);
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred: " + error.message);
        });
    }
}
    </script>
</body>
</html>