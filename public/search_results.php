<?php
session_start();
require __DIR__ . '/../config/db.php';

$searchResults = [];

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $searchQuery = "%" . $_GET['query'] . "%";

    $query = $conn->prepare("SELECT idno, firstname, middlename, lastname, email, session FROM users 
    WHERE (idno LIKE ? OR firstname LIKE ? OR lastname LIKE ? 
    OR CONCAT(firstname, ' ', lastname) LIKE ? 
    OR CONCAT(firstname, ' ', middlename, ' ', lastname) LIKE ?) 
    AND role != 'admin'");

    $query->bind_param("sssss", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $searchQuery);
    $query->execute();
    $result = $query->get_result();

    while ($row = $result->fetch_assoc()) {
        // Check if the user already has an active sit-in for today
        $sitInCheck = $conn->prepare("SELECT id FROM reservations WHERE idno = ? AND reservation_date = CURDATE() AND time_out IS NULL LIMIT 1");
        $sitInCheck->bind_param("i", $row['idno']);
        $sitInCheck->execute();
        $sitInCheck->store_result();
        $isSitInned = $sitInCheck->num_rows > 0;
        $sitInCheck->close();
        $row['isSitInned'] = $isSitInned;
        
        $searchResults[] = $row;
    }
    $query->close();
}

// Process Sit-In
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['logout_idno'])) {
        $idno = $_POST['logout_idno'];
        $time_out = date("H:i:s");
    
        // Update the reservations table to log out the user
        $logoutQuery = $conn->prepare("UPDATE reservations SET time_out = ? WHERE idno = ? AND reservation_date = CURDATE() AND time_out IS NULL");
        $logoutQuery->bind_param("si", $time_out, $idno);
    
        if ($logoutQuery->execute()) {
            // Deduct one session from the user's session count
            $deductSessionQuery = $conn->prepare("UPDATE users SET session = GREATEST(session - 1, 0) WHERE idno = ?");
            $deductSessionQuery->bind_param("i", $idno);
            $deductSessionQuery->execute();
            $deductSessionQuery->close();
    
            $_SESSION['success'] = "User successfully logged out and session deducted!";
        } else {
            $_SESSION['error'] = "Error logging out. Please try again.";
        }
        $logoutQuery->close();
    
        // Redirect to maintain search results
        header("Location: search_results.php?query=" . urlencode($_GET['query'] ?? ''));
        exit();
    
    } else {
        // Sit-In Process
        $idno = $_POST['idno'];
        $room_number = $_POST['room_number'];
        $purpose = $_POST['purpose'] === 'Others' ? $_POST['other_reason'] : $_POST['purpose'];
        $time_in = date("H:i:s");
        $reservation_date = date("Y-m-d");

        // Check if user already has an active sit-in
        $checkQuery = $conn->prepare("SELECT id FROM reservations WHERE idno = ? AND reservation_date = CURDATE() AND time_out IS NULL LIMIT 1");
        $checkQuery->bind_param("i", $idno);
        $checkQuery->execute();
        $checkQuery->store_result();

        if ($checkQuery->num_rows > 0) {
            $_SESSION['error'] = "User is already sitting in today.";
        } else {
            $insertQuery = $conn->prepare("INSERT INTO reservations (idno, room_number, reservation_date, time_in, purpose) VALUES (?, ?, ?, ?, ?)");
            $insertQuery->bind_param("iisss", $idno, $room_number, $reservation_date, $time_in, $purpose);

            if ($insertQuery->execute()) {
                $_SESSION['success'] = "Sit-in successfully recorded!";
            } else {
                $_SESSION['error'] = "Error processing sit-in. Please try again.";
            }
            $insertQuery->close();
        }
        $checkQuery->close();
    }

    // Redirect back to maintain the search results
    header("Location: search_results.php?query=" . urlencode($_GET['query'] ?? ''));
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Search Results</title>
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
        /* Sidebar */
        .sidebar {
            width: 5rem; /* Default width */
            transition: all 0.3s ease-in-out;
        }
        .sidebar:hover {
            width: 16rem; /* Expanded width */
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
            justify-content: center; /* Centers the icons */
            padding: 1rem;
        }
        .sidebar:hover a {
            justify-content: flex-start; /* Aligns text to the left on hover */
        }
        .sidebar i {
            font-size: 1.5rem; /* Slightly larger icons */
        }
        /* Main content shifts when sidebar expands */
        .main-content {
            margin-left: 5rem; /* Adjust based on the sidebar width */
            transition: margin-left 0.3s ease-in-out; /* Smooth transition */
        }
        .sidebar:hover + .main-content {
            margin-left: 16rem; /* Adjust content when sidebar expands */
        }
        /* Overlay */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease-in-out;
        }
        /* Modal Box */
        .modal {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 400px;
            text-align: center;
        }
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

            <!-- Content -->
            <div class="flex-1 p-6"><!-- Added relative positioning -->
                <?php if (!empty($searchResults)): ?>
                    <div class="table-container">
                        <table class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">ID No</th>
                                    <th class="py-4 px-4 text-center">First Name</th>
                                    <th class="py-4 px-4 text-center">Last Name</th>
                                    <th class="py-4 px-4 text-center">Email</th>
                                    <th class="py-4 px-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $index => $user): ?>
                                    <tr class="<?= $index % 2 === 0 ? 'bg-gray-100' : 'bg-gray-200' ?>">
                                        <td class="py-4 px-4 text-center text-black"><?php echo htmlspecialchars($user['idno']); ?></td>
                                        <td class="py-4 px-4 font-semibold text-center text-black"><?php echo htmlspecialchars($user['firstname']); ?></td>
                                        <td class="py-4 px-4 text-center text-black"><?php echo htmlspecialchars($user['lastname']); ?></td>
                                        <td class="py-4 px-4 text-center text-black"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <?php if ($user['isSitInned']): ?>
                                                <form method="POST" action="search_results.php?query=<?php echo urlencode($_GET['query'] ?? ''); ?>">
                                                    <input type="hidden" name="logout_idno" value="<?php echo $user['idno']; ?>">
                                                    <button class="bg-red-500 text-white px-4 py-2 rounded logout-btn">
                                                        Log Out
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="bg-blue-500 text-white px-4 py-2 rounded sit-in-btn"
                                                    data-idno="<?php echo $user['idno']; ?>"
                                                    data-fullname="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['middlename'] . ' ' . $user['lastname']); ?>"
                                                    data-session="<?php echo $user['session']; ?>">
                                                    Sit-In
                                                </button>
                                            <?php endif; ?>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">No results found.</p>
                <?php endif; ?>

                <!-- SIT-IN Modal -->
                <div id="sitInOverlay" class="overlay hidden fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
                    <div class="modal bg-white p-6 rounded-lg">
                        <h2 class="text-xl font-bold mb-4">SIT-IN</h2>
                        <form method="POST" action="search_results.php?query=<?php echo urlencode($_GET['query'] ?? ''); ?>">
    <div class="flex flex-col space-y-2">
        <div class="flex flex-col text-left">
            <label class="font-semibold">ID No:</label>
            <input type="text" id="idno" name="idno" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
        </div>
        <div class="flex flex-col text-left">
            <label class="font-semibold">Student Name:</label>
            <input type="text" id="fullname" name="fullname" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
        </div>
        <div class="flex flex-col text-left">
            <label class="font-semibold">Lab:</label>
            <select id="room_number" name="room_number" class="w-full border px-3 py-2 rounded bg-white">
                <option value="1">Lab 1</option>
                <option value="2">Lab 2</option>
                <option value="3">Lab 3</option>
                <option value="4">Lab 4</option>
            </select>
        </div>
        <div class="flex flex-col text-left">
            <label class="font-semibold">Purpose:</label>
            <select id="purpose" name="purpose" class="w-full border px-3 py-2 rounded bg-white" onchange="toggleOtherReason()">
                <option value="C Programming">C Programming</option>
                <option value="Java Programming">Java Programming</option>
                <option value="Others">Others</option>
            </select>
        </div>
        <div id="otherReasonDiv" class="hidden flex flex-col text-left">
            <label class="font-semibold">Specify Purpose:</label>
            <input type="text" id="otherReason" name="other_reason" class="w-full border px-3 py-2 rounded bg-white">
        </div>
        <div class="flex flex-col text-left">
            <label class="font-semibold">Remaining Sessions:</label>
            <input type="text" id="remainingSessions" name="remainingSessions" class="w-full border px-3 py-2 rounded bg-gray-200" readonly>
        </div>
    </div>

    <div class="flex justify-center gap-6 mt-6">
    <button class="w-40 h-12 border border-red-700 text-red-700 font-semibold rounded-lg hover:bg-red-700 hover:text-white transition duration-300" type="button" onclick="closeSitIn()">
        Cancel
    </button>
    <button class="w-40 h-12 bg-purple-700 text-white font-semibold rounded-lg hover:bg-purple-800 transition duration-300" type="submit">
        Save
    </button>
