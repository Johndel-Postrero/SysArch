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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { margin: 0; overflow-x: hidden; background: #0D0B1A; color: #fff; font-family: 'Inter', sans-serif; }
        #star-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
        .star-rating { color: #D4870A; }
        .hidden-column { display: none; }
        .message-cell { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Overlay — Dark Academic */
        .fb-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(5px);
            justify-content: center; align-items: center; z-index: 1000;
        }
        .fb-overlay-content {
            background: #151226; border: 1px solid rgba(139,63,217,0.3);
            border-radius: 20px; padding: 30px; width: 90%; max-width: 650px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), 0 0 30px rgba(139,63,217,0.1);
            color: #fff; position: relative; max-height: 85vh; overflow-y: auto;
        }
        .fb-overlay-content::-webkit-scrollbar { width: 6px; }
        .fb-overlay-content::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
        .fb-overlay-content::-webkit-scrollbar-thumb { background: rgba(139,63,217,0.4); border-radius: 10px; }
        .fb-close { position: absolute; top: 16px; right: 20px; color: #9A8FB0; background: none; border: none; cursor: pointer; font-size: 20px; transition: color 0.2s; }
        .fb-close:hover { color: #fff; }
        .fb-detail-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #9A8FB0; margin-bottom: 4px; }
        .fb-detail-value { font-size: 14px; color: #D1C7E0; margin-bottom: 16px; }
        .fb-detail-message { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 16px; font-size: 14px; color: #D1C7E0; line-height: 1.6; }
        .fb-flagged { background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.3); color: #ef4444; }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>

    <!-- Overlay for detailed view -->
    <div id="feedbackOverlay" class="fb-overlay">
        <div class="fb-overlay-content">
            <button class="fb-close" onclick="document.getElementById('feedbackOverlay').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
            <h2 style="font-family:'Orbitron',sans-serif; font-size:18px; margin-bottom:24px; color:#fff;">Feedback Details</h2>
            <div id="overlayContent"></div>
        </div>
    </div>

    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <!-- Controls Row -->
            <div class="controls-row">
                <div class="controls-left"></div>
                <div class="controls-right">
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" placeholder="Search feedback..." type="text"/>
                    </div>
                    <div style="position: relative;">
                        <button id="sortButton" class="filter-btn">
                            <i class="fas fa-sort-amount-down"></i>
                            <span id="sortButtonText">Sort: Newest First</span>
                            <i class="fas fa-chevron-down text-[10px] ml-1"></i>
                        </button>
                        <div id="sortDropdown" class="filter-dropdown hidden animate-fade-in" style="min-width: 160px; right: 0;">
                            <a href="#" data-sort="newest" class="sort-opt dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Newest First</a>
                            <a href="#" data-sort="oldest" class="sort-opt dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Oldest First</a>
                            <a href="#" data-sort="az" class="sort-opt dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Name: A - Z</a>
                            <a href="#" data-sort="za" class="sort-opt dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Name: Z - A</a>
                        </div>
                    </div>
                    <div class="export-group" style="position: relative; display: flex; gap: 6px;">
                        <button id="exportButton" class="btn-export btn-csv" style="background: linear-gradient(135deg, var(--purple-glow), var(--purple-light)); display:flex; align-items:center; gap:6px; color:#fff; border:none; padding:10px 20px; border-radius:12px; font-weight:600; font-size:13px; cursor:pointer;">
                            <i class="fas fa-file-export"></i><span>Export</span>
                            <i class="fas fa-chevron-down text-[10px] ml-1"></i>
                        </button>
                        <div id="exportDropdown" class="filter-dropdown hidden animate-fade-in" style="position: absolute; top: calc(100% + 8px); right: 0; background: #161326; border: 1px solid rgba(139, 63, 217, 0.3); border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5); z-index: 1000; min-width: 120px;">
                            <a href="#" id="exportCSV" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white"><i class="fas fa-file-csv text-blue-400 mr-2"></i> CSV</a>
                            <a href="#" id="exportExcel" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white"><i class="fas fa-file-excel text-green-400 mr-2"></i> Excel</a>
                            <a href="#" id="exportPDF" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white"><i class="fas fa-file-pdf text-red-400 mr-2"></i> PDF</a>
                        </div>
                        
                        <button id="printButton" class="btn-export btn-print" style="background: rgba(255,255,255,0.08); border: 1px solid var(--border); display:flex; align-items:center; gap:6px; color:#fff; padding:10px 20px; border-radius:12px; font-weight:600; font-size:13px; cursor:pointer;">
                            <i class="fas fa-print"></i><span>Print</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
                  <div class="content-card">
                    <div class="dark-table-wrap" style="height: auto !important; min-height: 370px !important; max-height: none !important; overflow: visible !important;">
                        <table id="feedbackTable" class="dark-table">
                            <thead>
                                <tr>
                                    <th>ID NUMBER</th>
                                    <th>FULL NAME</th>
                                    <th class="hidden-column">COURSE</th>
                                    <th>LABORATORY</th>
                                    <th>DATE</th>
                                    <th class="hidden-column">TIME IN</th>
                                    <th class="hidden-column">TIME OUT</th>
                                    <th class="max-w-[300px]">MESSAGE</th>
                                    <th class="hidden-column">RATING</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($feedbackData)): ?>
                                <?php foreach ($feedbackData as $index => $feedback): ?>
                                    <tr data-id="<?php echo $feedback['feedback_id']; ?>">
                                            <td><span class="id-cell <?php echo $feedback['contains_foul_word'] ? 'text-red-500' : ''; ?>"><?php echo htmlspecialchars($feedback['idno']); ?></span></td>
                                            <td><span class="name-text <?php echo $feedback['contains_foul_word'] ? 'text-red-500' : ''; ?>"><?php echo htmlspecialchars($feedback['lastname']. ', ' . $feedback['firstname']. ' ' . $feedback['middlename'] ); ?></span></td>
                                            <td class="hidden-column"><?php echo htmlspecialchars($feedback['course']. ' ' . $feedback['level']); ?></td>
                                            <td><span class="lab-badge"><?php echo htmlspecialchars($feedback['lab_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($feedback['created_at']))); ?></td>
                                            <td class="hidden-column"><?php echo htmlspecialchars(date('h:i:s A', strtotime($feedback['time_in']))); ?></td>
                                            <td class="hidden-column"><?php echo htmlspecialchars(date('h:i:s A', strtotime($feedback['time_out']))); ?></td>
                                            <td class="message-cell <?php echo $feedback['contains_foul_word'] ? 'text-red-500 font-bold' : ''; ?>"><?php echo htmlspecialchars($feedback['message']); ?></td>
                                            <td class="hidden-column" data-rating="<?php echo $feedback['rating']; ?>">
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
                                    <tr class="not-record">
                                        <td colspan="9" style="text-align:center;padding:60px 20px;color:#9A8FB0;">No feedback found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Row -->
                    <div class="pagination-row">
                        <div class="pagination-info" id="paginationInfo"></div>
                        <div class="pagination-controls" id="paginationControls"></div>
                    </div>
                  </div><!-- end content-card -->
        </div>
    </div>
    <script>
// Global variables for pagination
let currentPage = 1;
let totalPages = 1;
let currentSort = 'newest'; // Default sort

// Initialize sort dropdown functionality
document.getElementById('sortButton').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('sortDropdown').classList.toggle('hidden');
    document.getElementById('exportDropdown').classList.add('hidden');
});

// Initialize export dropdown functionality
document.getElementById('exportButton').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('exportDropdown').classList.toggle('hidden');
    document.getElementById('sortDropdown').classList.add('hidden');
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    const sortBtn = document.getElementById('sortButton');
    const sortDd = document.getElementById('sortDropdown');
    const expBtn = document.getElementById('exportButton');
    const expDd = document.getElementById('exportDropdown');
    
    if (sortDd && !sortDd.classList.contains('hidden') && !sortBtn.contains(e.target) && !sortDd.contains(e.target)) {
        sortDd.classList.add('hidden');
    }
    if (expDd && !expDd.classList.contains('hidden') && !expBtn.contains(e.target) && !expDd.contains(e.target)) {
        expDd.classList.add('hidden');
    }
});

// Overlay functionality
const overlay = document.getElementById('feedbackOverlay');
const overlayContent = document.getElementById('overlayContent');

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
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <div class="fb-detail-label">ID Number</div>
                    <div class="fb-detail-value">${rowData.idno}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Full Name</div>
                    <div class="fb-detail-value">${rowData.fullName}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Course & Year</div>
                    <div class="fb-detail-value">${rowData.course}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Laboratory</div>
                    <div class="fb-detail-value">${rowData.lab}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Date</div>
                    <div class="fb-detail-value">${rowData.date}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Time In</div>
                    <div class="fb-detail-value">${rowData.timeIn}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Time Out</div>
                    <div class="fb-detail-value">${rowData.timeOut}</div>
                </div>
                <div>
                    <div class="fb-detail-label">Rating</div>
                    <div class="star-rating" style="font-size:16px;">${stars.join('')}</div>
                </div>
            </div>
            <div style="margin-top:16px;">
                <div class="fb-detail-label">Message</div>
                <div class="fb-detail-message ${rowData.message && rowData.flagged ? 'fb-flagged' : ''}">${rowData.message}</div>
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
document.querySelectorAll('#sortDropdown .sort-opt').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        currentSort = this.getAttribute('data-sort');
        
        // Update sorting button visual label
        document.getElementById('sortButtonText').textContent = "Sort: " + this.textContent;
        
        currentPage = 1;
        filterTable();
        document.getElementById('sortDropdown').classList.add('hidden');
    });
});

