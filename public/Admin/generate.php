<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

// Include the database connection
require __DIR__ . '/../../config/db.php';

// Fetch data from the sitin table
$sql = "SELECT sitin.id, sitin.idno, users.lastname, users.firstname, users.middlename, sitin.purpose, sitin.lab_number, sitin.time_in, sitin.time_out, sitin.created_at
        FROM sitin 
        JOIN users ON sitin.idno = users.idno
        WHERE sitin.time_out IS NOT NULL
        ORDER By sitin.created_at DESC";

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
    <title>Generate Report</title>
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
                    <!-- Controls (Entries, Search, Filter) -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Entries Selection (Left) -->
                        <div class="flex items-center space-x-2">
                            <label class="text-gray-600" for="entries">
                                Entries per page
                            </label>
                            <select class="border border-gray-300 rounded-md p-2" id="entries">
                                <option value="all" selected>All</option>
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </div>

                        <!-- Search and Filter (Right) -->
                        <div class="flex items-center space-x-4">
                            <!-- Search -->
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <!-- Purpose Filter -->
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

                            <!-- Lab Filter -->
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

                    <!-- Calendar and Export Buttons -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Date Range Filter -->
                            <div class="dropdown-calendar">
                                <button id="dateRangeButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Select Date Range</span>
                                </button>
                                <div class="dropdown-calendar-content">
                                    <div class="flex space-x-2 mb-2">
                                        <input type="text" id="fromDate" class="border border-gray-300 rounded-md p-2" placeholder="From Date">
                                        <input type="text" id="toDate" class="border border-gray-300 rounded-md p-2" placeholder="To Date">
                                    </div>
                                    <div class="flex justify-end">
                                        <button id="clearDates" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Clear Dates
                                        </button>
                                    </div>
                                </div>
                            </div>

                        <!-- Export Buttons -->
                        <div class="flex space-x-2">
                            <button id="exportCSV" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-csv"></i>
                                <span>CSV</span>
                            </button>
                            <button id="exportExcel" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-excel"></i>
                                <span>Excel</span>
                            </button>
                            <button id="exportPDF" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-file-pdf"></i>
                                <span>PDF</span>
                            </button>
                            <button id="printButton" class="bg-[#002044] text-white px-4 py-2 rounded-md flex items-center space-x-2">
                                <i class="fas fa-print"></i>
                                <span>Print</span>
                            </button>
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
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname']. ' '. $sitin['middlename']. '.'); ?></td>
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
// Initialize Flatpickr for Date Range
const fromDateInput = flatpickr("#fromDate", {
    dateFormat: "Y-m-d"
});

