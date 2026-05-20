<?php
date_default_timezone_set('Asia/Manila'); // Set to Philippine time

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

// Fetch data from the users table for students
$sql = "SELECT u.idno, u.lastname, u.firstname, u.middlename, u.course, u.level, u.email, u.session, u.profile_picture, COALESCE(SUM(r.points), 0) AS total_points 
        FROM users u
        LEFT JOIN rewards r ON u.idno = r.idno
        WHERE u.role = 'student'
        GROUP BY u.idno";
$result = $conn->query($sql);

$sitinData = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sitinData[] = $row;
    }
}
// Define available courses
$courses = ['BSIT', 'BSCS', 'HM', 'CRIM', 'CBA'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Records – CCS Sit-In</title>
    <script>
        window.onpageshow = function(event) {
            if (event.persisted) { window.location.reload(); }
        };
    </script>
    <link rel="stylesheet" href="../fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebarad.php'; ?>

    <div class="main-wrapper">
        <?php include 'headerad.php'; ?>

        <div class="student-content">
            <!-- Controls Row: Actions, Search & Exports -->
            <div class="controls-row flex justify-between items-center gap-4 flex-wrap">
                <!-- Left: Actions -->
                <div class="flex items-center gap-3">
                    <button onclick="openAddModal()" class="btn-add">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </button>
                    <button id="resetSession" class="btn-reset-all">
                        <i class="fas fa-rotate-right"></i>
                        <span>Reset Session</span>
                    </button>
                </div>

                <!-- Right: Search, Export & Print -->
                <div class="flex items-center gap-3">
                    <!-- Search Box -->
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" placeholder="Search..." type="text"/>
                    </div>

                    <!-- Export Dropdown -->
                    <div style="position:relative;">
                        <button id="exportButton" class="btn-export btn-csv" style="background: linear-gradient(135deg, var(--purple-glow), var(--purple-light)); display:flex; align-items:center; gap:6px;">
                            <i class="fas fa-download"></i>
                            <span>Export</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="exportDropdown" class="sort-dropdown hidden" style="min-width: 120px;">
                            <a href="#" id="exportCSV"><i class="fas fa-file-csv text-blue-400 mr-2"></i> CSV</a>
                            <a href="#" id="exportExcel"><i class="fas fa-file-excel text-green-400 mr-2"></i> Excel</a>
                            <a href="#" id="exportPDF"><i class="fas fa-file-pdf text-red-400 mr-2"></i> PDF</a>
                        </div>
                    </div>

                    <!-- Print Button -->
                    <button id="printButton" class="btn-export btn-print">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="content-card">
            <!-- Records Header -->
            <div class="records-header">
                <div class="records-title">
                    <h3>Student Records</h3>
                    <span class="count-badge"><?php echo count($sitinData); ?> students</span>
                </div>
                <div class="sort-section" style="position:relative; z-index: 60;">
                    <span class="sort-label">Sort by:</span>
                    <button id="sortButton" class="dark-select" style="min-width:160px;cursor:pointer;">Select A filter</button>
                    <div id="sortDropdown" class="sort-dropdown hidden" style="min-width: 190px;">
                        <a href="#" data-sort="id-asc">ID Number (Low to High)</a>
                        <a href="#" data-sort="id-desc">ID Number (High to Low)</a>
                        <a href="#" data-sort="name-asc">Name A-Z</a>
                        <a href="#" data-sort="name-desc">Name Z-A</a>
                        <a href="#" data-sort="level-asc">Year Level (1st - 4th)</a>
                        <a href="#" data-sort="level-desc">Year Level (4th - 1st)</a>
                        <a href="#" data-sort="session-asc">Session (Low to High)</a>
                        <a href="#" data-sort="session-desc">Session (High to Low)</a>
                        <a href="#" data-sort="points-desc">Points (High to Low)</a>
                        <a href="#" data-sort="points-asc">Points (Low to High)</a>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="dark-table-wrap">
                <table id="sitinTable" class="dark-table">
                    <thead>
                        <tr>
                            <th class="sortable-header" data-sort-col="id" style="cursor: pointer;">ID NUMBER <i class="fas fa-sort sort-icon"></i></th>
                            <th>FULL NAME</th>
                            <th>COURSE</th>
                            <th class="sortable-header" data-sort-col="level" style="cursor: pointer;">YEAR LEVEL <i class="fas fa-sort sort-icon"></i></th>
                            <th>EMAIL</th>
                            <th class="sortable-header" data-sort-col="session" style="cursor: pointer;">SESSION <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable-header" data-sort-col="points" style="cursor: pointer;">POINTS <i class="fas fa-sort sort-icon"></i></th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sitinData)): ?>
                            <?php foreach ($sitinData as $index => $sitin): 
                                $courseLower = strtolower($sitin['course']);
                                $courseClass = 'course-default';
                                if ($courseLower === 'bsit') $courseClass = 'course-bsit';
                                elseif ($courseLower === 'bscs') $courseClass = 'course-bscs';
                                elseif ($courseLower === 'hm') $courseClass = 'course-hm';
                                elseif ($courseLower === 'crim') $courseClass = 'course-crim';
                                elseif ($courseLower === 'cba') $courseClass = 'course-cba';
                                
                                $hasProf = !empty($sitin['profile_picture']) && $sitin['profile_picture'] !== 'default-profile.png' && file_exists("../upload/" . $sitin['profile_picture']);
                            ?>
                                <tr>
                                    <td><span class="id-cell"><?php echo htmlspecialchars($sitin['idno']); ?></span></td>
                                    <td>
                                        <div class="name-cell">
                                            <?php if ($hasProf): ?>
                                                <img class="avatar" src="../upload/<?php echo htmlspecialchars($sitin['profile_picture']); ?>" alt="avatar">
                                            <?php else: ?>
                                                <div class="avatar flex items-center justify-center overflow-hidden w-8 h-8 rounded-full bg-white/5 border border-white/10">
                                                    <svg class="w-full h-full text-purple-300/80 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="background: rgba(139, 63, 217, 0.15); padding: 2px;">
                                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <span class="name-text"><?php echo htmlspecialchars($sitin['lastname'] . ', ' . $sitin['firstname'] . ' ' . $sitin['middlename']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="course-badge <?php echo $courseClass; ?>"><?php echo htmlspecialchars($sitin['course']); ?></span></td>
                                    <td><span class="level-badge"><?php 
                                        $lvl = htmlspecialchars($sitin['level']);
                                        if ($lvl == '1') echo '1st';
                                        elseif ($lvl == '2') echo '2nd';
                                        elseif ($lvl == '3') echo '3rd';
                                        elseif ($lvl == '4') echo '4th';
                                        else echo $lvl;
                                    ?></span></td>
                                    <td><span class="email-cell"><?php echo htmlspecialchars($sitin['email']); ?></span></td>
                                    <td><span class="session-badge"><?php echo htmlspecialchars($sitin['session']); ?></span></td>
                                    <td>
                                        <div class="points-cell">
                                            <i class="fas fa-star star"></i>
                                            <span><?php echo htmlspecialchars($sitin['total_points']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button onclick="openEditModal('<?php echo $sitin['idno']; ?>')" class="act-btn act-edit" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button onclick="resetStudentSession('<?php echo $sitin['idno']; ?>')" class="act-btn act-reset" title="Reset Session">
                                                <i class="fas fa-rotate-right"></i>
                                            </button>
                                            <button onclick="deleteStudent('<?php echo $sitin['idno']; ?>')" class="act-btn act-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="not-record">
                                <td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;">
                                    <i class="fas fa-user-slash mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>
                                    No student records found in the database.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr class="not-record" id="noMatchRow" style="display:none;">
                            <td colspan="8" style="text-align:center;padding:60px 20px;color:#9A8FB0;">
                                <i class="fas fa-search mb-3" style="font-size:40px;display:block;opacity:0.3;color:#8B3FD9;"></i>
                                <span style="font-size:15px;font-weight:500;">No matching student records found</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-row">
                <div class="pagination-info" id="paginationInfo"></div>
                <div class="pagination-controls" id="paginationControls"></div>
            </div>
            </div><!-- end content-card -->
<!-- Add Student Modal -->
<div id="addStudentModal" class="modal-overlay hidden">
    <div class="modal-box">
        <h2><i class="fas fa-user-plus" style="color:#10b981;margin-right:8px;"></i>Add Student</h2>
        <form id="addStudentForm" method="post" enctype="multipart/form-data">
            <div class="profile-upload-area">
                <label for="add-profile-picture-upload">
                    <img id="add-profile-picture-preview" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23c084fc'><rect width='100%25' height='100%25' fill='%23161326'/><path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/></svg>" alt="Profile Picture"/>
                    <span class="camera-icon"><i class="fas fa-camera"></i></span>
                </label>
                <input type="file" id="add-profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
            </div>
            <div class="form-wrapper">
                <input id="addIdNo" name="idno" placeholder="ID Number" type="number" required/>
                <span id="addIdNoFeedback" class="text-xs mt-1 block hidden"></span>
            </div>
            <div class="form-group">
                <input id="addLastName" name="lastname" placeholder="Last Name" type="text" required/>
                <input id="addFirstName" name="firstname" placeholder="First Name" type="text" required/>
                <input id="addMiddleName" name="middlename" placeholder="Middle Name" type="text"/>
            </div>
            <div class="form-group">
                <select id="addCourse" name="course" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="addLevel" name="level" required>
                    <option value="">Select Year Level</option>
                    <option value="1">1st</option>
                    <option value="2">2nd</option>
                    <option value="3">3rd</option>
                    <option value="4">4th</option>
                </select>
            </div>
            <div class="form-wrapper">
                <input id="addEmail" name="email" placeholder="Email Address" type="email" required/>
            </div>
            <div class="form-wrapper">
                <input id="addUser" name="username" placeholder="Username" type="text" required/>
                <span id="addUserFeedback" class="text-xs mt-1 block hidden"></span>
            </div>
            <div class="modal-btns">
                <button class="modal-btn-cancel" type="button" onclick="closeAddModal()">Cancel</button>
                <button class="modal-btn-submit" type="submit">Add Student</button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Student Modal -->
<div id="editStudentModal" class="modal-overlay hidden">
    <div class="modal-box">
        <h2><i class="fas fa-user-edit" style="color:#8B3FD9;margin-right:8px;"></i>Edit Student</h2>
        <form id="editStudentForm" method="post" enctype="multipart/form-data">
            <div class="profile-upload-area">
                <label for="profile-picture-upload">
                    <img id="profile-picture-preview" src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23c084fc'><rect width='100%25' height='100%25' fill='%23161326'/><path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/></svg>" alt="Profile Picture"/>
                    <span class="camera-icon"><i class="fas fa-camera"></i></span>
                </label>
                <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
            </div>
            <div class="form-wrapper">
                <input id="editIdNo" name="idno" placeholder="ID Number" type="number"/>
                <input type="hidden" id="oldIdNo" name="oldIdNo" value="" />
                <span id="editIdNoFeedback" class="text-xs mt-1 block hidden"></span>
            </div>
            <div class="form-group">
                <input id="editLastName" name="lastname" placeholder="Last Name" type="text"/>
                <input id="editFirstName" name="firstname" placeholder="First Name" type="text"/>
                <input id="editMiddleName" name="middlename" placeholder="Middle Name" type="text"/>
            </div>
            <div class="form-group">
                <select id="editCourse" name="course" required>
                    <option value="">Select Course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="editLevel" name="level">
                    <option value="">Select Year Level</option>
                    <option value="1">1st</option>
                    <option value="2">2nd</option>
                    <option value="3">3rd</option>
                    <option value="4">4th</option>
                </select>
            </div>
            <div class="form-wrapper">
                <input id="editEmail" name="email" placeholder="Email Address" type="email"/>
            </div>
            <div class="form-wrapper">
                <input id="editUser" name="username" placeholder="Username" type="text"/>
                <span id="editUserFeedback" class="text-xs mt-1 block hidden"></span>
            </div>
            <div class="form-wrapper" style="position:relative;">
                <input id="editPassword" name="password" placeholder="New Password" type="password"/>
                <i class="fas fa-eye" id="toggleEditPassword" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:#9A8FB0;"></i>
            </div>
            <div class="modal-btns">
                <button class="modal-btn-cancel" type="button" onclick="closeEditModal()">Cancel</button>
                <button class="modal-btn-submit" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Alert/Confirm Modal -->
<div id="customDialogModal" class="modal-overlay hidden">
    <div class="modal-box" style="max-width: 400px; text-align: center;">
        <h2 id="customDialogTitle" style="font-family: var(--font-h); font-size: 18px; color: #fff; margin-bottom: 16px;">Confirm</h2>
        <p id="customDialogMessage" style="color: var(--text-body); font-size: 14px; margin-bottom: 24px; line-height: 1.5;"></p>
        <div class="modal-btns" id="customDialogBtns">
            <!-- Buttons will be dynamically generated -->
        </div>
    </div>
</div>

        </div><!-- end student-content -->
    </div><!-- end main-wrapper -->
     <script>
        // Form live validation and restriction
        let addIdValid = true;
        let addUsernameValid = true;
        let editIdValid = true;
        let editUsernameValid = true;

        const customAlert = (message, title = "Notice") => {
            return new Promise((resolve) => {
                const modal = document.getElementById('customDialogModal');
                const titleEl = document.getElementById('customDialogTitle');
                const messageEl = document.getElementById('customDialogMessage');
                const btnsEl = document.getElementById('customDialogBtns');

                titleEl.innerHTML = `<i class="fas fa-info-circle" style="color: var(--purple-light); margin-right: 8px;"></i>${title}`;
                messageEl.textContent = message;
                
                btnsEl.innerHTML = `
                    <button class="modal-btn-submit" style="flex:1; background: linear-gradient(135deg, var(--purple-glow), var(--purple-light)); border:none; color:white; padding:10px; border-radius:10px;" id="customDialogOk">OK</button>
                `;

                modal.classList.remove('hidden');
                modal.classList.add('show');

                document.getElementById('customDialogOk').addEventListener('click', () => {
                    modal.classList.add('hidden');
                    modal.classList.remove('show');
                    resolve();
                });
            });
        };

        const customConfirm = (message, title = "Confirm Action") => {
            return new Promise((resolve) => {
                const modal = document.getElementById('customDialogModal');
                const titleEl = document.getElementById('customDialogTitle');
                const messageEl = document.getElementById('customDialogMessage');
                const btnsEl = document.getElementById('customDialogBtns');

                titleEl.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: var(--gold); margin-right: 8px;"></i>${title}`;
                messageEl.textContent = message;
                
                btnsEl.innerHTML = `
                    <button class="modal-btn-cancel" style="flex:1; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color:#ef4444; padding:10px; border-radius:10px;" id="customDialogCancel">Cancel</button>
                    <button class="modal-btn-submit" style="flex:1; background: linear-gradient(135deg, var(--purple-glow), var(--purple-light)); border:none; color:white; padding:10px; border-radius:10px;" id="customDialogConfirm">Yes, Proceed</button>
                `;

                modal.classList.remove('hidden');
                modal.classList.add('show');

                const close = (result) => {
                    modal.classList.add('hidden');
                    modal.classList.remove('show');
                    resolve(result);
                };

                document.getElementById('customDialogCancel').addEventListener('click', () => close(false));
                document.getElementById('customDialogConfirm').addEventListener('click', () => close(true));
            });
        };

        const restrictToDigitsOnly = (e) => {
            if (e.which < 48 || e.which > 57) {
                e.preventDefault();
            }
        };

        document.addEventListener('DOMContentLoaded', () => {
            const idNoAddInput = document.getElementById('addIdNo');
            const idNoEditInput = document.getElementById('editIdNo');
            const usernameAddInput = document.getElementById('addUser');
            const usernameAddFeedback = document.getElementById('addUserFeedback');
            const usernameEditInput = document.getElementById('editUser');
            const usernameEditFeedback = document.getElementById('editUserFeedback');
            const idAddFeedback = document.getElementById('addIdNoFeedback');
            const idEditFeedback = document.getElementById('editIdNoFeedback');

            if (idNoAddInput) {
                idNoAddInput.addEventListener('keypress', restrictToDigitsOnly);
                idNoAddInput.addEventListener('paste', (e) => {
                    const pasteData = e.clipboardData.getData('text');
                    if (!/^\d+$/.test(pasteData)) {
                        e.preventDefault();
                    }
                });
                idNoAddInput.addEventListener('input', () => {
                    checkIdNo(idNoAddInput, idAddFeedback, '', (isValid) => {
                        addIdValid = isValid;
                    });
                });
            }
            if (idNoEditInput) {
                idNoEditInput.addEventListener('keypress', restrictToDigitsOnly);
                idNoEditInput.addEventListener('paste', (e) => {
                    const pasteData = e.clipboardData.getData('text');
                    if (!/^\d+$/.test(pasteData)) {
                        e.preventDefault();
                    }
                });
                idNoEditInput.addEventListener('input', () => {
                    const oldId = document.getElementById('oldIdNo').value;
                    checkIdNo(idNoEditInput, idEditFeedback, oldId, (isValid) => {
                        editIdValid = isValid;
                    });
                });
            }

            if (usernameAddInput && usernameAddFeedback) {
                usernameAddInput.addEventListener('input', () => {
                    checkUsername(usernameAddInput, usernameAddFeedback, '', (isValid) => {
                        addUsernameValid = isValid;
                    });
                });
            }

            if (usernameEditInput && usernameEditFeedback) {
                usernameEditInput.addEventListener('input', () => {
                    const oldId = document.getElementById('oldIdNo').value;
                    checkUsername(usernameEditInput, usernameEditFeedback, oldId, (isValid) => {
                        editUsernameValid = isValid;
                    });
                });
            }
        });

        const checkUsername = (inputEl, feedbackEl, excludeId, callback) => {
            const val = inputEl.value.trim();
            if (val.length === 0) {
                feedbackEl.classList.add('hidden');
                callback(true);
                return;
            }
            
            fetch(`check_availability.php?username=${encodeURIComponent(val)}&exclude=${encodeURIComponent(excludeId)}`)
                .then(res => res.json())
                .then(data => {
                    feedbackEl.classList.remove('hidden');
                    if (data.available) {
                        feedbackEl.textContent = 'Username is available ✔';
                        feedbackEl.className = 'text-xs mt-1 block text-emerald-400';
                        callback(true);
                    } else {
                        feedbackEl.textContent = 'Username is already taken ✖';
                        feedbackEl.className = 'text-xs mt-1 block text-red-400';
                        callback(false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    callback(true);
                });
        };

        const checkIdNo = (inputEl, feedbackEl, excludeId, callback) => {
            const val = inputEl.value.trim();
            if (val.length === 0) {
                feedbackEl.classList.add('hidden');
                callback(true);
                return;
            }
            
            fetch(`check_availability.php?idno=${encodeURIComponent(val)}&exclude=${encodeURIComponent(excludeId)}`)
                .then(res => res.json())
                .then(data => {
                    feedbackEl.classList.remove('hidden');
                    if (data.available) {
                        feedbackEl.textContent = 'ID Number is unique ✔';
                        feedbackEl.className = 'text-xs mt-1 block text-emerald-400';
                        callback(true);
                    } else {
                        feedbackEl.textContent = 'ID Number is already registered ✖';
                        feedbackEl.className = 'text-xs mt-1 block text-red-400';
                        callback(false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    callback(true);
                });
        };

        // Sort functionality
        document.getElementById('sortButton').addEventListener('click', function() {
            const dropdown = document.getElementById('sortDropdown');
            dropdown.classList.toggle('hidden');
        });

        let currentSortCol = null;
        let currentSortOrder = 'asc';

        function performTableSort(sortType) {
            const rows = Array.from(document.querySelectorAll('#sitinTable tbody tr:not(.not-record)'));

            rows.sort((a, b) => {
                let aVal, bVal;
                switch (sortType) {
                    case 'id-asc':
                        aVal = parseInt(a.querySelector('td:nth-child(1)').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(1)').textContent.trim()) || 0;
                        return aVal - bVal;
                    case 'id-desc':
                        aVal = parseInt(a.querySelector('td:nth-child(1)').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(1)').textContent.trim()) || 0;
                        return bVal - aVal;
                    case 'name-asc':
                        aVal = a.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                        bVal = b.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                        return aVal.localeCompare(bVal);
                    case 'name-desc':
                        aVal = a.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                        bVal = b.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                        return bVal.localeCompare(aVal);
                    case 'level-asc':
                        aVal = a.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
                        bVal = b.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
                        const getLvlWeight = (lvl) => {
                            if (lvl.includes('1st')) return 1;
                            if (lvl.includes('2nd')) return 2;
                            if (lvl.includes('3rd')) return 3;
                            if (lvl.includes('4th')) return 4;
                            return 0;
                        };
                        return getLvlWeight(aVal) - getLvlWeight(bVal);
                    case 'level-desc':
                        aVal = a.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
                        bVal = b.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
                        const getLvlWeightDesc = (lvl) => {
                            if (lvl.includes('1st')) return 1;
                            if (lvl.includes('2nd')) return 2;
                            if (lvl.includes('3rd')) return 3;
                            if (lvl.includes('4th')) return 4;
                            return 0;
                        };
                        return getLvlWeightDesc(bVal) - getLvlWeightDesc(aVal);
                    case 'session-asc':
                        aVal = parseInt(a.querySelector('td:nth-child(6)').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(6)').textContent.trim()) || 0;
                        return aVal - bVal;
                    case 'session-desc':
                        aVal = parseInt(a.querySelector('td:nth-child(6)').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(6)').textContent.trim()) || 0;
                        return bVal - aVal;
                    case 'points-desc':
                        aVal = parseInt(a.querySelector('td:nth-child(7) .points-cell span').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(7) .points-cell span').textContent.trim()) || 0;
                        return bVal - aVal;
                    case 'points-asc':
                        aVal = parseInt(a.querySelector('td:nth-child(7) .points-cell span').textContent.trim()) || 0;
                        bVal = parseInt(b.querySelector('td:nth-child(7) .points-cell span').textContent.trim()) || 0;
                        return aVal - bVal;
                    default:
                        return 0;
                }
            });

            const tbody = document.querySelector('#sitinTable tbody');
            const templateRows = Array.from(tbody.querySelectorAll('tr.not-record'));
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
            templateRows.forEach(row => tbody.appendChild(row));
            
            currentPage = 1;
            updateTableVisibility();
        }

        // Header click sort
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const col = this.getAttribute('data-sort-col');
                
                if (currentSortCol === col) {
                    currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSortCol = col;
                    currentSortOrder = col === 'points' ? 'desc' : 'asc';
                }
                
                document.querySelectorAll('.sortable-header .sort-icon').forEach(icon => {
                    icon.classList.remove('fa-sort-up', 'fa-sort-down');
                    icon.classList.add('fa-sort');
                });
                
                const icon = this.querySelector('.sort-icon');
                icon.classList.remove('fa-sort');
                icon.classList.add(currentSortOrder === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
                
                const sortType = `${col}-${currentSortOrder}`;
                performTableSort(sortType);
                
                const sortLabelMap = {
                    'id-asc': 'ID Number (Low to High)',
                    'id-desc': 'ID Number (High to Low)',
                    'level-asc': 'Year Level (1st - 4th)',
                    'level-desc': 'Year Level (4th - 1st)',
                    'session-asc': 'Session (Low to High)',
                    'session-desc': 'Session (High to Low)',
                    'points-desc': 'Points (High to Low)',
                    'points-asc': 'Points (Low to High)'
                };
                if (sortLabelMap[sortType]) {
                    document.getElementById('sortButton').textContent = sortLabelMap[sortType];
                }
            });
        });

        document.querySelectorAll('#sortDropdown a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const sortType = this.getAttribute('data-sort');
                const sortLabelText = this.textContent;
                document.getElementById('sortButton').textContent = sortLabelText;
                
                performTableSort(sortType);
                
                if (sortType.includes('-')) {
                    const parts = sortType.split('-');
                    const col = parts[0];
                    const order = parts[1];
                    currentSortCol = col;
                    currentSortOrder = order;
                    
                    document.querySelectorAll('.sortable-header .sort-icon').forEach(icon => {
                        icon.classList.remove('fa-sort-up', 'fa-sort-down');
                        icon.classList.add('fa-sort');
                    });
                    
                    const header = document.querySelector(`.sortable-header[data-sort-col="${col}"]`);
                    if (header) {
                        const icon = header.querySelector('.sort-icon');
                        icon.classList.remove('fa-sort');
                        icon.classList.add(order === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
                    }
                }
                
                document.getElementById('sortDropdown').classList.add('hidden');
            });
        });

        // Close Sort Dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const sortButton = document.getElementById('sortButton');
            const sortDropdown = document.getElementById('sortDropdown');
            if (!sortButton.contains(event.target) && !sortDropdown.contains(event.target)) {
                sortDropdown.classList.add('hidden');
            }
        });


// Reset Session for All Students
document.getElementById('resetSession').addEventListener('click', function() {
    customConfirm("Are you sure you want to reset the session for ALL students?", "Reset All Sessions").then(confirmed => {
        if (confirmed) {
            fetch('reset_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({}) // Empty object means reset all
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customAlert("Session reset successfully for all students!", "Success").then(() => location.reload());
                } else {
                    customAlert("Error resetting session: " + data.error, "Error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    });
});

// Reset Session for a Specific Student
function resetStudentSession(idno) {
    customConfirm("Are you sure you want to reset this student's session?", "Reset Student Session").then(confirmed => {
        if (confirmed) {
            fetch('reset_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ idno: idno })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customAlert("Session reset successfully for this student!", "Success").then(() => location.reload());
                } else {
                    customAlert("Error resetting session: " + data.error, "Error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    });
}

        // Edit Student Modal Functions
 // Preview image on file select
document.getElementById("profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById("profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});// Open Edit Modal with current profile picture
function openEditModal(idno) {
    fetch(`get_student.php?idno=${idno}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                customAlert(data.error, "Error Fetching Student");
                return;
            }
            document.getElementById('oldIdNo').value = data.idno;
            document.getElementById('editIdNo').value = data.idno;
            document.getElementById('editUser').value = data.username;
            document.getElementById('editFirstName').value = data.firstname;
            document.getElementById('editMiddleName').value = data.middlename;
            document.getElementById('editLastName').value = data.lastname;
            document.getElementById('editCourse').value = data.course;
            document.getElementById('editLevel').value = data.level;
            document.getElementById('editEmail').value = data.email;

            const profilePicturePreview = document.getElementById('profile-picture-preview');
            if (data.profile_picture && data.profile_picture !== 'default-profile.png') {
                profilePicturePreview.src = data.profile_picture;
            } else {
                profilePicturePreview.src = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23c084fc'><rect width='100%25' height='100%25' fill='%23161326'/><path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/></svg>";
            }

            document.getElementById('editStudentModal').classList.remove('hidden');
            document.getElementById('editStudentModal').classList.add('show');
        })
        .catch(error => { console.error('Error:', error); });
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editStudentModal').classList.add('hidden');
    document.getElementById('editStudentModal').classList.remove('show');
    document.getElementById('editStudentForm').reset();
    document.getElementById('editIdNoFeedback').classList.add('hidden');
    document.getElementById('editUserFeedback').classList.add('hidden');
    editIdValid = true;
    editUsernameValid = true;
}

// Open Add Modal
function openAddModal() {
    document.getElementById('addStudentModal').classList.remove('hidden');
    document.getElementById('addStudentModal').classList.add('show');
}

// Close Add Modal
function closeAddModal() {
    document.getElementById('addStudentModal').classList.add('hidden');
    document.getElementById('addStudentModal').classList.remove('show');
    document.getElementById('addStudentForm').reset();
    document.getElementById('addIdNoFeedback').classList.add('hidden');
    document.getElementById('addUserFeedback').classList.add('hidden');
    addIdValid = true;
    addUsernameValid = true;
}
// Handle form submission
// Handle form submission
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!editIdValid) {
        customAlert("Please use a unique and valid ID Number.", "Validation Error");
        return;
    }
    if (!editUsernameValid) {
        customAlert("Please use a unique Username.", "Validation Error");
        return;
    }
    console.log("Form submitted"); // Debugging: Check if the form submission is triggered

    const formData = new FormData(this);
    console.log("FormData:", formData); // Debugging: Check the form data being sent

    fetch('update_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log("Response received:", response); // Debugging: Check the response
        return response.json();
    })
    .then(data => {
        console.log("Data:", data); // Debugging: Check the parsed JSON response
        if (data.success) {
            customAlert("Student updated successfully!", "Success").then(() => {
                location.reload(); // Reload the page to reflect changes
            });
        } else {
            customAlert("Error updating student: " + data.error, "Error");
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debugging: Check for fetch errors
        customAlert("An unexpected error occurred.", "Error");
    });
});

// Delete Student Function
function deleteStudent(idno) {
    customConfirm("Are you sure you want to delete this student?", "Delete Student").then(confirmed => {
        if (confirmed) {
            fetch(`delete_student.php?idno=${idno}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    customAlert("Student deleted successfully!", "Success").then(() => {
                        location.reload(); // Reload the page to reflect changes
                    });
                } else {
                    customAlert("Error deleting student: " + data.error, "Error");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                customAlert("An unexpected error occurred.", "Error");
            });
        }
    });
}

// Toggle password visibility in the edit modal
document.getElementById("toggleEditPassword").addEventListener("click", function () {
    const passwordInput = document.getElementById("editPassword");
    const icon = this;

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
});

// Handle Add Student Form Submission
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!addIdValid) {
        customAlert("Please use a unique and valid ID Number.", "Validation Error");
        return;
    }
    if (!addUsernameValid) {
        customAlert("Please use a unique Username.", "Validation Error");
        return;
    }
    const formData = new FormData(this);
    const lastName = formData.get('lastname').substring(0, 4).toLowerCase();
    const idNo = formData.get('idno').toString().substring(0, 4);
    const defaultPassword = lastName + idNo;
    formData.append('password', defaultPassword);

    fetch('add_student.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            customAlert("Student added successfully!", "Success").then(() => {
                location.reload();
            });
        } else {
            customAlert("Error adding student: " + data.error, "Error");
        }
    })
    .catch(error => {
        console.error('Error:', error);
        customAlert("An unexpected error occurred.", "Error");
    });
});

// Preview image on file select for Add Student Modal
document.getElementById("add-profile-picture-upload").addEventListener("change", function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById("add-profile-picture-preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

function getExportHeaderText() {
    return [
        "University of Cebu",
        "College of Computer Studies",
        "Computer Laboratory Sit-In Monitoring System Report"
    ];
}

// Export to CSV with header
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
        .slice(0, -1) // Exclude the last column (Action)
        .map(th => `"${th.textContent.replace(/"/g, '""')}"`)
        .join(',');
    csvContent += headers + "\n";

    // Add table data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(td => `"${td.textContent.trim().replace(/"/g, '""')}"`)
                .join(',');
            csvContent += rowData + "\n";
        }
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "student_records.csv");
    document.body.appendChild(link);
    link.click();
});

// Export to Excel with header
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
    const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
        .slice(0, -1) // Exclude the last column (Action)
        .map(th => th.textContent);
    data.push(headers);

    // Add table data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(td => td.textContent);
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
    XLSX.writeFile(wb, "student_records.xlsx");
});

