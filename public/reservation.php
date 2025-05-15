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

// Function to check upcoming reservations (5 minutes before)
function checkUpcomingReservations($conn, $userId) {
    $now = new DateTime();
    $currentDate = $now->format('Y-m-d');
    $currentTime = $now->format('H:i:00');
    
    // Calculate time 5 minutes from now
    $fiveMinutesLater = clone $now;
    $fiveMinutesLater->add(new DateInterval('PT2M'));
    $futureTime = $fiveMinutesLater->format('H:i:00');
    
    $query = $conn->prepare("
        SELECT r.reservation_id, r.lab_number, r.pc_number, r.reservation_date, r.time_in, 
               TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(r.reservation_date, ' ', r.time_in)) AS minutes_left
        FROM reservations r
        WHERE r.idno = ? 
        AND r.reservation_date = ?
        AND r.time_in BETWEEN ? AND ?
        AND r.status = 'approved'
        AND r.time_in_status = 'pending'
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = ? 
            AND n.message LIKE CONCAT('%Your reservation for Lab ', r.lab_number, ', PC ', r.pc_number, ' will start in 2 minutes%')
            AND DATE(n.created_at) = ?
        )
    ");
    
    $query->bind_param("isssis", $userId, $currentDate, $currentTime, $futureTime, $userId, $currentDate);
    $query->execute();
    $result = $query->get_result();
    
    $upcomingReservations = [];
    while ($row = $result->fetch_assoc()) {
        $upcomingReservations[] = $row;
    }
    
    return $upcomingReservations;
}

