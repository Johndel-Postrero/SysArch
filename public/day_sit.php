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
// Update the SQL query to join with rewards table
$sql = "SELECT sitin.id, sitin.idno, users.lastname, users.firstname, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at,
        rewards.id AS reward_id
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        LEFT JOIN rewards ON sitin.id = rewards.sitin_id
        WHERE sitin.time_out IS NOT NULL AND DATE(sitin.created_at) = CURDATE()
        ORDER BY sitin.created_at DESC";

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
                                <option value="all">All</option>
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
                                    <option value="Web Development">Web Development</option>
                                    <option value="Systems Integration & Architecture">Systems Integration & Architecture</option>
                                    <option value="Embedded Systems & IoT">Embedded Systems & IoT</option>
                                    <option value="Digital Logic & Design">Digital Logic & Design</option>
                                    <option value="Computer Application">Computer Application</option>
                                    <option value="Database">Database</option>
                                    <option value="Project Management">Project Management</option>
                                    <option value="Mobile Application">Mobile Application</option>
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
                                    <th class="py-4 px-4 text-center">ACTION</th>
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
                                            <td class="py-4 px-4 text-center">
                                                <?php if (empty($sitin['reward_id'])): ?>
                                                    <button onclick="giveReward(<?php echo $sitin['id']; ?>, '<?php echo $sitin['idno']; ?>', '<?php echo $sitin['lastname']; ?>', '<?php echo $sitin['firstname']; ?>')" 
                                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                                                        Reward
                                                    </button>
                                                <?php else: ?>
                                                    <button disabled class="bg-gray-300 text-white px-3 py-1 rounded">
                                                        Rewarded
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="py-4 px-4 text-center">No data found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex justify-between items-center mt-4">
                        <div class="text-gray-600" id="paginationInfo"></div>
                        <div class="flex space-x-2" id="paginationControls"></div>
                    </div>
                </div>
            </div>      
        </div>
    </div>
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

        // Render Pie Charts
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

        // Global variables for pagination
        let currentPage = 1;
        let totalPages = 1;

        // Main filter function with pagination
        function filterTable() {
            const searchValue = document.getElementById('searchInput').value.toLowerCase();
            const purposeValue = document.getElementById('purposeFilter').value.toLowerCase();
            const labValue = document.getElementById('labFilter').value.toLowerCase();
            const entriesPerPage = document.getElementById('entries').value;
            
            const rows = document.querySelectorAll('#sitinTable tbody tr');
            let visibleRows = [];
            let totalVisible = 0;

            // First pass: count all matching rows
            rows.forEach((row) => {
                const cells = row.querySelectorAll('td');
                const purposeCell = cells[3].textContent.toLowerCase();
                const labCell = cells[4].textContent.toLowerCase();
                
                const matchesSearch = searchValue ? 
                    Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(searchValue)) : true;
                const matchesPurpose = purposeValue ? purposeCell.includes(purposeValue) : true;
                const matchesLab = labValue ? labCell.includes(labValue) : true;

                if (matchesSearch && matchesPurpose && matchesLab) {
                    visibleRows.push(row);
                    totalVisible++;
                }
            });

            // Show all rows if "All" is selected
            if (entriesPerPage === "all") {
                rows.forEach(row => row.style.display = 'none');
                visibleRows.forEach(row => row.style.display = '');
                updatePaginationControls(totalVisible, true);
                return;
            }

            // Calculate total pages for paginated results
            const entriesNum = parseInt(entriesPerPage);
            totalPages = Math.ceil(totalVisible / entriesNum);
            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            } else if (totalPages === 0) {
                currentPage = 1;
            }

            // Second pass: show/hide rows based on pagination
            const startIndex = (currentPage - 1) * entriesNum;
            const endIndex = startIndex + entriesNum;

            rows.forEach(row => row.style.display = 'none');
            visibleRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

            // Update pagination controls
            updatePaginationControls(totalVisible, false);
        }

        function updatePaginationControls(totalVisible, showAll) {
            const entriesPerPage = document.getElementById('entries').value;
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationControls = document.getElementById('paginationControls');
            
            if (entriesPerPage === "all" || showAll) {
                // Show all entries - hide pagination controls
                paginationInfo.textContent = `Showing all ${totalVisible} entries`;
                paginationControls.innerHTML = '';
                return;
            }
            
            // Show paginated results
            const entriesNum = parseInt(entriesPerPage);
            const startEntry = totalVisible === 0 ? 0 : (currentPage - 1) * entriesNum + 1;
            const endEntry = Math.min(currentPage * entriesNum, totalVisible);
            
            // Update pagination info
            paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalVisible} entries`;
            
            // Update pagination controls
            paginationControls.innerHTML = '';
            
            // Previous button
            const prevButton = document.createElement('button');
            prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevButton.className = `px-3 py-1 rounded-md border ${currentPage === 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
            prevButton.disabled = currentPage === 1;
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    filterTable();
                }
            });
            paginationControls.appendChild(prevButton);
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            if (startPage > 1) {
                const firstPageButton = document.createElement('button');
                firstPageButton.textContent = '1';
                firstPageButton.className = 'px-3 py-1 rounded-md border bg-white text-[#002044] hover:bg-gray-100';
                firstPageButton.addEventListener('click', () => {
                    currentPage = 1;
                    filterTable();
                });
                paginationControls.appendChild(firstPageButton);
                
                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.textContent = '...';
                    ellipsis.className = 'px-2 py-1';
                    paginationControls.appendChild(ellipsis);
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = `px-3 py-1 rounded-md border ${i === currentPage ? 'bg-[#002044] text-white' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
                pageButton.addEventListener('click', () => {
                    currentPage = i;
                    filterTable();
                });
                paginationControls.appendChild(pageButton);
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.textContent = '...';
                    ellipsis.className = 'px-2 py-1';
                    paginationControls.appendChild(ellipsis);
                }
                
                const lastPageButton = document.createElement('button');
                lastPageButton.textContent = totalPages;
                lastPageButton.className = 'px-3 py-1 rounded-md border bg-white text-[#002044] hover:bg-gray-100';
                lastPageButton.addEventListener('click', () => {
                    currentPage = totalPages;
                    filterTable();
                });
                paginationControls.appendChild(lastPageButton);
            }
            
            // Next button
            const nextButton = document.createElement('button');
            nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextButton.className = `px-3 py-1 rounded-md border ${currentPage === totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-[#002044] hover:bg-gray-100'}`;
            nextButton.disabled = currentPage === totalPages;
            nextButton.addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    filterTable();
                }
            });
            paginationControls.appendChild(nextButton);
        }

        // Event listeners for all filters
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('purposeFilter').addEventListener('change', filterTable);
        document.getElementById('labFilter').addEventListener('change', filterTable);
        document.getElementById('entries').addEventListener('change', function() {
            currentPage = 1; // Reset to first page when changing entries per page
            filterTable();
        });

        // Initialize table with default filters and pagination
        filterTable();

        function giveReward(sitinId, idno, lastname, firstname) {
            if (confirm(`Give reward to ${lastname}, ${firstname} (ID: ${idno})?`)) {
                fetch('give_reward.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `sitin_id=${sitinId}&idno=${idno}&lastname=${encodeURIComponent(lastname)}&firstname=${encodeURIComponent(firstname)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reward given successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while giving reward.');
                });
            }
        }
    </script>
</body>
</html>