// Export to PDF with header
document.getElementById('exportPDF').addEventListener('click', function() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    const headers = Array.from(document.querySelectorAll('#sitinTable thead th'))
        .slice(0, -1) // Exclude the last column (Action)
        .map(th => th.textContent);
    const rows = document.querySelectorAll('#sitinTable tbody tr');
    const data = [];

    // Add header text
    const headerText = [
        "University of Cebu",
        "College of Computer Studies",
        "Student Records"
    ];
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold'); 
    doc.text(headerText[0], doc.internal.pageSize.width / 2, 30, { align: 'center' });
    doc.text(headerText[1], doc.internal.pageSize.width / 2, 50, { align: 'center' });
    doc.text(headerText[2], doc.internal.pageSize.width / 2, 70, { align: 'center' });
    
    // Prepare table data
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const rowData = Array.from(row.querySelectorAll('td'))
                .slice(0, -1) // Exclude the last column (Action)
                .map(td => td.textContent);
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
        },
    });

    doc.save("student_records.pdf");
});

// Print functionality with header
document.getElementById('printButton').addEventListener('click', function() {
    const rows = Array.from(document.querySelectorAll('#sitinTable tbody tr'))
        .filter(row => row.style.display !== 'none');
    
    // Create a temporary container
    const tempDiv = document.createElement('div');
    
    // Add header
    const headerText = [
        "UNIVERSITY OF CEBU",
        "College of Computer Studies",
        "Student Records"
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
    
    // Create table
    const printTable = document.createElement('table');
    printTable.style.width = '100%';
    printTable.style.borderCollapse = 'collapse';
    printTable.style.marginTop = '20px';
    
    // Table header
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    const headers = [
        "ID NUMBER", "FULL NAME", "COURSE", "LEVEL", 
        "EMAIL", "SESSION", "POINTS"
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
        
        // Course
        const courseCell = document.createElement('td');
        courseCell.textContent = cells[2].textContent;
        courseCell.style.border = '1px solid #000';
        courseCell.style.padding = '8px';
        courseCell.style.textAlign = 'center';
        newRow.appendChild(courseCell);
        
        // Level
        const levelCell = document.createElement('td');
        levelCell.textContent = cells[3].textContent;
        levelCell.style.border = '1px solid #000';
        levelCell.style.padding = '8px';
        levelCell.style.textAlign = 'center';
        newRow.appendChild(levelCell);
        
        // Email
        const emailCell = document.createElement('td');
        emailCell.textContent = cells[4].textContent;
        emailCell.style.border = '1px solid #000';
        emailCell.style.padding = '8px';
        emailCell.style.textAlign = 'center';
        newRow.appendChild(emailCell);
        
        // Session
        const sessionCell = document.createElement('td');
        sessionCell.textContent = cells[5].textContent;
        sessionCell.style.border = '1px solid #000';
        sessionCell.style.padding = '8px';
        sessionCell.style.textAlign = 'center';
        newRow.appendChild(sessionCell);

        // Points
        const pointsCell = document.createElement('td');
        pointsCell.textContent = cells[6].textContent;
        pointsCell.style.border = '1px solid #000';
        pointsCell.style.padding = '8px';
        pointsCell.style.textAlign = 'center';
        newRow.appendChild(pointsCell);
        
        tbody.appendChild(newRow);
    });
    
    printTable.appendChild(tbody);
    tempDiv.appendChild(printTable);
    
    // Print using printJS
    printJS({
        printable: tempDiv.innerHTML,
        type: 'raw-html',
        css: [
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css',
            'css/add.css' // Include your custom CSS if needed
        ],
        style: `
            @page { size: auto; margin: 5mm; }
            body { font-family: "Poppins-Regular", Arial, sans-serif; margin: 0; padding: 10px; }
            h1, h2, h3 { margin: 5px 0; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
            th, td { border: 1px solid #000; padding: 6px; text-align: center; }
            th { background-color: #002044 !important; color: white !important; -webkit-print-color-adjust: exact; }
            tr:nth-child(even) { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }
            @media print {
                .no-print { display: none !important; }
            }
        `,
        onLoadingEnd: function() {
            tempDiv.remove();
        }
    });
});