// Main filter function with pagination
function filterTable() {
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const entriesNum = 7; // Fixed 7 entries per page matching announcements style
    
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

    // Calculate total pages for paginated results
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
    const paginationInfo = document.getElementById('paginationInfo');
    const paginationControls = document.getElementById('paginationControls');
    const entriesNum = 7;
    
    if (showAll || totalPages <= 1) {
        const startEntry = totalVisible === 0 ? 0 : 1;
        paginationInfo.textContent = `Showing ${startEntry} to ${totalVisible} of ${totalVisible} entries`;
        paginationControls.innerHTML = '';
        return;
    }
    
    const startEntry = totalVisible === 0 ? 0 : (currentPage - 1) * entriesNum + 1;
    const endEntry = Math.min(currentPage * entriesNum, totalVisible);
    
    paginationInfo.textContent = `Showing ${startEntry} to ${endEntry} of ${totalVisible} entries`;
    paginationControls.innerHTML = '';
    
    // Previous button
    const prevButton = document.createElement('button');
    prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevButton.className = 'page-btn';
    if (currentPage === 1) {
        prevButton.disabled = true;
        prevButton.classList.add('disabled');
    }
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
        firstPageButton.className = 'page-btn';
        firstPageButton.addEventListener('click', () => {
            currentPage = 1;
            filterTable();
        });
        paginationControls.appendChild(firstPageButton);
        
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.textContent = '...';
            ellipsis.className = 'px-2 py-1 text-gray-500';
            paginationControls.appendChild(ellipsis);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageButton = document.createElement('button');
        pageButton.textContent = i;
        pageButton.className = 'page-btn';
        if (i === currentPage) {
            pageButton.classList.add('active');
        }
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
            ellipsis.className = 'px-2 py-1 text-gray-500';
            paginationControls.appendChild(ellipsis);
        }
        
        const lastPageButton = document.createElement('button');
        lastPageButton.textContent = totalPages;
        lastPageButton.className = 'page-btn';
        lastPageButton.addEventListener('click', () => {
            currentPage = totalPages;
            filterTable();
        });
        paginationControls.appendChild(lastPageButton);
    }
    
    // Next button
    const nextButton = document.createElement('button');
    nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextButton.className = 'page-btn';
    if (currentPage === totalPages) {
        nextButton.disabled = true;
        nextButton.classList.add('disabled');
    }
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

