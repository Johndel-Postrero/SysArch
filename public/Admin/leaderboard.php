<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . '/../../config/db.php';

// Fetch data for the sitin table
$sql = "SELECT s.sitin_id, s.idno, u.lastname, u.firstname, s.purpose, s.lab_number, s.time_in, s.time_out, s.created_at,
        (SELECT SUM(points) FROM rewards WHERE idno = s.idno) as total_rewards,
        (SELECT r.pc_number FROM reservations r WHERE r.idno = s.idno AND r.lab_number = s.lab_number AND r.reservation_date = DATE(s.created_at) ORDER BY r.reservation_id DESC LIMIT 1) as res_pc
        FROM sitin s
        JOIN users u ON s.idno = u.idno
        ORDER BY s.created_at DESC";
$result = $conn->query($sql);

$sitinData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['pc_number'] = $row['res_pc'] ? $row['res_pc'] : (($row['sitin_id'] % 30) + 1);
        $sitinData[] = $row;
    }
}

// Fetch Chart Data for Trend (Last 7 days)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('M d', strtotime($date));
    $countResult = $conn->query("SELECT COUNT(*) as total FROM sitin WHERE DATE(created_at) = '$date'");
    $count = $countResult ? $countResult->fetch_assoc()['total'] : 0;
    $chartData[] = ['label' => $label, 'count' => $count];
}

// Calculate purpose and lab distribution for pie charts
$purposeCounts = [
    "C Programming" => 0, "C# Programming" => 0, "Java Programming" => 0, 
    "PHP Programming" => 0, "ASP Net" => 0, "Web Development" => 0, 
    "Systems Integration & Architecture" => 0, "Embedded Systems & IoT" => 0, 
    "Digital Logic & Design" => 0, "Computer Application" => 0, 
    "Database" => 0, "Project Management" => 0, "Mobile Application" => 0, "Others" => 0
];
$labCounts = ["524" => 0, "526" => 0, "528" => 0, "530" => 0, "542" => 0, "544" => 0];