</script>
 

<script>
// Global variables for pagination
let currentPage = 1;
const rowsPerPage = 6; // Exactly 6 entries per page

// Initialize the table when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
        currentPage = 1;
        updateTableVisibility();
    });

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
    
    // Initial update
    updateTableVisibility();
});

// Function to update table visibility with pagination
function updateTableVisibility() {
    const searchValue = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#sitinTable tbody tr:not(.not-record)');
    let visibleRows = [];
    
    // Filter rows based on search input
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowData = {
            idno: cells[0].textContent.toLowerCase(),
            name: cells[1].textContent.toLowerCase(),
            course: cells[2].textContent.toLowerCase(),
            level: cells[3].textContent.toLowerCase(),
            email: cells[4].textContent.toLowerCase()
        };
        
        const matchesSearch = searchValue === '' || 
            rowData.idno.includes(searchValue) ||
            rowData.name.includes(searchValue) ||
            rowData.course.includes(searchValue) ||
            rowData.level.includes(searchValue) ||
            rowData.email.includes(searchValue);
        
        if (matchesSearch) {
            visibleRows.push(row);
        }
        row.style.display = 'none'; // Hide all rows initially
    });
    
    const noMatchRow = document.getElementById('noMatchRow');
    if (rows.length > 0) {
        if (visibleRows.length === 0) {
            if (noMatchRow) noMatchRow.style.display = '';
        } else {
            if (noMatchRow) noMatchRow.style.display = 'none';
        }
    }

    // Calculate total pages
    const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
    
    // Show only the rows for the current page
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    for (let i = startIndex; i < endIndex && i < visibleRows.length; i++) {
        visibleRows[i].style.display = '';
    }
    
    // Update pagination info
    const startEntry = visibleRows.length === 0 ? 0 : startIndex + 1;
    const endEntry = Math.min(endIndex, visibleRows.length);
    document.getElementById('paginationInfo').textContent = 
        `Showing ${startEntry} to ${endEntry} of ${visibleRows.length} entries`;
    
    // Update pagination controls (only show if more than 1 page)
    if (totalPages <= 1) {
        document.getElementById('paginationControls').innerHTML = '';
    } else {
        updatePaginationControls(totalPages);
    }
}

