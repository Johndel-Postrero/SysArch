<?php
session_start();
if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function () {
            const dialog = document.getElementById('successDialog');
            if (dialog) {
                dialog.showModal();
            }
        });
    </script>";
    unset($_SESSION['login_success']); // Remove success flag
}

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php"); 
    exit();
}

// Include the database connection
require __DIR__ . '/../../config/db.php';

// Fetch data from the sitin table (including records where time_out is NULL)
$sql = "SELECT sitin.sitin_id, sitin.idno, users.lastname, users.firstname, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at
        FROM sitin 
        JOIN users ON sitin.idno = users.idno";

$result = $conn->query($sql);

$sitinData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sitinData[] = $row;
    }
}

// Fetch total students
$totalStudentsQuery = "SELECT COUNT(*) as total FROM users WHERE role = 'student'";
$totalStudentsResult = $conn->query($totalStudentsQuery);
$totalStudents = $totalStudentsResult->fetch_assoc()['total'];

// Fetch currently sit-in (time_out is null)
$currentSitInQuery = "SELECT COUNT(*) as total FROM sitin WHERE time_out IS NULL";
$currentSitInResult = $conn->query($currentSitInQuery);
$currentSitIn = $currentSitInResult->fetch_assoc()['total'];

// Fetch total sit-ins (time_in and time_out are not null)
$totalSitInQuery = "SELECT COUNT(*) as total FROM sitin WHERE time_in IS NOT NULL AND time_out IS NOT NULL";
$totalSitInResult = $conn->query($totalSitInQuery);
$totalSitIn = $totalSitInResult->fetch_assoc()['total'];

// Fetch approved reservations
$approvedReservationsQuery = "SELECT COUNT(*) as total FROM reservations WHERE status = 'approved'";
$approvedReservationsResult = $conn->query($approvedReservationsQuery);
$approvedReservations = $approvedReservationsResult->fetch_assoc()['total'];

$conn->close();
?>

