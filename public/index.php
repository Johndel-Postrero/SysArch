<?php
date_default_timezone_set('Asia/Manila');
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

// Fetch session data for the logged-in user
$username = $_SESSION['login_user'];
$query = $conn->prepare("SELECT session FROM users WHERE username = ?");
$query->bind_param("s", $username);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

$sessionsLeft = $user['session'];
$sessionsUsed = 30 - $sessionsLeft; // Assuming the total sessions are 30

$query->close();

// Fetch the latest 10 announcements
$announcementsQuery = $conn->prepare("SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 10");
$announcementsQuery->execute();
$announcementsResult = $announcementsQuery->get_result();
$announcements = [];

while ($row = $announcementsResult->fetch_assoc()) {
    // Calculate the time difference
    $createdAt = new DateTime($row['created_at']);
    $now = new DateTime();
    $interval = $createdAt->diff($now);

    if ($interval->y > 0) {
        $timeAgo = $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
    } elseif ($interval->m > 0) {
        $timeAgo = $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
    } elseif ($interval->d > 0) {
        $timeAgo = $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
    } elseif ($interval->h > 0) {
        $timeAgo = $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
    } elseif ($interval->i > 0) {
        $timeAgo = $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
    } else {
        $timeAgo = "Just now";
    }

    $announcements[] = [
        'title' => $row['title'],
        'timeAgo' => $timeAgo
    ];
}

$announcementsQuery->close();

// Fetch lab usage data
$labUsageQuery = $conn->prepare("
    SELECT 
        sitin_date, 
        SUM(TIME_TO_SEC(TIMEDIFF(time_out, time_in))) AS total_seconds 
    FROM sitin 
    WHERE time_out IS NOT NULL 
    GROUP BY sitin_date 
    ORDER BY sitin_date DESC 
    LIMIT 30
");
$labUsageQuery->execute();
$labUsageResult = $labUsageQuery->get_result();
$labUsageData = [];

while ($row = $labUsageResult->fetch_assoc()) {
    // Convert total seconds to hours
    $totalHours = $row['total_seconds'] / 3600; // 3600 seconds = 1 hour
    $labUsageData[$row['sitin_date']] = $totalHours;
}

$labUsageQuery->close();
$conn->close();
?>
<html>
<head>
    <title>Dashboard</title>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart.js -->
    <style>
        body {
            font-family: "Poppins-Regular";
            color: #333;
            font-size: 16px;
            margin: 0;
        }
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
        .dropdown-content {
            display: none;
            margin-left: 2rem;
        }
        .dropdown:hover .dropdown-content {
            display: block;
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

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left Column: Sessions and Lab Usage -->
                    <div class="md:col-span-2 space-y-6">
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Sessions Left -->
                            <div class="bg-[#002044] text-white p-4 rounded-lg flex items-center justify-between h-24">
                                <div>
                                    <p class="text-3xl font-semibold"><?php echo $sessionsLeft; ?></p>
                                    <p>Sessions Left</p>
                                </div>
                                <i class="fas fa-calendar-alt text-3xl"></i>
                            </div>
                            <!-- Sessions Used -->
                            <div class="bg-white p-4 rounded-lg flex items-center justify-between shadow h-24">
                                <div>
                                    <p class="text-3xl font-semibold"><?php echo $sessionsUsed; ?></p>
                                    <p>Sessions Used</p>
                                </div>
                                <i class="fas fa-calendar-alt text-3xl text-gray-500"></i>
                            </div>
                        </div>
                        <!-- Lab Usage -->
                        <div class="bg-white p-6 rounded-lg shadow">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Lab Usage</h3>
                                <!-- Dropdown for Lab Usage -->
                                <div class="relative">
                                    <select id="timeRange" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                                        <option value="7" selected>Last 7 Days</option>
                                        <option value="14">Last 14 Days</option>
                                        <option value="30">Last 30 Days</option>
                                    </select>
                                </div>
                            </div>
                            <canvas id="labUsageChart"></canvas> <!-- Chart -->
                        </div>
                    </div>
                    <!-- Right Column: Announcements -->
                    <div class="bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-semibold mb-4">Announcements</h3>
                        <div class="space-y-4">
                            <?php if (!empty($announcements)): ?>
                                <?php foreach ($announcements as $index => $announcement): ?>
                                    <?php if ($index === 0): ?>
                                        <!-- First announcement with special styling -->
                                        <div class="bg-[#002044] text-white p-4 rounded-lg flex justify-between items-center">
                                            <p><?php echo htmlspecialchars($announcement['title']); ?></p>
                                            <span class="text-sm"><?php echo $announcement['timeAgo']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <!-- Subsequent announcements with different styling -->
                                        <div class="bg-white p-4 rounded-lg flex justify-between items-center shadow">
                                            <p><?php echo htmlspecialchars($announcement['title']); ?></p>
                                            <span class="text-sm text-gray-500"><?php echo $announcement['timeAgo']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- If no announcements are found -->
                                <div class="bg-[#002044] text-white p-4 rounded-lg flex justify-between items-center">
                                    <p>No announcements found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Pass PHP data to JavaScript -->
<script>
    const labUsageData = <?php echo json_encode($labUsageData); ?>;
    console.log(labUsageData); // Debugging: Check the data in the browser console
</script>

<!-- Updated Chart.js Script -->
<script>
    const ctx = document.getElementById('labUsageChart').getContext('2d');

    // Prepare data for the chart
    const prepareChartData = (range) => {
        const labels = [];
        const data = [];

        // Get the last `range` days
        const today = new Date();
        for (let i = range - 1; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            const formattedDate = date.toISOString().split('T')[0]; // Format as YYYY-MM-DD

            labels.push(formattedDate);
            data.push(labUsageData[formattedDate] ? labUsageData[formattedDate] : 0);
        }

        return { labels, data };
    };

    // Initialize the chart
    let labUsageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: prepareChartData(7).labels,
            datasets: [{
                label: 'Hours Used',
                data: prepareChartData(7).data,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hours'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });

    // Update the chart when the time range changes
    document.getElementById('timeRange').addEventListener('change', function () {
        const selectedRange = parseInt(this.value);
        const { labels, data } = prepareChartData(selectedRange);

        labUsageChart.data.labels = labels;
        labUsageChart.data.datasets[0].data = data;
        labUsageChart.update();
    });
</script>
</body>
</html>