// Function to update pagination controls
// Replace the existing updatePaginationControls function in student.php with this:
function updatePaginationControls(totalPages) {
    const paginationControls = document.getElementById('paginationControls');
    paginationControls.innerHTML = '';
    
    // Previous button
    const prevButton = document.createElement('button');
    prevButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
    prevButton.className = `page-btn`;
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateTableVisibility();
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
            updateTableVisibility();
        });
        paginationControls.appendChild(firstPageButton);
        
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.textContent = '...';
            ellipsis.style.cssText = 'padding:0 6px;color:#9A8FB0;';
            paginationControls.appendChild(ellipsis);
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const pageButton = document.createElement('button');
        pageButton.textContent = i;
        pageButton.className = `page-btn ${i === currentPage ? 'active' : ''}`;
        pageButton.addEventListener('click', () => {
            currentPage = i;
            updateTableVisibility();
        });
        paginationControls.appendChild(pageButton);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.textContent = '...';
            ellipsis.style.cssText = 'padding:0 6px;color:#9A8FB0;';
            paginationControls.appendChild(ellipsis);
        }
        
        const lastPageButton = document.createElement('button');
        lastPageButton.textContent = totalPages;
        lastPageButton.className = 'page-btn';
        lastPageButton.addEventListener('click', () => {
            currentPage = totalPages;
            updateTableVisibility();
        });
        paginationControls.appendChild(lastPageButton);
    }
    
    // Next button
    const nextButton = document.createElement('button');
    nextButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
    nextButton.className = `page-btn ${currentPage === totalPages ? '' : ''}`;
    nextButton.disabled = currentPage === totalPages;
    nextButton.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            updateTableVisibility();
        }
    });
    paginationControls.appendChild(nextButton);
}
</script>

