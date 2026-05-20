<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
session_start();
if (!isset($_SESSION['login_user'])) { header("Location: login.php"); exit(); }
require __DIR__ . '/../config/db.php';

// Sorting & Search Setup
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$order_by = "a.created_at DESC"; // Default: Newest First
if ($sort === 'oldest') {
    $order_by = "a.created_at ASC";
} elseif ($sort === 'az') {
    $order_by = "a.title ASC";
} elseif ($sort === 'za') {
    $order_by = "a.title DESC";
}

// Pagination Setup
$limit = 7;
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_num < 1) $page_num = 1;
$offset = ($page_num - 1) * $limit;

// Build search condition
$search_cond = " WHERE u.role = 'admin' ";
$params = [];
$types = "";

if ($search !== "") {
    $search_cond .= " AND (a.title LIKE ? OR a.description LIKE ?) ";
    $search_like = "%" . $search . "%";
    $params = [$search_like, $search_like];
    $types = "ss";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM announcements a JOIN users u ON a.admin_id = u.user_id" . $search_cond;
$stmt = $conn->prepare($count_query);
if ($search !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_rows / $limit);
if ($page_num > $total_pages && $total_pages > 0) $page_num = $total_pages;
$offset = ($page_num - 1) * $limit;

// Fetch announcements with sorting, search, limit and offset
$query = "SELECT a.*, u.firstname, u.middlename, u.lastname, u.profile_picture,
                 (SELECT COUNT(*) FROM comments c WHERE c.announcement_id = a.announcement_id) AS comment_count
          FROM announcements a
          JOIN users u ON a.admin_id = u.user_id
          " . $search_cond . "
          ORDER BY $order_by
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($search !== "") {
    $bind_params = array_merge($params, [$limit, $offset]);
    $bind_types = $types . "ii";
    $stmt->bind_param($bind_types, ...$bind_params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$announcements = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $announcements[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – CCS Sit-In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .ann-row {
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .ann-row:hover td {
            background: rgba(139, 63, 217, 0.08) !important;
        }
        .ann-author-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            border: 2px solid rgba(139,63,217,0.4);
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            background: rgba(139,63,217,0.15); flex-shrink: 0;
        }
        .comment-area {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            color: #fff;
            font-size: 14px;
            font-family: var(--font-b);
            width: 100%;
            resize: vertical;
            min-height: 72px;
            outline: none;
            transition: all 0.3s;
        }
        .comment-area:focus { border-color: var(--purple-glow); box-shadow: 0 0 12px rgba(139,63,217,0.2); }
        .comment-area::placeholder { color: var(--text-dim); }
        .btn-comment {
            background: var(--purple-glow); color: #fff;
            border: none; padding: 9px 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600; font-family: var(--font-b);
            cursor: pointer; transition: all 0.3s; margin-top: 8px;
        }
        .btn-comment:hover { background: var(--purple-light); }
        .comment-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(139,63,217,0.1);
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 10px;
        }
        .search-bar {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: #fff; padding: 10px 16px 10px 40px;
            border-radius: 12px; font-size: 14px; font-family: var(--font-b);
            outline: none; width: 100%; transition: all 0.3s;
        }
        .search-bar:focus { border-color: var(--purple-glow); box-shadow: 0 0 12px rgba(139,63,217,0.2); }
        .search-bar::placeholder { color: var(--text-dim); }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        <div class="student-content">
            <div class="content-card">
                <!-- Controls -->
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
                    <div style="position:relative;flex:1;max-width:400px;">
                        <i class="fas fa-search" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:13px;"></i>
                        <input type="text" id="annSearch" placeholder="Search announcements…" class="search-bar" value="<?php echo htmlspecialchars($search); ?>" onkeypress="handleSearchKeyPress(event)">
                    </div>
                    <div style="position:relative;">
                        <button id="sortBtn" style="display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-dim);padding:10px 16px;border-radius:12px;font-size:13px;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;" onmouseover="this.style.borderColor='var(--purple-glow)'" onmouseout="this.style.borderColor='var(--border)'" onclick="toggleSortDropdown(event)">
                            <i class="fas fa-sort-amount-down"></i> Sort: <?php
                                if ($sort === 'oldest') echo 'Oldest First';
                                elseif ($sort === 'az') echo 'A - Z';
                                elseif ($sort === 'za') echo 'Z - A';
                                else echo 'Newest First';
                            ?>
                        </button>
                        <div id="sortMenu" style="display:none;position:absolute;top:calc(100% + 8px);right:0;background:#161326;border:1px solid rgba(139,63,217,0.3);border-radius:12px;overflow:hidden;min-width:160px;box-shadow:0 20px 40px rgba(0,0,0,0.4);z-index:100;">
                            <a href="?page=1&sort=newest&search=<?php echo urlencode($search); ?>" style="color:#D1C7E0;font-size:13px;text-decoration:none;display:block;padding:10px 16px;transition:all 0.2s;" onmouseover="this.style.background='rgba(139,63,217,0.1)'" onmouseout="this.style.background=''">Newest First</a>
                            <a href="?page=1&sort=oldest&search=<?php echo urlencode($search); ?>" style="color:#D1C7E0;font-size:13px;text-decoration:none;display:block;padding:10px 16px;transition:all 0.2s;" onmouseover="this.style.background='rgba(139,63,217,0.1)'" onmouseout="this.style.background=''">Oldest First</a>
                            <a href="?page=1&sort=az&search=<?php echo urlencode($search); ?>" style="color:#D1C7E0;font-size:13px;text-decoration:none;display:block;padding:10px 16px;transition:all 0.2s;" onmouseover="this.style.background='rgba(139,63,217,0.1)'" onmouseout="this.style.background=''">A - Z</a>
                            <a href="?page=1&sort=za&search=<?php echo urlencode($search); ?>" style="color:#D1C7E0;font-size:13px;text-decoration:none;display:block;padding:10px 16px;transition:all 0.2s;" onmouseover="this.style.background='rgba(139,63,217,0.1)'" onmouseout="this.style.background=''">Z - A</a>
                        </div>
                    </div>
                </div>

                <!-- Announcement List Table -->
                <div class="records-header">
                    <div class="records-title">
                        <h3 style="font-family: var(--font-h); font-weight: 700; letter-spacing: 1px;">Announcement List</h3>
                    </div>
                </div>

                <div class="dark-table-wrap" style="flex:1; overflow-y:auto;">
                    <table class="dark-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;">POST NO.</th>
                                <th>TITLE</th>
                                <th style="width: 180px;">DATE</th>
                                <th style="width: 140px; text-align: center;">COMMENTS</th>
                                <th style="width: 140px; text-align: center;">ATTACHMENT</th>
                            </tr>
                        </thead>
                        <tbody id="annTableBody">
                            <?php if (!empty($announcements)): ?>
                                <?php foreach ($announcements as $row):
                                    $dateStr = date("Y-m-d", strtotime($row['created_at']));
                                    $hasAttachment = !empty($row['attachment']);
                                ?>
                                <tr class="ann-row" data-id="<?php echo $row['announcement_id']; ?>">
                                    <td class="id-cell">#<?php echo htmlspecialchars($row['announcement_id']); ?></td>
                                    <td style="color: #fff; font-weight: 600;" class="title-cell"><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td style="color: var(--text-dim);"><?php echo $dateStr; ?></td>
                                    <td style="text-align: center; color: var(--text-dim);"><?php echo intval($row['comment_count']); ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($hasAttachment): ?>
                                            <i class="fas fa-paperclip" style="color: #C084FC;"></i>
                                        <?php else: ?>
                                            <span style="color: rgba(255,255,255,0.15);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 40px 0;">
                                        <i class="fas fa-bullhorn" style="font-size: 32px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                                        No announcements found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_rows > 0): ?>
                    <?php
                    $start_entry = $total_rows == 0 ? 0 : $offset + 1;
                    $end_entry = min($offset + $limit, $total_rows);
                    ?>
                    <div class="pagination-row" style="margin-top: 16px; padding: 0 4px;">
                        <div class="pagination-info" style="color: var(--text-dim); font-size: 13px;">
                            Showing <?php echo $start_entry; ?> to <?php echo $end_entry; ?> of <?php echo $total_rows; ?> entries
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-controls" style="display: flex; align-items: center; gap: 6px;">
                                <!-- Prev Link -->
                                <a class="page-btn <?php echo ($page_num <= 1) ? 'disabled pointer-events-none opacity-50' : ''; ?>" 
                                   href="?page=<?php echo $page_num - 1; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>"
                                   style="text-decoration: none;">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a class="page-btn <?php echo ($page_num == $i) ? 'active' : ''; ?>" 
                                       href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>"
                                       style="text-decoration: none;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <!-- Next Link -->
                                <a class="page-btn <?php echo ($page_num >= $total_pages) ? 'disabled pointer-events-none opacity-50' : ''; ?>" 
                                   href="?page=<?php echo $page_num + 1; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>"
                                   style="text-decoration: none;">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Announcement Detail Modal -->
    <div id="annModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 600px; display: flex; flex-direction: column; max-height: 85vh; padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                <h2 style="margin: 0; font-family: var(--font-h); font-size: 15px; color: #fff; letter-spacing: 1px;">ANNOUNCEMENT DETAILS</h2>
                <button onclick="closeModal()" style="background: none; border: none; color: var(--text-dim); font-size: 18px; cursor: pointer; transition: color 0.2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='var(--text-dim)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div style="flex: 1; overflow-y: auto; padding-right: 4px;" class="main-content-scroll">
                <!-- Author and Date Header -->
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <div id="modalAvatar" class="ann-author-avatar" style="width: 44px; height: 44px;">
                        <!-- JS inserted avatar -->
                    </div>
                    <div>
                        <p id="modalAuthorName" style="font-size: 13px; font-weight: 600; color: #fff; margin: 0;"></p>
                        <p id="modalPublishDate" style="font-size: 11px; color: var(--text-dim); margin: 0;"></p>
                    </div>
                </div>
                
                <!-- Title & Body -->
                <h3 id="modalTitle" style="font-family: var(--font-h); font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 12px; line-height: 1.4;"></h3>
                <div id="modalBody" style="font-size: 14px; color: var(--text-body); line-height: 1.6; margin-bottom: 20px; word-break: break-word;"></div>
                
                <!-- Attachment -->
                <div id="modalAttachmentContainer" style="margin-bottom: 24px; display: none;">
                    <h4 style="font-size: 11px; font-weight: 600; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Attachment</h4>
                    <div id="modalAttachmentContent"></div>
                </div>

                <!-- Announcement Likes Row -->
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                    <button id="modalLikePostBtn" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); color: var(--text-dim); padding: 8px 16px; border-radius: 10px; font-size: 13px; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--purple-glow)'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="far fa-heart" id="modalLikePostIcon"></i> Like (<span id="modalPostLikesCount">0</span>)
                    </button>
                </div>
                
                <!-- Comments Section -->
                <div style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px;">
                    <h4 style="font-family: var(--font-h); font-size: 13px; font-weight: 700; color: #fff; letter-spacing: 0.5px; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                        <i class="far fa-comments" style="color: #D4870A;"></i> COMMENTS (<span id="modalCommentsCount">0</span>)
                    </h4>
                    
                    <!-- Write Comment -->
                    <div style="margin-bottom: 16px;">
                        <textarea id="modalCommentText" class="comment-area" placeholder="Write a comment…" style="min-height: 60px; font-size: 13px;"></textarea>
                        <button id="modalPostCommentBtn" class="btn-comment" style="display: flex; align-items: center; gap: 6px; margin-top: 8px; padding: 8px 16px;">
                            <i class="fas fa-paper-plane"></i> Post Comment
                        </button>
                    </div>
                    
                    <!-- Comments List -->
                    <div id="modalCommentsList" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- JS inserted comments -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Announcements JSON Data
    const announcementsData = <?php echo json_encode($announcements); ?>;

    // Star canvas
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,stars=[];function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',resize);resize();for(let i=0;i<120;i++)stars.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function draw(){ctx.clearRect(0,0,W,H);stars.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(draw);}draw();})();

    // Search Redirect
    function handleSearchKeyPress(event) {
        if (event.key === "Enter") {
            const val = document.getElementById("annSearch").value.trim();
            window.location.href = `?page=1&sort=<?php echo $sort; ?>&search=${encodeURIComponent(val)}`;
        }
    }

    // Sort Dropdown
    function toggleSortDropdown(event) {
        event.stopPropagation();
        const menu = document.getElementById('sortMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    document.addEventListener('click', () => {
        const menu = document.getElementById('sortMenu');
        if (menu) menu.style.display = 'none';
    });

    // Open Modal with Details
    document.querySelectorAll('.ann-row').forEach(row => {
        row.addEventListener('click', function() {
            const id = parseInt(this.dataset.id);
            const ann = announcementsData.find(a => parseInt(a.announcement_id) === id);
            if (!ann) return;
            
            // Populate Modal Fields
            document.getElementById('modalAuthorName').innerHTML = `${ann.firstname} ${ann.lastname} <span style="color:rgba(139,63,217,0.8);font-size:11px;margin-left:6px;">· Admin</span>`;
            document.getElementById('modalPublishDate').textContent = new Date(ann.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            document.getElementById('modalTitle').textContent = ann.title;
            document.getElementById('modalBody').innerHTML = ann.description.replace(/\n/g, '<br>');
            
            // Author Avatar
            const avatarDiv = document.getElementById('modalAvatar');
            if (ann.profile_picture) {
                avatarDiv.innerHTML = `<img src="upload/${ann.profile_picture}" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                const initials = (ann.firstname.substring(0,1) + ann.lastname.substring(0,1)).toUpperCase();
                avatarDiv.innerHTML = `<span style="font-size:14px;font-weight:700;color:#C084FC;">${initials}</span>`;
            }
            
            // Attachment Rendering
            const attachContainer = document.getElementById('modalAttachmentContainer');
            const attachContent = document.getElementById('modalAttachmentContent');
            if (ann.attachment) {
                attachContainer.style.display = 'block';
                const fileExt = ann.attachment.split('.').pop().toLowerCase();
                const imgExts = ['jpg','jpeg','png','gif','bmp','webp'];
                if (imgExts.includes(fileExt)) {
                    attachContent.innerHTML = `<img src="announce/${ann.attachment}" style="width:100%; max-height:280px; object-fit:contain; border-radius:8px; border:1px solid var(--border);">`;
                } else {
                    attachContent.innerHTML = `
                        <a href="announce/${ann.attachment}" download
                           style="display:inline-flex;align-items:center;gap:8px;background:rgba(139,63,217,0.12);border:1px solid rgba(139,63,217,0.25);color:#C084FC;padding:8px 16px;border-radius:10px;font-size:13px;text-decoration:none;font-weight:600;transition:all 0.3s;" onmouseover="this.style.background='rgba(139,63,217,0.22)'" onmouseout="this.style.background='rgba(139,63,217,0.12)'">
                            <i class="fas fa-file-download"></i> Download ${ann.attachment}
                        </a>
                    `;
                }
            } else {
                attachContainer.style.display = 'none';
            }
            
            // Comments Count
            document.getElementById('modalCommentsCount').textContent = ann.comment_count;

            // Load Announcement Likes
            loadAnnouncementLikes(ann.announcement_id);
            
            // Toggle Announcement Like Callback
            const likePostBtn = document.getElementById('modalLikePostBtn');
            likePostBtn.onclick = function() {
                const fd = new FormData();
                fd.append('action', 'like_announcement');
                fd.append('announcement_id', ann.announcement_id);
                
                fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(data => {
                    if (data.success) {
                        loadAnnouncementLikes(ann.announcement_id);
                    }
                });
            };
            
            // Post Comment Callback
            const postBtn = document.getElementById('modalPostCommentBtn');
            postBtn.onclick = function() {
                const text = document.getElementById('modalCommentText').value.trim();
                if(!text) return;
                const fd = new FormData();
                fd.append('action', 'add');
                fd.append('announcement_id', ann.announcement_id);
                fd.append('comment_text', text);
                
                fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(data => {
                    if (data.success) {
                        document.getElementById('modalCommentText').value = '';
                        ann.comment_count = parseInt(ann.comment_count) + 1;
                        document.getElementById('modalCommentsCount').textContent = ann.comment_count;
                        
                        // Update table row comments cell real-time
                        const tableRow = document.querySelector(`.ann-row[data-id="${ann.announcement_id}"]`);
                        if (tableRow) {
                            const cells = tableRow.querySelectorAll('td');
                            if (cells.length >= 4) {
                                cells[3].textContent = ann.comment_count;
                            }
                        }
                        
                        loadCommentsForModal(ann.announcement_id);
                    }
                });
            };
            
            // Load Comments
            loadCommentsForModal(ann.announcement_id);
            
            // Open Modal
            document.getElementById('annModal').classList.add('show');
        });
    });

    function closeModal() {
        document.getElementById('annModal').classList.remove('show');
        document.getElementById('modalCommentText').value = '';
    }
    
    // Close modal on click outside box
    document.getElementById('annModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    function loadAnnouncementLikes(id) {
        const fd = new FormData();
        fd.append('action', 'get_announcement_like');
        fd.append('announcement_id', id);
        
        fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            document.getElementById('modalPostLikesCount').textContent = data.like_count;
            const btn = document.getElementById('modalLikePostBtn');
            const icon = document.getElementById('modalLikePostIcon');
            if (data.user_liked) {
                icon.className = 'fas fa-heart';
                icon.style.color = '#ef4444';
                btn.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                btn.style.background = 'rgba(239, 68, 68, 0.08)';
                btn.style.color = '#ef4444';
            } else {
                icon.className = 'far fa-heart';
                icon.style.color = '';
                btn.style.borderColor = 'var(--border)';
                btn.style.background = 'rgba(255,255,255,0.03)';
                btn.style.color = 'var(--text-dim)';
            }
        });
    }

    function loadCommentsForModal(id) {
        const fd = new FormData();
        fd.append('action', 'get');
        fd.append('announcement_id', id);
        
        fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if(!data.success) return;
            const list = document.getElementById('modalCommentsList');
            list.innerHTML = '';
            
            if (data.comments.length > 0) {
                const parents = data.comments.filter(c => c.parent_id === null);
                const replies = data.comments.filter(c => c.parent_id !== null);
                
                parents.forEach(c => {
                    list.appendChild(buildComment(c, id));
                });
                
                replies.forEach(r => {
                    const parentRepliesList = document.getElementById(`replies-list-${r.parent_id}`);
                    if (parentRepliesList) {
                        parentRepliesList.appendChild(buildReply(r, id));
                    }
                });
            } else {
                list.innerHTML = '<div style="text-align:center; padding:20px 0; color:var(--text-dim); font-size:12px;">No comments yet. Be the first to comment!</div>';
            }
        });
    }

    function buildComment(c, announcementId) {
        const div = document.createElement('div');
        div.className = 'comment-item';
        div.style.marginBottom = '16px';
        div.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="width:32px;height:32px;border-radius:50%;overflow:hidden;background:rgba(139,63,217,0.15);display:flex;align-items:center;justify-content:center;border:1px solid rgba(139,63,217,0.3);">
                    ${c.profile_picture ? `<img src="upload/${c.profile_picture}" style="width:100%;height:100%;object-fit:cover;">` : `<svg viewBox="0 0 24 24" style="width:100%;height:100%;fill:#C084FC;padding:4px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>`}
                </div>
                <div>
                    <span style="font-size:13px;font-weight:600;color:#fff;">${c.firstname} ${c.lastname}</span>
                    <span style="font-size:11px;color:rgba(139,63,217,0.8);margin-left:6px;">${c.role}</span>
                    <div style="font-size:11px;color:var(--text-dim);">${new Date(c.created_at).toLocaleString()}</div>
                </div>
            </div>
            <p id="comment-text-${c.comment_id}" style="font-size:13px;color:#D1C7E0;line-height:1.6;margin:0 0 8px 0;">${c.comment_text}</p>
            
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
                <button onclick="toggleCommentLike(${c.comment_id}, ${announcementId})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:12px;color:${parseInt(c.user_liked) ? '#ef4444' : 'var(--text-dim)'};cursor:pointer;transition:color 0.2s;">
                    <i class="${parseInt(c.user_liked) ? 'fas' : 'far'} fa-heart" style="${parseInt(c.user_liked) ? 'color:#ef4444;' : ''}"></i> Like (${c.like_count || 0})
                </button>
                <button onclick="toggleReplyForm(${c.comment_id})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-dim);cursor:pointer;transition:color 0.2s;">
                    <i class="far fa-comment"></i> Reply
                </button>
            </div>
            
            <!-- Reply Form -->
            <div id="reply-form-${c.comment_id}" style="display:none; margin-top:10px; margin-left:14px; padding-left:14px; border-left:2px solid rgba(139,63,217,0.3);">
                <div style="display:flex; gap:10px; align-items:center;">
                    <input type="text" id="reply-input-${c.comment_id}" placeholder="Reply to this comment…" class="search-bar" style="height:36px; padding:6px 12px; font-size:12px;">
                    <button onclick="postCommentReply(${c.comment_id}, ${announcementId})" class="btn-comment" style="padding:6px 12px; font-size:11px; margin-top:0;">Send</button>
                </div>
            </div>
            
            <!-- Replies List Container -->
            <div id="replies-list-${c.comment_id}" style="margin-top:10px; margin-left:14px; padding-left:14px; border-left:2px solid rgba(255,255,255,0.05); display:flex; flex-direction:column; gap:10px;"></div>
        `;
        return div;
    }

    function buildReply(r, announcementId) {
        const div = document.createElement('div');
        div.className = 'comment-item reply-item';
        div.style.background = 'rgba(255,255,255,0.01)';
        div.style.padding = '8px 12px';
        div.style.borderRadius = '8px';
        div.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <div style="width:26px;height:26px;border-radius:50%;overflow:hidden;background:rgba(139,63,217,0.15);display:flex;align-items:center;justify-content:center;border:1px solid rgba(139,63,217,0.3);">
                    ${r.profile_picture ? `<img src="upload/${r.profile_picture}" style="width:100%;height:100%;object-fit:cover;">` : `<svg viewBox="0 0 24 24" style="width:100%;height:100%;fill:#C084FC;padding:3px;"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>`}
                </div>
                <div>
                    <span style="font-size:12px;font-weight:600;color:#fff;">${r.firstname} ${r.lastname}</span>
                    <span style="font-size:10px;color:rgba(139,63,217,0.8);margin-left:6px;">${r.role}</span>
                    <div style="font-size:10px;color:var(--text-dim);">${new Date(r.created_at).toLocaleString()}</div>
                </div>
            </div>
            <p id="comment-text-${r.comment_id}" style="font-size:12px;color:#D1C7E0;line-height:1.5;margin:0 0 6px 0;">${r.comment_text}</p>
            
            <div style="display:flex;align-items:center;gap:14px;">
                <button onclick="toggleCommentLike(${r.comment_id}, ${announcementId})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:11px;color:${parseInt(r.user_liked) ? '#ef4444' : 'var(--text-dim)'};cursor:pointer;transition:color 0.2s;">
                    <i class="${parseInt(r.user_liked) ? 'fas' : 'far'} fa-heart" style="${parseInt(r.user_liked) ? 'color:#ef4444;' : ''}"></i> Like (${r.like_count || 0})
                </button>
            </div>
        `;
        return div;
    }

    function toggleCommentLike(commentId, announcementId) {
        const fd = new FormData();
        fd.append('action', 'like_comment');
        fd.append('comment_id', commentId);
        
        fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if (data.success) {
                loadCommentsForModal(announcementId);
            }
        });
    }

    function toggleReplyForm(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        if (form) {
            form.style.display = form.style.display === 'block' ? 'none' : 'block';
            if (form.style.display === 'block') {
                document.getElementById(`reply-input-${commentId}`).focus();
            }
        }
    }

    function postCommentReply(parentCommentId, announcementId) {
        const input = document.getElementById(`reply-input-${parentCommentId}`);
        const text = input.value.trim();
        if (!text) return;
        
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('announcement_id', announcementId);
        fd.append('comment_text', text);
        fd.append('parent_id', parentCommentId);
        
        fetch('Admin/comment_operations.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(data => {
            if (data.success) {
                input.value = '';
                loadCommentsForModal(announcementId);
                
                // Update table comments count
                const tableRow = document.querySelector(`.ann-row[data-id="${announcementId}"]`);
                if (tableRow) {
                    const cells = tableRow.querySelectorAll('td');
                    if (cells.length >= 4) {
                        const newCount = parseInt(cells[3].textContent) + 1;
                        cells[3].textContent = newCount;
                        document.getElementById('modalCommentsCount').textContent = newCount;
                    }
                }
            }
        });
    }
    </script>
</body>
</html>