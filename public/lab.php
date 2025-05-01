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

// Get all labs
$labs = [524, 526, 528, 530, 542, 544];
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get current lab from query parameters
$current_lab = $_GET['lab'] ?? $labs[0];

// Get schedule for the selected lab
$schedule_query = $conn->prepare("SELECT * FROM lab_schedules WHERE lab_number = ? ORDER BY day_of_week, start_time");
$schedule_query->bind_param("i", $current_lab);
$schedule_query->execute();
$schedule_result = $schedule_query->get_result();
$all_schedules = $schedule_result->fetch_all(MYSQLI_ASSOC);

// Organize schedules by day and time
$organized_schedules = [];
foreach ($all_schedules as $schedule) {
    $organized_schedules[$schedule['day_of_week']][$schedule['start_time']] = [
        'end_time' => $schedule['end_time'],
        'status' => $schedule['status'],
        'notes' => $schedule['notes']
    ];
}

// Generate time slots
$time_slots = [];
$start = strtotime('7:30 AM');
$end = strtotime('8:00 PM');
$interval = 30 * 60; // 30 minutes in seconds

for ($time = $start; $time <= $end; $time += $interval) {
    $time_slots[] = date('H:i', $time);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab Schedule Viewer</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        };
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com"></script>
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
        .time-cell {
            min-width: 100px;
        }
        .day-header {
            min-width: 150px;
        }
        .status-available {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-unavailable {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .status-cell {
            padding: 8px;
            border-radius: 4px;
            text-align: center;
            font-weight: 500;
        }
        .table-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        table {
            border-collapse: separate;
            border-spacing: 0;
        }
        th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            z-index: 10;
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
                    <div class="bg-white p-6 rounded-lg shadow">
                        <!-- Lab Selection -->
                        <h2 class="text-lg font-semibold mb-3">Select Laboratory:</h2>
                        <div class="lab-tabs">
                            <?php foreach ($labs as $lab): ?>
                                <div class="lab-tab <?php echo $lab == $current_lab ? 'active' : ''; ?>" 
                                     onclick="changeLab(<?php echo $lab; ?>)">
                                    Lab <?php echo $lab; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Schedule Table -->
                        <h2 class="text-lg font-semibold mt-6 mb-3">Schedule for Lab <?php echo $current_lab; ?></h2>
                        
                        <div class="table-container">
                            <table class="w-full">
                                <thead>
                                    <tr>
                                        <th class="time-cell py-3 px-4 text-left bg-gray-50 border-b">Time</th>
                                        <?php foreach ($days_of_week as $day): ?>
                                            <th class="day-header py-3 px-4 text-center bg-gray-50 border-b"><?php echo $day; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($time_slots as $time): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="time-cell py-3 px-4 border-b text-sm">
                                                <?php echo date('g:i A', strtotime($time)); ?>
                                            </td>
                                            <?php foreach ($days_of_week as $day): 
                                                $status = 'available';
                                                $notes = '';
                                                
                                                // Check if this time slot is covered by any schedule
                                                foreach ($organized_schedules[$day] ?? [] as $start => $schedule) {
                                                    if ($time >= $start && $time < $schedule['end_time']) {
                                                        $status = $schedule['status'];
                                                        $notes = $schedule['notes'];
                                                        break;
                                                    }
                                                }
                                            ?>
                                                <td class="py-3 px-4 border-b">
                                                    <div class="status-cell <?php echo 'status-' . $status; ?>" 
                                                         title="<?php echo htmlspecialchars($notes); ?>">
                                                        <?php echo ucfirst($status); ?>
                                                        <?php if (!empty($notes)): ?>
                                                            <i class="fas fa-info-circle ml-1 text-gray-600"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Change lab
        function changeLab(labNumber) {
            const url = new URL(window.location.href);
            url.searchParams.set('lab', labNumber);
            window.location.href = url.toString();
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for lab tabs
            document.querySelectorAll('.lab-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const labNumber = this.textContent.match(/\d+/)[0];
                    changeLab(labNumber);
                });
            });
        });
    </script>
</body>
</html>