<!-- Star Background Animation -->
<script>
(function(){
    const canvas = document.getElementById('star-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, stars = [], shoots = [];

    function resize() {
        W = canvas.width = window.innerWidth;
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

    function spawnShoot() {
        shoots.push({
            x: Math.random() * W * 1.2, y: Math.random() * H * 0.5,
            len: Math.random() * 100 + 50, speed: Math.random() * 5 + 3,
            angle: Math.PI / 4, alpha: 1
        });
    }
    setInterval(spawnShoot, 3000);

    function draw() {
        ctx.clearRect(0, 0, W, H);
        stars.forEach(s => {
            s.a += s.da;
            if (s.a <= 0 || s.a >= 1) s.da *= -1;
            ctx.beginPath();
            ctx.arc(s.x % W, s.y % H, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200,180,255,${s.a.toFixed(2)})`;
            ctx.fill();
        });

        shoots.forEach((s, i) => {
            s.x += Math.cos(s.angle) * s.speed;
            s.y += Math.sin(s.angle) * s.speed;
            s.alpha -= 0.015;

            const grad = ctx.createLinearGradient(
                s.x - Math.cos(s.angle) * s.len,
                s.y - Math.sin(s.angle) * s.len, s.x, s.y
            );
            grad.addColorStop(0, `rgba(212,135,10,0)`);
            grad.addColorStop(1, `rgba(200,160,255,${s.alpha.toFixed(2)})`);

            ctx.beginPath();
            ctx.moveTo(s.x - Math.cos(s.angle) * s.len, s.y - Math.sin(s.angle) * s.len);
            ctx.lineTo(s.x, s.y);
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1;
            ctx.stroke();

            if (s.alpha <= 0) shoots.splice(i, 1);
        });
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>
</body>
</html>