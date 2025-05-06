<?php
date_default_timezone_set('Asia/Manila');

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . '/../../config/db.php';

$sql = "SELECT feedback.feedback_id, users.idno, users.lastname, users.firstname, users.middlename, users.course, users.level, sitin.time_in, sitin.time_out, sitin.lab_number, feedback.message, feedback.rating, feedback.created_at 
        FROM feedback 
        JOIN users ON feedback.user_id = users.user_id
        JOIN sitin ON feedback.sitin_id = sitin.sitin_id
        ORDER BY feedback.created_at DESC";
$result = $conn->query($sql);

$foulWords = [
    "fuck you",
    "fuck",
    "shit",
    "gago",
    "yawa",
    "putang ina",
    "tang ina",
    "atay",
    "giatay",
    "pisti",
    "minatay",
    "puta",
    "bogo"
    // Add more foul words here
];
$feedbackData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Check for foul words in the message
        $containsFoulWord = false;
        $message = strtolower($row['message']);
        foreach ($foulWords as $word) {
            if (strpos($message, strtolower($word)) !== false) {
                $containsFoulWord = true;
                break;
            }
        }
        $row['contains_foul_word'] = $containsFoulWord;
        $feedbackData[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feedback Report</title>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
body { font-family: "Poppins-Regular"; color: #333; font-size: 16px; margin: 0; }
        .main-content {
            margin-left: 5rem;
            transition: margin-left 0.3s ease-in-out;
        }
        .sidebar:hover + .main-content {
            margin-left: 16rem;
        }
        .star-rating {
            color: #ffc107;
        }
        .dropdowns {
            position: relative;
            display: inline-block;
        }
        /* Overlay styles */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .overlay-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .hidden-column {
            display: none;
        }
        .message-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <!-- Overlay for detailed view -->
    <div id="feedbackOverlay" class="overlay">
        <div class="overlay-content relative">
            <span class="close-overlay text-gray-600">&times;</span>
            <h2 class="text-xl font-bold mb-4">Feedback Details</h2>
            <div id="overlayContent" class="space-y-4"></div>
        </div>
    </div>

    <div class="flex h-screen">
        <?php include 'sidebarad.php'; ?>

        <div class="main-content flex-1 flex flex-col">
            <?php include 'headerad.php'; ?>
            <div class="flex-1 p-6 flex flex-col items-center">
                <div class="w-full max-w-6xl">
                    <!-- Controls (Entries, Search, Sort, Export) -->
                    <div class="flex justify-between items-center mb-4">
                        <!-- Left Side: Entries per page -->
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

                        <!-- Right Side: Search, Sort, and Export -->
                        <div class="flex items-center space-x-4">
                            <!-- Search -->
                            <div class="relative">
                                <input id="searchInput" class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <!-- Sort Dropdown - Original Style -->
                            <div class="dropdowns">
                                <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <div id="sortDropdown" class="dropdowns-content absolute bg-white rounded-lg shadow-lg border border-gray-200 w-32 hidden">
                                    <a href="#" data-sort="az" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                    <a href="#" data-sort="za" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                    <a href="#" data-sort="newest" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                    <a href="#" data-sort="oldest" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
                                </div>
                            </div>

                            <!-- Export Buttons -->
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
                        <table id="feedbackTable" class="min-w-full bg-white shadow-md rounded-lg">
                            <thead>
                                <tr class="bg-[#002044] text-white">
                                    <th class="py-4 px-4 text-center">ID NUMBER</th>
                                    <th class="py-4 px-4 text-center">FULL NAME</th>
                                    <th class="py-4 px-4 text-center hidden-column">COURSE</th>
                                    <th class="py-4 px-4 text-center">LABORATORY</th>
                                    <th class="py-4 px-4 text-center">DATE</th>
                                    <th class="py-4 px-4 text-center hidden-column">TIME IN</th>
                                    <th class="py-4 px-4 text-center hidden-column">TIME OUT</th>
                                    <th class="py-4 px-4 text-center max-w-[300px]">MESSAGE</th>
                                    <th class="py-4 px-4 text-center hidden-column">RATING</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($feedbackData)): ?>
                                <?php foreach ($feedbackData as $index => $feedback): ?>
                                    <tr class="<?php echo ($feedback['contains_foul_word'] ? 'text-red-500' : ($index % 2 === 0 ? 'bg-gray-100' : 'bg-gray-200')); ?>" data-id="<?php echo $feedback['feedback_id']; ?>">
                                            <td class="py-4 px-4 font-semibold text-center"><?php echo htmlspecialchars($feedback['idno']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($feedback['lastname']. ', ' . $feedback['firstname']. ' ' . $feedback['middlename'] ); ?></td>
                                            <td class="py-4 px-4 text-center hidden-column"><?php echo htmlspecialchars($feedback['course']. ' ' . $feedback['level']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars($feedback['lab_number']); ?></td>
                                            <td class="py-4 px-4 text-center"><?php echo htmlspecialchars(date('Y-m-d', strtotime($feedback['created_at']))); ?></td>
                                            <td class="py-4 px-4 text-center hidden-column"><?php echo htmlspecialchars(date('h:i:s A', strtotime($feedback['time_in']))); ?></td>
                                            <td class="py-4 px-4 text-center hidden-column"><?php echo htmlspecialchars(date('h:i:s A', strtotime($feedback['time_out']))); ?></td>
                                            <td class="py-4 px-4 text-center message-cell"><?php echo htmlspecialchars($feedback['message']); ?></td>
                                            <td class="py-4 px-4 text-center hidden-column" data-rating="<?php echo $feedback['rating']; ?>">
                                                <div class="star-rating">
                                                    <?php
                                                    $rating = $feedback['rating'];
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $rating) {
                                                            echo '<i class="fas fa-star"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="py-4 px-4 text-center">No feedback found.</td>
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
// Global variables for pagination
let currentPage = 1;
let totalPages = 1;
let currentSort = 'newest'; // Default sort

// Initialize dropdown functionality
document.querySelector('.dropdowns').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('sortDropdown').classList.toggle('hidden');
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdowns')) {
        document.getElementById('sortDropdown').classList.add('hidden');
    }
});

