<?php
// First: Handle headers and sessions
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Then: Include DB and other files
require __DIR__ . '/../../config/db.php';

// Get all labs (assuming labs 524, 526, 528, 530, 542, 544)
$labs = [524, 526, 528, 530, 542, 544];
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$all_days = array_merge(['all'], $days_of_week); // Add 'all' option for days


// Handle PC status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pc_status'])) {
    $lab_number = $_POST['lab_number'];
    $pc_number = $_POST['pc_number'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO lab_pcs (lab_number, pc_number, status) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = ?");
    $stmt->bind_param("iiss", $lab_number, $pc_number, $status, $status);
    $stmt->execute();
    $stmt->close();
}

// Handle bulk PC status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_update_pc_status'])) {
    $lab_number = $_POST['lab_number'];
    $pc_numbers = explode(',', $_POST['pc_numbers']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("INSERT INTO lab_pcs (lab_number, pc_number, status) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE status = ?");
    
    foreach ($pc_numbers as $pc_number) {
        $stmt->bind_param("iiss", $lab_number, $pc_number, $status, $status);
        $stmt->execute();
    }
    
    $stmt->close();
}

// Handle schedule updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $lab_number = $_POST['lab_number'];
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    
    // Determine which labs and days to update
    $labs_to_update = ($lab_number === 'all') ? $labs : [$lab_number];
    $days_to_update = ($day_of_week === 'all') ? $days_of_week : [$day_of_week];
    
    // Prepare the statement
    $stmt = $conn->prepare("INSERT INTO lab_schedules (lab_number, day_of_week, start_time, end_time, status, notes) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE start_time = ?, end_time = ?, status = ?, notes = ?");
    
    foreach ($labs_to_update as $lab) {
        foreach ($days_to_update as $day) {
            $stmt->bind_param("isssssssss", $lab, $day, $start_time, $end_time, $status, $notes,
                             $start_time, $end_time, $status, $notes);
            $stmt->execute();
        }
    }
    
    $stmt->close();
    
    // Refresh the page to show updates
    header("Location: ".$_SERVER['PHP_SELF']."?lab=".$current_lab."&day=".$current_day);
    exit();
}


// Get current schedule for display
$current_lab = $_GET['lab'] ?? $labs[0];
$current_day = $_GET['day'] ?? 'Monday';

$schedule_query = $conn->prepare("SELECT * FROM lab_schedules WHERE lab_number = ? AND day_of_week = ? ORDER BY start_time");
$schedule_query->bind_param("is", $current_lab, $current_day);
$schedule_query->execute();
$schedule_result = $schedule_query->get_result();
$schedules = $schedule_result->fetch_all(MYSQLI_ASSOC);

// Get PC status for current lab
$pc_query = $conn->prepare("SELECT * FROM lab_pcs WHERE lab_number = ? ORDER BY pc_number");
$pc_query->bind_param("i", $current_lab);
$pc_query->execute();
$pc_result = $pc_query->get_result();
$pcs = $pc_result->fetch_all(MYSQLI_ASSOC);

// Generate time slots for the schedule
$time_slots = [];
$start = strtotime('7:30 AM');
$end = strtotime('8:00 PM');
$interval = 30 * 60; // 30 minutes in seconds

for ($time = $start; $time <= $end; $time += $interval) {
    $time_slots[] = [
        'start' => date('H:i', $time),
        'end' => date('H:i', $time + $interval),
        'status' => 'available' // Default status
    ];
}

