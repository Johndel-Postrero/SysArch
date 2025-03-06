<?php
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

// Fetch data from the sitin table
$sql = "SELECT sitin.id, sitin.idno, users.lastname, users.firstname, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        WHERE sitin.time_out IS NOT NULL AND DATE(sitin.created_at) = CURDATE()";

$result = $conn->query($sql);

$sitinData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sitinData[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Current Sit-In Records</title>
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
    <!-- Include Chart.js -->
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
            margin-left: 16rem; /* Adjust content when sidebar expands */
        }
        .header {
            z-index: 1000;
        }
        .chart-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .chart {
            width: 45%;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            <div class="flex-1 p-6 flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <!-- Pie Charts -->
                    <div class="chart-container">
                        <div class="chart">
                            <canvas id="purposeChart"></canvas>
                        </div>
                        <div class="chart">
                            <canvas id="labChart"></canvas>
                        </div>
                    </div>

                    <!-- Controls (Entries, Search, Filter) -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Entries Selection (Left) -->
                        <div class="flex items-center space-x-2">
                            <label class="text-gray-600" for="entries">
                                Entries per page
                            </label>
                            <select class="border border-gray-300 rounded-md p-2" id="entries">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>

                        <!-- Search and Filter (Right) -->
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <div class="relative">
                                <select id="purposeFilter" class="border border-gray-300 rounded-md p-2">
                                    <option value="">Filter by Purpose</option>
                                    <option value="C Programming">C Programming</option>
                                    <option value="C# Programming">C# Programming</option>
                                    <option value="Java Programming">Java Programming</option>
                                    <option value="PHP Programming">PHP Programming</option>
                                    <option value="ASP Net">ASP Net</option>
                                    <option value="Others">Others</option>
                                </select>
                            </div>
                            <div class="relative">
                                <select id="labFilter" class="border border-gray-300 rounded-md p-2">
                                    <option value="">Filter by Lab</option>
                                    <option value="524">524</option>
                                    <option value="526">526</option>
                                    <option value="528">528</option>
                                    <option value="530">530</option>
                                    <option value="542">542</option>
                                    <option value="544">544</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">SIT ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">NAME</th>
                                    <th class="py-4 px-4 text-center">PURPOSE</th>
                                    <th class="py-4 px-4 text-center">LAB</th>
                                    <th class="py-4 px-4 text-center">LOGIN</th>
                                    <th class="py-4 px-4 text-center">LOGOUT</th>
                                    <th class="py-4 px-4 text-center">DATE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sitinData)): ?>
                                    <?php foreach ($sitinData as $index => $sitin): ?>
                                        <tr class="<?php echo ($index % 2 === 0) ? 'bg-gray-100' : 'bg-gray-200'; ?>">
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['id']); ?></td>
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['purpose']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lab_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('h:i:s A', strtotime($sitin['time_in']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('h:i:s A', strtotime($sitin['time_out']))); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('Y-m-d', strtotime($sitin['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="py-4 px-4 text-center">No data found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>      
        </div>
    </div>
    <script>
        // Data for Pie Charts
        const purposeData = {
            labels: ["C Programming", "C# Programming", "Java Programming ", "PHP Programming", "ASP Net", "Others"],
            datasets: [{
                data: [
                    <?php
                    $javaCount = 0;
                    $cCount = 0;
                    $csCount = 0;
                    $phpCount = 0;
                    $aspCount = 0;
                    $othersCount = 0;
                    foreach ($sitinData as $sitin) {
                        if ($sitin['purpose'] === 'Java Programming') $javaCount++;
                        elseif ($sitin['purpose'] === 'C Programming') $cCount++;
                        elseif ($sitin['purpose'] === 'C# Programming') $csCount++;
                        elseif ($sitin['purpose'] === 'PHP Programming') $phpCount++;
                        elseif ($sitin['purpose'] === 'ASP Net') $aspCount++;
                        else $othersCount++;
                    }
                    echo $cCount . ', ' . $csCount . ', ' . $javaCount . ', ' . $phpCount . ', ' . $aspCount . ', ' . $othersCount;
                    ?>
                ],
                backgroundColor: ["#1E3A8A", "#1D4ED8", "#3B82F6", "#60A5FA", "#93C5FD", "#BFDBFE"],
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

        // Render Pie Charts
        const purposeChart = new Chart(document.getElementById('purposeChart'), {
            type: 'pie',
            data: purposeData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Purpose Distribution',
                    },
                },
            },
        });

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

        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#sitinTable tbody tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                let match = false;

                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(searchValue)) {
                        match = true;
                    }
                });

                row.style.display = match ? '' : 'none';
            });
        });

        // Filter Functionality
        const purposeFilter = document.getElementById('purposeFilter');
        const labFilter = document.getElementById('labFilter');
        const sitinTable = document.getElementById('sitinTable');

        function filterTable() {
            const purposeValue = purposeFilter.value.toLowerCase();
            const labValue = labFilter.value.toLowerCase();
            const rows = sitinTable.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const purposeCell = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                const labCell = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                const matchesPurpose = purposeValue ? purposeCell.includes(purposeValue) : true;
                const matchesLab = labValue ? labCell.includes(labValue) : true;

                row.style.display = matchesPurpose && matchesLab ? '' : 'none';
            });
        }

        purposeFilter.addEventListener('change', filterTable);
        labFilter.addEventListener('change', filterTable);

                // Entries per page functionality
                document.getElementById('entries').addEventListener('change', function() {
        const selectedValue = parseInt(this.value); // Get the selected value (10, 25, or 50)
        const rows = document.querySelectorAll('#sitinTable tbody tr'); // Get all table rows

        rows.forEach((row, index) => {
            if (index < selectedValue) {
                row.style.display = ''; // Show rows up to the selected value
            } else {
                row.style.display = 'none'; // Hide the rest
            }
        });
    });

    // Initialize table with default entries per page
    function initializeTable() {
        const defaultEntries = 5; // Default number of entries
        const rows = document.querySelectorAll('#sitinTable tbody tr'); // Get all table rows

        rows.forEach((row, index) => {
            if (index < defaultEntries) {
                row.style.display = ''; // Show rows up to the default value
            } else {
                row.style.display = 'none'; // Hide the rest
            }
        });
    }

    // Call the initialize function on page load
    initializeTable();
    </script>
</body>
</html>