</div>

</form>

                    </div>
                </div>
            </div> <!-- End of flex-1 p-6 -->
        </div> <!-- End of main-content -->
    </div> <!-- End of flex h-screen -->

    <script>
document.querySelectorAll('.sit-in-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('idno').value = this.getAttribute('data-idno');
        document.getElementById('fullname').value = this.getAttribute('data-fullname');
        document.getElementById('remainingSessions').value = this.getAttribute('data-session');
        document.getElementById('sitInOverlay').classList.remove('hidden');
    });
});

document.getElementById('sitInForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent form from reloading the page

    let formData = new FormData(this);

    fetch("search_results.php?query=<?php echo urlencode($_GET['query'] ?? ''); ?>", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log(data); // Debugging
        alert("Sit-in successfully recorded!"); // Optional: Display success message
        document.getElementById('sitInOverlay').classList.add('hidden'); // Close modal
        window.location.reload(); // Reload the page to reflect changes
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Error processing sit-in. Please try again.");
    });
});

function closeSitIn() {
    document.getElementById('sitInOverlay').classList.add('hidden');
}

function toggleOtherReason() {
    var purposeSelect = document.getElementById("purpose");
    var otherReasonDiv = document.getElementById("otherReasonDiv");
    otherReasonDiv.classList.toggle("hidden", purposeSelect.value !== "Others");
}
    </script>

</body>
</html>
