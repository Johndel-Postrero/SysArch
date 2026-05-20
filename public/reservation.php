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

$idno = $_SESSION['login_user'];
$sql = "SELECT user_id, idno, lastname, firstname, session FROM users WHERE idno = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $idno);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Content-Type: application/json");
    echo json_encode(["error" => "User not found"]);
    exit();
}

$activeSitinCheck = $conn->prepare("SELECT sitin_id FROM sitin WHERE idno = ? AND time_out IS NULL");
$activeSitinCheck->bind_param("s", $idno);
$activeSitinCheck->execute();
$activeSitinResult = $activeSitinCheck->get_result();
$hasActiveSitin = ($activeSitinResult->num_rows > 0);
$activeSitinCheck->close();

// Function to check upcoming reservations (5 minutes before)
function checkUpcomingReservations($conn, $idno, $userId) {
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
    
    $query->bind_param("ssssis", $idno, $currentDate, $currentTime, $futureTime, $userId, $currentDate);
    $query->execute();
    $result = $query->get_result();
    
    $upcomingReservations = [];
    while ($row = $result->fetch_assoc()) {
        $upcomingReservations[] = $row;
    }
    
    return $upcomingReservations;
}

// Handle AJAX requests for upcoming reservations
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['check_upcoming'])) {
    header('Content-Type: application/json');
    
    try {
        $upcomingReservations = checkUpcomingReservations($conn, $user['idno'], $user['user_id']);
        $notifications = [];
        
        foreach ($upcomingReservations as $reservation) {
            $minutesLeft = $reservation['minutes_left'];
            $message = "Your reservation for Lab {$reservation['lab_number']}, PC {$reservation['pc_number']} " . 
                      "will start in 2 minutes at " . date('g:i A', strtotime($reservation['time_in']));
            
            // Save notification to database
            saveStudentNotification($message, $user['user_id'], $conn);
            
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

        // Check if student is currently in an active sit-in session
        $activeSitinCheck = $conn->prepare("SELECT sitin_id FROM sitin WHERE idno = ? AND time_out IS NULL");
        $activeSitinCheck->bind_param("s", $idno);
        $activeSitinCheck->execute();
        $activeSitinCheck->store_result();
        if ($activeSitinCheck->num_rows > 0) {
            $activeSitinCheck->close();
            throw new Exception("You cannot make a reservation while you are currently in an active sit-in session.");
        }
        $activeSitinCheck->close();
        
        if (date('w', strtotime($reservation_date)) == 0) {
            throw new Exception("Reservations are not allowed on Sundays");
        }
        
        if ($hour < 7 || $hour >= 20) {
            throw new Exception("Reservations allowed between 7am-8pm only");
        }
        
        $checkQuery = $conn->prepare("SELECT reservation_id FROM reservations WHERE idno = ? AND reservation_date = ? AND time_in = ?");
        $checkQuery->bind_param("sss", $idno, $reservation_date, $time_24hr);
        $checkQuery->execute();
        $checkQuery->store_result();
        
        if ($checkQuery->num_rows > 0) {
            throw new Exception("You already have a reservation at this time");
        }
        
        $pcCheckQuery = $conn->prepare("
            SELECT reservation_id FROM reservations 
            WHERE lab_number = ? AND pc_number = ? AND reservation_date = ? AND time_in = ?
        ");
        $pcCheckQuery->bind_param("siss", $lab_number, $pc_number, $reservation_date, $time_24hr);
        $pcCheckQuery->execute();
        $pcCheckQuery->store_result();
        
        if ($pcCheckQuery->num_rows > 0) {
            throw new Exception("PC already reserved at this time");
        }
        
        $pcStatusQuery = $conn->prepare("
            SELECT status FROM lab_pcs 
            WHERE lab_number = ? AND pc_number = ?
        ");
        $pcStatusQuery->bind_param("si", $lab_number, $pc_number);
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
        $insertQuery->bind_param("ssisss", $idno, $lab_number, $pc_number, $reservation_date, $time_24hr, $purpose);

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
$reservationsQuery->bind_param("s", $user['idno']);
$reservationsQuery->execute();
$reservationsResult = $reservationsQuery->get_result();
$reservations = $reservationsResult->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations – CCS Sit-In</title>
    <script>window.onpageshow=function(e){if(e.persisted)window.location.reload();};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .res-table{width:100%;border-collapse:separate;border-spacing:0 8px;}
        .res-table thead th{padding:0 14px 10px;text-align:left;font-size:11px;font-weight:700;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);}
        .res-table tbody td{padding:0 14px;height:54px;font-size:13px;vertical-align:middle;background:rgba(255,255,255,0.02);border-top:1px solid transparent;border-bottom:1px solid transparent;}
        .res-table tbody td:first-child{border-radius:12px 0 0 12px;border-left:1px solid transparent;}
        .res-table tbody td:last-child{border-radius:0 12px 12px 0;border-right:1px solid transparent;}
        .res-table tbody tr:hover td{background:rgba(139,63,217,0.05);border-color:rgba(139,63,217,0.2);}
        .res-table tbody tr:hover td:first-child{border-left:1px solid rgba(139,63,217,0.2);}
        .res-table tbody tr:hover td:last-child{border-right:1px solid rgba(139,63,217,0.2);}
        .badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
        .badge-approved{background:rgba(16,185,129,0.15);color:#10b981;}
        .badge-pending{background:rgba(234,179,8,0.15);color:#eab308;}
        .badge-declined{background:rgba(239,68,68,0.15);color:#ef4444;}
        .badge-completed{background:rgba(59,130,246,0.15);color:#3b82f6;}
        .badge-sitinned{background:rgba(139,63,217,0.15);color:#C084FC;}
        .lab-badge{display:inline-flex;align-items:center;background:rgba(139,63,217,0.12);color:var(--purple-light);border:1px solid rgba(139,63,217,0.2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;}
        .ctrl-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;}
        .h-search{position:relative;}
        .h-search input{background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:8px 16px 8px 36px;border-radius:10px;font-size:13px;width:220px;outline:none;transition:all 0.3s;font-family:var(--font-b);}
        .h-search input:focus{border-color:var(--purple-glow);box-shadow:0 0 12px rgba(139,63,217,0.2);}
        .h-search input::placeholder{color:var(--text-dim);}
        .h-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:12px;}
        .h-select{background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:8px 12px;border-radius:10px;font-size:13px;font-family:var(--font-b);outline:none;cursor:pointer;}
        .btn-add-res{display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--purple-glow),var(--purple-light));color:#fff;border:none;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;box-shadow:0 4px 15px rgba(139,63,217,0.3);}
        .btn-add-res:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(139,63,217,0.5);}
        .pag-row{display:flex;justify-content:space-between;align-items:center;margin-top:16px;}
        .pag-info{color:var(--text-dim);font-size:12px;}
        .pag-btns{display:flex;gap:5px;}
        .pag-btn{min-width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:transparent;color:var(--text-dim);font-family:var(--font-b);transition:all 0.3s;}
        .pag-btn:hover:not(.active):not(:disabled){border-color:var(--purple-glow);color:#fff;background:var(--purple-hover);}
        .pag-btn.active{background:var(--purple-glow);color:#fff;border-color:var(--purple-glow);}
        .pag-btn:disabled{opacity:0.4;cursor:not-allowed;}
        /* Modal */
        .res-modal{position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(5px);display:none;align-items:center;justify-content:center;z-index:2000;overflow-y:auto;padding:20px;}
        .res-modal.show{display:flex;}
        .res-box{background:#0f0d1f;border:1px solid rgba(139,63,217,0.35);border-radius:22px;padding:30px;width:100%;max-width:520px;box-shadow:0 30px 60px rgba(0,0,0,0.7);position:relative;}
        .res-box h2{font-family:var(--font-h);font-size:16px;color:#fff;margin:0 0 22px;letter-spacing:1px;}
        .field-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:6px;display:block;}
        .d-input{width:100%;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;font-family:var(--font-b);outline:none;transition:all 0.3s;}
        .d-input:focus{border-color:var(--purple-glow);box-shadow:0 0 12px rgba(139,63,217,0.2);}
        .d-input[readonly]{opacity:0.5;cursor:not-allowed;}
        .d-select{width:100%;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;font-family:var(--font-b);outline:none;cursor:pointer;-webkit-appearance:none;}
        .d-select:focus{border-color:var(--purple-glow);}
        .d-select option{background:#1A1530;}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .pc-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:8px;}
        .pc-item{padding:8px 4px;text-align:center;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);color:var(--text-dim);background:rgba(255,255,255,0.03);transition:all 0.2s;}
        .pc-item:hover{border-color:var(--purple-glow);color:#fff;}
        .pc-item.selected{background:var(--purple-glow);color:#fff;border-color:var(--purple-glow);box-shadow:0 0 10px rgba(139,63,217,0.4);}
        .pc-item.unavailable{background:rgba(239,68,68,0.1);color:#ef4444;border-color:rgba(239,68,68,0.2);cursor:not-allowed;}
        .modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:22px;}
        .btn-modal-cancel{background:rgba(255,255,255,0.05);color:var(--text-dim);border:1px solid var(--border);padding:10px 20px;border-radius:10px;font-size:13px;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;}
        .btn-modal-cancel:hover{border-color:#ef4444;color:#ef4444;}
        .btn-modal-submit{background:linear-gradient(135deg,var(--purple-glow),var(--purple-light));color:#fff;border:none;padding:10px 24px;border-radius:10px;font-size:13px;font-weight:700;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;}
        .btn-modal-submit:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(139,63,217,0.4);}
        /* flatpickr dark override */
        .flatpickr-calendar{background:#161326!important;border:1px solid rgba(139,63,217,0.3)!important;border-radius:14px!important;box-shadow:0 20px 40px rgba(0,0,0,0.5)!important;}
        .flatpickr-day{color:#D1C7E0!important;}
        .flatpickr-day.selected{background:var(--purple-glow)!important;border-color:var(--purple-glow)!important;}
        .flatpickr-day:hover{background:rgba(139,63,217,0.2)!important;}
        .flatpickr-months .flatpickr-month,.flatpickr-weekdays,.flatpickr-weekday{background:#0f0d1f!important;color:#9A8FB0!important;}
        .flatpickr-current-month{color:#fff!important;}
        /* toast */
        .res-toast{position:fixed;bottom:28px;right:28px;z-index:9999;background:#161326;border:1px solid rgba(139,63,217,0.3);border-radius:14px;padding:14px 20px;display:flex;align-items:center;gap:12px;box-shadow:0 20px 40px rgba(0,0,0,0.5);transform:translateY(120%);opacity:0;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);min-width:260px;}
        .res-toast.show{transform:translateY(0);opacity:1;}
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        <div class="student-content">
            <div class="content-card">
                <!-- Controls -->
                <div class="ctrl-row">
                    <!-- Hidden entries select to keep pagination logic working if needed, defaulting to all -->
                    <select id="entriesSelect" style="display:none;"><option value="all" selected>all</option></select>
                    
                    <div style="display:flex;align-items:center;gap:12px;margin-left:auto;">
                        <div class="h-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="resSearch" placeholder="Search…">
                        </div>
                        <button class="btn-add-res" onclick="openModal()">
                            <i class="fas fa-plus"></i> New Reservation
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div style="flex:1;overflow-x:auto;">
                    <table class="res-table" id="resTable">
                        <thead>
                            <tr>
                                <th>Lab</th>
                                <th>PC</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                            <tr><td colspan="6" style="text-align:center;color:var(--text-dim);padding:50px 0;">
                                <i class="fas fa-calendar-times" style="font-size:32px;opacity:0.3;display:block;margin-bottom:10px;"></i>No reservations yet.
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($reservations as $r): ?>
                            <tr>
                                <td><span class="lab-badge">Lab <?php echo htmlspecialchars($r['lab_number']); ?></span></td>
                                <td style="color:#D1C7E0;font-weight:600;">PC <?php echo htmlspecialchars($r['pc_number']); ?></td>
                                <td style="color:#fff;font-weight:500;"><?php echo date('M d, Y', strtotime($r['reservation_date'])); ?></td>
                                <td style="color:var(--text-dim);font-size:12px;"><?php echo date('g:i A', strtotime($r['time_in'])); ?></td>
                                <td style="color:var(--text-dim);font-size:13px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($r['purpose']); ?></td>
                                <td>
                                    <?php
                                    if ($r['time_in_status'] === 'completed') echo '<span class="badge badge-completed">Completed</span>';
                                    elseif ($r['time_in_status'] === 'sit-inned') echo '<span class="badge badge-sitinned">Sit-inned</span>';
                                    elseif ($r['status'] === 'approved') echo '<span class="badge badge-approved">Approved</span>';
                                    elseif ($r['status'] === 'declined') echo '<span class="badge badge-declined">Declined</span>';
                                    else echo '<span class="badge badge-pending">Pending</span>';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-row">
                    <div class="pagination-info" id="pagInfo"></div>
                    <div class="pagination-controls" id="pagBtns"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Reservation Modal -->
    <div id="reservationModal" class="res-modal">
        <div class="res-box">
            <button onclick="closeModal()" style="position:absolute;top:18px;right:18px;background:none;border:none;color:var(--text-dim);font-size:18px;cursor:pointer;"><i class="fas fa-times"></i></button>
            <h2><i class="fas fa-calendar-plus" style="color:var(--purple-glow);margin-right:10px;"></i>NEW RESERVATION</h2>
            <form id="reservationForm">
                <div class="form-grid" style="margin-bottom:14px;">
                    <div>
                        <label class="field-lbl">ID Number</label>
                        <input class="d-input" type="text" value="<?php echo htmlspecialchars($user['idno']); ?>" readonly>
                    </div>
                    <div>
                        <label class="field-lbl">Student Name</label>
                        <input class="d-input" type="text" value="<?php echo htmlspecialchars($user['firstname'].' '.$user['lastname']); ?>" readonly>
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label class="field-lbl">Sessions Remaining</label>
                    <input class="d-input" type="text" value="<?php echo htmlspecialchars($user['session']); ?>" readonly>
                </div>
                <div style="margin-bottom:14px;">
                    <label class="field-lbl">Purpose</label>
                    <select class="d-select" name="purpose" onchange="toggleOtherReason()" required>
                        <option value="">Select Purpose</option>
                        <?php foreach(['C Programming','C# Programming','Java Programming','PHP Programming','ASP Net','Web Development','Systems Integration & Architecture','Embedded Systems & IoT','Digital Logic & Design','Computer Application','Database','Project Management','Mobile Application','Others'] as $p): ?>
                        <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="otherReasonDiv" style="display:none;margin-bottom:14px;">
                    <label class="field-lbl">Specify Purpose</label>
                    <input class="d-input" type="text" name="other_reason" placeholder="Describe your purpose…">
                </div>
                <div style="margin-bottom:14px;">
                    <label class="field-lbl">Laboratory</label>
                    <select class="d-select" name="lab_number" id="labSelect" required onchange="loadAvailablePCs()">
                        <option value="">Select Lab</option>
                        <?php foreach([524,526,528,530,542,544] as $lab): ?>
                        <option value="<?php echo $lab; ?>">Lab <?php echo $lab; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="pcSelectionContainer" style="display:none;margin-bottom:14px;">
                    <label class="field-lbl">Select a PC</label>
                    <div id="pcContainer" class="pc-grid"></div>
                    <input type="hidden" name="pc_number" id="selectedPC" required>
                </div>
                <div class="form-grid" style="margin-bottom:6px;">
                    <div>
                        <label class="field-lbl">Date</label>
                        <input class="d-input" type="text" id="reservation_date" name="reservation_date" placeholder="Select date" required>
                    </div>
                    <div>
                        <label class="field-lbl">Time In</label>
                        <input class="d-input" type="time" name="time_in" required>
                    </div>
                </div>
                <div class="modal-btns">
                    <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-check" style="margin-right:6px;"></i>Reserve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast -->
    <div id="resToast" class="res-toast">
        <i id="resToastIcon" class="fas fa-check-circle" style="font-size:20px;color:#10b981;"></i>
        <div>
            <div id="resToastTitle" style="font-family:var(--font-h);font-size:11px;font-weight:700;color:#fff;letter-spacing:0.5px;margin-bottom:2px;">SUCCESS</div>
            <div id="resToastMsg" style="font-size:13px;color:var(--text-dim);"></div>
        </div>
    </div>

    <script>
    // Star canvas
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();

    // Flatpickr
    const datePicker = flatpickr("#reservation_date", {
        minDate:"today", maxDate:new Date().fp_incr(14),
        disable:[function(date){return date.getDay()===0;}],
        dateFormat:"Y-m-d"
    });

    const hasActiveSitin = <?php echo $hasActiveSitin ? 'true' : 'false'; ?>;
    function openModal(){
        if (hasActiveSitin) {
            showToast(false, "You cannot make a reservation while you are currently in an active sit-in session.");
            return;
        }
        document.getElementById('reservationModal').classList.add('show');
    }
    function closeModal(){
        document.getElementById('reservationModal').classList.remove('show');
        document.getElementById('selectedPC').value='';
        document.getElementById('pcSelectionContainer').style.display='none';
        document.getElementById('reservationForm').reset();
    }
    function toggleOtherReason(){
        const v=document.querySelector('select[name="purpose"]').value;
        document.getElementById('otherReasonDiv').style.display=v==='Others'?'block':'none';
    }

    async function loadAvailablePCs(){
        const lab=document.getElementById('labSelect').value;
        const cont=document.getElementById('pcContainer');
        const wrap=document.getElementById('pcSelectionContainer');
        if(!lab){wrap.style.display='none';return;}
        wrap.style.display='block';
        cont.innerHTML='<p style="color:var(--text-dim);font-size:13px;">Loading…</p>';
        try{
            const res=await fetch('get_available_pcs.php?lab='+lab);
            const data=await res.json();
            if(data.success){
                const avail=data.pcs.filter(p=>p.status==='available');
                if(avail.length){
                    cont.innerHTML=avail.map(p=>`<div class="pc-item" data-pc="${p.pc_number}" onclick="selectPC(this)">PC ${p.pc_number}</div>`).join('');
                } else {
                    cont.innerHTML='<p style="color:#ef4444;font-size:13px;">No available PCs.</p>';
                }
            }
        }catch(e){cont.innerHTML='<p style="color:#ef4444;font-size:13px;">Error loading PCs.</p>';}
    }
    function selectPC(el){
        document.querySelectorAll('.pc-item').forEach(i=>i.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('selectedPC').value=el.dataset.pc;
    }

    document.getElementById('reservationForm').addEventListener('submit',async function(e){
        e.preventDefault();
        const fd=new FormData(this);
        fd.append('reserve','1');
        const sel=datePicker.selectedDates[0];
        if(sel)fd.set('reservation_date',datePicker.formatDate(sel,'Y-m-d'));
        try{
            const res=await fetch('reservation.php',{method:'POST',body:fd});
            const data=await res.json();
            showToast(data.success,data.message);
            if(data.success){closeModal();setTimeout(()=>location.reload(),1500);}
        }catch(err){showToast(false,'An error occurred.');}
    });

    function showToast(ok,msg){
        const t=document.getElementById('resToast');
        document.getElementById('resToastMsg').textContent=msg;
        document.getElementById('resToastTitle').textContent=ok?'SUCCESS':'ERROR';
        document.getElementById('resToastIcon').className='fas '+(ok?'fa-check-circle':'fa-exclamation-circle');
        document.getElementById('resToastIcon').style.color=ok?'#10b981':'#ef4444';
        t.classList.add('show');
        setTimeout(()=>t.classList.remove('show'),3500);
    }

    // Pagination
    let curPage = 1, totalPages = 1;
    const allRows = () => [...document.querySelectorAll('#resTable tbody tr:not(.empty-row)')];
    
    function getVis() {
        const q = document.getElementById('resSearch').value.toLowerCase();
        return allRows().filter(r => {
            if (!q) return true;
            return [...r.querySelectorAll('td')].map(c => c.textContent.toLowerCase()).join(' ').includes(q);
        });
    }

    function render() {
        const vis = getVis();
        const ep = document.getElementById('entriesSelect').value;
        allRows().forEach(r => r.style.display = 'none');

        if (ep === 'all') {
            vis.forEach(r => r.style.display = '');
            const info = document.getElementById('pagInfo');
            const controls = document.getElementById('pagBtns');
            info.textContent = `Showing ${vis.length} entries`;
            controls.innerHTML = '';
            return;
        }

        const num = parseInt(ep);
        totalPages = Math.ceil(vis.length / num);
        if (curPage > totalPages && totalPages > 0) curPage = totalPages;
        else if (totalPages === 0) curPage = 1;

        const start = (curPage - 1) * num;
        vis.slice(start, start + num).forEach(r => r.style.display = '');
        
        // Update pagination info and buttons
        const info = document.getElementById('pagInfo');
        const controls = document.getElementById('pagBtns');
        
        const s = vis.length === 0 ? 0 : (curPage - 1) * num + 1;
        const e = Math.min(curPage * num, vis.length);
        info.textContent = `Showing ${s} to ${e} of ${vis.length} entries`;
        controls.innerHTML = '';

        if (totalPages <= 1) return;

        // Prev
        const prev = document.createElement('button');
        prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prev.className = 'page-btn'; prev.disabled = curPage === 1;
        prev.addEventListener('click', () => { if (curPage > 1) { curPage--; render(); } });
        controls.appendChild(prev);

        // Pages
        const max = 5;
        let sp = Math.max(1, curPage - Math.floor(max / 2));
        let epPages = Math.min(totalPages, sp + max - 1);
        if (epPages - sp + 1 < max) sp = Math.max(1, epPages - max + 1);

        for (let i = sp; i <= epPages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = `page-btn ${i === curPage ? 'active' : ''}`;
            btn.addEventListener('click', () => { curPage = i; render(); });
            controls.appendChild(btn);
        }

        // Next
        const next = document.createElement('button');
        next.innerHTML = '<i class="fas fa-chevron-right"></i>';
        next.className = 'page-btn'; next.disabled = curPage === totalPages;
        next.addEventListener('click', () => { if (curPage < totalPages) { curPage++; render(); } });
        controls.appendChild(next);
    }

    document.getElementById('resSearch').addEventListener('input', () => { curPage = 1; render(); });
    document.getElementById('entriesSelect').addEventListener('change', () => { curPage = 1; render(); });
    render();
    </script>
</body>
</html>