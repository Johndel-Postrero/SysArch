<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . '/../config/db.php';

// Handle form submission for new schedule
// Handle form submission for new schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lab_number = $_POST['lab_number'];
    $schedule_type = $_POST['schedule_type'];
    $weekday = $_POST['weekday'] ?? null;
    $specific_date = $_POST['specific_date'] ?? null;
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $reason = $_POST['reason'] ?? '';
    
    // Validate time range (7am to 9pm)
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    $min_time = strtotime('07:00:00');
    $max_time = strtotime('21:00:00');
    
    if ($start < $min_time || $end > $max_time) {
        $_SESSION['schedule_error'] = "Lab hours must be between 7:00 AM and 9:00 PM";
    } elseif ($start >= $end) {
        $_SESSION['schedule_error'] = "End time must be after start time";
    } else {
        // Validate specific date if selected
        if ($schedule_type === 'specific' && empty($specific_date)) {
            $_SESSION['schedule_error'] = "Please select a specific date";
        } else {
            // Delete conflicting schedules if any
            if (!empty($_POST['conflict_ids'])) {
                $conflict_ids = explode(',', $_POST['conflict_ids']);
                $placeholders = implode(',', array_fill(0, count($conflict_ids), '?'));
                $stmt = $conn->prepare("DELETE FROM lab_schedule WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($conflict_ids)), ...$conflict_ids);
                $stmt->execute();
                $stmt->close();
                
                $_SESSION['schedule_notice'] = count($conflict_ids) . " conflicting schedule(s) were removed.";
            }
            
            // Handle "all labs" option
            if ($lab_number === 'all') {
                $all_labs = ['524', '526', '528', '530', '542', '544'];
                $success_count = 0;
                
                foreach ($all_labs as $lab) {
                    // Insert new schedule using TIME fields
                    $stmt = $conn->prepare("INSERT INTO lab_schedule 
                                          (lab_number, schedule_type, weekday, specific_date, start_time, end_time, status, reason) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssisssss", $lab, $schedule_type, $weekday, $specific_date, $start_time, $end_time, $status, $reason);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }
                
                if ($success_count > 0) {
                    $_SESSION['schedule_success'] = "Schedule added successfully for $success_count labs!" . 
                                         (isset($_SESSION['schedule_notice']) ? " " . $_SESSION['schedule_notice'] : "");
                    unset($_SESSION['schedule_notice']);
                } else {
                    $_SESSION['schedule_error'] = "Failed to add schedules!";
                }
            } else {
                // Handle single lab scheduling
                $stmt = $conn->prepare("INSERT INTO lab_schedule 
                                      (lab_number, schedule_type, weekday, specific_date, start_time, end_time, status, reason) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisssss", $lab_number, $schedule_type, $weekday, $specific_date, $start_time, $end_time, $status, $reason);
                
                if ($stmt->execute()) {
                    $_SESSION['schedule_success'] = "Schedule added successfully!" . 
                                         (isset($_SESSION['schedule_notice']) ? " " . $_SESSION['schedule_notice'] : "");
                    unset($_SESSION['schedule_notice']);
                } else {
                    $_SESSION['schedule_error'] = "Error adding schedule: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM lab_schedule WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['schedule_success'] = "Schedule deleted successfully!";
    } else {
        $_SESSION['schedule_error'] = "Error deleting schedule: " . $conn->error;
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch existing schedules
// Fetch existing schedules - MODIFIED QUERIES
$schedules = [
    'recurring' => [],
    'specific' => []
];

// Recurring schedules grouped by lab and weekday
$result = $conn->query("
    SELECT id, lab_number, weekday, 
           TIME_FORMAT(start_time, '%H:%i') as start_time, 
           TIME_FORMAT(end_time, '%H:%i') as end_time, 
           status, reason 
    FROM lab_schedule 
    WHERE schedule_type = 'recurring'
    ORDER BY lab_number, weekday, start_time
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules['recurring'][$row['lab_number']][$row['weekday']][] = $row;
    }
}

// Specific date schedules grouped by lab and date
$result = $conn->query("
    SELECT id, lab_number, 
           DATE_FORMAT(specific_date, '%Y-%m-%d') as specific_date,
           TIME_FORMAT(start_time, '%H:%i') as start_time, 
           TIME_FORMAT(end_time, '%H:%i') as end_time, 
           status, reason 
    FROM lab_schedule 
    WHERE schedule_type = 'specific'
    ORDER BY lab_number, specific_date, start_time
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules['specific'][$row['lab_number']][$row['specific_date']][] = $row;
    }
}
// Check current lab occupancy
$occupancy = [];
$lab_numbers = ['524', '526', '528', '530', '542', '544'];
foreach ($lab_numbers as $lab) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sitin WHERE lab_number = ? AND time_out IS NULL");
    $stmt->bind_param("s", $lab);
    $stmt->execute();
    $res = $stmt->get_result();
    $occupancy[$lab] = $res->fetch_assoc()['count'];
    $stmt->close();
}

// Weekday names for display
$weekdays = [
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab Schedule Management</title>
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
            margin-left: 16rem;
        }
        .header {
            z-index: 1000;
        }
        .schedule-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        .schedule-form {
            flex: 1;
            min-width: 300px;
        }
        .schedule-display {
            flex: 2;
            min-width: 500px;
            max-height: 600px;
        }
        .time-slot {
            display: flex;
            justify-content: space-between;
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 4px;
        }
        .time-slot.available {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
        }
        .time-slot.unavailable {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        .lab-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 15px;
            overflow-x: auto;
        }
        .lab-tab {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
        }
        .lab-tab.active {
            border-bottom-color: #002044;
            font-weight: bold;
        }
        .weekday-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 15px;
            overflow-x: auto;
        }
        .weekday-tab {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-size: 14px;
            white-space: nowrap;
        }
        .weekday-tab.active {
            border-bottom-color: #002044;
            font-weight: bold;
        }
        .schedule-type-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 15px;
        }
        .schedule-type-tab {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-size: 14px;
        }
        .schedule-type-tab.active {
            border-bottom-color: #002044;
            font-weight: bold;
        }
        .occupancy-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: bold;
        }
        .occupancy-full {
            background-color: #ef4444;
            color: white;
        }
        .occupancy-partial {
            background-color: #f59e0b;
            color: white;
        }
        .occupancy-empty {
            background-color: #10b981;
            color: white;
        }
        .lab-schedule {
            max-height: 350px;
            overflow-y: auto;
            padding-right: 10px;
        }
        @media (max-width: 1024px) {
            .schedule-container {
                flex-direction: column;
            }
            .schedule-form, .schedule-display {
                min-width: 100%;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="main-content flex-1 flex flex-col">
            <?php include 'header.php'; ?>
            <div class="flex-1 p-6 flex flex-col">
                <div class="w-full max-w-6xl mx-auto">
                    <?php if (isset($_SESSION['schedule_error'])): ?>
                        <script>
                            alert("Error: <?php echo addslashes($_SESSION['schedule_error']); ?>");
                        </script>
                        <?php unset($_SESSION['schedule_error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['schedule_success'])): ?>
                        <script>
                            alert("Success: <?php echo addslashes($_SESSION['schedule_success']); ?>");
                        </script>
                        <?php unset($_SESSION['schedule_success']); ?>
                    <?php endif; ?>
                    
                    <!-- Current Lab Occupancy -->
                    <div class="bg-white p-4 rounded-lg shadow mb-6">
                        <h2 class="text-lg font-semibold mb-3">Current Lab Occupancy</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <?php foreach ($occupancy as $lab => $count): ?>
                                <div class="border rounded-lg p-3 text-center">
                                    <div class="font-bold">Lab <?php echo $lab; ?></div>
                                    <div class="text-sm text-gray-600 mb-1">Current: <?php echo $count; ?> / 5</div>
                                    <span class="occupancy-badge <?php 
                                        echo $count >= 5 ? 'occupancy-full' : 
                                             ($count > 0 ? 'occupancy-partial' : 'occupancy-empty'); 
                                    ?>">
                                        <?php echo $count >= 5 ? 'Full' : ($count > 0 ? 'Partial' : 'Empty'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="schedule-container">
                        <!-- Schedule Display -->
                        <div class="schedule-display bg-white p-6 rounded-lg shadow">
                            <h2 class="text-xl font-semibold mb-4">Lab Schedules</h2>
                            
                            <!-- Schedule Type Tabs 
                            <div class="schedule-type-tabs">
                                <div class="schedule-type-tab active" data-type="recurring">Recurring Schedules</div>
                                <div class="schedule-type-tab" data-type="specific">Specific Date Schedules</div>
                            </div> -->
                            
                            <!-- Lab Selection Tabs -->
                            <div class="lab-tabs">
                                <div class="lab-tab active" data-lab="all">All Labs</div>
                                <?php foreach (['524', '526', '528', '530', '542', '544'] as $lab): ?>
                                    <div class="lab-tab" data-lab="<?php echo $lab; ?>">Lab <?php echo $lab; ?></div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Weekday Selection Tabs (for recurring schedules) -->
                            <div id="weekdayTabs" class="weekday-tabs">
                                <?php foreach ($weekdays as $num => $day): ?>
                                    <div class="weekday-tab <?php echo $num == 2 ? 'active' : ''; ?>" data-weekday="<?php echo $num; ?>">
                                        <?php echo $day; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Date Selection (for specific schedules) -->
                            <div id="dateSelection" style="display: none;" class="mb-4">
                                <label for="scheduleDate" class="block text-sm font-medium text-gray-700">Select Date:</label>
                                <input type="date" id="scheduleDate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-[#002044] focus:border-[#002044]" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <!-- Schedule Content -->
                            <div id="scheduleContent">
                                <!-- Recurring Schedules -->
                                <div id="recurringSchedules">
                                    <!-- All Labs View -->
                                    <?php foreach ($weekdays as $num => $day): ?>
                                        <div class="lab-schedule lab-all weekday-<?php echo $num; ?>" style="<?php echo $num == 2 ? 'display: block;' : 'display: none;'; ?>">
                                            <h3 class="font-medium mb-3">All Labs - <?php echo $day; ?></h3>
                                            
                                            <?php foreach (['524', '526', '528', '530', '542', '544'] as $lab): ?>
                                                <div class="mb-4">
                                                    <h4 class="font-medium text-sm mb-2">Lab <?php echo $lab; ?></h4>
                                                    
                                                    <?php if (isset($schedules['recurring'][$lab][$num]) && !empty($schedules['recurring'][$lab][$num])): ?>
                                                        <?php foreach ($schedules['recurring'][$lab][$num] as $schedule): ?>
                                                            <div class="time-slot <?php echo $schedule['status']; ?>">
                                                                <div>
                                                                    <?php echo $schedule['start_time']; ?> - 
                                                                    <?php echo $schedule['end_time']; ?>
                                                                    <?php if ($schedule['reason']): ?>
                                                                        <span class="text-xs text-gray-500 ml-2">(<?php echo $schedule['reason']; ?>)</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <a href="?delete=<?php echo $schedule['id']; ?>" 
                                                                       class="text-red-500 hover:text-red-700 text-sm"
                                                                       onclick="return confirmDelete();">
                                                                        Delete
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="text-gray-500 italic text-sm">No schedule defined</div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Individual Lab Views -->
                                    <?php foreach (['524', '526', '528', '530', '542', '544'] as $lab): ?>
                                        <?php foreach ($weekdays as $num => $day): ?>
                                            <div class="lab-schedule lab-<?php echo $lab; ?> weekday-<?php echo $num; ?>" 
                                                 style="display: none;">
                                                <h3 class="font-medium mb-3">Lab <?php echo $lab; ?> - <?php echo $day; ?></h3>
                                                
                                                <?php if (isset($schedules['recurring'][$lab][$num]) && !empty($schedules['recurring'][$lab][$num])): ?>
                                                    <?php foreach ($schedules['recurring'][$lab][$num] as $schedule): ?>
                                                        <div class="time-slot <?php echo $schedule['status']; ?>">
                                                            <div>
                                                                <?php echo $schedule['start_time']; ?> - 
                                                                <?php echo $schedule['end_time']; ?>
                                                                <?php if ($schedule['reason']): ?>
                                                                    <span class="text-xs text-gray-500 ml-2">(<?php echo $schedule['reason']; ?>)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div>
                                                                <a href="?delete=<?php echo $schedule['id']; ?>" 
                                                                   class="text-red-500 hover:text-red-700 text-sm"
                                                                   onclick="return confirmDelete();">
                                                                    Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-gray-500 italic">No schedule defined for this day</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Specific Date Schedules -->
                                <div id="specificSchedules" style="display: none;">
                                    <!-- All Labs View -->
                                    <div class="lab-schedule lab-all">
                                        <h3 class="font-medium mb-3">All Labs - <span id="selectedDateDisplay"></span></h3>
                                        
                                        <?php foreach (['524', '526', '528', '530', '542', '544'] as $lab): ?>
                                            <div class="mb-4">
                                                <h4 class="font-medium text-sm mb-2">Lab <?php echo $lab; ?></h4>
                                                <div id="specificScheduleContent-<?php echo $lab; ?>">
                                                    <div class="text-gray-500 italic text-sm">Select a date to view schedules</div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Individual Lab Views -->
                                    <?php foreach (['524', '526', '528', '530', '542', '544'] as $lab): ?>
                                        <div class="lab-schedule lab-<?php echo $lab; ?>" style="display: none;">
                                            <h3 class="font-medium mb-3">Lab <?php echo $lab; ?> - <span class="date-display"></span></h3>
                                            <div id="specificLabScheduleContent-<?php echo $lab; ?>">
                                                <div class="text-gray-500 italic">Select a date to view schedules</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Schedule type tabs
            const scheduleTypeTabs = document.querySelectorAll('.schedule-type-tab');
            scheduleTypeTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    scheduleTypeTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const type = this.dataset.type;
                    
                    // Show corresponding content
                    if (type === 'recurring') {
                        document.getElementById('recurringSchedules').style.display = 'block';
                        document.getElementById('specificSchedules').style.display = 'none';
                        document.getElementById('weekdayTabs').style.display = 'flex';
                        document.getElementById('dateSelection').style.display = 'none';
                        
                        // Trigger display of current active lab/weekday
                        const activeLab = document.querySelector('.lab-tab.active').dataset.lab;
                        const activeWeekday = document.querySelector('.weekday-tab.active').dataset.weekday;
                        
                        document.querySelectorAll('.lab-schedule').forEach(content => {
                            content.style.display = 'none';
                        });
                        
                        if (activeLab === 'all') {
                            document.querySelector(`.lab-all.weekday-${activeWeekday}`).style.display = 'block';
                        } else {
                            document.querySelector(`.lab-${activeLab}.weekday-${activeWeekday}`).style.display = 'block';
                        }
                    } else {
                        document.getElementById('recurringSchedules').style.display = 'none';
                        document.getElementById('specificSchedules').style.display = 'block';
                        document.getElementById('weekdayTabs').style.display = 'none';
                        document.getElementById('dateSelection').style.display = 'block';
                        
                        // Show all labs view by default
                        document.querySelectorAll('.lab-schedule').forEach(content => {
                            content.style.display = 'none';
                        });
                        
                        const activeLab = document.querySelector('.lab-tab.active').dataset.lab;
                        if (activeLab === 'all') {
                            document.querySelector('.lab-schedule.lab-all').style.display = 'block';
                        } else {
                            document.querySelector(`.lab-schedule.lab-${activeLab}`).style.display = 'block';
                        }
                    }
                });
            });
            
            // Lab tabs
            const labTabs = document.querySelectorAll('.lab-tab');
            labTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    labTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const lab = this.dataset.lab;
                    const activeType = document.querySelector('.schedule-type-tab.active').dataset.type;
                    
                    if (activeType === 'recurring') {
                        const activeWeekday = document.querySelector('.weekday-tab.active').dataset.weekday;
                        
                        document.querySelectorAll('.lab-schedule').forEach(content => {
                            content.style.display = 'none';
                        });
                        
                        if (lab === 'all') {
                            document.querySelector(`.lab-all.weekday-${activeWeekday}`).style.display = 'block';
                        } else {
                            document.querySelector(`.lab-${lab}.weekday-${activeWeekday}`).style.display = 'block';
                        }
                    } else {
                        document.querySelectorAll('.lab-schedule').forEach(content => {
                            content.style.display = 'none';
                        });
                        
                        if (lab === 'all') {
                            document.querySelector('.lab-schedule.lab-all').style.display = 'block';
                        } else {
                            document.querySelector(`.lab-schedule.lab-${lab}`).style.display = 'block';
                        }
                        
                        // Update specific date content if date is selected
                        const selectedDate = document.getElementById('scheduleDate').value;
                        if (selectedDate) {
                            updateSpecificDateSchedules(selectedDate, lab);
                        }
                    }
                });
            });
            
            // Weekday tabs (for recurring schedules)
            const weekdayTabs = document.querySelectorAll('.weekday-tab');
            weekdayTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    weekdayTabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    const weekday = this.dataset.weekday;
                    const activeLab = document.querySelector('.lab-tab.active').dataset.lab;
                    
                    document.querySelectorAll('.lab-schedule').forEach(content => {
                        content.style.display = 'none';
                    });
                    
                    if (activeLab === 'all') {
                        document.querySelector(`.lab-all.weekday-${weekday}`).style.display = 'block';
                    } else {
                        document.querySelector(`.lab-${activeLab}.weekday-${weekday}`).style.display = 'block';
                    }
                });
            });
            
            // Date selection (for specific schedules)
            document.getElementById('scheduleDate').addEventListener('change', function() {
                const selectedDate = this.value;
                const activeLab = document.querySelector('.lab-tab.active').dataset.lab;
                
                updateSpecificDateSchedules(selectedDate, activeLab);
            });
            
            // Set default end time to be 1 hour after start time
            document.getElementById('start_time').addEventListener('change', function() {
                const startTime = this.value;
                const [hours, minutes] = startTime.split(':').map(Number);
                
                // Calculate end time (1 hour later)
                let endHours = hours + 1;
                if (endHours > 21) endHours = 21;
                
                document.getElementById('end_time').value = `${endHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
            });
        });
        
        // Toggle between recurring and specific date fields in form
        function toggleScheduleType() {
            const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
            
            if (scheduleType === 'recurring') {
                document.getElementById('recurringFields').style.display = 'block';
                document.getElementById('specificFields').style.display = 'none';
                document.getElementById('weekday').required = true;
                document.getElementById('specific_date').required = false;
            } else {
                document.getElementById('recurringFields').style.display = 'none';
                document.getElementById('specificFields').style.display = 'block';
                document.getElementById('weekday').required = false;
                document.getElementById('specific_date').required = true;
                
                // Set minimum date to today
                document.getElementById('specific_date').min = new Date().toISOString().split('T')[0];
            }
        }
        
        // Update specific date schedules when date is selected
        function updateSpecificDateSchedules(date, lab) {
            // Format date for display
            const dateObj = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('en-US', options);
            
            // Update date display
            if (lab === 'all') {
                document.getElementById('selectedDateDisplay').textContent = formattedDate;
            } else {
                document.querySelectorAll('.date-display').forEach(el => {
                    el.textContent = formattedDate;
                });
            }
            
            // Fetch schedules for this date via AJAX
            fetch(`get_specific_schedules.php?date=${date}&lab=${lab}`)
                .then(response => response.json())
                .then(data => {
                    if (lab === 'all') {
                        // Update all labs view
                        ['524', '526', '528', '530', '542', '544'].forEach(labNum => {
                            const container = document.getElementById(`specificScheduleContent-${labNum}`);
                            updateSpecificScheduleContainer(container, data[labNum] || []);
                        });
                    } else {
                        // Update single lab view
                        const container = document.getElementById(`specificLabScheduleContent-${lab}`);
                        updateSpecificScheduleContainer(container, data[lab] || []);
                    }
                })
                .catch(error => {
                    console.error('Error fetching specific schedules:', error);
                });
        }
        
        // Update the container with specific date schedules
        function updateSpecificScheduleContainer(container, schedules) {
            if (schedules.length === 0) {
                container.innerHTML = '<div class="text-gray-500 italic text-sm">No schedule defined for this date</div>';
                return;
            }
            
            let html = '';
            schedules.forEach(schedule => {
                html += `
                    <div class="time-slot ${schedule.status}">
                        <div>
                            ${schedule.start_time} - ${schedule.end_time}
                            ${schedule.reason ? `<span class="text-xs text-gray-500 ml-2">(${schedule.reason})</span>` : ''}
                        </div>
                        <div>
                            <a href="?delete=${schedule.id}" 
                               class="text-red-500 hover:text-red-700 text-sm"
                               onclick="return confirmDelete();">
                                Delete
                            </a>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Form validation and schedule checking
        async function validateScheduleForm(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const labNumber = formData.get('lab_number');
    const scheduleType = formData.get('schedule_type');
    const weekday = formData.get('weekday');
    const specificDate = formData.get('specific_date');
    const startTime = formData.get('start_time');
    const endTime = formData.get('end_time');
    
    // Basic validation
    if (startTime >= endTime) {
        alert('End time must be after start time');
        return false;
    }
    
    if (scheduleType === 'specific' && !specificDate) {
        alert('Please select a specific date');
        return false;
    }
    
    // Prepare data for conflict check
    const checkData = new URLSearchParams();
    checkData.append('lab_number', labNumber);
    checkData.append('schedule_type', scheduleType);
    checkData.append('start_time', startTime);
    checkData.append('end_time', endTime);
    
    if (scheduleType === 'recurring') {
        checkData.append('weekday', weekday);
    } else {
        checkData.append('specific_date', specificDate);
    }
    
    try {
        // Show loading indicator
        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        
        const response = await fetch('check_schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: checkData,
            credentials: 'same-origin'
        });
        
        // First check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get the response text first
        const responseText = await response.text();
        
        // Try to parse as JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON:', responseText);
            throw new Error('Invalid server response format');
        }
        
        if (result.error) {
            throw new Error(result.error);
        }
        
        if (result.has_conflicts) {
            // Build conflict message
            let conflictMessage = "The following conflicting schedules exist:\n\n";
            
            if (labNumber === 'all') {
                const labsWithConflicts = [...new Set(result.conflicts.map(c => c.lab))];
                conflictMessage += `Labs: ${labsWithConflicts.join(', ')}\n`;
            }
            
            result.conflicts.forEach(conflict => {
                const labText = labNumber === 'all' ? `Lab ${conflict.lab}: ` : '';
                const dateText = conflict.specific_date ? ` (${conflict.specific_date})` : '';
                conflictMessage += `${labText}${conflict.start_time} - ${conflict.end_time}${dateText}\n`;
            });
            
            conflictMessage += "\nDo you want to override these schedules?";
            
            if (confirm(conflictMessage)) {
                document.getElementById('conflict_ids').value = result.conflicts.map(c => c.id).join(',');
                form.submit();
            }
        } else {
            form.submit();
        }
    } catch (error) {
        console.error('Error checking schedule:', error);
        alert(`Error: ${error.message}`);
    } finally {
        // Restore button state
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = originalButtonText;
        }
    }
}
        
        // Confirm delete function
        function confirmDelete() {
            return confirm('Are you sure you want to delete this schedule?');
        }
    </script>
</body>
</html>