// Initialize table on page load
filterTable();
    </script>

    <!-- Star & Shooting Star Canvas -->
    <script>
    (function(){
        const canvas = document.getElementById('star-canvas');
        if(!canvas) return;
        const ctx = canvas.getContext('2d');
        let W, H, stars = [], shoots = [];
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        for (let i = 0; i < 180; i++) {
            stars.push({ x: Math.random()*9999, y: Math.random()*9999, r: Math.random()*1.4+0.3, a: Math.random(), da: (Math.random()*0.008+0.003)*(Math.random()<.5?1:-1) });
        }
        function spawnShoot() {
            shoots.push({ x: Math.random()*W*1.2, y: Math.random()*H*0.5, len: Math.random()*120+80, speed: Math.random()*6+4, angle: Math.PI/4, alpha: 1, tail: [] });
            setTimeout(spawnShoot, Math.random()*6000+3000);
        }
        setTimeout(spawnShoot, 2000);
        function draw() {
            ctx.clearRect(0,0,W,H);
            stars.forEach(s => { s.a += s.da; if(s.a<=0||s.a>=1) s.da*=-1; ctx.beginPath(); ctx.arc(s.x%W, s.y%H, s.r, 0, Math.PI*2); ctx.fillStyle=`rgba(255,255,255,${s.a*0.8})`; ctx.fill(); });
            shoots.forEach((s,i) => { s.x += Math.cos(s.angle)*s.speed; s.y += Math.sin(s.angle)*s.speed; s.tail.push({x:s.x,y:s.y}); if(s.tail.length>20) s.tail.shift(); s.alpha -= 0.008;
                ctx.beginPath(); s.tail.forEach((p,j) => { j===0?ctx.moveTo(p.x,p.y):ctx.lineTo(p.x,p.y); });
                ctx.strokeStyle=`rgba(200,180,255,${s.alpha*0.6})`; ctx.lineWidth=1.5; ctx.stroke();
                if(s.alpha<=0||s.x>W+200||s.y>H+200) shoots.splice(i,1);
            });
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>
</body>
</html>