// Overlay functionality
const overlay = document.getElementById('feedbackOverlay');
const overlayContent = document.getElementById('overlayContent');
const closeOverlay = document.querySelector('.close-overlay');

// Close overlay when clicking X
closeOverlay.addEventListener('click', function() {
    overlay.style.display = 'none';
});

// Close overlay when clicking outside content
overlay.addEventListener('click', function(e) {
    if (e.target === overlay) {
        overlay.style.display = 'none';
    }
});

// Add click event to table rows
document.querySelectorAll('#feedbackTable tbody tr').forEach(row => {
    row.addEventListener('click', function() {
        const cells = this.querySelectorAll('td');
        
        // Get all data from the row
        const rowData = {
            idno: cells[0].textContent,
            fullName: cells[1].textContent,
            course: cells[2].textContent,
            lab: cells[3].textContent,
            date: cells[4].textContent,
            timeIn: cells[5].textContent,
            timeOut: cells[6].textContent,
            message: cells[7].textContent,
            rating: this.querySelector('td[data-rating]').getAttribute('data-rating')
        };

        // Create HTML for overlay content
        const stars = [];
        for (let i = 1; i <= 5; i++) {
            stars.push(i <= rowData.rating ? 
                '<i class="fas fa-star text-yellow-400"></i>' : 
                '<i class="far fa-star text-yellow-400"></i>');
        }

        overlayContent.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="font-semibold">ID Number:</p>
                    <p>${rowData.idno}</p>
                </div>
                <div>
                    <p class="font-semibold">Full Name:</p>
                    <p>${rowData.fullName}</p>
                </div>
                <div>
                    <p class="font-semibold">Course & Year:</p>
                    <p>${rowData.course}</p>
                </div>
                <div>
                    <p class="font-semibold">Laboratory:</p>
                    <p>${rowData.lab}</p>
                </div>
                <div>
                    <p class="font-semibold">Date:</p>
                    <p>${rowData.date}</p>
                </div>
                <div>
                    <p class="font-semibold">Time In:</p>
                    <p>${rowData.timeIn}</p>
                </div>
                <div>
                    <p class="font-semibold">Time Out:</p>
                    <p>${rowData.timeOut}</p>
                </div>
                <div>
                    <p class="font-semibold">Rating:</p>
                    <div class="flex">${stars.join('')}</div>
                </div>
                <div class="col-span-2">
                    <p class="font-semibold">Message:</p>
                    <p class="whitespace-pre-wrap">${rowData.message}</p>
                </div>
            </div>
        `;

        overlay.style.display = 'flex';
    });
});

// Search Functionality
document.getElementById('searchInput').addEventListener('input', function() {
    currentPage = 1;
    filterTable();
});

// Sort Functionality
document.querySelectorAll('#sortDropdown a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        currentSort = this.getAttribute('data-sort');
        currentPage = 1;
        filterTable();
        document.getElementById('sortDropdown').classList.add('hidden');
    });
});

// Main filter function with pagination
function filterTable() {
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const entriesPerPage = document.getElementById('entries').value;
    
    const rows = document.querySelectorAll('#feedbackTable tbody tr');
    let visibleRows = [];
    let totalVisible = 0;

    // First pass: filter rows by search and count visible rows
    rows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        let match = searchValue === '';
        
        if (searchValue !== '') {
            cells.forEach(cell => {
                if (!cell.classList.contains('hidden-column') && 
                    cell.textContent.toLowerCase().includes(searchValue)) {
                    match = true;
                }
            });
        }

        if (match) {
            visibleRows.push(row);
            totalVisible++;
        }
    });

    // Sort the visible rows
    sortVisibleRows(visibleRows);

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

// Sort visible rows based on current sort type
function sortVisibleRows(rows) {
    rows.sort((a, b) => {
        const aMessage = a.querySelector('td:nth-child(8)').textContent.toLowerCase();
        const bMessage = b.querySelector('td:nth-child(8)').textContent.toLowerCase();
        const aDate = a.querySelector('td:nth-child(5)').textContent;
        const bDate = b.querySelector('td:nth-child(5)').textContent;

        switch (currentSort) {
            case 'az':
                return aMessage.localeCompare(bMessage);
            case 'za':
                return bMessage.localeCompare(aMessage);
            case 'newest':
                return new Date(bDate) - new Date(aDate);
            case 'oldest':
                return new Date(aDate) - new Date(bDate);
            default:
                return 0;
        }
    });
}

// Update pagination controls
function updatePaginationControls(totalVisible, showAll) {
    const entriesPerPage = document.getElementById('entries').value;
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');
    
    if (entriesPerPage === "all" || showAll) {
        paginationInfo.textContent = `Showing all ${totalVisible} entries`;
        paginationControls.innerHTML = '';
        return;
    }
    
    const entriesNum = parseInt(entriesPerPage);
    const startEntry = totalVisible === 0 ? 0 : (currentPage - 1) * entriesNum + 1;
    const endEntry = Math.min(currentPage * entriesNum, totalVisible);
    
    paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalVisible} entries`;
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

// Helper function to get full name from the table cell
function getFullName(cell) {
    return cell.textContent.trim();
}

function getExportHeaderText() {
    return [
        ["UNIVERSITY OF CEBU"],
        ["College of Computer Studies"],
        ["Computer Laboratory Sit-In Monitoring System"],
        ["Feedback Report"]
    ];
}

// Export to CSV - includes all columns with header
document.getElementById('exportCSV').addEventListener('click', function() {
    const rows = document.querySelectorAll('#feedbackTable tbody tr');
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add header information
    const headerText = getExportHeaderText();
    headerText.forEach(row => {
        csvContent += row.map(field => `"${field}"`).join(',') + "\n";
    });
    
    // Add column headers
    const headers = [
        "ID NUMBER",
        "FULL NAME",
        "COURSE AND YEAR",
        "LABORATORY",
        "DATE",
        "TIME IN",
        "TIME OUT",
        "MESSAGE",
        "RATING"
    ];
    csvContent += headers.join(',') + "\n";

    // Add data rows
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            const rowData = [
                cells[0].textContent,
                getFullName(cells[1]),
                cells[2].textContent,
                cells[3].textContent,
                cells[4].textContent,
                cells[5].textContent,
                cells[6].textContent,
                cells[7].textContent,
                row.querySelector('td[data-rating]').getAttribute('data-rating') + ' out of 5'
            ];
            csvContent += rowData.map(field => `"${field.replace(/"/g, '""')}"`).join(',') + "\n";
        }
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "feedback_report.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

// Export to Excel - includes all columns with merged header
document.getElementById('exportExcel').addEventListener('click', function() {
    const rows = document.querySelectorAll('#feedbackTable tbody tr');
    
    // Create workbook
    const wb = XLSX.utils.book_new();
    
    // Create worksheet data
    const data = [];
    
    // Add header information
    const headerText = getExportHeaderText();
    headerText.forEach(row => {
        data.push(row);
    });
    
    // Add column headers
    data.push([
        "ID NUMBER",
        "FULL NAME",
        "COURSE AND YEAR",
        "LABORATORY",
        "DATE",
        "TIME IN",
        "TIME OUT",
        "MESSAGE",
        "RATING"
    ]);
    
    // Add data rows
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            data.push([
                cells[0].textContent,
                getFullName(cells[1]),
                cells[2].textContent,
                cells[3].textContent,
                cells[4].textContent,
                cells[5].textContent,
                cells[6].textContent,
                cells[7].textContent,
                row.querySelector('td[data-rating]').getAttribute('data-rating') + ' out of 5'
            ]);
        }
    });
    
    // Create worksheet
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Merge header cells
    const merge = [];
    for (let i = 0; i < headerText.length; i++) {
        merge.push({ s: { r: i, c: 0 }, e: { r: i, c: 8 } }); // Merge all columns for each header row
    }
    ws["!merges"] = merge;
    
    // Add worksheet to workbook
    XLSX.utils.book_append_sheet(wb, ws, "Feedback Report");
    
    // Save the file
    XLSX.writeFile(wb, "feedback_report.xlsx");
});

// Export to PDF with header
document.getElementById('exportPDF').addEventListener('click', function() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    
    // Add header text
    const headerText = getExportHeaderText();
    let yPosition = 30;
    
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold');
    doc.text(headerText[0], doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
    yPosition += 20;
    doc.text(headerText[1], doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
    yPosition += 20;
    doc.text(headerText[2], doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
    yPosition += 20;
    doc.text(headerText[3], doc.internal.pageSize.width / 2, yPosition, { align: 'center' });
    yPosition += 20;
    

    
    // Table data
    const headers = [
        ["ID Number", "Full Name", "Course/Year", "Lab", "Date", "Time In", "Time Out", "Message", "Rating"]
    ];
    
    const rows = document.querySelectorAll('#feedbackTable tbody tr');
    const data = [];

    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            data.push([
                cells[0].textContent,
                getFullName(cells[1]),
                cells[2].textContent,
                cells[3].textContent,
                cells[4].textContent,
                cells[5].textContent,
                cells[6].textContent,
                cells[7].textContent,
                row.querySelector('td[data-rating]').getAttribute('data-rating') + '/5'
            ]);
        }
    });

    doc.autoTable({
        head: headers,
        body: data,
        startY: yPosition,
        margin: { top: yPosition },
        styles: {
            fontSize: 8,
            cellPadding: 3,
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
        columnStyles: {
            0: { cellWidth: 50 },
            1: { cellWidth: 80 },
            2: { cellWidth: 60 },
            3: { cellWidth: 30 },
            4: { cellWidth: 50 },
            5: { cellWidth: 60 },
            6: { cellWidth: 60 },
            7: { cellWidth: 100 },
            8: { cellWidth: 30 }
        },
        didDrawPage: function (data) {
            doc.setFontSize(10);
            doc.setTextColor(150);
            const pageCount = doc.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                doc.setPage(i);
                doc.text('Page ' + i + ' of ' + pageCount, doc.internal.pageSize.width - 50, doc.internal.pageSize.height - 20);
            }
        }
    });

    doc.save("feedback_report.pdf");
});

// Print functionality - includes all columns
document.getElementById('printButton').addEventListener('click', function() {
    const rows = Array.from(document.querySelectorAll('#feedbackTable tbody tr'))
        .filter(row => row.style.display !== 'none');
    
    // Create a temporary container
    const tempDiv = document.createElement('div');
    
    // Add header
    const headerText = getExportHeaderText();
    const headerDiv = document.createElement('div');
    headerDiv.style.textAlign = 'center';
    headerDiv.style.marginBottom = '13px';
    
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
    
    const date = document.createElement('p');
    date.textContent = headerText[4];
    date.style.fontSize = '14px';
    headerDiv.appendChild(date);
    
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
        "ID NUMBER", "FULL NAME", "COURSE AND YEAR", "LABORATORY", 
        "DATE", "TIME IN", "TIME OUT", "MESSAGE", "RATING"
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
        
        // Full Name
        const nameCell = document.createElement('td');
        nameCell.textContent = cells[1].textContent;
        nameCell.style.border = '1px solid #000';
        nameCell.style.padding = '8px';
        nameCell.style.textAlign = 'center';
        newRow.appendChild(nameCell);
        
        // Course and Year
        const courseCell = document.createElement('td');
        courseCell.textContent = cells[2].textContent;
        courseCell.style.border = '1px solid #000';
        courseCell.style.padding = '8px';
        courseCell.style.textAlign = 'center';
        newRow.appendChild(courseCell);
        
        // Laboratory
        const labCell = document.createElement('td');
        labCell.textContent = cells[3].textContent;
        labCell.style.border = '1px solid #000';
        labCell.style.padding = '8px';
        labCell.style.textAlign = 'center';
        newRow.appendChild(labCell);
        
        // Date
        const dateCell = document.createElement('td');
        dateCell.textContent = cells[4].textContent;
        dateCell.style.border = '1px solid #000';
        dateCell.style.padding = '8px';
        dateCell.style.textAlign = 'center';
        newRow.appendChild(dateCell);
        
        // Time In
        const timeInCell = document.createElement('td');
        timeInCell.textContent = cells[5].textContent;
        timeInCell.style.border = '1px solid #000';
        timeInCell.style.padding = '8px';
        timeInCell.style.textAlign = 'center';
        newRow.appendChild(timeInCell);
        
        // Time Out
        const timeOutCell = document.createElement('td');
        timeOutCell.textContent = cells[6].textContent;
        timeOutCell.style.border = '1px solid #000';
        timeOutCell.style.padding = '8px';
        timeOutCell.style.textAlign = 'center';
        newRow.appendChild(timeOutCell);
        
        // Message
        const messageCell = document.createElement('td');
        messageCell.textContent = cells[7].textContent;
        messageCell.style.border = '1px solid #000';
        messageCell.style.padding = '8px';
        messageCell.style.textAlign = 'center';
        messageCell.style.whiteSpace = 'normal';
        newRow.appendChild(messageCell);
        
        // Rating
        const ratingCell = document.createElement('td');
        ratingCell.textContent = row.querySelector('td[data-rating]').getAttribute('data-rating') + ' out of 5';
        ratingCell.style.border = '1px solid #000';
        ratingCell.style.padding = '8px';
        ratingCell.style.textAlign = 'center';
        newRow.appendChild(ratingCell);
        
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

// Entries per page functionality
document.getElementById('entries').addEventListener('change', function() {
    currentPage = 1;
    filterTable();
});

// Initialize table on page load
filterTable();
    </script>
</body>
</html>