// Apply existing schedules to time slots
foreach ($schedules as $schedule) {
    foreach ($time_slots as &$slot) {
        if ($slot['start'] >= $schedule['start_time'] && $slot['end'] <= $schedule['end_time']) {
            $slot['status'] = $schedule['status'];
            $slot['notes'] = $schedule['notes'] ?? '';
        }
    }
}
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
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        .tab-button.active {
            border-bottom-color: #002044;
            color: #002044;
            font-weight: bold;
        }
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        .pc-item {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pc-item.available {
            background-color: #d1fae5;
            border-color: #10b981;
        }
        .pc-item.unavailable {
            background-color: #fee2e2;
            border-color: #ef4444;
        }
        .pc-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 1024px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
            .pc-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <?php include 'sidebarad.php'; ?>
        <div class="main-content flex-1 flex flex-col">
            <?php include 'headerad1.php'; ?>
            
            <div class="flex-1 p-6 flex flex-col">
                <div class="w-full max-w-6xl mx-auto">
                    <!-- Main Tabs -->
                    <div class="flex border-b mb-6">
                        <button id="pcManagementTab" class="tab-button active" onclick="switchMainTab('pcManagement')">
                            <i class="fas fa-desktop mr-2"></i> Computer Laboratory Management
                        </button>
                        <button id="scheduleTab" class="tab-button" onclick="switchMainTab('schedule')">
                            <i class="fas fa-calendar-alt mr-2"></i> Lab Schedules
                        </button>
                    </div>

                    <!-- PC Management Tab -->
                    <div id="pcManagementContent" class="tab-content active">
                        <h2 class="text-xl font-bold mb-4">Computer Laboratory Management</h2>
                        
                        <!-- Lab Selection -->
                        <div class="bg-white p-4 rounded-lg shadow mb-6">
                            <h3 class="font-semibold mb-2">Select Laboratory:</h3>
                            <div class="lab-tabs">
                                <?php foreach ($labs as $lab): ?>
                                    <div class="lab-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>" 
                                        data-lab="<?php echo $lab; ?>">
                                        Lab <?php echo $lab; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- PC Grid -->
                            <h3 class="font-semibold mt-4">PC Availability:</h3>
                            <p class="text-sm text-gray-600 mb-3">Select PCs and choose an action, or click individual PCs to toggle status</p>

                            <!-- Bulk Action Controls -->
                            <div class="flex items-center mb-4 space-x-3">
                                <div>
                                    <input type="checkbox" id="selectAll" class="mr-2" onclick="toggleSelectAll()">
                                    <label for="selectAll">Select All</label>
                                </div>
                                <button onclick="bulkUpdateStatus('available')" class="px-3 py-1 bg-green-100 text-green-800 rounded hover:bg-green-200 text-sm">
                                    <i class="fas fa-check-circle mr-1"></i> Mark Selected as Available
                                </button>
                                <button onclick="bulkUpdateStatus('unavailable')" class="px-3 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200 text-sm">
                                    <i class="fas fa-times-circle mr-1"></i> Mark Selected as Unavailable
                                </button>
                            </div>

                            <div class="pc-grid">
                                <?php for ($i = 1; $i <= 50; $i++): 
                                    $pc_status = 'available';
                                    foreach ($pcs as $pc) {
                                        if ($pc['pc_number'] == $i) {
                                            $pc_status = $pc['status'];
                                            break;
                                        }
                                    }
                                ?>
                                    <div class="pc-item <?php echo $pc_status; ?>" onclick="togglePcCheckbox(<?php echo $i; ?>, event)">
                                        <div class="flex items-center justify-between">
                                            <input type="checkbox" class="pc-checkbox mr-2" data-pc="<?php echo $i; ?>" id="pc-checkbox-<?php echo $i; ?>">
                                            <span>
                                                PC <?php echo $i; ?>
                                            </span>
                                        </div>
                                        <div class="text-xs mt-1">
                                            <span class="px-2 py-1 rounded-full <?php echo $pc_status == 'available' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo ucfirst($pc_status); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Tab -->
                    <div id="scheduleContent" class="tab-content">
                        <h2 class="text-xl font-bold mb-4">Lab Schedules</h2>
                        
                        <div class="bg-white p-4 rounded-lg shadow mb-6">
                            <!-- Lab Selection -->
                            <h3 class="font-semibold mb-2">Select Laboratory:</h3>
                            <div class="lab-tabs">
                                <?php foreach ($labs as $lab): ?>
                                    <div class="lab-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>" 
                                         onclick="changeLab(<?php echo $lab; ?>, 'schedule')">
                                        Lab <?php echo $lab; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Day Selection -->
                            <h3 class="font-semibold mt-4">Select Day:</h3>
                            <div class="weekday-tabs">
                                <?php foreach ($days_of_week as $day): ?>
                                    <div class="weekday-tab <?php echo $day == $current_day ? 'active' : ''; ?>" 
                                         onclick="changeDay('<?php echo $day; ?>')">
                                        <?php echo $day; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Schedule Display -->
                            <h3 class="font-semibold mt-4">Schedule for <?php echo $current_day; ?>:</h3>
                            
                            <div class="schedule-grid mt-4">
                                <!-- Existing Schedule -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold mb-3">Current Schedule</h4>
                                    <div class="space-y-2 max-h-96 overflow-y-auto">
                                        <?php foreach ($time_slots as $slot): ?>
                                            <div class="time-slot <?php echo $slot['status']; ?>">
                                                <span><?php echo date('g:i A', strtotime($slot['start'])); ?> - <?php echo date('g:i A', strtotime($slot['end'])); ?></span>
                                                <span class="font-semibold"><?php echo ucfirst($slot['status']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Schedule Form -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold mb-3">Update Schedule</h4>
                                    <form id="scheduleForm" method="post">
                                        <input type="hidden" name="update_schedule" value="1">
                                        <input type="hidden" name="day_of_week" value="<?php echo $current_day; ?>">
                                        
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                                            <select name="lab_number" class="border rounded p-2 w-full" required>
                                                <option value="all">All Laboratories</option>
                                                <?php foreach ($labs as $lab): ?>
                                                    <option value="<?php echo $lab; ?>" <?php echo $lab == $current_lab ? 'selected' : ''; ?>>
                                                        Lab <?php echo $lab; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Day of Week</label>
                                            <select name="day_of_week" class="border rounded p-2 w-full" required>
                                                <option value="all">All Days</option>
                                                <?php foreach ($days_of_week as $day): ?>
                                                    <option value="<?php echo $day; ?>" <?php echo $day == $current_day ? 'selected' : ''; ?>>
                                                        <?php echo $day; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                                                                
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Time Slot</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <select name="start_time" class="border rounded p-2 w-full" required>
                                                    <?php for ($hour = 7; $hour <= 20; $hour++): ?>
                                                        <?php for ($minute = 0; $minute < 60; $minute += 30): ?>
                                                            <?php 
                                                                $time = sprintf("%02d:%02d", $hour, $minute);
                                                                $display_time = date('g:i A', strtotime($time));
                                                            ?>
                                                            <option value="<?php echo $time; ?>"><?php echo $display_time; ?></option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                                <select name="end_time" class="border rounded p-2 w-full" required>
                                                    <?php for ($hour = 7; $hour <= 20; $hour++): ?>
                                                        <?php for ($minute = 0; $minute < 60; $minute += 30): ?>
                                                            <?php 
                                                                $time = sprintf("%02d:%02d", $hour, $minute);
                                                                $display_time = date('g:i A', strtotime($time));
                                                            ?>
                                                            <option value="<?php echo $time; ?>" <?php echo $time == '20:00' ? 'selected' : ''; ?>>
                                                                <?php echo $display_time; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                            <select name="status" class="border rounded p-2 w-full" required>
                                                <option value="available">Available</option>
                                                <option value="unavailable">Unavailable</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                                            <textarea name="notes" class="border rounded p-2 w-full" rows="2"></textarea>
                                        </div>
                                        
                                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                                            Update Schedule
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle checkbox when PC card is clicked
        function togglePcCheckbox(pcNumber, event) {
            // Stop event propagation if clicking on the checkbox itself
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL') {
                return;
            }
            
            const checkbox = document.getElementById(`pc-checkbox-${pcNumber}`);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                
                // Update the select all checkbox state
                updateSelectAllCheckbox();
            }
        }

        // Update the select all checkbox based on current selections
        function updateSelectAllCheckbox() {
            const checkboxes = document.querySelectorAll('.pc-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            if (checkboxes.length === 0) return;
            
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            const someChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            
            if (allChecked) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else if (someChecked) {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }

        // Switch between main tabs
        function switchMainTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content and mark tab as active
            document.getElementById(tabName + 'Content').classList.add('active');
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // Update URL without reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }

        // Check URL for initial tab
        function checkInitialTab() {
            const urlParams = new URLSearchParams(window.location.search);
            const initialTab = urlParams.get('tab') || 'pcManagement';
            switchMainTab(initialTab);
        }

        // Change lab for PC management
        function changeLab(labNumber, fromTab = 'pcManagement') {
            // Update URL with new lab parameter
            const url = new URL(window.location.href);
            url.searchParams.set('lab', labNumber);
            
            if (fromTab === 'schedule') {
                // Keep the day parameter if we're in schedule tab
                url.searchParams.set('day', '<?php echo $current_day; ?>');
            } else {
                // Remove day parameter if we're in PC management tab
                url.searchParams.delete('day');
            }
            
            // Reload the page to get fresh data
            window.location.href = url.toString();
        }

        // Change day for schedule
        function changeDay(day) {
            // Update URL with new day parameter
            const url = new URL(window.location.href);
            url.searchParams.set('day', day);
            
            // Reload the page to get fresh data
            window.location.href = url.toString();
        }

        // Toggle select all checkboxes
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.pc-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            // If indeterminate, treat as unchecked and check all
            if (selectAll.indeterminate) {
                selectAll.indeterminate = false;
                selectAll.checked = true;
            }
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        // Bulk update status for selected PCs
        function bulkUpdateStatus(newStatus) {
            const selectedPCs = [];
            const checkboxes = document.querySelectorAll('.pc-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Please select at least one PC');
                return;
            }
            
            if (!confirm(`Are you sure you want to mark ${checkboxes.length} PC(s) as ${newStatus}?`)) {
                return;
            }
            
            checkboxes.forEach(checkbox => {
                selectedPCs.push(checkbox.dataset.pc);
            });
            
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const labInput = document.createElement('input');
            labInput.type = 'hidden';
            labInput.name = 'lab_number';
            labInput.value = <?php echo json_encode($current_lab); ?>;
            
            const pcsInput = document.createElement('input');
            pcsInput.type = 'hidden';
            pcsInput.name = 'pc_numbers';
            pcsInput.value = selectedPCs.join(',');
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'bulk_update_pc_status';
            updateInput.value = '1';
            
            form.appendChild(labInput);
            form.appendChild(pcsInput);
            form.appendChild(statusInput);
            form.appendChild(updateInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Update PC status
        function updatePcStatus(labNumber, pcNumber, newStatus) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const labInput = document.createElement('input');
            labInput.type = 'hidden';
            labInput.name = 'lab_number';
            labInput.value = labNumber;
            
            const pcInput = document.createElement('input');
            pcInput.type = 'hidden';
            pcInput.name = 'pc_number';
            pcInput.value = pcNumber;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'update_pc_status';
            updateInput.value = '1';
            
            form.appendChild(labInput);
            form.appendChild(pcInput);
            form.appendChild(statusInput);
            form.appendChild(updateInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            const labSelect = this.elements['lab_number'];
            const daySelect = this.elements['day_of_week'];
            const statusSelect = this.elements['status'];
            
            let confirmMessage = 'Are you sure you want to update the schedule';
            
            if (labSelect.value === 'all') {
                confirmMessage += ' for ALL laboratories';
            } else {
                confirmMessage += ` for Lab ${labSelect.value}`;
            }
            
            if (daySelect.value === 'all') {
                confirmMessage += ' for ALL days';
            } else {
                confirmMessage += ` for ${daySelect.value}`;
            }
            
            confirmMessage += ` (${statusSelect.value})?`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            checkInitialTab();
            
            // Add click handlers for lab tabs in PC management
            document.querySelectorAll('#pcManagementContent .lab-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.textContent.match(/\d+/)[0];
                    changeLab(labNumber, 'pcManagement');
                });
            });
            
            // Add click handlers for lab tabs in schedule
            document.querySelectorAll('#scheduleContent .lab-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.textContent.match(/\d+/)[0];
                    changeLab(labNumber, 'schedule');
                });
            });
            
            // Add click handlers for day tabs
            document.querySelectorAll('.weekday-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const day = this.textContent.trim();
                    changeDay(day);
                });
            });
            
            // Add change event listeners to all PC checkboxes
            document.querySelectorAll('.pc-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectAllCheckbox);
            });
        });
    </script>
</body>
</html>