// Handle AJAX requests for upcoming reservations
// Handle AJAX requests for upcoming reservations
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['check_upcoming'])) {
    header('Content-Type: application/json');
    
    try {
        $upcomingReservations = checkUpcomingReservations($conn, $user['idno']);
        $notifications = [];
        
        foreach ($upcomingReservations as $reservation) {
            $minutesLeft = $reservation['minutes_left'];
            $message = "Your reservation for Lab {$reservation['lab_number']}, PC {$reservation['pc_number']} " . 
                      "will start in 2 minutes at " . date('g:i A', strtotime($reservation['time_in']));
            
            // Save notification to database
            saveStudentNotification($message, $user['idno'], $conn);
            
            $notifications[] = [
                'message' => $message,
                'reservation_id' => $reservation['reservation_id'],
                'time_in' => $reservation['time_in'],
                'reservation_date' => $reservation['reservation_date']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'has_upcoming' => !empty($notifications),
            'notifications' => $notifications
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Notification functions
function saveAdminNotification($message, $conn) {
    // Get admin user ID (assuming admin user_id is 1)
    $admin_id = 1;
    
    $stmt = $conn->prepare("INSERT INTO notifications (message, notification_type, user_id) VALUES (?, 'admin', ?)");
    $stmt->bind_param("si", $message, $admin_id);
    $stmt->execute();
    $stmt->close();
    
    // Debug logging
    error_log("Admin notification saved: $message");
}

function saveStudentNotification($message, $userId, $conn) {
    $stmt = $conn->prepare("INSERT INTO notifications (message, user_id, notification_type) VALUES (?, ?, 'student')");
    $stmt->bind_param("si", $message, $userId);
    $result = $stmt->execute();
    
    // Debug logging
    if ($result) {
        error_log("Student notification saved for user $userId: $message");
    } else {
        error_log("Failed to save student notification: " . $stmt->error);
    }
    
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
        
        $checkQuery = $conn->prepare("SELECT reservation_id FROM reservations WHERE idno = ? AND reservation_date = ? AND time_in = ?");
        $checkQuery->bind_param("iss", $idno, $reservation_date, $time_24hr);
        $checkQuery->execute();
        $checkQuery->store_result();
        
        if ($checkQuery->num_rows > 0) {
            throw new Exception("You already have a reservation at this time");
        }
        
        $pcCheckQuery = $conn->prepare("
            SELECT reservation_id FROM reservations 
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
    SELECT r.reservation_id, r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, r.time_in_status,
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

        /* Notification Styles */
        .notification-toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #002044;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            max-width: 400px;
            z-index: 9999;
            animation: slideIn 0.5s ease-out;
        }

        .notification-toast.hide {
            animation: slideOut 0.5s ease-out forwards;
        }

        .notification-close {
            cursor: pointer;
            margin-left: 15px;
            font-size: 20px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .notification-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
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
                                                <?php if ($reservation['time_in_status'] == 'completed'): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                                        Completed
                                                    </span>
                                                <?php elseif ($reservation['time_in_status'] == 'sit-inned'): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs bg-violet-100 text-violet-800">
                                                        Sit-inned
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 rounded-full text-xs 
                                                        <?php 
                                                            if ($reservation['status'] == 'approved') echo 'bg-green-100 text-green-800';
                                                            elseif ($reservation['status'] == 'declined') echo 'bg-red-100 text-red-800';
                                                            else echo 'bg-yellow-100 text-yellow-800';
                                                        ?>">
                                                        <?php echo htmlspecialchars(ucfirst($reservation['status'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
                    const availablePCs = data.pcs.filter(pc => pc.status === 'available');
                    
                    if (availablePCs.length > 0) {
                        availablePCs.forEach(pc => {
                            html += `
                                <div class="pc-item" 
                                    data-pc="${pc.pc_number}" 
                                    onclick="selectPC(this)"
                                    title="Available">
                                    PC ${pc.pc_number}
                                </div>
                            `;
                        });
                    } else {
                        html = '<p class="text-gray-500">No available PCs in this lab</p>';
                    }
                    
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

        // Global variables for pagination
        let currentPage = 1;
        let totalPages = 1;
        let currentSort = 'newest'; // Default sort

        // Main filter function with pagination
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const entriesPerPage = document.getElementById('entries').value;
            
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            let visibleRows = [];
            let totalVisible = 0;

            // First pass: filter rows by search and count visible rows
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

                if (match) {
                    visibleRows.push(row);
                    totalVisible++;
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
                const aDate = a.querySelector('td:nth-child(3)').textContent;
                const bDate = b.querySelector('td:nth-child(3)').textContent;

                switch (currentSort) {
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
        document.addEventListener('DOMContentLoaded', function() {
            filterTable();
        });

        // Enhanced notification system
// Enhanced notification system
function showNotification(message, duration = 10000) {
    // Remove any existing notifications
    const existingNotifications = document.querySelectorAll('.notification-toast');
    existingNotifications.forEach(notification => notification.remove());

    // Create toast notification
    const toast = document.createElement('div');
    toast.className = 'notification-toast';
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-bell mr-3"></i>
            <span>${message}</span>
        </div>
        <span class="notification-close">&times;</span>
    `;
    
    document.body.appendChild(toast);
    
    // Close button functionality
    const closeBtn = toast.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    });
    
    // Auto-close after duration
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, duration);

    // Play notification sound if available
    try {
        const audio = new Audio('notification-sound.mp3');
        audio.play().catch(e => console.log('Audio play failed:', e));
    } catch (e) {
        console.log('Audio not available:', e);
    }
}

// Improved check for upcoming reservations
function checkForUpcomingReservations() {
    fetch('reservation.php?check_upcoming=1')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_upcoming) {
                data.notifications.forEach(notification => {
                    // Show toast notification
                    showNotification(notification.message, 1000);
                    
                    // Also show browser notification if available
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Reservation Reminder', {
                            body: notification.message,
                            icon: 'notification-icon.png'
                        });
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error checking reservations:', error);
            showNotification("Error checking reservations. Please refresh the page.", 1000);
        });
}

// Request notification permission and set up checks
document.addEventListener('DOMContentLoaded', function() {
    // Request notification permission
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            console.log('Notification permission:', permission);
        });
    }
    
    // First check right away
    checkForUpcomingReservations();
    
    // Then check every 30 seconds for more timely notifications
    setInterval(checkForUpcomingReservations, 30 * 1000);
});
    </script>
</body>
</html>