const toDateInput = flatpickr("#toDate", {
    dateFormat: "Y-m-d"
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
    filterTable();
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
    const fromDate = fromDateInput.selectedDates[0] ? fromDateInput.selectedDates[0] : null;
    const toDate = toDateInput.selectedDates[0] ? toDateInput.selectedDates[0] : null;
    
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    let visibleRows = [];
    let totalVisible = 0;

    // First pass: count all matching rows
    rows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        const purposeCell = cells[2].textContent.toLowerCase();
        const labCell = cells[3].textContent.toLowerCase();
        const dateCell = cells[6].textContent;
        const rowDate = new Date(dateCell);
        
        const rowDateOnly = new Date(rowDate.getFullYear(), rowDate.getMonth(), rowDate.getDate());
        const fromDateOnly = fromDate ? new Date(fromDate.getFullYear(), fromDate.getMonth(), fromDate.getDate()) : null;
        const toDateOnly = toDate ? new Date(toDate.getFullYear(), toDate.getMonth(), toDate.getDate()) : null;

        const matchesSearch = searchValue ? 
            Array.from(cells).some(cell => cell.textContent.toLowerCase().includes(searchValue)) : true;
        const matchesPurpose = purposeValue ? purposeCell.includes(purposeValue) : true;
        const matchesLab = labValue ? labCell.includes(labValue) : true;
        const matchesDateRange = (
            (!fromDateOnly || rowDateOnly >= fromDateOnly) && 
            (!toDateOnly || rowDateOnly <= toDateOnly)
        );

        if (matchesSearch && matchesPurpose && matchesLab && matchesDateRange) {
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
fromDateInput.config.onChange.push(filterTable);
toDateInput.config.onChange.push(filterTable);

// Initialize table with default filters and pagination
filterTable();

function getExportHeaderText() {
    return [
        "University of Cebu",
        "College of Computer Studies",
        "Computer Laboratory Sit-In Monitoring System Report"
    ];
}

// Export to CSV with proper quoting of fields
document.getElementById('exportCSV').addEventListener('click', function() {
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add header text
    const headerText = getExportHeaderText();
    headerText.forEach(line => {
        csvContent += `"${line}"\n`;
    });
    csvContent += "\n"; // Add empty line after header
    
    // Add table headers
    const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
        .map(th => `"${th.textContent.replace(/"/g, '""')}"`)
        .join(',');
    csvContent += headers + "\n";

    // Rest of your existing CSV code...
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td')).map(cell => {
                return `"${cell.textContent.trim().replace(/"/g, '""')}"`;
            }).join(',');
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

// Export to Excel (updated to only export visible rows)
document.getElementById('exportExcel').addEventListener('click', function() {
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    const data = [];
    
    // Add header text
    const headerText = getExportHeaderText();
    headerText.forEach(line => {
        data.push([line]);
    });
    data.push([]); // Empty row
    
    // Add table headers
    const headers = Array.from(document.querySelectorAll('#sitinTable thead th')).map(th => th.textContent);
    data.push(headers);

    // Add table data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td')).map(td => td.textContent);
            data.push(rowData);
        }
    });

    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Merge cells for header text to center them
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: headers.length - 1 } },
        { s: { r: 2, c: 0 }, e: { r: 2, c: headers.length - 1 } }
    ];
    
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
    XLSX.writeFile(wb, "sitin_records.xlsx");
});

