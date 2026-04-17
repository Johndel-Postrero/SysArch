<?php
date_default_timezone_set('Asia/Manila');
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: http://172.19.131.167/CCS-SITIN/login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../config/db.php';

// Fetch user data including points
$username = $_SESSION['login_user'];
$query = $conn->prepare("SELECT u.session, COALESCE(SUM(r.points), 0) AS total_points 
                        FROM users u
                        LEFT JOIN rewards r ON u.idno = r.idno
                        WHERE u.username = ?");
$query->bind_param("s", $username);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

$sessionsLeft = $user['session'];
$pointsAccumulated = $user['total_points']; // Get total points from rewards table

$query->close();

// Fetch the latest 10 announcements
$announcementsQuery = $conn->prepare("SELECT title, created_at, attachment FROM announcements ORDER BY created_at DESC LIMIT 10");
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
        'timeAgo' => $timeAgo,
        'attachment' => $row['attachment']
    ];
}

$announcementsQuery->close();

// Fetch lab usage data
$labUsageQuery = $conn->prepare("
    SELECT 
        DATE(created_at) as sitin_date,
        COUNT(*) as sitin_count
    FROM sitin 
    WHERE idno = ? AND time_out IS NOT NULL
    GROUP BY DATE(created_at)
    ORDER BY sitin_date DESC 
    LIMIT 30
");
$labUsageQuery->bind_param("i", $_SESSION['idno']);
$labUsageQuery->execute();
$labUsageResult = $labUsageQuery->get_result();
$labUsageData = [];

while ($row = $labUsageResult->fetch_assoc()) {
    $labUsageData[$row['sitin_date']] = $row['sitin_count'];
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
                            <!-- Points Accumulated -->
                            <div class="bg-white p-4 rounded-lg flex items-center justify-between shadow h-24">
                                <div>
                                    <p class="text-3xl font-semibold"><?php echo $pointsAccumulated; ?></p>
                                    <p>Points Accumulated</p>
                                </div>
                                <i class="fas fa-award text-3xl text-yellow-500"></i> <!-- Changed icon to star -->
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
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Announcements</h3>
                            <a href="announcement.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
                        </div>
                        <div class="space-y-4">
                            <?php if (!empty($announcements)): ?>
                                <?php foreach ($announcements as $index => $announcement): ?>
                                    <?php if ($index === 0): ?>
                                        <!-- First announcement with special styling -->
                                        <a href="announcement.php" class="block">
                                            <div class="bg-[#002044] text-white p-4 rounded-lg">
                                                <div class="flex justify-between items-center mb-2">
                                                    <p class="font-semibold"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                                    <span class="text-sm"><?php echo $announcement['timeAgo']; ?></span>
                                                </div>
                                                <?php if (!empty($announcement['attachment'])): ?>
                                                    <?php
                                                    $file_path = "announce/" . htmlspecialchars($announcement['attachment']);
                                                    $file_extension = strtolower(pathinfo($announcement['attachment'], PATHINFO_EXTENSION));
                                                    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                                    ?>
                                                    <div class="mt-2">
                                                        <?php if (in_array($file_extension, $image_extensions)): ?>
                                                            <img src="<?php echo $file_path; ?>" alt="Announcement Image" class="w-full h-32 object-cover rounded-lg">
                                                        <?php else: ?>
                                                            <span class="text-blue-300 hover:text-blue-100 underline">
                                                                <?php echo htmlspecialchars($announcement['attachment']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <!-- Subsequent announcements with different styling -->
                                        <a href="announcement.php" class="block">
                                            <div class="bg-white p-4 rounded-lg flex justify-between items-center shadow hover:bg-gray-50 transition-colors">
                                                <div class="flex-1">
                                                    <p class="font-semibold"><?php echo htmlspecialchars($announcement['title']); ?></p>
                                                    <?php if (!empty($announcement['attachment'])): ?>
                                                        <?php
                                                        $file_path = "announce/" . htmlspecialchars($announcement['attachment']);
                                                        $file_extension = strtolower(pathinfo($announcement['attachment'], PATHINFO_EXTENSION));
                                                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                                        ?>
                                                        <div class="mt-2">
                                                            <?php if (in_array($file_extension, $image_extensions)): ?>
                                                                <img src="<?php echo $file_path; ?>" alt="Announcement Image" class="w-full h-24 object-cover rounded-lg">
                                                            <?php else: ?>
                                                                <span class="text-blue-500 hover:text-blue-700 underline">
                                                                    <?php echo htmlspecialchars($announcement['attachment']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-sm text-gray-500 ml-4"><?php echo $announcement['timeAgo']; ?></span>
                                            </div>
                                        </a>
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
        type: 'line',
        data: {
            labels: prepareChartData(7).labels,
            datasets: [{
                label: 'Daily Sit-ins',
                data: prepareChartData(7).data,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3B82F6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Your Daily Sit-in Activity'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return `Sit-ins: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Number of Sit-ins'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
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