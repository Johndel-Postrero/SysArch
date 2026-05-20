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
require __DIR__ . '/auto_time_in.php';

// Fetch pending reservations
$pendingSql = "SELECT r.reservation_id, r.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, 
               r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, r.created_at
        FROM reservations r
        JOIN users u ON r.idno = u.idno
        WHERE r.status = 'pending'
        ORDER BY r.reservation_date DESC, r.time_in DESC";
$pendingResult = $conn->query($pendingSql);
$pendingReservations = [];
if ($pendingResult->num_rows > 0) {
    while ($row = $pendingResult->fetch_assoc()) { $pendingReservations[] = $row; }
}

// Fetch reservation logs
$logsSql = "SELECT r.reservation_id, r.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, 
           r.lab_number, r.pc_number, r.reservation_date, r.time_in, r.purpose, r.status, r.time_in_status, r.created_at
    FROM reservations r
    JOIN users u ON r.idno = u.idno
    WHERE r.status IN ('approved', 'declined', 'cancelled')
    ORDER BY r.reservation_date DESC, r.time_in DESC";
$logsResult = $conn->query($logsSql);
$reservationLogs = [];
if ($logsResult->num_rows > 0) {
    while ($row = $logsResult->fetch_assoc()) { $reservationLogs[] = $row; }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations – CCS Sit-In</title>
    <script>window.onpageshow = function(e){ if(e.persisted) window.location.reload(); };</script>
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tab content visibility */
        .tab-content { display: none; }
        .tab-content.active { display: flex; flex-direction: column; flex: 1; overflow: hidden; }

        /* Status Badges */
        .status-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
        }
        .status-approved { background: rgba(16,185,129,0.15); color: #10b981; }
        .status-declined { background: rgba(239,68,68,0.15); color: #ef4444; }
        .status-sit-inned { background: rgba(139,63,217,0.15); color: #a855f7; }
        .status-completed { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .status-pending { background: rgba(234,179,8,0.15); color: #eab308; }

        /* Action buttons for reservation */
        .res-approve {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            background: rgba(16,185,129,0.12); color: #10b981;
            border: 1px solid rgba(16,185,129,0.25); cursor: pointer;
            transition: all 0.3s; font-family: var(--font-b);
        }
        .res-approve:hover { background: #10b981; color: #fff; }
        .res-decline {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            background: rgba(239,68,68,0.12); color: #ef4444;
            border: 1px solid rgba(239,68,68,0.25); cursor: pointer;
            transition: all 0.3s; font-family: var(--font-b);
        }
        .res-decline:hover { background: #ef4444; color: #fff; }

        .lab-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 36px; padding: 4px 10px; border-radius: 8px;
            background: rgba(59,130,246,0.12); color: #3b82f6;
            font-weight: 700; font-size: 12px; border: 1px solid rgba(59,130,246,0.2);
        }
        .pc-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 28px; height: 28px; border-radius: 50%;
            background: rgba(139,63,217,0.1); color: var(--purple-light);
            font-size: 12px; font-weight: 700; border: 1px solid var(--border);
        }
        .date-cell { color: var(--text-body); font-size: 13px; }
        .time-cell { color: var(--gold); font-weight: 600; font-size: 13px; }
        .purpose-cell { color: var(--text-dim); font-size: 13px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Theme Confirm & Alert Modals */
        .theme-modal-backdrop {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(6, 4, 17, 0.85);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .theme-modal-backdrop.show {
            display: flex;
            opacity: 1;
        }
        .theme-modal-card {
            background: rgba(22, 19, 38, 0.95);
            border: 1px solid rgba(139, 63, 217, 0.35);
            border-radius: 22px;
            padding: 36px 30px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8), 0 0 30px rgba(139, 63, 217, 0.15);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #fff;
        }
        .theme-modal-backdrop.show .theme-modal-card {
            transform: scale(1);
        }
        .theme-modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            background: rgba(139, 63, 217, 0.12);
            color: var(--purple-light);
            border: 1px solid rgba(139, 63, 217, 0.25);
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.15);
        }
        .theme-modal-icon.success {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.25);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }
        .theme-modal-icon.error {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.25);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.15);
        }
        .theme-modal-icon.approve {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.25);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }
        .theme-modal-icon.decline {
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.25);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.15);
        }
        .theme-modal-card h3 {
            font-family: var(--font-h);
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .theme-modal-card p {
            font-size: 13.5px;
            color: var(--text-dim);
            line-height: 1.5;
            margin-bottom: 28px;
        }
        .theme-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .theme-btn-primary {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            font-family: var(--font-h);
            letter-spacing: 1px;
            color: #fff;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, var(--purple-glow), var(--purple-light));
            box-shadow: 0 4px 15px rgba(139, 63, 217, 0.3);
        }
        .theme-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(139, 63, 217, 0.5);
        }
        .theme-btn-primary.approve {
            background: linear-gradient(135deg, #10b981, #34d399);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .theme-btn-primary.approve:hover {
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5);
        }
        .theme-btn-primary.decline {
            background: linear-gradient(135deg, #ef4444, #f87171);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .theme-btn-primary.decline:hover {
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5);
        }
        .theme-btn-secondary {
            flex: 1;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            font-family: var(--font-b);
            color: var(--text-dim);
            cursor: pointer;
            transition: all 0.3s;
        }
        .theme-btn-secondary:hover {
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>

    <!-- Custom Theme Confirm Modal -->
    <div id="themeConfirmModal" class="theme-modal-backdrop">
        <div class="theme-modal-card">
            <div class="theme-modal-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <h3 id="themeConfirmTitle">CONFIRM ACTION</h3>
            <p id="themeConfirmMessage">Are you sure you want to proceed?</p>
            <div class="theme-modal-actions">
                <button id="themeConfirmCancel" class="theme-btn-secondary">Cancel</button>
                <button id="themeConfirmBtn" class="theme-btn-primary">Proceed</button>
            </div>
        </div>
    </div>

    <!-- Custom Theme Alert Modal -->
    <div id="themeAlertModal" class="theme-modal-backdrop">
        <div class="theme-modal-card">
            <div class="theme-modal-icon success">
                <i class="fas fa-check-circle" id="themeAlertIcon"></i>
            </div>
            <h3 id="themeAlertTitle">NOTIFICATION</h3>
            <p id="themeAlertMessage">Action completed successfully.</p>
            <div class="theme-modal-actions">
                <button id="themeAlertBtn" class="theme-btn-primary" style="flex:none; width: 120px;">OK</button>
            </div>
        </div>
    </div>
    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <!-- Tabs -->
            <div class="analytics-tabs">
                <button id="pendingTab" class="analytics-tab-btn active" onclick="switchTab('pending')">
                    <i class="fas fa-hourglass-half"></i>
                    <span>Pending</span>
                    <?php if(count($pendingReservations) > 0): ?>
                        <span class="tab-count"><?php echo count($pendingReservations); ?></span>
                    <?php endif; ?>
                </button>
                <button id="logsTab" class="analytics-tab-btn" onclick="switchTab('logs')">
                    <i class="fas fa-clock-rotate-left"></i>
                    <span>Logs</span>
                    <?php if(count($reservationLogs) > 0): ?>
                        <span class="tab-count"><?php echo count($reservationLogs); ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Controls Box -->
            <div class="controls-row">
                <div class="controls-left">
                    <!-- Changed to bulk selection controls -->
                    <button id="selectStudentBtn" class="filter-btn" style="background: rgba(139,63,217,0.15); border: 1px solid var(--purple); color: var(--purple-light);">
                        <i class="fas fa-list-check"></i> <span>Select Student</span>
                    </button>
                    <div id="bulkActions" style="display: none; gap: 8px; margin-left: 8px;">
                        <button id="bulkApproveBtn" class="res-approve" style="padding: 6px 10px;" title="Approve Selected">
                            <i class="fas fa-check"></i>
                        </button>
                        <button id="bulkDeclineBtn" class="res-decline" style="padding: 6px 10px;" title="Decline Selected">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                    <!-- Hidden entries select to keep pagination logic working if needed, defaulting to all -->
                    <select id="entries" style="display:none;"><option value="all" selected>all</option></select>
                </div>
                <div class="controls-right">
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" placeholder="Search..." type="text"/>
                    </div>
                    <div style="position:relative;">
                        <button id="filterButton" class="filter-btn">
                            <i class="fas fa-filter"></i><span>Filter</span>
                        </button>
                        <div id="filterDropdown" class="filter-dropdown hidden">
                            <label>Laboratory</label>
                            <select id="labFilter" class="dark-select" style="width:100%;margin-bottom:8px;">
                                <option value="">All Labs</option>
                                <option value="524">524</option>
                                <option value="526">526</option>
                                <option value="528">528</option>
                                <option value="530">530</option>
                                <option value="542">542</option>
                                <option value="544">544</option>
                            </select>
                            <div id="statusFilterContainer" class="hidden">
                                <label>Status</label>
                                <select id="statusFilter" class="dark-select" style="width:100%;">
                                    <option value="">All Status</option>
                                    <option value="approved">Approved</option>
                                    <option value="declined">Declined</option>
                                    <option value="sit-inned">Sit-Inned</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PENDING TAB -->
            <div id="pendingContent" class="tab-content active">
              <div class="content-card">
                <div class="records-header">
                    <div class="records-title">
                        <h3>Pending Reservations</h3>
                    </div>
                </div>
                <div class="dark-table-wrap">
                    <table id="pendingTable" class="dark-table">
                        <thead>
                            <tr>
                                <th class="select-col" style="display:none; width: 40px;"><input type="checkbox" id="selectAllPending" class="dark-checkbox"></th>
                                <th>ID NUMBER</th>
                                <th>STUDENT NAME</th>
                                <th>LAB</th>
                                <th>PC</th>
                                <th>DATE</th>
                                <th>TIME IN</th>
                                <th>PURPOSE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingReservations)): ?>
                                <tr class="not-record"><td colspan="9" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-inbox mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>No pending reservations at the moment</td></tr>
                            <?php else: ?>
                                <?php foreach ($pendingReservations as $r): ?>
                                    <tr>
                                        <td class="select-col" style="display:none;"><input type="checkbox" class="pending-checkbox dark-checkbox" value="<?php echo $r['reservation_id']; ?>"></td>
                                        <td><span class="id-cell"><?php echo htmlspecialchars($r['idno']); ?></span></td>
                                        <td><span class="name-text"><?php echo htmlspecialchars($r['lastname'] . ', ' . $r['firstname'] . ' ' . substr($r['middlename'],0,1) . '.'); ?></span></td>
                                        <td><span class="lab-badge"><?php echo htmlspecialchars($r['lab_number']); ?></span></td>
                                        <td><span class="pc-badge"><?php echo htmlspecialchars($r['pc_number']); ?></span></td>
                                        <td><span class="date-cell"><?php echo date('M j, Y', strtotime($r['reservation_date'])); ?></span></td>
                                        <td><span class="time-cell"><?php echo date('g:i A', strtotime($r['time_in'])); ?></span></td>
                                        <td><span class="purpose-cell" title="<?php echo htmlspecialchars($r['purpose']); ?>"><?php echo htmlspecialchars($r['purpose']); ?></span></td>
                                        <td>
                                            <div class="action-btns">
                                                <button onclick="approveReservation(<?php echo $r['reservation_id']; ?>)" class="res-approve"><i class="fas fa-check"></i> Approve</button>
                                                <button onclick="declineReservation(<?php echo $r['reservation_id']; ?>)" class="res-decline"><i class="fas fa-xmark"></i> Decline</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="not-record" id="pendingNoMatch" style="display:none;"><td colspan="9" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-search mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i><span style="font-size:15px;font-weight:500;">No matching pending reservations found</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-row">
                    <div class="pagination-info" id="pendingPaginationInfo"></div>
                    <div class="pagination-controls" id="pendingPaginationControls"></div>
                </div>
              </div><!-- end content-card -->
            </div>

            <!-- LOGS TAB -->
            <div id="logsContent" class="tab-content">
              <div class="content-card">
                <div class="records-header">
                    <div class="records-title">
                        <h3>Reservation Logs</h3>
                    </div>
                </div>
                <div class="dark-table-wrap">
                    <table id="logsTable" class="dark-table">
                        <thead>
                            <tr>
                                <th>ID NUMBER</th>
                                <th>STUDENT NAME</th>
                                <th>LAB</th>
                                <th>PC</th>
                                <th>DATE</th>
                                <th>TIME IN</th>
                                <th>PURPOSE</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservationLogs)): ?>
                                <tr class="not-record"><td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-history mb-3" style="font-size:40px;display:block;opacity:0.3;color:#3b82f6;"></i>No reservation logs found</td></tr>
                            <?php else: ?>
                                <?php foreach ($reservationLogs as $log):
                                    $statusClass = 'status-pending';
                                    $statusLabel = ucfirst($log['status']);
                                    if ($log['time_in_status'] == 'completed') { $statusClass = 'status-completed'; $statusLabel = 'Completed'; }
                                    elseif ($log['time_in_status'] == 'sit-inned') { $statusClass = 'status-sit-inned'; $statusLabel = 'Sit-Inned'; }
                                    elseif ($log['status'] == 'approved') $statusClass = 'status-approved';
                                    elseif ($log['status'] == 'declined') $statusClass = 'status-declined';
                                    elseif ($log['status'] == 'cancelled') { $statusClass = 'status-declined'; $statusLabel = 'Cancelled'; }
                                ?>
                                    <tr>
                                        <td><span class="id-cell"><?php echo htmlspecialchars($log['idno']); ?></span></td>
                                        <td><span class="name-text"><?php echo htmlspecialchars($log['lastname'] . ', ' . $log['firstname'] . ' ' . substr($log['middlename'],0,1) . '.'); ?></span></td>
                                        <td><span class="lab-badge"><?php echo htmlspecialchars($log['lab_number']); ?></span></td>
                                        <td><span class="pc-badge"><?php echo htmlspecialchars($log['pc_number']); ?></span></td>
                                        <td><span class="date-cell"><?php echo date('M j, Y', strtotime($log['reservation_date'])); ?></span></td>
                                        <td><span class="time-cell"><?php echo date('g:i A', strtotime($log['time_in'])); ?></span></td>
                                        <td><span class="purpose-cell" title="<?php echo htmlspecialchars($log['purpose']); ?>"><?php echo htmlspecialchars($log['purpose']); ?></span></td>
                                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr class="not-record" id="logsNoMatch" style="display:none;"><td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;"><i class="fas fa-search mb-3" style="font-size:40px;display:block;opacity:0.3;color:#3b82f6;"></i><span style="font-size:15px;font-weight:500;">No matching reservation logs found</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination-row">
                    <div class="pagination-info" id="logsPaginationInfo"></div>
                    <div class="pagination-controls" id="logsPaginationControls"></div>
                </div>
              </div><!-- end content-card -->
            </div>

        </div><!-- end student-content -->
    </div><!-- end main-wrapper -->

    <script>
    // Tab switching
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.analytics-tab-btn').forEach(t => t.classList.remove('active'));
        document.getElementById(tabName + 'Content').classList.add('active');
        document.getElementById(tabName + 'Tab').classList.add('active');
        document.getElementById('searchInput').value = '';
        document.getElementById('labFilter').value = '';
        if (document.getElementById('statusFilter')) document.getElementById('statusFilter').value = '';
        const sc = document.getElementById('statusFilterContainer');
        if (tabName === 'logs') sc.classList.remove('hidden'); else sc.classList.add('hidden');
        currentPage = 1;
        filterTable();
    }

    // Filter dropdown toggle
    document.getElementById('filterButton').addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('filterDropdown').classList.toggle('hidden');
    });
    document.addEventListener('click', function() {
        document.getElementById('filterDropdown').classList.add('hidden');
    });
    document.getElementById('filterDropdown').addEventListener('click', function(e) { e.stopPropagation(); });

    // Pagination
    let currentPage = 1, totalPages = 1;

    function filterTable() {
        const searchValue = document.getElementById('searchInput').value.toLowerCase();
        const labValue = document.getElementById('labFilter').value.toLowerCase();
        const statusValue = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value.toLowerCase() : '';
        let entriesPerPage = document.getElementById('entries').value;
        const activeTab = document.querySelector('.tab-content.active').id;
        if (activeTab === 'logsContent') {
            entriesPerPage = '6';
        }
        const activeTable = document.querySelector('.tab-content.active').querySelector('table');
        const rows = activeTable.querySelectorAll('tbody tr:not(.not-record)');
        let visibleRows = [];

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            const labCellIndex = activeTab === 'logsContent' ? 2 : 3;
            const labCell = cells[labCellIndex] ? cells[labCellIndex].textContent.toLowerCase() : '';
            let matchesSearch = searchValue ? Array.from(cells).some(c => c.textContent.toLowerCase().includes(searchValue)) : true;
            let matchesLab = labValue ? labCell.includes(labValue) : true;
            let matchesStatus = true;
            if (activeTab === 'logsContent' && statusValue && cells[7]) {
                matchesStatus = cells[7].textContent.toLowerCase().includes(statusValue);
            }
            if (matchesSearch && matchesLab && matchesStatus) visibleRows.push(row);
        });

        rows.forEach(r => r.style.display = 'none');

        const noMatchRow = activeTable.querySelector('[id$="NoMatch"]');
        if (rows.length > 0) {
            if (visibleRows.length === 0) {
                if (noMatchRow) noMatchRow.style.display = '';
            } else {
                if (noMatchRow) noMatchRow.style.display = 'none';
            }
        }

        if (entriesPerPage === 'all') {
            visibleRows.forEach(r => r.style.display = '');
            updatePagination(visibleRows.length, true, activeTab);
            return;
        }

        const num = parseInt(entriesPerPage);
        totalPages = Math.ceil(visibleRows.length / num);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        else if (totalPages === 0) currentPage = 1;

        const start = (currentPage - 1) * num;
        visibleRows.slice(start, start + num).forEach(r => r.style.display = '');
        updatePagination(visibleRows.length, false, activeTab);
    }

    function updatePagination(total, showAll, activeTab) {
        const tabName = activeTab.replace('Content', '');
        const info = document.getElementById(tabName + 'PaginationInfo');
        const controls = document.getElementById(tabName + 'PaginationControls');
        let epp = document.getElementById('entries').value;
        if (activeTab === 'logsContent') {
            epp = '6';
        }

        if (epp === 'all' || showAll || totalPages <= 1) {
            info.textContent = `Showing ${total} entries`;
            controls.innerHTML = '';
            return;
        }

        const num = parseInt(epp);
        const s = total === 0 ? 0 : (currentPage - 1) * num + 1;
        const e = Math.min(currentPage * num, total);
        info.textContent = `Showing ${s} to ${e} of ${total} entries`;
        controls.innerHTML = '';

        // Prev
        const prev = document.createElement('button');
        prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prev.className = 'page-btn'; prev.disabled = currentPage === 1;
        prev.addEventListener('click', () => { if (currentPage > 1) { currentPage--; filterTable(); } });
        controls.appendChild(prev);

        // Pages
        const max = 5;
        let sp = Math.max(1, currentPage - Math.floor(max / 2));
        let ep = Math.min(totalPages, sp + max - 1);
        if (ep - sp + 1 < max) sp = Math.max(1, ep - max + 1);

        for (let i = sp; i <= ep; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = `page-btn ${i === currentPage ? 'active' : ''}`;
            btn.addEventListener('click', () => { currentPage = i; filterTable(); });
            controls.appendChild(btn);
        }

        // Next
        const next = document.createElement('button');
        next.innerHTML = '<i class="fas fa-chevron-right"></i>';
        next.className = 'page-btn'; next.disabled = currentPage === totalPages;
        next.addEventListener('click', () => { if (currentPage < totalPages) { currentPage++; filterTable(); } });
        controls.appendChild(next);
    }

    // Event listeners
    document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; filterTable(); });
    document.getElementById('labFilter').addEventListener('change', () => { currentPage = 1; filterTable(); });
    if (document.getElementById('statusFilter'))
        document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; filterTable(); });
    document.getElementById('entries').addEventListener('change', () => { currentPage = 1; filterTable(); });

    filterTable();

    // Custom theme confirm/alert handlers
    function showThemeConfirm(title, message, isApproveOrDecline, onConfirm) {
        const modal = document.getElementById('themeConfirmModal');
        const titleEl = document.getElementById('themeConfirmTitle');
        const msgEl = document.getElementById('themeConfirmMessage');
        const iconEl = modal.querySelector('.theme-modal-icon');
        const confirmBtn = document.getElementById('themeConfirmBtn');

        titleEl.textContent = title;
        msgEl.textContent = message;
        
        // Reset classes
        iconEl.className = 'theme-modal-icon';
        confirmBtn.className = 'theme-btn-primary';
        
        if (isApproveOrDecline === 'approve') {
            iconEl.classList.add('approve');
            iconEl.innerHTML = '<i class="fas fa-check-circle"></i>';
            confirmBtn.classList.add('approve');
            confirmBtn.textContent = 'Approve';
        } else if (isApproveOrDecline === 'decline') {
            iconEl.classList.add('decline');
            iconEl.innerHTML = '<i class="fas fa-times-circle"></i>';
            confirmBtn.classList.add('decline');
            confirmBtn.textContent = 'Decline';
        } else {
            iconEl.innerHTML = '<i class="fas fa-question-circle"></i>';
            confirmBtn.textContent = 'Proceed';
        }

        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);

        const cleanup = () => {
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
            
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            const cancelBtn = document.getElementById('themeConfirmCancel');
            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        };

        document.getElementById('themeConfirmCancel').addEventListener('click', () => {
            cleanup();
        });

        document.getElementById('themeConfirmBtn').addEventListener('click', () => {
            cleanup();
            if (onConfirm) onConfirm();
        });
    }

    function showThemeAlert(title, message, type = 'success', onClose = null) {
        const modal = document.getElementById('themeAlertModal');
        const titleEl = document.getElementById('themeAlertTitle');
        const msgEl = document.getElementById('themeAlertMessage');
        const iconContainer = document.getElementById('themeAlertIcon').parentElement;
        const iconEl = document.getElementById('themeAlertIcon');

        titleEl.textContent = title;
        msgEl.textContent = message;

        // Reset classes
        iconContainer.className = 'theme-modal-icon';
        if (type === 'success') {
            iconContainer.classList.add('success');
            iconEl.className = 'fas fa-check-circle';
        } else {
            iconContainer.classList.add('error');
            iconEl.className = 'fas fa-exclamation-circle';
        }

        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);

        const cleanup = () => {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                if (onClose) onClose();
            }, 300);
            const alertBtn = document.getElementById('themeAlertBtn');
            const newAlertBtn = alertBtn.cloneNode(true);
            alertBtn.parentNode.replaceChild(newAlertBtn, alertBtn);
        };

        document.getElementById('themeAlertBtn').addEventListener('click', () => {
            cleanup();
        });
    }

    // Approve / Decline
    function approveReservation(id) {
        window.approveReservation(id);
    }
    function declineReservation(id) {
        window.declineReservation(id);
    }

    function updateReservationStatus(id, status) {
        return fetch('update_reservation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `reservation_id=${id}&status=${status}`
        })
        .then(r => r.ok ? r.json() : Promise.reject('Network error'))
        .then(d => { if (d.success) { return d; } else throw new Error(d.message); });
    }

    // Bulk selection logic
    let selectionMode = false;
    const selectStudentBtn = document.getElementById('selectStudentBtn');
    const bulkActions = document.getElementById('bulkActions');
    const selectCols = document.querySelectorAll('.select-col');
    const selectAllPending = document.getElementById('selectAllPending');
    const pendingCheckboxes = document.querySelectorAll('.pending-checkbox');

    selectStudentBtn.addEventListener('click', function() {
        selectionMode = !selectionMode;
        if (selectionMode) {
            this.innerHTML = '<i class="fas fa-times"></i> <span>Cancel Selection</span>';
            this.style.background = 'rgba(239,68,68,0.15)';
            this.style.borderColor = '#ef4444';
            this.style.color = '#ef4444';
            bulkActions.style.display = 'flex';
            selectCols.forEach(col => col.style.display = '');
        } else {
            this.innerHTML = '<i class="fas fa-list-check"></i> <span>Select Student</span>';
            this.style.background = 'rgba(139,63,217,0.15)';
            this.style.borderColor = 'var(--purple)';
            this.style.color = 'var(--purple-light)';
            bulkActions.style.display = 'none';
            selectCols.forEach(col => col.style.display = 'none');
            // Uncheck all
            if(selectAllPending) selectAllPending.checked = false;
            pendingCheckboxes.forEach(cb => cb.checked = false);
        }
    });

    if(selectAllPending) {
        selectAllPending.addEventListener('change', function() {
            const isChecked = this.checked;
            pendingCheckboxes.forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = isChecked;
                }
            });
        });
    }

    document.getElementById('bulkApproveBtn').addEventListener('click', function() {
        processBulkAction('approved');
    });
    
    document.getElementById('bulkDeclineBtn').addEventListener('click', function() {
        processBulkAction('declined');
    });

    function processBulkAction(status) {
        const selectedIds = Array.from(pendingCheckboxes)
            .filter(cb => cb.checked && cb.closest('tr').style.display !== 'none')
            .map(cb => cb.value);

        if (selectedIds.length === 0) {
            showThemeAlert("No Selection", "Please select at least one student reservation.", "error");
            return;
        }

        const actionText = status === 'approved' ? 'approve' : 'decline';
        const displayStatus = status === 'approved' ? 'approve' : 'decline';
        showThemeConfirm(
            status === 'approved' ? "Bulk Approve" : "Bulk Decline",
            `Are you sure you want to ${actionText} all ${selectedIds.length} selected reservation(s)?`,
            displayStatus,
            () => {
                const promises = selectedIds.map(id => updateReservationStatus(id, status));
                Promise.allSettled(promises).then(results => {
                    const successes = results.filter(r => r.status === 'fulfilled');
                    showThemeAlert(
                        status === 'approved' ? "Bulk Approved" : "Bulk Declined",
                        `Successfully ${actionText}d ${successes.length} out of ${selectedIds.length} selected reservations.`,
                        "success",
                        () => location.reload()
                    );
                });
            }
        );
    }
    
    // Override single approve/decline to reload automatically
    window.approveReservation = function(id) {
        showThemeConfirm(
            "Approve Reservation",
            "Are you sure you want to approve this student's reservation request?",
            "approve",
            () => {
                updateReservationStatus(id, 'approved')
                    .then(d => {
                        showThemeAlert("Approved", d.message, "success", () => location.reload());
                    })
                    .catch(e => {
                        console.error(e);
                        showThemeAlert("Error", e.message || 'An error occurred.', "error");
                    });
            }
        );
    };
    window.declineReservation = function(id) {
        showThemeConfirm(
            "Decline Reservation",
            "Are you sure you want to decline this student's reservation request?",
            "decline",
            () => {
                updateReservationStatus(id, 'declined')
                    .then(d => {
                        showThemeAlert("Declined", d.message, "success", () => location.reload());
                    })
                    .catch(e => {
                        console.error(e);
                        showThemeAlert("Error", e.message || 'An error occurred.', "error");
                    });
            }
        );
    };

    </script>

    <!-- Star Background -->
    <script>
    (function(){
        const canvas = document.getElementById('star-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let W, H, stars = [], shoots = [];
        function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        for (let i = 0; i < 150; i++) {
            stars.push({ x: Math.random()*9999, y: Math.random()*9999, r: Math.random()*1.2+0.3, a: Math.random(),
                da: (Math.random()*0.005+0.002)*(Math.random()<.5?1:-1) });
        }
        function spawnShoot() {
            shoots.push({ x: Math.random()*W*1.2, y: Math.random()*H*0.5, len: Math.random()*100+50,
                speed: Math.random()*5+3, angle: Math.PI/4, alpha: 1 });
        }
        setInterval(spawnShoot, 3000);
        function draw() {
            ctx.clearRect(0, 0, W, H);
            stars.forEach(s => {
                s.a += s.da; if (s.a <= 0 || s.a >= 1) s.da *= -1;
                ctx.beginPath(); ctx.arc(s.x%W, s.y%H, s.r, 0, Math.PI*2);
                ctx.fillStyle = `rgba(200,180,255,${s.a.toFixed(2)})`; ctx.fill();
            });
            shoots.forEach((s, i) => {
                s.x += Math.cos(s.angle)*s.speed; s.y += Math.sin(s.angle)*s.speed; s.alpha -= 0.015;
                const g = ctx.createLinearGradient(s.x-Math.cos(s.angle)*s.len, s.y-Math.sin(s.angle)*s.len, s.x, s.y);
                g.addColorStop(0, `rgba(212,135,10,0)`); g.addColorStop(1, `rgba(200,160,255,${s.alpha.toFixed(2)})`);
                ctx.beginPath(); ctx.moveTo(s.x-Math.cos(s.angle)*s.len, s.y-Math.sin(s.angle)*s.len);
                ctx.lineTo(s.x, s.y); ctx.strokeStyle = g; ctx.lineWidth = 1; ctx.stroke();
                if (s.alpha <= 0) shoots.splice(i, 1);
            });
            requestAnimationFrame(draw);
        }
        draw();
    })();
    </script>
</body>
</html>