// Export to PDF (updated to only export visible rows)
document.getElementById('exportPDF').addEventListener('click', function() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    const headers = Array.from(document.querySelectorAll('#sitinTable thead th')).map(th => th.textContent);
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    const data = [];

    // Add header text
    const headerText = getExportHeaderText();
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold'); 
    doc.text(headerText[0], doc.internal.pageSize.width / 2, 30, { align: 'center' });
    doc.text(headerText[1], doc.internal.pageSize.width / 2, 50, { align: 'center' });
    doc.text(headerText[2], doc.internal.pageSize.width / 2, 70, { align: 'center' });
    
    // Prepare table data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td')).map(td => td.textContent);
            data.push(rowData);
        }
    });

    doc.autoTable({
        head: [headers],
        body: data,
        startY: 90, // Start table below the header text
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
            fillColor: [0, 32, 68],
            textColor: [255, 255, 255],
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

// Print functionality - includes all columns with proper styling
document.getElementById('printButton').addEventListener('click', function() {
    const rows = Array.from(document.querySelectorAll('#sitinTable tbody tr'))
        .filter(row => row.style.display !== 'none');
    
    // Create a temporary container
    const tempDiv = document.createElement('div');
    
    // Add header
    const headerText = [
        "UNIVERSITY OF CEBU",
        "College of Computer Studies",
        "Computer Laboratory Sit-In Monitoring System",
    ];
    
    const headerDiv = document.createElement('div');
    headerDiv.style.textAlign = 'center';
    headerDiv.style.marginBottom = '20px';
    
    const title1 = document.createElement('h1');
    title1.textContent = headerText[0];
    title1.style.fontSize = '14px';
    title1.style.fontWeight = 'bold';
    title1.style.marginBottom = '5px';
    headerDiv.appendChild(title1);
    
    const title2 = document.createElement('h2');
    title2.textContent = headerText[1];
    title2.style.fontSize = '14px';
    title2.style.marginBottom = '5px';
    headerDiv.appendChild(title2);
    
    const title3 = document.createElement('h3');
    title3.textContent = headerText[2];
    title3.style.fontSize = '14px';
    title3.style.marginBottom = '5px';
    headerDiv.appendChild(title3);
    
    const reportTitle = document.createElement('h2');
    reportTitle.textContent = headerText[3];
    reportTitle.style.fontSize = '14px';
    reportTitle.style.fontWeight = 'bold';
    reportTitle.style.marginBottom = '5px';
    headerDiv.appendChild(reportTitle);
    
    tempDiv.appendChild(headerDiv);
    
    // Create table
    const printTable = document.createElement('table');
    printTable.style.width = '100%';
    printTable.style.borderCollapse = 'collapse';
    printTable.style.marginTop = '20px';
    
    // Table header
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    const headers = [
        "ID NUMBER", "NAME", "PURPOSE", "LAB", 
        "LOGIN", "LOGOUT", "DATE"
    ];
    
    headers.forEach(headerText => {
        const th = document.createElement('th');
        th.textContent = headerText;
        th.style.border = '1px solid #000';
        th.style.padding = '8px';
        th.style.backgroundColor = '#002044';
        th.style.color = 'white';
        th.style.textAlign = 'center';
        headerRow.appendChild(th);
    });
    
    thead.appendChild(headerRow);
    printTable.appendChild(thead);
    
    // Table body
    const tbody = document.createElement('tbody');
    
    rows.forEach((row, index) => {
        const cells = row.querySelectorAll('td');
        const newRow = document.createElement('tr');
        
        newRow.style.backgroundColor = index % 2 === 0 ? '#f2f2f2' : '#ffffff';
        
        // ID Number
        const idCell = document.createElement('td');
        idCell.textContent = cells[0].textContent;
        idCell.style.border = '1px solid #000';
        idCell.style.padding = '8px';
        idCell.style.textAlign = 'center';
        newRow.appendChild(idCell);
        
        // Name
        const nameCell = document.createElement('td');
        nameCell.textContent = cells[1].textContent;
        nameCell.style.border = '1px solid #000';
        nameCell.style.padding = '8px';
        nameCell.style.textAlign = 'center';
        newRow.appendChild(nameCell);
        
        // Purpose
        const purposeCell = document.createElement('td');
        purposeCell.textContent = cells[2].textContent;
        purposeCell.style.border = '1px solid #000';
        purposeCell.style.padding = '8px';
        purposeCell.style.textAlign = 'center';
        newRow.appendChild(purposeCell);
        
        // Lab
        const labCell = document.createElement('td');
        labCell.textContent = cells[3].textContent;
        labCell.style.border = '1px solid #000';
        labCell.style.padding = '8px';
        labCell.style.textAlign = 'center';
        newRow.appendChild(labCell);
        
        // Login
        const loginCell = document.createElement('td');
        loginCell.textContent = cells[4].textContent;
        loginCell.style.border = '1px solid #000';
        loginCell.style.padding = '8px';
        loginCell.style.textAlign = 'center';
        newRow.appendChild(loginCell);
        
        // Logout
        const logoutCell = document.createElement('td');
        logoutCell.textContent = cells[5].textContent;
        logoutCell.style.border = '1px solid #000';
        logoutCell.style.padding = '8px';
        logoutCell.style.textAlign = 'center';
        newRow.appendChild(logoutCell);
        
        // Date
        const dateCell = document.createElement('td');
        dateCell.textContent = cells[6].textContent;
        dateCell.style.border = '1px solid #000';
        dateCell.style.padding = '8px';
        dateCell.style.textAlign = 'center';
        newRow.appendChild(dateCell);
        
        tbody.appendChild(newRow);
    });
    
    printTable.appendChild(tbody);
    tempDiv.appendChild(printTable);
    
    // Print using printJS
    printJS({
        printable: tempDiv.innerHTML,
        type: 'raw-html',
        css: [
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css'
        ],
        style: `
            @media print {
                body { font-family: "Poppins-Regular", Arial, sans-serif; }
                h1, h2, h3 { margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #000; padding: 8px; text-align: center; }
                th { background-color: #002044 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                tr:nth-child(even) { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        `,
        onLoadingEnd: function() {
            tempDiv.remove();
        }
    });
});
</script>
</body>
</html>