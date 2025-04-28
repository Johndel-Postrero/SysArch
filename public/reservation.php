<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

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

// Notification functions
function saveAdminNotification($message, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, notification_type) VALUES (?, 'admin')");
    $stmt->bind_param("s", $message);
    $stmt->execute();
    $stmt->close();
}

function saveStudentNotification($message, $userId, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    $stmt->bind_param("si", $message, $userId);
    $stmt->execute();
    $stmt->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reserve'])) {
    header('Content-Type: application/json');
    
    try {
        $idno = $user['idno'];
        $lab_number = $_POST['lab_number'];
        $pc_number = $_POST['pc_number'];
        $purpose = ($_POST['purpose'] === 'Others') ? $_POST['other_reason'] : $_POST['purpose'];
        $reservation_date = $_POST['reservation_date'];
        $time_in = $_POST['time_in'];
        
        if (empty($lab_number) || empty($pc_number) || empty($purpose) || empty($reservation_date) || empty($time_in)) {
            throw new Exception("All fields are required");
        }

        $time_24hr = date("H:i", strtotime($time_in));
        if (!$time_24hr) {
            throw new Exception("Invalid time format");
        }
        
        $hour = (int)date('H', strtotime($time_24hr));

        if ($user['session'] <= 0) {
            throw new Exception("You don't have enough sessions left");
        }
        
        if (date('w', strtotime($reservation_date)) == 0) {
            throw new Exception("Reservations are not allowed on Sundays");
        }
        
        if ($hour < 7 || $hour >= 20) {
            throw new Exception("Reservations allowed between 7am-8pm only");
        }
        
        $checkQuery = $conn->prepare("SELECT id FROM reservations WHERE idno = ? AND reservation_date = ? AND time_in = ?");
        $checkQuery->bind_param("iss", $idno, $reservation_date, $time_24hr);
        $checkQuery->execute();
        $checkQuery->store_result();
        
        if ($checkQuery->num_rows > 0) {
            throw new Exception("You already have a reservation at this time");
        }
        
        $pcCheckQuery = $conn->prepare("
            SELECT id FROM reservations 
            WHERE lab_number = ? AND pc_number = ? AND reservation_date = ? AND time_in = ?
        ");
        $pcCheckQuery->bind_param("iiss", $lab_number, $pc_number, $reservation_date, $time_24hr);
        $pcCheckQuery->execute();
        $pcCheckQuery->store_result();
        
        if ($pcCheckQuery->num_rows > 0) {
            throw new Exception("PC already reserved at this time");
        }
        
        $pcStatusQuery = $conn->prepare("
            SELECT status FROM lab_pcs 
            WHERE lab_number = ? AND pc_number = ?
        ");
        $pcStatusQuery->bind_param("ii", $lab_number, $pc_number);
        $pcStatusQuery->execute();
        $pcStatusResult = $pcStatusQuery->get_result();
        
        if ($pcStatusResult->num_rows === 0) {
            throw new Exception("Selected PC doesn't exist in this lab");
        }
        
        $pcStatus = $pcStatusResult->fetch_assoc()['status'];
        if ($pcStatus !== 'available') {
            throw new Exception("Selected PC is not available (Status: " . $pcStatus . ")");
        }
        
        $insertQuery = $conn->prepare("
            INSERT INTO reservations (idno, lab_number, pc_number, reservation_date, time_in, purpose) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertQuery->bind_param("iiisss", $idno, $lab_number, $pc_number, $reservation_date, $time_24hr, $purpose);

        if ($insertQuery->execute()) {
            // Notify admin about new reservation request
            $adminMessage = "New reservation request from " . $user['firstname'] . " " . $user['lastname'] . 
                          " for Lab " . $lab_number . ", PC " . $pc_number . 
                          " on " . $reservation_date . " at " . $time_in;
            saveAdminNotification($adminMessage, $conn);
            
            echo json_encode(["success" => true, "message" => "Reservation created successfully!"]);
        } else {
            throw new Exception("Database error: " . $conn->error);
        }

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit();
}

$reservationsQuery = $conn->prepare("
    SELECT r.id, r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, 
           (CURRENT_TIMESTAMP >= TIMESTAMP(r.reservation_date, r.time_in)) AS is_past
    FROM reservations r 
    WHERE r.idno = ? 
    ORDER BY r.reservation_date DESC, r.time_in DESC
");
$reservationsQuery->bind_param("i", $user['idno']);
$reservationsQuery->execute();
$reservationsResult = $reservationsQuery->get_result();
$reservations = $reservationsResult->fetch_all(MYSQLI_ASSOC);
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

        .pc-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .pc-item {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            border-radius: 4px;
        }
        .pc-item:hover {
            background-color: #f0f0f0;
        }
        .pc-item.selected {
            background-color: #002044;
            color: white;
        }
        .pc-item.unavailable {
            background-color: #ffcccc;
            cursor: not-allowed;
        }
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

                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            
                            <button onclick="openModal()" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-plus"></i>
                                <span>Add Reservation</span>
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">Lab Number</th>
                                    <th class="py-4 px-4 text-center">PC Number</th>
                                    <th class="py-4 px-4 text-center">Date</th>
                                    <th class="py-4 px-4 text-center">Time</th>
                                    <th class="py-4 px-4 text-center">Purpose</th>
                                    <th class="py-4 px-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reservations)): ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center">No reservations found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reservations as $index => $reservation): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['lab_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['pc_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('F j, Y', strtotime($reservation['reservation_date']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('g:i A', strtotime($reservation['time_in']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($reservation['purpose']); ?></td>
                                            <td class="py-4 px-4 text-center">
                                                <span class="px-2 py-1 rounded-full text-xs 
                                                    <?php 
                                                        if ($reservation['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                        elseif ($reservation['status'] == 'declined') echo 'bg-red-100 text-red-800';
                                                        else echo 'bg-yellow-100 text-yellow-800';
                                                    ?>">
                                                    <?php echo htmlspecialchars(ucfirst($reservation['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="reservationModal" class="overlay hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="modal bg-white p-6 rounded-lg w-full max-w-md max-h-[700px] overflow-hidden md:overflow-auto overflow-hidden md:overflow-y-auto [&::-webkit-scrollbar]:hidden">
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
                        <label class="font-semibold">Remaining Sessions:</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['session']); ?>" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
                    </div>
                    <div class="flex flex-col text-left">
                        <label class="font-semibold">Purpose:</label>
                        <select name="purpose" class="w-full border px-3 py-2 rounded bg-white [&::-webkit-scrollbar]:hidden" onchange="toggleOtherReason()" required>
                            <option value="">Select Purpose</option>
                            <option value="C Programming">C Programming</option>
                            <option value="C# Programming">C# Programming</option>
                            <option value="Java Programming">Java Programming</option>
                            <option value="PHP Programming">PHP Programming</option>
                            <option value="ASP Net">ASP Net</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Systems Integration & Architecture">Systems Integration & Architecture</option>
                            <option value="Embedded Systems & IoT">Embedded Systems & IoT</option>
                            <option value="Digital Logic & Design">Digital Logic & Design</option>
                            <option value="Computer Application">Computer Application</option>
                            <option value="Database">Database</option>
                            <option value="Project Management">Project Management</option>
                            <option value="Mobile Application">Mobile Application</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    
                    <div id="otherReasonDiv" class="hidden flex flex-col text-left">
                        <label class="font-semibold">Specify Purpose:</label>
                        <input type="text" name="other_reason" class="w-full border px-3 py-2 rounded bg-white">
                    </div>

                    <div class="flex flex-col text-left">
                        <label class="font-semibold">Lab Number:</label>
                        <select name="lab_number" id="labSelect" class="w-full border px-3 py-2 rounded bg-white" required onchange="loadAvailablePCs()">
                            <option value="">Select Lab</option>
                            <option value="524">524</option>
                            <option value="526">526</option>
                            <option value="528">528</option>
                            <option value="530">530</option>
                            <option value="542">542</option>
                            <option value="544">544</option>
                        </select>
                    </div>
                    
                    <div class="flex flex-col text-left" id="pcSelectionContainer" style="display: none;">
                        <label class="font-semibold">Available PCs:</label>
                        <div id="pcContainer" class="pc-grid">
                            <p class="text-gray-500">Please select a lab first</p>
                        </div>
                        <input type="hidden" name="pc_number" id="selectedPC" required>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">Date:</label>
                            <input type="text" id="reservation_date" name="reservation_date" class="w-full border px-3 py-2 rounded bg-white" placeholder="Select date" required>
                        </div>
                        
                        <div class="flex flex-col text-left">
                            <label class="font-semibold">Time In:</label>
                            <input type="time" name="time_in" class="w-full border px-3 py-2 rounded bg-white" required>
                        </div>
                    </div>
                    

                </div>

                <div class="flex justify-center gap-6 pt-4">
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
        const datePicker = flatpickr("#reservation_date", {
            minDate: "today",
            maxDate: new Date().fp_incr(14),
            disable: [
                function(date) {
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
            document.getElementById('selectedPC').value = '';
            document.getElementById('pcSelectionContainer').style.display = 'none';
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
        
        async function loadAvailablePCs() {
            const labNumber = document.getElementById('labSelect').value;
            const pcContainer = document.getElementById('pcContainer');
            const pcSelectionContainer = document.getElementById('pcSelectionContainer');
            const selectedPC = document.getElementById('selectedPC');
            
            // Hide the container if no lab is selected
            if (!labNumber) {
                pcSelectionContainer.style.display = 'none';
                pcContainer.innerHTML = '<p class="text-gray-500">Please select a lab first</p>';
                selectedPC.value = '';
                return;
            }
            
            // Show the container when lab is selected
            pcSelectionContainer.style.display = 'flex';
            pcContainer.innerHTML = '<p class="text-gray-500">Loading available PCs...</p>';
            
            try {
                const response = await fetch('get_available_pcs.php?lab=' + labNumber);
                const data = await response.json();
                
                if (data.success) {
                    let html = '';
                    data.pcs.forEach(pc => {
                        const isAvailable = pc.status === 'available';
                        html += `
                            <div class="pc-item ${isAvailable ? '' : 'unavailable'}" 
                                data-pc="${pc.pc_number}" 
                                onclick="${isAvailable ? 'selectPC(this)' : ''}"
                                title="${isAvailable ? 'Available' : 'Unavailable (' + pc.status + ')'}">
                                PC ${pc.pc_number}
                            </div>
                        `;
                    });
                    pcContainer.innerHTML = html;
                } else {
                    pcContainer.innerHTML = '<p class="text-red-500">Error loading PCs: ' + data.message + '</p>';
                }
            } catch (error) {
                console.error('Error:', error);
                pcContainer.innerHTML = '<p class="text-red-500">Error loading PCs</p>';
            }
        }
        
        function selectPC(element) {
            const pcNumber = element.getAttribute('data-pc');
            const pcItems = document.querySelectorAll('.pc-item');
            
            pcItems.forEach(item => {
                item.classList.remove('selected');
            });
            
            element.classList.add('selected');
            document.getElementById('selectedPC').value = pcNumber;
        }

        document.getElementById('reservationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            try {
                const form = e.target;
                const formData = new FormData(form);
                
                formData.append('reserve', '1');
                
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

        // Table filtering and search functionality
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
    </script>
</body>
</html>