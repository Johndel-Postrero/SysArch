<?php
// Start session at the very top
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// Fetch user's lastname and firstname from the database
$username = $_SESSION['login_user'];
$sql = "SELECT idno, lastname, firstname FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    // For API response, set JSON header.
    header("Content-Type: application/json");
    echo json_encode(["error" => "User not found"]);
    exit();
}

$idno = $user['idno'];
$lastname = $user['lastname'];
$firstname = $user['firstname'];

// Check if the request method is POST and has JSON input.
// If it is POST then process the reservation submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set header for JSON response
    header("Content-Type: application/json");

    // Get JSON input from fetch request
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["error" => "Invalid input data"]);
        exit();
    }

    // Get form data from JSON
    $room_number = $data['room_number'] ?? null;
    $seat_number = $data['seat_number'] ?? null;
    $reservation_date = $data['reservation_date'] ?? null;
    $time_in = $data['time_in'] ?? null;
    $purpose = $data['purpose'] ?? null;

    // Validate input
    if (!$room_number || !$seat_number || !$reservation_date || !$time_in || !$purpose) {
        echo json_encode(["error" => "All fields are required"]);
        exit();
    }

    // Insert reservation
    $sql = "INSERT INTO reservations (idno, lastname, firstname, room_number, seat_number, reservation_date, time_in, purpose) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssisss", $idno, $lastname, $firstname, $room_number, $seat_number, $reservation_date, $time_in, $purpose);

    if ($stmt->execute()) {
        // Convert time_in from 24-hour format to 12-hour format (e.g. 7:27 PM)
        $dateTimeObj = DateTime::createFromFormat('H:i', $time_in);
        $timeFormatted = $dateTimeObj ? $dateTimeObj->format('g:i A') : $time_in;
    
        // Build the formatted confirmation message
        $confirmation = "Reservation confirmed\n";
        $confirmation .= "Room: {$room_number}\n";
        $confirmation .= "Date: {$reservation_date}\n";
        $confirmation .= "Time In: {$timeFormatted}\n";
        $confirmation .= "Seat Number: {$seat_number}\n";
        $confirmation .= "Purpose: {$purpose}";
    
        echo json_encode(["success" => $confirmation]);
    } else {
        echo json_encode(["error" => "Failed to reserve"]);
    }
    

    $stmt->close();
    $conn->close();
    exit(); // Exit after processing POST
}

// If not POST, render the HTML page (GET request)
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
        .room-card {
            transition: transform 0.2s ease-in-out;
        }
        .room-card:hover {
            transform: scale(1.05);
        }
        .hidden {
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

            <!-- Room Selection Section -->
            <div id="roomSelection" class="p-6">
                <h2 class="text-2xl font-semibold mb-4">Select a Room</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6" id="roomCards">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <div class="room-card bg-white p-4 rounded-lg shadow text-center cursor-pointer" data-room="<?= $i ?>">
                            <img src="inc/computer.png" alt="Room <?= $i ?>" class="w-full h-40 object-cover rounded-lg mb-2">
                            <p class="text-lg font-semibold">Room <?= $i ?></p>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Calendar & Form Section -->
            <div id="calendarInterface" class="hidden p-6 rounded-lg shadow flex flex-col lg:flex-row gap-3">
                <!-- Calendar -->
                <div class="lg:w-1/3">
                    <h3 class="text-xl font-semibold mb-4">Select a Date</h3>
                    <div id="fullCalendar"></div>
                </div>

                <!-- Reservation Form (Appears Beside the Calendar) -->
                <div class="lg:w-1/2 bg-white p-6 rounded-lg shadow hidden" id="reservationForm">
                    <h3 class="text-xl font-semibold mb-4">Reservation Details</h3>
                    <form id="reservationDetails">
                        <input type="hidden" id="selectedDate" name="reservation_date">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Selected Date</label>
                            <p id="selectedDateText" class="text-lg font-semibold text-blue-600"></p>
                        </div>

                        <div class="mb-4">
                            <label for="timeIn" class="block text-sm font-medium text-gray-700">Time In</label>
                            <input type="time" id="timeIn" name="time_in" class="w-full p-2 border rounded-lg">
                        </div>

                        <div class="mb-4">
                            <label for="seatNumber" class="block text-sm font-medium text-gray-700">Seat Number</label>
                            <select id="seatNumber" name="seat_number" class="w-full p-2 border rounded-lg">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?= $i ?>">Seat <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                            <textarea id="purpose" name="purpose" class="w-full p-2 border rounded-lg" rows="3" placeholder="Enter purpose of sit-in"></textarea>
                        </div>

                        <div class="flex justify-between">
                            <button type="button" id="backToRoom" class="bg-gray-500 text-white px-4 py-2 rounded-lg">Back</button>
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedRoom = null;
        let selectedDate = null;

        // Room Card Click Event
        document.querySelectorAll('.room-card').forEach(card => {
            card.addEventListener('click', () => {
                selectedRoom = card.getAttribute('data-room');
                document.getElementById('roomSelection').classList.add('hidden');
                document.getElementById('calendarInterface').classList.remove('hidden');

                // Initialize Inline Calendar
                flatpickr("#fullCalendar", {
                    inline: true,
                    minDate: "today",
                    disable: [
                        function(date) {
                            return (date.getDay() === 0 || date.getDay() === 6);
                        }
                    ],
                    dateFormat: "Y-m-d",
                    onChange: function(selectedDates, dateStr) {
                        if (selectedDates.length > 0) {
                            selectedDate = dateStr;
                            document.getElementById('selectedDate').value = selectedDate;
                            document.getElementById('selectedDateText').innerText = selectedDate;
                            document.getElementById('reservationForm').classList.remove('hidden');
                        }
                    }
                });
            });
        });

        // Back Button (To Room Selection)
        document.getElementById('backToRoom').addEventListener('click', () => {
            document.getElementById('calendarInterface').classList.add('hidden');
            document.getElementById('roomSelection').classList.remove('hidden');
        });

        // Form Submission
        document.getElementById('reservationDetails').addEventListener('submit', (e) => {
            e.preventDefault();

            const seatNumber = document.getElementById('seatNumber').value;
            const timeIn = document.getElementById('timeIn').value;
            const purpose = document.getElementById('purpose').value;

            if (!selectedDate || !seatNumber || !timeIn || !purpose) {
                alert("Please fill in all fields before confirming your reservation.");
                return;
            }

            // Send data to the same page using JSON
            fetch('reservation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    room_number: selectedRoom,
                    seat_number: seatNumber,
                    reservation_date: selectedDate,
                    time_in: timeIn,
                    purpose: purpose
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.success);
                    window.location.href = "reservation.php"; // Reload page after success
                } else {
                    alert(data.error);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html>