foreach ($sitinData as $s) {
    $purpose = $s['purpose'];
    if (array_key_exists($purpose, $purposeCounts)) {
        $purposeCounts[$purpose]++;
    } else {
        $purposeCounts["Others"]++;
    }
    
    $lab = $s['lab_number'];
    if (array_key_exists($lab, $labCounts)) {
        $labCounts[$lab]++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics – CCS Sit-In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <style>
        :root {
            --bg-main: #060411;
            --bg-card: rgba(22, 19, 38, 0.6);
            --purple-glow: #8B3FD9;
            --purple-light: #C084FC;
            --gold: #D4870A;
            --text-main: #ffffff;
            --text-dim: #9A8FB0;
            --border: rgba(139, 63, 217, 0.2);
            --font-h: 'Orbitron', sans-serif;
            --font-b: 'Inter', sans-serif;
        }

        body {
            background-color: #0D0B1A;
            color: var(--text-main);
            font-family: var(--font-b);
            margin: 0;
            overflow-x: hidden;
        }

        #star-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            display: block;
        }

        .main-wrapper {
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .analytics-content {
            padding: 30px 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Tabs */
        .tab-bar {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
            gap: 30px;
        }
        
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-dim);
            font-family: var(--font-b);
            font-size: 15px;
            font-weight: 600;
            padding: 0 10px 15px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            color: #fff;
        }
        
        .tab-btn.active {
            color: #fff;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--purple-glow), var(--purple-light));
            border-radius: 3px 3px 0 0;
            box-shadow: 0 -2px 10px rgba(139, 63, 217, 0.5);
        }

        /* Charts */
        .chart-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            backdrop-filter: blur(10px);
        }
        
        .chart-header {
            font-family: var(--font-h);
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 24px;
            text-align: center;
            letter-spacing: 1px;
        }

        .donut-container {
            height: 250px;
            position: relative;
            margin-bottom: 30px;
        }

        /* Legend Grid */
        .custom-legend {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 12px;
            color: var(--text-dim);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        /* Table Area Container Rules */
        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            backdrop-filter: blur(10px);
        }

        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .filter-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23C084FC' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 14px center !important;
            background-size: 14px !important;
            padding: 10px 16px !important;
            padding-right: 38px !important;
            font-size: 13px !important;
            height: 42px !important;
            background-color: rgba(22, 19, 38, 0.8) !important;
            border: 1px solid rgba(139, 63, 217, 0.3) !important;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
            color: #fff !important;
            cursor: pointer;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .custom-select:focus {
            border-color: #8B3FD9 !important;
            box-shadow: 0 0 12px rgba(139, 63, 217, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.2) !important;
        }

        .custom-select option {
            background-color: #161326 !important;
            color: #D1C7E0 !important;
            padding: 12px !important;
            font-size: 14px !important;
        }

        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: #fff;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            outline: none;
            transition: all 0.3s;
            width: 380px !important;
        }

        .search-input:focus {
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.2);
            background: rgba(255, 255, 255, 0.08);
        }

        .sort-dropdown a:hover {
            background: rgba(139, 63, 217, 0.15) !important;
            color: #C084FC !important;
        }

        .sortable-header {
            cursor: pointer;
            user-select: none;
            transition: color 0.3s ease;
        }

        .sortable-header:hover {
            color: var(--purple-light) !important;
        }

        .btn-print {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid var(--border) !important;
            color: #fff !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-print:hover {
            background: rgba(139, 63, 217, 0.25) !important;
            border-color: var(--purple-glow) !important;
            transform: translateY(-1px);
        }

        /* Beautiful Custom Scrollbar for all pages/elements */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(13, 11, 26, 0.5);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #8B3FD9, #C084FC);
            border-radius: 10px;
            border: 1px solid rgba(13, 11, 26, 0.3);
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #C084FC, #a855f7);
        }

        /* Fixed Table Container Rules */
        .table-container {
            height: 430px !important;
            min-height: 430px !important;
            max-height: 430px !important;
            overflow: hidden !important;
            position: relative;
        }

        #records-pane:not(.hidden) {
            display: flex !important;
            flex-direction: column;
            flex: 1;
        }

        #records-pane .content-card {
            flex: 1 !important;
            display: flex;
            flex-direction: column;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .custom-table th {
            text-align: left;
            color: var(--text-dim);
            font-weight: 600;
            font-size: 12px;
            padding: 0 20px 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border);
        }

        .custom-table tr {
            height: 52px !important;
        }

        .custom-table td {
            height: 52px !important;
            padding: 0 20px !important;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid transparent;
            border-bottom: 1px solid transparent;
            white-space: nowrap;
        }

        .custom-table tr:hover td {
            background: rgba(139, 63, 217, 0.05);
            border-top: 1px solid rgba(139, 63, 217, 0.2);
            border-bottom: 1px solid rgba(139, 63, 217, 0.2);
        }

        .custom-table td:first-child { border-radius: 12px 0 0 12px; border-left: 1px solid transparent; }
        .custom-table td:last-child { border-radius: 0 12px 12px 0; border-right: 1px solid transparent; }
        .custom-table tr:hover td:first-child { border-left: 1px solid rgba(139, 63, 217, 0.2); }
        .custom-table tr:hover td:last-child { border-right: 1px solid rgba(139, 63, 217, 0.2); }

        /* Badges */
        .purpose-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .lab-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.1);
            color: #60A5FA;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .status-active {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Empty State */
        .empty-state {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 100%;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto !important;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-dim);
            font-size: 13px;
        }

        .page-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .page-btn:hover:not(:disabled) {
            background: rgba(139, 63, 217, 0.2);
            border-color: var(--purple-glow);
        }
        .page-btn.active {
            background: var(--purple-glow);
            border-color: var(--purple-glow);
            color: #fff;
        }
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="analytics-content">
            
            <div class="tab-bar">
                <button class="tab-btn active" id="tab-charts" onclick="switchTab('charts')">
                    <i class="fas fa-chart-pie"></i> Charts
                </button>
                <button class="tab-btn" id="tab-records" onclick="switchTab('records')">
                    <i class="fas fa-history"></i> Sit-in Records
                </button>
            </div>

            <!-- CHARTS TAB -->
            <div id="charts-pane">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Purpose Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">Purpose Distribution</div>
                        <div class="donut-container">
                            <canvas id="purposeChart"></canvas>
                        </div>
                        <div class="custom-legend" id="purposeLegend"></div>
                    </div>
                    
                    <!-- Lab Distribution -->
                    <div class="chart-card">
                        <div class="chart-header">Lab Distribution</div>
                        <div class="donut-container">
                            <canvas id="labChart"></canvas>
                        </div>
                        <div class="custom-legend" id="labLegend"></div>
                    </div>
                </div>

                <!-- Trend Line Chart -->
                <div class="chart-card w-full">
                    <div class="chart-header flex justify-between items-center mb-6">
                        <span>Daily Sit-In Trend</span>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="sitInTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- RECORDS TAB -->
            <div id="records-pane" class="hidden">
                <div class="content-card">
                    
                    <div class="table-controls">
                        <div class="filter-group">
                            <div class="relative">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                                <input type="text" id="searchInput" class="search-input pl-10" style="width: 380px !important;" placeholder="Search students, sit-ins..." oninput="filterTable()">
                            </div>
                        </div>

                        <div class="filter-group">
                            <select id="purposeFilter" class="custom-select" onchange="filterTable()">
                                <option value="">All Purposes</option>
                                <?php foreach (array_keys($purposeCounts) as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select id="labFilter" class="custom-select" onchange="filterTable()">
                                <option value="">All Labs</option>
                                <?php foreach (array_keys($labCounts) as $l): ?>
                                    <option value="<?php echo htmlspecialchars($l); ?>">Lab <?php echo htmlspecialchars($l); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Premium Export Dropdown matching Student Export -->
                            <div style="position:relative; z-index: 99;">
                                <button id="exportButton" class="btn-export" style="background: linear-gradient(135deg, var(--purple-glow), var(--purple-light)); display:flex; align-items:center; gap:6px; color:#fff; border:none; padding:10px 20px; border-radius:12px; font-weight:600; font-size:13px; cursor:pointer;">
                                    <i class="fas fa-download"></i>
                                    <span>Export</span>
                                    <i class="fas fa-chevron-down text-xs ml-1"></i>
                                </button>
                                <div id="exportDropdown" class="sort-dropdown hidden" style="position: absolute; top: calc(100% + 8px); right: 0; background: #161326; border: 1px solid rgba(139, 63, 217, 0.3); border-radius: 12px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5); z-index: 1000; min-width: 120px;">
                                    <a href="#" id="exportCSV" style="display:block; padding:10px 16px; color:#D1C7E0; font-size:13px; text-decoration:none; transition:all 0.3s;"><i class="fas fa-file-csv text-blue-400 mr-2"></i> CSV</a>
                                    <a href="#" id="exportExcel" style="display:block; padding:10px 16px; color:#D1C7E0; font-size:13px; text-decoration:none; transition:all 0.3s;"><i class="fas fa-file-excel text-green-400 mr-2"></i> Excel</a>
                                    <a href="#" id="exportPDF" style="display:block; padding:10px 16px; color:#D1C7E0; font-size:13px; text-decoration:none; transition:all 0.3s;"><i class="fas fa-file-pdf text-red-400 mr-2"></i> PDF</a>
                                </div>
                            </div>

                            <!-- Print Button matching Student Page layout -->
                            <button id="printButton" class="btn-print">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>

                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th class="sortable-header" onclick="sortTable(0)">PC NUMBER <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                        <th class="sortable-header" onclick="sortTable(1)">ID NUMBER <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                        <th class="sortable-header" onclick="sortTable(2)">STUDENT NAME <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                        <th class="sortable-header" onclick="sortTable(3)">PURPOSE <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                        <th class="sortable-header" onclick="sortTable(4)">LAB <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                        <th>LOGIN</th>
                                        <th>LOGOUT</th>
                                        <th class="sortable-header" onclick="sortTable(7)">DATE <i class="fas fa-sort text-[10px] ml-1"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <?php 
                                    $purposeColors = [
                                        "C Programming" => "bg-pink-500/20 text-pink-400 border border-pink-500/30",
                                        "C# Programming" => "bg-purple-500/20 text-purple-400 border border-purple-500/30",
                                        "Java Programming" => "bg-yellow-500/20 text-yellow-400 border border-yellow-500/30",
                                        "PHP Programming" => "bg-blue-500/20 text-blue-400 border border-blue-500/30",
                                        "ASP Net" => "bg-orange-500/20 text-orange-400 border border-orange-500/30",
                                        "Web Development" => "bg-green-500/20 text-green-400 border border-green-500/30",
                                        "Systems Integration & Architecture" => "bg-indigo-500/20 text-indigo-400 border border-indigo-500/30",
                                        "Embedded Systems & IoT" => "bg-red-500/20 text-red-400 border border-red-500/30",
                                        "Digital Logic & Design" => "bg-teal-500/20 text-teal-400 border border-teal-500/30",
                                        "Computer Application" => "bg-cyan-500/20 text-cyan-400 border border-cyan-500/30",
                                        "Database" => "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30",
                                        "Project Management" => "bg-amber-500/20 text-amber-400 border border-amber-500/30",
                                        "Mobile Application" => "bg-fuchsia-500/20 text-fuchsia-400 border border-fuchsia-500/30",
                                        "Others" => "bg-gray-500/20 text-gray-400 border border-gray-500/30"
                                    ];
                                    
                                    if (!empty($sitinData)): 
                                        foreach ($sitinData as $sitin): 
                                            $pColor = $purposeColors[$sitin['purpose']] ?? "bg-gray-500/20 text-gray-400 border border-gray-500/30";
                                            $hasReward = (floatval($sitin['total_rewards']) > 0);
                                    ?>
                                        <tr class="table-row">
                                            <td class="font-medium text-white">PC <?php echo htmlspecialchars($sitin['pc_number']); ?></td>
                                            <td class="text-orange-400 font-medium"><?php echo htmlspecialchars($sitin['idno']); ?></td>
                                            <td class="text-white">
                                                <?php if($hasReward): ?><i class="fas fa-star text-yellow-400 mr-2 text-xs"></i><?php endif; ?>
                                                <?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname']); ?>
                                            </td>
                                            <td><span class="purpose-badge <?php echo $pColor; ?>"><?php echo htmlspecialchars($sitin['purpose']); ?></span></td>
                                            <td><span class="lab-badge"><?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                            <td class="text-gray-300"><?php echo date('h:i:s A', strtotime($sitin['time_in'])); ?></td>
                                            <td>
                                                <?php if (empty($sitin['time_out'])): ?>
                                                    <span class="status-active">Active</span>
                                                <?php else: ?>
                                                    <span class="text-gray-300"><?php echo date('h:i:s A', strtotime($sitin['time_out'])); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-gray-300"><?php echo date('Y-m-d', strtotime($sitin['created_at'])); ?></td>
                                        </tr>
                                    <?php 
                                        endforeach; 
                                    endif; 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="emptyState" class="empty-state <?php echo empty($sitinData) ? '' : 'hidden'; ?>">
                            <i class="fas fa-history text-5xl text-gray-600 mb-4"></i>
                            <h3 class="text-white text-lg font-medium mb-2">No Sit-in Records Found</h3>
                            <p class="text-gray-500">There are no records matching your current filters.</p>
                        </div>
                    </div>

                    <div class="pagination-container <?php echo empty($sitinData) ? 'hidden' : ''; ?>" id="paginationWrapper">
                        <div id="showingText">Showing 0 entries</div>
                        <div class="flex gap-2" id="paginationControls"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab Switching Logic
        function switchTab(tabId) {
            const hash = window.location.hash;
            document.getElementById('tab-charts').classList.remove('active');
            document.getElementById('tab-records').classList.remove('active');
            document.getElementById('charts-pane').classList.add('hidden');
            document.getElementById('records-pane').classList.add('hidden');
            
            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById(tabId + '-pane').classList.remove('hidden');
            
            // Fix chart rendering bug when switching tabs by triggering resize
            if(tabId === 'charts') {
                window.dispatchEvent(new Event('resize'));
            }
        }

        // Auto-switch based on URL hash
        if(window.location.hash === '#records') {
            switchTab('records');
        } else {
            switchTab('charts');
        }

        // Chart Configurations
        const purposeLabels = <?php echo json_encode(array_keys($purposeCounts)); ?>;
        const purposeData = <?php echo json_encode(array_values($purposeCounts)); ?>;
        const purposeColors = [
            "#1E3A8A", "#1D4ED8", "#3B82F6", "#60A5FA", "#93C5FD", "#BFDBFE",
            "#4C1D95", "#5B21B6", "#7C3AED", "#8B5CF6", "#A78BFA", "#C4B5FD",
            "#7E22CE", "#9333EA"
        ];

        const labLabels = <?php echo json_encode(array_map(function($l){return "Lab $l";}, array_keys($labCounts))); ?>;
        const labData = <?php echo json_encode(array_values($labCounts)); ?>;
        const labColors = ["#1E3A8A", "#1D4ED8", "#3B82F6", "#60A5FA", "#93C5FD", "#BFDBFE"];

        function createLegend(containerId, canvasId, labels, allLabels, colors) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            labels.forEach((label) => {
                const trueIndex = allLabels.indexOf(label);
                if (trueIndex === -1) return;

                const item = document.createElement('div');
                item.className = 'legend-item cursor-pointer select-none transition-all duration-300 hover:opacity-80';

                const colorBox = document.createElement('div');
                colorBox.className = 'legend-color transition-opacity duration-300';
                colorBox.style.background = colors[trueIndex % colors.length];
                colorBox.style.pointerEvents = 'none';

                const textSpan = document.createElement('span');
                textSpan.innerText = label;
                textSpan.className = 'transition-all duration-300';
                textSpan.style.pointerEvents = 'none';

                item.appendChild(colorBox);
                item.appendChild(textSpan);

                item.onclick = function() {
                    const chart = window[canvasId + 'Instance'];
                    if (!chart) {
                        console.error("Chart instance not found on window:", canvasId + 'Instance');
                        return;
                    }

                    const isVisible = typeof chart.getDataVisibility === 'function'
                        ? chart.getDataVisibility(trueIndex)
                        : (chart.getDatasetMeta(0) && chart.getDatasetMeta(0).data[trueIndex] ? !chart.getDatasetMeta(0).data[trueIndex].hidden : true);

                    if (typeof chart.toggleDataVisibility === 'function') {
                        chart.toggleDataVisibility(trueIndex);
                    } else {
                        const meta = chart.getDatasetMeta(0);
                        if (meta && meta.data && meta.data[trueIndex]) {
                            meta.data[trueIndex].hidden = isVisible; // Hide it if it was visible
                        }
                    }
                    chart.update();

                    if (isVisible) {
                        textSpan.style.textDecoration = 'line-through';
                        textSpan.style.opacity = '0.3';
                        colorBox.style.opacity = '0.3';
                    } else {
                        textSpan.style.textDecoration = 'none';
                        textSpan.style.opacity = '1';
                        colorBox.style.opacity = '1';
                    }
                };

                container.appendChild(item);
            });
        }

        let chartsInitialized = false;

        function initCharts() {
            if (chartsInitialized) return;
            
            try {
                // Purpose Chart
                const canvasPurpose = document.getElementById('purposeChart');
                if (canvasPurpose) {
                    const purposeChart = new Chart(canvasPurpose.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: purposeLabels,
                            datasets: [{
                                data: purposeData,
                                backgroundColor: purposeColors,
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: { legend: { display: false } }
                        }
                    });
                    window.purposeChartInstance = purposeChart;
                    createLegend('purposeLegend', 'purposeChart', purposeLabels.filter((l,i)=>purposeData[i]>0), purposeLabels, purposeColors);
                }

                // Lab Chart
                const canvasLab = document.getElementById('labChart');
                if (canvasLab) {
                    const labChart = new Chart(canvasLab.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: labLabels,
                            datasets: [{
                                data: labData,
                                backgroundColor: labColors,
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: { legend: { display: false } }
                        }
                    });
                    window.labChartInstance = labChart;
                    createLegend('labLegend', 'labChart', labLabels.filter((l,i)=>labData[i]>0), labLabels, labColors);
                }

                // Trend Chart
                const canvasTrend = document.getElementById('sitInTrendChart');
                if (canvasTrend) {
                    const ctxTrend = canvasTrend.getContext('2d');
                    const trendGradient = ctxTrend.createLinearGradient(0, 0, 0, 300);
                    trendGradient.addColorStop(0, 'rgba(139, 63, 217, 0.4)');
                    trendGradient.addColorStop(1, 'rgba(139, 63, 217, 0)');

                    const trendLabels = [<?php echo "'" . implode("','", array_column($chartData, 'label')) . "'"; ?>];
                    const trendData = [<?php echo implode(",", array_column($chartData, 'count')); ?>];

                    new Chart(ctxTrend, {
                        type: 'line',
                        data: {
                            labels: trendLabels,
                            datasets: [{
                                label: 'Sessions',
                                data: trendData,
                                borderColor: '#8B3FD9',
                                borderWidth: 2,
                                pointBackgroundColor: '#8B3FD9',
                                pointRadius: 4,
                                fill: true,
                                backgroundColor: trendGradient,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { 
                                    min: 0,
                                    suggestedMax: 5,
                                    grid: { color: 'rgba(255, 255, 255, 0.05)' }, 
                                    ticks: { 
                                        color: '#9A8FB0', 
                                        font: { size: 10 },
                                        precision: 0,
                                        stepSize: 1
                                    } 
                                },
                                x: { grid: { display: false }, ticks: { color: '#9A8FB0', font: { size: 10 } } }
                            }
                        }
                    });
                }
                chartsInitialized = true;
            } catch (err) {
                console.error("Error initializing charts:", err);
            }
            initTable();
        }

        function tryInitCharts() {
            if (typeof Chart !== 'undefined') {
                initCharts();
            } else {
                window.addEventListener('load', initCharts);
                let attempts = 0;
                const interval = setInterval(() => {
                    attempts++;
                    if (typeof Chart !== 'undefined') {
                        clearInterval(interval);
                        initCharts();
                    } else if (attempts > 50) {
                        clearInterval(interval);
                        console.error("Chart.js failed to load after 5 seconds.");
                    }
                }, 100);
            }
        }

        if (document.readyState === 'complete') {
            tryInitCharts();
        } else {
            window.addEventListener('load', tryInitCharts);
        }

        // Star Background Animation
        (function(){
            const canvas = document.getElementById('star-canvas');
            const ctx = canvas.getContext('2d');
            let W, H, stars = [], shoots = [];

            function resize() {
                W = canvas.width  = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            for (let i = 0; i < 150; i++) {
                stars.push({
                    x: Math.random() * 9999, y: Math.random() * 9999,
                    r: Math.random() * 1.2 + 0.3, a: Math.random(),
                    da: (Math.random() * 0.005 + 0.002) * (Math.random() < .5 ? 1 : -1)
                });
            }

            function draw() {
                ctx.clearRect(0, 0, W, H);
                stars.forEach(s => {
                    s.a += s.da;
                    if (s.a <= 0 || s.a >= 1) s.da *= -1;
                    ctx.beginPath();
                    ctx.arc(s.x % W, s.y % H, s.r, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(200,180,255,${Math.abs(s.a).toFixed(2)})`;
                    ctx.fill();
                });
                requestAnimationFrame(draw);
            }
            draw();
        })();

        // Table Logic
        let currentPage = 1;
        let entriesPerPage = 6;
        let filteredRows = [];

        function initTable() {
            filterTable();
        }

        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const purpose = document.getElementById('purposeFilter').value.toLowerCase();
            const lab = document.getElementById('labFilter').value;
            const rows = document.querySelectorAll('.table-row');
            
            filteredRows = [];
            
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const rowPurpose = row.cells[3].innerText.toLowerCase();
                const rowLab = row.cells[4].innerText;
                
                const matchSearch = text.includes(search);
                const matchPurpose = purpose === "" || rowPurpose === purpose;
                const matchLab = lab === "" || rowLab === lab;
                
                if (matchSearch && matchPurpose && matchLab) {
                    filteredRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });

            currentPage = 1;
            updateTableDisplay();
        }

        function updateTableDisplay() {
            const total = filteredRows.length;
            
            if (total === 0) {
                document.getElementById('emptyState').classList.remove('hidden');
                document.getElementById('paginationWrapper').classList.add('hidden');
                return;
            } else {
                document.getElementById('emptyState').classList.add('hidden');
                document.getElementById('paginationWrapper').classList.remove('hidden');
            }

            const ep = entriesPerPage === 0 ? total : entriesPerPage;
            const totalPages = Math.ceil(total / ep) || 1;
            const start = (currentPage - 1) * ep;
            const end = start + ep;

            filteredRows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            document.getElementById('showingText').innerHTML = `Showing ${total > 0 ? start + 1 : 0} to ${Math.min(end, total)} of ${total} entries`;
            
            renderPagination(totalPages);
        }

        function renderPagination(totalPages) {
            const controls = document.getElementById('paginationControls');
            controls.innerHTML = '';
            
            if (totalPages <= 1) return;

            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-btn';
            prevBtn.innerText = 'Previous';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => { currentPage--; updateTableDisplay(); };
            controls.appendChild(prevBtn);

            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
                btn.innerText = i;
                btn.onclick = () => { currentPage = i; updateTableDisplay(); };
                controls.appendChild(btn);
            }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-btn';
            nextBtn.innerText = 'Next';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => { currentPage++; updateTableDisplay(); };
            controls.appendChild(nextBtn);
        }

        // Sorting Logic
        let currentSortColumn = -1;
        let sortDirection = 'asc';

        function sortTable(colIndex) {
            if (currentSortColumn === colIndex) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSortColumn = colIndex;
                sortDirection = 'asc';
            }

            // Update header icons
            const headers = document.querySelectorAll('.custom-table th.sortable-header');
            headers.forEach(th => {
                const icon = th.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-sort text-[10px] ml-1';
                    icon.style.color = '';
                }
            });

            // Find current header clicked
            const activeHeader = document.querySelector(`.custom-table th.sortable-header[onclick="sortTable(${colIndex})"]`);
            if (activeHeader) {
                const icon = activeHeader.querySelector('i');
                if (icon) {
                    icon.className = `fas fa-sort-${sortDirection === 'asc' ? 'up' : 'down'} text-[10px] ml-1`;
                    icon.style.color = 'var(--purple-light)';
                }
            }

            // Sort filteredRows in memory
            filteredRows.sort((a, b) => {
                let valA = a.cells[colIndex].innerText.trim();
                let valB = b.cells[colIndex].innerText.trim();

                // Handle PC Number, ID Number, Lab as numbers
                if (colIndex === 0 || colIndex === 1 || colIndex === 4) {
                    const numA = parseFloat(valA.replace(/[^\d.]/g, '')) || 0;
                    const numB = parseFloat(valB.replace(/[^\d.]/g, '')) || 0;
                    return sortDirection === 'asc' ? numA - numB : numB - numA;
                }

                // Handle Date
                if (colIndex === 7) {
                    const dateA = new Date(valA);
                    const dateB = new Date(valB);
                    return sortDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }

                // String comparison
                return sortDirection === 'asc' 
                    ? valA.localeCompare(valB) 
                    : valB.localeCompare(valA);
            });

            // Re-append sorted rows to DOM to keep order correct
            const tbody = document.getElementById('tableBody');
            filteredRows.forEach(row => {
                tbody.appendChild(row);
            });

            currentPage = 1;
            updateTableDisplay();
        }

        // Toggle Export Dropdown
        const exportButton = document.getElementById('exportButton');
        const exportDropdown = document.getElementById('exportDropdown');
        if (exportButton && exportDropdown) {
            exportButton.addEventListener('click', function(e) {
                e.stopPropagation();
                exportDropdown.classList.toggle('hidden');
            });

            document.addEventListener('click', function(event) {
                if (!exportButton.contains(event.target) && !exportDropdown.contains(event.target)) {
                    exportDropdown.classList.add('hidden');
                }
            });
        }

        // Export CSV
        document.getElementById('exportCSV').addEventListener('click', function(e) {
            e.preventDefault();
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "\"University of Cebu\"\n";
            csvContent += "\"College of Computer Studies\"\n";
            csvContent += "\"Computer Laboratory Sit-In Monitoring System - Sit-in Records\"\n\n";
            
            const headers = ["PC NUMBER", "ID NUMBER", "STUDENT NAME", "PURPOSE", "LAB", "LOGIN", "LOGOUT", "DATE"];
            csvContent += headers.map(h => `"${h}"`).join(',') + "\n";

            filteredRows.forEach(row => {
                let rowData = [];
                for(let i=0; i<8; i++) {
                    rowData.push(`"${row.cells[i].innerText.trim().replace(/"/g, '""')}"`);
                }
                csvContent += rowData.join(',') + "\n";
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "sitin_records.csv");
            document.body.appendChild(link);
            link.click();
            link.remove();
        });

        // Export Excel
        document.getElementById('exportExcel').addEventListener('click', function(e) {
            e.preventDefault();
            const data = [
                ["University of Cebu"],
                ["College of Computer Studies"],
                ["Computer Laboratory Sit-In Monitoring System - Sit-in Records"],
                []
            ];
            
            const headers = ["PC NUMBER", "ID NUMBER", "STUDENT NAME", "PURPOSE", "LAB", "LOGIN", "LOGOUT", "DATE"];
            data.push(headers);

            filteredRows.forEach(row => {
                let rowData = [];
                for(let i=0; i<8; i++) {
                    rowData.push(row.cells[i].innerText.trim());
                }
                data.push(rowData);
            });

            const ws = XLSX.utils.aoa_to_sheet(data);
            
            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } },
                { s: { r: 1, c: 0 }, e: { r: 1, c: headers.length - 1 } },
                { s: { r: 2, c: 0 }, e: { r: 2, c: headers.length - 1 } }
            ];
            
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "SitIn Records");
            XLSX.writeFile(wb, "sitin_records.xlsx");
        });

        // Export PDF
        document.getElementById('exportPDF').addEventListener('click', function(e) {
            e.preventDefault();
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');
            const headers = ["PC NUMBER", "ID NUMBER", "STUDENT NAME", "PURPOSE", "LAB", "LOGIN", "LOGOUT", "DATE"];
            const data = [];

            const headerText = [
                "University of Cebu",
                "College of Computer Studies",
                "Sit-In Records Report"
            ];
            doc.setFontSize(12);
            doc.setFont(undefined, 'bold'); 
            doc.text(headerText[0], doc.internal.pageSize.width / 2, 30, { align: 'center' });
            doc.text(headerText[1], doc.internal.pageSize.width / 2, 50, { align: 'center' });
            doc.text(headerText[2], doc.internal.pageSize.width / 2, 70, { align: 'center' });
            
            filteredRows.forEach(row => {
                let rowData = [];
                for(let i=0; i<8; i++) {
                    rowData.push(row.cells[i].innerText.trim());
                }
                data.push(rowData);
            });

            doc.autoTable({
                head: [headers],
                body: data,
                startY: 90,
                margin: { top: 20 },
                styles: {
                    fontSize: 8,
                    cellPadding: 4,
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
                }
            });

            doc.save("sitin_records.pdf");
        });

        // Print functionality with header matching Student Page layout
        document.getElementById('printButton').addEventListener('click', function() {
            const rows = Array.from(document.querySelectorAll('#tableBody tr'))
                .filter(row => row.style.display !== 'none');
            
            const tempDiv = document.createElement('div');
            
            const headerText = [
                "UNIVERSITY OF CEBU",
                "College of Computer Studies",
                "Sit-in Monitoring Records Report"
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
            
            tempDiv.appendChild(headerDiv);
            
            const printTable = document.createElement('table');
            printTable.style.width = '100%';
            printTable.style.borderCollapse = 'collapse';
            printTable.style.marginTop = '20px';
            
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            
            const headers = [
                "PC NUMBER", "ID NUMBER", "STUDENT NAME", "PURPOSE", 
                "LAB", "LOGIN", "LOGOUT", "DATE"
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
            
            const tbody = document.createElement('tbody');
            
            rows.forEach((row, index) => {
                const cells = row.querySelectorAll('td');
                const newRow = document.createElement('tr');
                
                newRow.style.backgroundColor = index % 2 === 0 ? '#f2f2f2' : '#ffffff';
                
                // PC Number
                const pcCell = document.createElement('td');
                pcCell.textContent = cells[0].textContent.trim();
                pcCell.style.border = '1px solid #000';
                pcCell.style.padding = '8px';
                pcCell.style.textAlign = 'center';
                newRow.appendChild(pcCell);
                
                // ID Number
                const idCell = document.createElement('td');
                idCell.textContent = cells[1].textContent.trim();
                idCell.style.border = '1px solid #000';
                idCell.style.padding = '8px';
                idCell.style.textAlign = 'center';
                newRow.appendChild(idCell);
                
                // Student Name
                const nameCell = document.createElement('td');
                nameCell.textContent = cells[2].textContent.trim();
                nameCell.style.border = '1px solid #000';
                nameCell.style.padding = '8px';
                nameCell.style.textAlign = 'center';
                newRow.appendChild(nameCell);
                
                // Purpose
                const purposeCell = document.createElement('td');
                purposeCell.textContent = cells[3].textContent.trim();
                purposeCell.style.border = '1px solid #000';
                purposeCell.style.padding = '8px';
                purposeCell.style.textAlign = 'center';
                newRow.appendChild(purposeCell);
                
                // Lab
                const labCell = document.createElement('td');
                labCell.textContent = cells[4].textContent.trim();
                labCell.style.border = '1px solid #000';
                labCell.style.padding = '8px';
                labCell.style.textAlign = 'center';
                newRow.appendChild(labCell);
                
                // Login
                const loginCell = document.createElement('td');
                loginCell.textContent = cells[5].textContent.trim();
                loginCell.style.border = '1px solid #000';
                loginCell.style.padding = '8px';
                loginCell.style.textAlign = 'center';
                newRow.appendChild(loginCell);
                
                // Logout
                const logoutCell = document.createElement('td');
                logoutCell.textContent = cells[6].textContent.trim();
                logoutCell.style.border = '1px solid #000';
                logoutCell.style.padding = '8px';
                logoutCell.style.textAlign = 'center';
                newRow.appendChild(logoutCell);
                
                // Date
                const dateCell = document.createElement('td');
                dateCell.textContent = cells[7].textContent.trim();
                dateCell.style.border = '1px solid #000';
                dateCell.style.padding = '8px';
                dateCell.style.textAlign = 'center';
                newRow.appendChild(dateCell);
                
                tbody.appendChild(newRow);
            });
            
            printTable.appendChild(tbody);
            tempDiv.appendChild(printTable);
            
            printJS({
                printable: tempDiv.innerHTML,
                type: 'raw-html',
                style: `
                    @page { size: auto; margin: 5mm; }
                    body { font-family: Arial, sans-serif; margin: 0; padding: 10px; }
                    h1, h2, h3 { margin: 5px 0; text-align: center; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                    th, td { border: 1px solid #000; padding: 6px; text-align: center; }
                    th { background-color: #002044 !important; color: white !important; -webkit-print-color-adjust: exact; }
                    tr:nth-child(even) { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
                `
            });
        });
    </script>
</body>
</html>