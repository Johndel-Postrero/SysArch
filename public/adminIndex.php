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
?>

<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
?>
<?php

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}
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
     header.header.flex.items-center.justify-between.bg-white.py-6.px-6{
    padding-top: 10px;
    padding-bottom: 10px;
}
body {
    font-family: "Poppins-Regular";
    color: #333;
    font-size: 16px;
    margin: 0; }
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
.header form{
    margin-top: 16px;
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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left Column: Sessions and Lab Usage -->
                    <div class="md:col-span-2 space-y-6">
                        <div class="grid grid-cols-2 gap-6">

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
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Updated Chart.js Script -->
<script>
    const ctx = document.getElementById('labUsageChart').getContext('2d');
    
    const usageData = {
        7: [4, 6, 3, 5, 7, 2, 8],
        14: [4, 6, 3, 5, 7, 2, 8, 5, 4, 6, 7, 3, 2, 5],
        30: [5, 3, 4, 6, 7, 2, 8, 5, 4, 6, 7, 3, 2, 5, 7, 6, 5, 4, 3, 2, 6, 8, 5, 7, 3, 2, 4, 6, 5, 7]
    };

    const labelsData = {
        7: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        14: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7', 'Day 8', 'Day 9', 'Day 10', 'Day 11', 'Day 12', 'Day 13', 'Day 14'],
        30: Array.from({length: 30}, (_, i) => `Day ${i + 1}`)
    };

    let labUsageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labelsData[7],
            datasets: [{
                label: 'Hours Used',
                data: usageData[7],
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    document.getElementById('timeRange').addEventListener('change', function () {
        const selectedRange = this.value;
        labUsageChart.data.labels = labelsData[selectedRange];
        labUsageChart.data.datasets[0].data = usageData[selectedRange];
        labUsageChart.update();
    });
</script>
</body>
</html>