<!DOCTYPE html>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            display: flex;
            justify-content: space-between;
            gap: 10px; /* Increased gap (2x larger) */
            margin-bottom: 20px;
        }
        .chart {
            background: white;
            padding: 15px; /* Reduced padding */
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        /* Adjust widths for individual charts */
        .chart#purposeChartContainer {
            width: 30%; /* Smaller width for purpose pie chart */
        }
        .chart#labChartContainer {
            width: 30%; /* Smaller width for lab pie chart */
        }
        .chart#dailyTrendsChartContainer {
            width: 40%; /* Wider width for bar chart */
            height: 450px; /* Increased height for bar chart */
        }
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
            <div class="flex-1 p-6">
                <!-- Cards Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Students -->
                    <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Students</p>
                            <p class="text-2xl font-bold"><?php echo $totalStudents; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-800"></i>
                    </div>

                    <!-- Currently Sit-in -->
                    <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Currently Sit-in</p>
                            <p class="text-2xl font-bold"><?php echo $currentSitIn; ?></p>
                        </div>
                        <i class="fas fa-chair text-3xl text-green-800"></i>
                    </div>

                    <!-- Reservations (Static) -->
                    <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Reservations</p>
                            <p class="text-2xl font-bold"><?php echo $approvedReservations; ?></p>
                        </div>
                        <i class="fas fa-calendar-check text-3xl text-purple-800"></i>
                    </div>

                    <!-- Total Sit-in -->
                    <div class="bg-white p-6 rounded-lg shadow-md flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Sit-In</p>
                            <p class="text-2xl font-bold"><?php echo $totalSitIn; ?></p>
                        </div>
                        <i class="fas fa-clock text-3xl text-orange-800"></i>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="chart-container">
                    <!-- Purpose Distribution Pie Chart -->
                    <div class="chart" id="purposeChartContainer">
                        <canvas id="purposeChart"></canvas>
                    </div>

                    <!-- Lab Distribution Pie Chart -->
                    <div class="chart" id="labChartContainer">
                        <canvas id="labChart"></canvas>
                    </div>

                    <!-- Daily Sit-in Trends Line Chart -->
                    <div class="chart" id="dailyTrendsChartContainer">
                        <canvas id="dailyTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Script -->
    <script>
        // Data for Purpose Pie Chart - now including all possible purposes
        const purposeData = {
            labels: [
                "C Programming", 
                "C# Programming", 
                "Java Programming", 
                "PHP Programming", 
                "ASP Net", 
                "Web Development", 
                "Systems Integration & Architecture", 
                "Embedded Systems & IoT", 
                "Digital Logic & Design", 
                "Computer Application", 
                "Database", 
                "Project Management", 
                "Mobile Application", 
                "Others"
            ],
            datasets: [{
                data: [
                    <?php
                    // Initialize counts for all purposes
                    $purposeCounts = [
                        "C Programming" => 0,
                        "C# Programming" => 0,
                        "Java Programming" => 0,
                        "PHP Programming" => 0,
                        "ASP Net" => 0,
                        "Web Development" => 0,
                        "Systems Integration & Architecture" => 0,
                        "Embedded Systems & IoT" => 0,
                        "Digital Logic & Design" => 0,
                        "Computer Application" => 0,
                        "Database" => 0,
                        "Project Management" => 0,
                        "Mobile Application" => 0,
                        "Others" => 0
                    ];
                    
                    // Count each purpose occurrence
                    foreach ($sitinData as $sitin) {
                        $purpose = $sitin['purpose'];
                        if (array_key_exists($purpose, $purposeCounts)) {
                            $purposeCounts[$purpose]++;
                        } else {
                            $purposeCounts["Others"]++;
                        }
                    }
                    
                    // Output the counts in the same order as the labels
                    echo implode(', ', [
                        $purposeCounts["C Programming"],
                        $purposeCounts["C# Programming"],
                        $purposeCounts["Java Programming"],
                        $purposeCounts["PHP Programming"],
                        $purposeCounts["ASP Net"],
                        $purposeCounts["Web Development"],
                        $purposeCounts["Systems Integration & Architecture"],
                        $purposeCounts["Embedded Systems & IoT"],
                        $purposeCounts["Digital Logic & Design"],
                        $purposeCounts["Computer Application"],
                        $purposeCounts["Database"],
                        $purposeCounts["Project Management"],
                        $purposeCounts["Mobile Application"],
                        $purposeCounts["Others"]
                    ]);
                    ?>
                ],
                backgroundColor: [
                    "#1E3A8A", "#1D4ED8", "#3B82F6", "#60A5FA", "#93C5FD", "#BFDBFE",
                    "#4C1D95", "#5B21B6", "#7C3AED", "#8B5CF6", "#A78BFA", "#C4B5FD",
                    "#7E22CE", "#9333EA"
                ],
            }]
        };

        const labData = {
            labels: ["524", "526", "528", "530", "542", "544"],
            datasets: [{
                data: [
                    <?php
                    $labCounts = array_fill_keys(["524", "526", "528", "530", "542", "544"], 0);
                    foreach ($sitinData as $sitin) {
                        if (array_key_exists($sitin['lab_number'], $labCounts)) {
                            $labCounts[$sitin['lab_number']]++;
                        }
                    }
                    echo implode(', ', $labCounts);
                    ?>
                ],
                backgroundColor: ["#1E3A8A", "#1D4ED8", "#3B82F6", "#60A5FA", "#93C5FD", "#BFDBFE"],
            }]
        };

        // Data for Daily Sit-in Trends Line Chart
        const dailyTrendsData = {
            labels: [
                <?php
                // Get the last 7 days
                $dates = [];
                $counts = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dates[] = "'" . date('M d', strtotime($date)) . "'";
                    
                    // Count sit-ins for this date
                    $count = 0;
                    foreach ($sitinData as $sitin) {
                        if (date('Y-m-d', strtotime($sitin['created_at'])) === $date) {
                            $count++;
                        }
                    }
                    $counts[] = $count;
                }
                echo implode(', ', $dates);
                ?>
            ],
            datasets: [{
                label: 'Daily Sit-ins',
                data: [<?php echo implode(', ', $counts); ?>],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3B82F6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        };

        // Render Purpose Pie Chart
        const purposeChart = new Chart(document.getElementById('purposeChart'), {
            type: 'pie',
            data: purposeData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    title: {
                        display: true,
                        text: 'Purpose Distribution',
                    },
                },
            },
        });

        // Render Lab Distribution Pie Chart
        const labChart = new Chart(document.getElementById('labChart'), {
            type: 'pie',
            data: labData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Lab Distribution',
                    },
                },
            },
        });

        // Render Daily Sit-in Trends Line Chart
        const dailyTrendsChart = new Chart(document.getElementById('dailyTrendsChart'), {
            type: 'line',
            data: dailyTrendsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    title: {
                        display: true,
                        text: 'Daily Sit-in Trends (Last 7 Days)',
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
            },
        });
    </script>
</body>
</html>
</html>