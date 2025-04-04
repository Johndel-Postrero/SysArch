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
        WHERE sitin.time_out IS NOT NULL";

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <style>
        body {
            font-family: "Poppins-Regular";
            color: #333;
            font-size: 16px;
            margin: 0;
        }
        .header {
            z-index: 1000;
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
        /* Custom Dropdown Calendar */
        .dropdown-calendar {
            position: relative;
            display: inline-block;
        }
        .dropdown-calendar-content {
            display: none;
            position: absolute;
            background-color: #fff;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 16px;
            z-index: 1000;
        }
        .dropdown-calendar.open .dropdown-calendar-content {
            display: block;
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
                    <!-- Simplified Controls Section -->
                    <div class="bg-white p-4 rounded-lg shadow mb-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <!-- Left Side - Filters -->
                            <div class="flex flex-col sm:flex-row gap-4">
                                <!-- Entries Selector -->
                                <div class="flex items-center">
                                    <label class="text-gray-600 mr-2 whitespace-nowrap">Show</label>
                                    <select class="border border-gray-300 rounded-md p-2" id="entries">
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                    <span class="text-gray-600 ml-2 whitespace-nowrap">entries</span>
                                </div>

                                <!-- Search -->
                                <div class="relative flex-1 min-w-[200px]">
                                    <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search..." type="text"/>
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </div>

                            <!-- Right Side - Actions -->
                            <div class="flex flex-col sm:flex-row gap-2">
                                <!-- Date Range Filter -->
                                <div class="dropdown-calendar">
                                    <button id="dateRangeButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2 whitespace-nowrap">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Date Range</span>
                                    </button>
                                    <div class="dropdown-calendar-content">
                                        <div class="flex space-x-2 mb-2">
                                            <input type="text" id="fromDate" class="border border-gray-300 rounded-md p-2" placeholder="From Date">
                                            <input type="text" id="toDate" class="border border-gray-300 rounded-md p-2" placeholder="To Date">
                                        </div>
                                        <div class="flex justify-between">
                                            <button id="clearDates" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                Clear Dates
                                            </button>
                                            <button class="text-[#002044] hover:text-blue-800 text-sm font-medium" onclick="document.querySelector('.dropdown-calendar').classList.remove('open')">
                                                Close
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Filters -->
                                <div class="flex space-x-2">
                                    <select id="purposeFilter" class="border border-gray-300 rounded-md p-2 min-w-[120px]">
                                        <option value="">All Purposes</option>
                                        <option value="C Programming">C Programming</option>
                                        <option value="C# Programming">C#</option>
                                        <option value="Java Programming">Java</option>
                                        <option value="PHP Programming">PHP</option>
                                        <option value="ASP Net">ASP Net</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    
                                    <select id="labFilter" class="border border-gray-300 rounded-md p-2 min-w-[80px]">
                                        <option value="">All Labs</option>
                                        <option value="524">524</option>
                                        <option value="526">526</option>
                                        <option value="528">528</option>
                                        <option value="530">530</option>
                                        <option value="542">542</option>
                                        <option value="544">544</option>
                                    </select>
                                </div>

                                <!-- Export Dropdown -->
                                <div class="relative">
                                    <button id="exportDropdownButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2 whitespace-nowrap">
                                        <i class="fas fa-download"></i>
                                        <span>Export</span>
                                    </button>
                                    <div id="exportDropdown" class="absolute right-0 mt-1 w-40 bg-white rounded-md shadow-lg hidden z-10">
                                        <div class="py-1">
                                            <button id="exportCSV" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-file-csv mr-2"></i>CSV
                                            </button>
                                            <button id="exportExcel" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-file-excel mr-2"></i>Excel
                                            </button>
                                            <button id="exportPDF" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-file-pdf mr-2"></i>PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Print Button -->
                                <button id="printButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center justify-center whitespace-nowrap">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table id="sitinTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
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
                                        <td colspan="7" class="py-4 px-4 text-center">No data found</td>
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
    // Initialize Flatpickr for Date Range
    const fromDateInput = flatpickr("#fromDate", {
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            filterTableByDateRange();
        }
    });

    const toDateInput = flatpickr("#toDate", {
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            filterTableByDateRange();
        }
    });

    // Toggle Dropdown Calendar
    const dropdownCalendar = document.querySelector('.dropdown-calendar');
    document.getElementById('dateRangeButton').addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownCalendar.classList.toggle('open');
    });

    // Prevent dropdown from closing when clicking inside it
    document.querySelector('.dropdown-calendar-content').addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Close Dropdown Calendar when clicking outside
    document.addEventListener('click', function(event) {
        if (!dropdownCalendar.contains(event.target)) {
            dropdownCalendar.classList.remove('open');
        }
    });

    // Clear Dates button functionality
    document.getElementById('clearDates').addEventListener('click', function(e) {
        e.stopPropagation();
        fromDateInput.clear();
        toDateInput.clear();
        filterTableByDateRange();
    });

    // Export Dropdown Toggle
    document.getElementById('exportDropdownButton').addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('exportDropdown');
        dropdown.classList.toggle('hidden');
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        document.getElementById('exportDropdown').classList.add('hidden');
    });

    // Filter Table by Date Range
    function filterTableByDateRange() {
        const fromDate = fromDateInput.selectedDates[0] ? fromDateInput.selectedDates[0] : null;
        const toDate = toDateInput.selectedDates[0] ? toDateInput.selectedDates[0] : null;
        const rows = document.querySelectorAll('#sitinTable tbody tr');

        rows.forEach(row => {
            const dateCell = row.querySelector('td:nth-child(7)').textContent;
            const rowDate = new Date(dateCell);

            // Reset time components to ensure only the date is compared
            const rowDateOnly = new Date(rowDate.getFullYear(), rowDate.getMonth(), rowDate.getDate());
            const fromDateOnly = fromDate ? new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate()) : null;
            const toDateOnly = toDate ? new Date(toDate.getFullYear(), toDate.getMonth(), toDate.getDate()) : null;

            // Check if the row date is within the selected range (inclusive)
            if (
                (!fromDateOnly || rowDateOnly >= fromDateOnly) && // Row date is after or equal to "From" date
                (!toDateOnly || rowDateOnly <= toDateOnly)       // Row date is before or equal to "To" date
            ) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

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
            const purposeCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const labCell = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            const matchesPurpose = purposeValue ? purposeCell.includes(purposeValue) : true;
            const matchesLab = labValue ? labCell.includes(labValue) : true;

            row.style.display = matchesPurpose && matchesLab ? '' : 'none';
        });
    }

    purposeFilter.addEventListener('change', filterTable);
    labFilter.addEventListener('change', filterTable);

    // Export to CSV
    document.getElementById('exportCSV').addEventListener('click', function() {
        const rows = document.querySelectorAll('#sitinTable tbody tr');
        let csvContent = "data:text/csv;charset=utf-8,";
        const headers = Array.from(document.querySelectorAll('#sitinTable thead th')).map(th => th.textContent).join(',');
        csvContent += headers + "\n";

        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const rowData = Array.from(row.querySelectorAll('td')).map(td => td.textContent).join(',');
                csvContent += rowData + "\n";
            }
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "sitin_records.csv");
        document.body.appendChild(link);
        link.click();
    });

    // Export to Excel
    document.getElementById('exportExcel').addEventListener('click', function() {
        const rows = document.querySelectorAll('#sitinTable tbody tr');
        const data = [];
        const headers = Array.from(document.querySelectorAll('#sitinTable thead th')).map(th => th.textContent);
        data.push(headers);

        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const rowData = Array.from(row.querySelectorAll('td')).map(td => td.textContent);
                data.push(rowData);
            }
        });

        const ws = XLSX.utils.aoa_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
        XLSX.writeFile(wb, "sitin_records.xlsx");
    });

    // Export to PDF
    document.getElementById('exportPDF').addEventListener('click', function() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'pt', 'a4');
        const headers = Array.from(document.querySelectorAll('#sitinTable thead th')).map(th => th.textContent);
        const rows = document.querySelectorAll('#sitinTable tbody tr');
        const data = [];

        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const rowData = Array.from(row.querySelectorAll('td')).map(td => td.textContent);
                data.push(rowData);
            }
        });

        doc.autoTable({
            head: [headers],
            body: data,
            startY: 20,
            margin: { top: 20 },
            styles: {
                fontSize: 10,
                cellPadding: 5,
                valign: 'middle',
                halign: 'center',
                lineColor: [0, 0, 0],
                lineWidth: 0.1,
            },
            headStyles: {
                fillColor: false,
                textColor: [0, 0, 0],
                fontStyle: 'bold',
                lineWidth: 0.1,
            },
            bodyStyles: {
                fillColor: false,
                textColor: [0, 0, 0],
                lineWidth: 0.1,
            },
            alternateRowStyles: {
                fillColor: false,
            },
            columnStyles: {
                0: { cellWidth: 'auto' },
                1: { cellWidth: 'auto' },
                2: { cellWidth: 'auto' },
                3: { cellWidth: 'auto' },
                4: { cellWidth: 'auto' },
                5: { cellWidth: 'auto' },
                6: { cellWidth: 'auto' },
            },
        });

        doc.save("sitin_records.pdf");
    });

    // Print Table
    document.getElementById('printButton').addEventListener('click', function() {
        printJS({
            printable: 'sitinTable',
            type: 'html',
            style: 'table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #000; padding: 8px; text-align: center; }'
        });
    });

    // Entries per page functionality
    document.getElementById('entries').addEventListener('change', function() {
        const selectedValue = parseInt(this.value);
        const rows = document.querySelectorAll('#sitinTable tbody tr');

        rows.forEach((row, index) => {
            if (index < selectedValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Initialize table with default entries per page
    function initializeTable() {
        const defaultEntries = 5;
        const rows = document.querySelectorAll('#sitinTable tbody tr');

        rows.forEach((row, index) => {
            if (index < defaultEntries) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Call the initialize function on page load
    initializeTable();
</script>
</body>
</html>