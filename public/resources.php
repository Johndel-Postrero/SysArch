<?php
// Add at the top for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// Database connection
require __DIR__ . '/../config/db.php';

// Get current folder (if any)
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Fetch resources from the database
$query = "
    SELECT r.*, u.firstname, u.lastname 
    FROM resources r
    JOIN users u ON r.admin_id = u.user_id
    WHERE r.parent_id " . ($current_folder ? "= $current_folder" : "IS NULL") . "
    ORDER BY r.is_folder DESC, r.uploaded_at DESC
";
$result = $conn->query($query);

if (!$result) {
    echo "Query error: " . $conn->error;
}

// Fetch breadcrumbs if in a folder
$breadcrumbs = [];
if ($current_folder) {
    $folder_id = $current_folder;
    while ($folder_id) {
        $folder_query = "SELECT resource_id, title, parent_id FROM resources WHERE resource_id = $folder_id";
        $folder_result = $conn->query($folder_query);
        if ($folder_result && $folder_result->num_rows > 0) {
            $folder = $folder_result->fetch_assoc();
            $breadcrumbs[] = $folder;
            $folder_id = $folder['parent_id'];
        } else {
            break;
        }
    }
    $breadcrumbs = array_reverse($breadcrumbs);
}

// Helper function to format file sizes
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources – CCS Sit-In</title>
    <script>window.onpageshow=function(e){if(e.persisted)window.location.reload();};</script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .folder-icon{color:#f59e0b;} .pdf-icon{color:#ef4444;} .doc-icon{color:#3b82f6;} .video-icon{color:#8b5cf6;} .image-icon{color:#10b981;}
        .resource-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:16px;cursor:pointer;transition:all 0.3s;position:relative;z-index:1;}
        .resource-card:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(139,63,217,0.15);border-color:rgba(139,63,217,0.35);}
        .resource-card.menu-active{z-index:50;}
        .resource-menu{position:absolute;top:calc(100% + 5px);right:0;background:#161326;border:1px solid rgba(139,63,217,0.3);min-width:160px;box-shadow:0 20px 40px rgba(0,0,0,0.4);z-index:100;border-radius:12px;display:none;padding:6px 0;}
        .resource-menu a{padding:9px 16px;display:block;text-decoration:none;color:#D1C7E0;font-size:13px;transition:background 0.2s;}
        .resource-menu a:hover{background:rgba(139,63,217,0.1);color:#fff;}
        .show{display:block;}
        .modal{display:none;position:fixed;z-index:2000;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);overflow-y:auto;}
        .modal-content{background:#0f0d1f;border:1px solid rgba(139,63,217,0.35);margin:5% auto;padding:24px;border-radius:20px;width:800px;max-width:92%;box-shadow:0 30px 60px rgba(0,0,0,0.6);}
        .preview-modal{max-width:92%;width:860px;}
        .modal-content h3{font-family:var(--font-h);font-size:14px;color:#fff;letter-spacing:1px;}
        #previewContent img,#previewContent video{border-radius:10px;}
        .res-search{background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:9px 16px 9px 36px;border-radius:10px;font-size:13px;outline:none;width:240px;transition:all 0.3s;font-family:var(--font-b);}
        .res-search:focus{border-color:var(--purple-glow);box-shadow:0 0 12px rgba(139,63,217,0.2);}
        .res-search::placeholder{color:var(--text-dim);}
        .ctrl-btn{display:flex;align-items:center;gap:7px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-dim);padding:8px 14px;border-radius:10px;font-size:13px;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;}
        .ctrl-btn:hover{border-color:var(--purple-glow);color:#fff;}
        .ctrl-btn.active{background:var(--purple-glow);border-color:var(--purple-glow);color:#fff;}
        .drop-menu{display:none;position:absolute;top:calc(100% + 8px);right:0;background:#161326;border:1px solid rgba(139,63,217,0.3);border-radius:12px;overflow:hidden;min-width:160px;box-shadow:0 20px 40px rgba(0,0,0,0.4);z-index:100;}
        .drop-menu a{display:block;padding:10px 16px;color:#D1C7E0;font-size:13px;text-decoration:none;transition:background 0.2s;}
        .drop-menu a:hover{background:rgba(139,63,217,0.1);color:#fff;}
        .breadcrumb-item:after{content:'/';margin:0 8px;color:var(--text-dim);}
        .breadcrumb-item:last-child:after{content:'';}
        .file-icon{font-size:2rem;margin-right:1rem;}
        .list-view .resource-card{display:flex;flex-direction:row;align-items:center;}
        .list-view .resource-icon{margin-right:16px;}
        .list-view .resource-details{flex:1;}
        .modal-icon-btn{background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-dim);width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.3s;}
        .modal-icon-btn:hover{border-color:var(--purple-glow);color:#fff;}
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
            
            <div class="flex-1 overflow-auto">
                <div class="p-6 max-w-5xl mx-auto w-full">
                    <!-- Breadcrumbs -->
                    <?php if (!empty($breadcrumbs)): ?>
                        <div style="display:flex;align-items:center;font-size:13px;color:var(--text-dim);margin-bottom:16px;gap:4px;">
                            <a href="resources.php" style="color:var(--purple-light);text-decoration:none;"><i class="fas fa-home"></i></a>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <span class="breadcrumb-item">
                                    <a href="resources.php?folder=<?php echo $crumb['resource_id']; ?>" 
                                       style="color:var(--purple-light);text-decoration:none;">
                                        <?php echo htmlspecialchars($crumb['title']); ?>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Controls -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px;flex-wrap:wrap;">
                        <div style="position:relative;">
                            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:12px;"></i>
                            <input class="res-search" placeholder="Search resources…" type="text" id="searchInput"/>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button id="gridViewBtn" class="ctrl-btn active"><i class="fas fa-th-large"></i></button>
                            <button id="listViewBtn" class="ctrl-btn"><i class="fas fa-list"></i></button>
                            <div style="position:relative;">
                                <button id="sortButton" class="ctrl-btn"><i class="fas fa-sort"></i> Sort</button>
                                <div id="sortDropdown" class="drop-menu">
                                    <a href="#" data-sort="name-asc"><i class="fas fa-sort-alpha-down mr-2"></i>Name A-Z</a>
                                    <a href="#" data-sort="name-desc"><i class="fas fa-sort-alpha-up mr-2"></i>Name Z-A</a>
                                    <a href="#" data-sort="date-newest"><i class="fas fa-arrow-down mr-2"></i>Newest</a>
                                    <a href="#" data-sort="date-oldest"><i class="fas fa-arrow-up mr-2"></i>Oldest</a>
                                    <a href="#" data-sort="size-largest"><i class="fas fa-sort-amount-down mr-2"></i>Largest</a>
                                    <a href="#" data-sort="size-smallest"><i class="fas fa-sort-amount-up mr-2"></i>Smallest</a>
                                </div>
                            </div>
                            <div style="position:relative;">
                                <button id="filterButton" class="ctrl-btn"><i class="fas fa-filter"></i> Filter</button>
                                <div id="filterDropdown" class="drop-menu">
                                    <a href="#" data-filter="all"><i class="fas fa-layer-group mr-2"></i>All Items</a>
                                    <a href="#" data-filter="folder"><i class="fas fa-folder mr-2"></i>Folders</a>
                                    <a href="#" data-filter="pdf"><i class="fas fa-file-pdf mr-2"></i>PDFs</a>
                                    <a href="#" data-filter="doc"><i class="fas fa-file-word mr-2"></i>Documents</a>
                                    <a href="#" data-filter="video"><i class="fas fa-file-video mr-2"></i>Videos</a>
                                    <a href="#" data-filter="other"><i class="fas fa-file-alt mr-2"></i>Other Files</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resources Grid -->
                    <div id="resourcesContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $file_path = "resources/" . $row['file_name'];
                                $is_folder = $row['is_folder'];
                                
                                if ($is_folder) {
                                    $icon = 'fa-folder';
                                    $icon_class = 'folder-icon';
                                    $file_type = 'folder';
                                } else {
                                    $file_extension = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                                    
                                    // Determine file type and icon
                                    $file_type = 'other';
                                    $icon = 'fa-file';
                                    $icon_class = 'file-icon';
                                    
                                    if (in_array($file_extension, ['pdf'])) {
                                        $file_type = 'pdf';
                                        $icon = 'fa-file-pdf';
                                        $icon_class = 'pdf-icon';
                                    } elseif (in_array($file_extension, ['doc', 'docx', 'odt', 'txt', 'rtf'])) {
                                        $file_type = 'doc';
                                        $icon = 'fa-file-word';
                                        $icon_class = 'doc-icon';
                                    } elseif (in_array($file_extension, ['mp4', 'mov', 'avi', 'mkv', 'wmv'])) {
                                        $file_type = 'video';
                                        $icon = 'fa-file-video';
                                        $icon_class = 'video-icon';
                                    } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                                        $file_type = 'image';
                                        $icon = 'fa-file-image';
                                        $icon_class = 'image-icon';
                                    }
                                }
                                ?>
                                
                                <div class="resource-card" 
                                     data-file-type="<?php echo $file_type; ?>"
                                     data-name="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                                     data-date="<?php echo strtotime($row['uploaded_at']); ?>"
                                     data-size="<?php echo $row['file_size'] ?? 0; ?>"
                                     data-id="<?php echo $row['resource_id']; ?>">
                                    
                                    <!-- Main content - clickable area -->
                                    <div class="flex items-start h-full cursor-pointer resource-main">
                                        <div class="flex-shrink-0 resource-icon">
                                            <i class="fas <?php echo $icon; ?> <?php echo $icon_class; ?> file-icon"></i>
                                        </div>
                                        <div class="ml-3 flex-1 flex flex-col h-full resource-details">
                                            <div class="flex-1">
                                                <h3 style="font-weight:700;font-size:14px;color:#fff;"><?php echo htmlspecialchars($row['title']); ?></h3>
                                            </div>
                                            
                                            <div style="margin-top:10px;font-size:12px;color:var(--text-dim);">
                                                <div style="display:flex;align-items:center;gap:6px;">
                                                    <span><?php echo date("M j, Y", strtotime($row['uploaded_at'])); ?></span>
                                                    <?php if (!$is_folder): ?>
                                                        <span>·</span>
                                                        <span><?php echo formatFileSize($row['file_size']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Context menu button -->
                                    <div class="absolute top-2 right-2">
                                        <button class="text-gray-500 hover:text-gray-700 resource-menu-btn">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="resource-menu">
                                            <?php if ($is_folder): ?>
                                                <a href="resources.php?folder=<?php echo $row['resource_id']; ?>"><i class="fas fa-folder-open mr-2"></i>Open</a>
                                            <?php else: ?>
                                                <a href="#" class="preview-file" data-id="<?php echo $row['resource_id']; ?>"><i class="fas fa-eye mr-2"></i>Preview</a>
                                                <a href="<?php echo $file_path; ?>" download="<?php echo htmlspecialchars($row['file_name']); ?>"><i class="fas fa-download mr-2"></i>Download</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:50px 20px;text-align:center;grid-column:1/-1;">
                                <i class="fas fa-folder-open" style="font-size:36px;color:var(--text-dim);opacity:0.4;display:block;margin-bottom:12px;"></i>
                                <p style="font-size:15px;font-weight:600;color:var(--text-dim);">This folder is empty</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="modal-content preview-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                <h3 id="previewTitle"></h3>
                <div style="display:flex;gap:8px;">
                    <button id="printPreview" class="modal-icon-btn"><i class="fas fa-print"></i></button>
                    <button id="downloadPreview" class="modal-icon-btn"><i class="fas fa-download"></i></button>
                    <button id="closePreviewModal" class="modal-icon-btn"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div id="previewContent" style="display:flex;justify-content:center;align-items:center;min-height:400px;">
            </div>
        </div>
    </div>
    <script>(function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();</script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // DOM Elements
        const searchInput = document.getElementById('searchInput');
        const sortButton = document.getElementById('sortButton');
        const sortDropdown = document.getElementById('sortDropdown');
        const filterButton = document.getElementById('filterButton');
        const filterDropdown = document.getElementById('filterDropdown');
        const resourceCards = Array.from(document.querySelectorAll('.resource-card'));
        const resourcesContainer = document.getElementById('resourcesContainer');
        const gridViewBtn = document.getElementById('gridViewBtn');
        const listViewBtn = document.getElementById('listViewBtn');
        const previewModal = document.getElementById('previewModal');
        const closePreviewModal = document.getElementById('closePreviewModal');
        const previewTitle = document.getElementById('previewTitle');
        const previewContent = document.getElementById('previewContent');
        const printPreview = document.getElementById('printPreview');
        const downloadPreview = document.getElementById('downloadPreview');

        sortButton.addEventListener('click', function (e) {
            e.stopPropagation();
            sortDropdown.style.display = sortDropdown.style.display === 'block' ? 'none' : 'block';
            filterDropdown.style.display = 'none';
        });

        filterButton.addEventListener('click', function (e) {
            e.stopPropagation();
            filterDropdown.style.display = filterDropdown.style.display === 'block' ? 'none' : 'block';
            sortDropdown.style.display = 'none';
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!sortButton.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortDropdown.style.display = 'none';
            }
            if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.style.display = 'none';
            }
        });

        // Search functionality
        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            resourceCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const title = card.querySelector('h3').textContent.toLowerCase();
                
                if (name.includes(searchTerm) || title.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Sort functionality
        sortDropdown.querySelectorAll('a').forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                sortDropdown.style.display = 'none';
                
                const sortOption = this.getAttribute('data-sort');
                
                resourceCards.sort((a, b) => {
                    const nameA = a.getAttribute('data-name');
                    const nameB = b.getAttribute('data-name');
                    const dateA = parseInt(a.getAttribute('data-date'));
                    const dateB = parseInt(b.getAttribute('data-date'));
                    const sizeA = parseInt(a.getAttribute('data-size'));
                    const sizeB = parseInt(b.getAttribute('data-size'));
                    
                    switch (sortOption) {
                        case 'name-asc':
                            return nameA.localeCompare(nameB);
                        case 'name-desc':
                            return nameB.localeCompare(nameA);
                        case 'date-newest':
                            return dateB - dateA;
                        case 'date-oldest':
                            return dateA - dateB;
                        case 'size-largest':
                            return sizeB - sizeA;
                        case 'size-smallest':
                            return sizeA - sizeB;
                        default:
                            return 0;
                    }
                });
                
                // Re-append sorted cards
                resourceCards.forEach(card => resourcesContainer.appendChild(card));
            });
        });

        // Filter functionality
        filterDropdown.querySelectorAll('a').forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                filterDropdown.style.display = 'none';
                
                const filterType = this.getAttribute('data-filter');
                
                resourceCards.forEach(card => {
                    if (filterType === 'all') {
                        card.style.display = 'block';
                    } else {
                        const fileType = card.getAttribute('data-file-type');
                        card.style.display = fileType === filterType ? 'block' : 'none';
                    }
                });
            });
        });

        // View Toggle
        gridViewBtn.addEventListener('click', function() {
            resourcesContainer.classList.remove('list-view');
            resourcesContainer.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4';
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
        });

        listViewBtn.addEventListener('click', function() {
            resourcesContainer.classList.add('list-view');
            resourcesContainer.className = 'grid grid-cols-1 gap-2 list-view';
            listViewBtn.classList.add('active');
            gridViewBtn.classList.remove('active');
        });

        // Preview Modal
        closePreviewModal.addEventListener('click', function() {
            previewModal.style.display = 'none';
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === previewModal) {
                previewModal.style.display = 'none';
            }
        });

        // Context menu for resources
        document.querySelectorAll('.resource-menu-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const menu = this.nextElementSibling;
                const card = this.closest('.resource-card');
                
                // Close all other open menus first
                document.querySelectorAll('.resource-menu').forEach(m => {
                    if (m !== menu) {
                        m.classList.remove('show');
                        m.closest('.resource-card').classList.remove('menu-active');
                    }
                });
                
                // Toggle the current menu
                menu.classList.toggle('show');
                
                // Toggle active class on card to increase z-index
                if (menu.classList.contains('show')) {
                    card.classList.add('menu-active');
                    
                    // Check if menu is going off-screen
                    const menuRect = menu.getBoundingClientRect();
                    if (menuRect.right > window.innerWidth) {
                        // Position menu to the left if it's going off-screen
                        menu.style.right = 'auto';
                        menu.style.left = '0';
                    }
                    
                    // If menu would go below the viewport, position it above the button
                    if (menuRect.bottom > window.innerHeight) {
                        menu.style.top = 'auto';
                        menu.style.bottom = '100%';
                    }
                } else {
                    card.classList.remove('menu-active');
                }
            });
        });
        
        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.resource-menu');
            dropdowns.forEach(dropdown => {
                if (!dropdown.contains(e.target) && 
                    !e.target.classList.contains('resource-menu-btn') && 
                    !e.target.closest('.resource-menu-btn')) {
                    dropdown.classList.remove('show');
                    const card = dropdown.closest('.resource-card');
                    if (card) {
                        card.classList.remove('menu-active');
                    }
                }
            });
        });

        // Click on resource main area
        document.querySelectorAll('.resource-main').forEach(element => {
            element.addEventListener('click', function() {
                const card = this.closest('.resource-card');
                const isFolder = card.getAttribute('data-file-type') === 'folder';
                const resourceId = card.getAttribute('data-id');
                
                if (isFolder) {
                    window.location.href = 'resources.php?folder=' + resourceId;
                } else {
                    // Show preview for files
                    previewResource(resourceId);
                }
            });
        });

        // Preview file from menu
        document.querySelectorAll('.preview-file').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const resourceId = this.getAttribute('data-id');
                previewResource(resourceId);
                
                // Close any open context menus
                document.querySelectorAll('.resource-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
        });

        // Print functionality for preview modal
        printPreview.addEventListener('click', function() {
            // Get the file type from the preview content
            let fileType = '';
            const img = previewContent.querySelector('img');
            const object = previewContent.querySelector('object');
            const video = previewContent.querySelector('video');
            
            if (img) {
                fileType = 'image';
            } else if (object && object.data) {
                fileType = 'pdf';
            } else if (video) {
                fileType = 'video';
            }
            
            if (fileType === 'pdf') {
                // For PDFs, open in new window and print
                const pdfUrl = object.data;
                const printWindow = window.open(pdfUrl, '_blank');
                if (printWindow) {
                    printWindow.onload = function() {
                        printWindow.print();
                    };
                }
            } else if (fileType === 'image') {
                // For images, use printJS
                printJS({
                    printable: img.src,
                    type: 'image',
                    imageStyle: 'width:100%;max-width:800px;'
                });
            } else if (fileType === 'video') {
                // For videos, show message
                alert('Video printing is not supported. Please use the download option instead.');
            } else {
                // For other files, show message
                alert('Printing is not supported for this file type. Please use the download option instead.');
            }
        });

        // Function to preview a resource
        function previewResource(resourceId) {
            fetch('get_resource.php?resource_id=' + resourceId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    previewTitle.textContent = data.title;
                    previewContent.innerHTML = '';
                    
                    // Fix the file path to properly point to the resources directory
                    const filePath = 'resources/' + data.file_name;
                    
                    // Update download button to force download
                    downloadPreview.onclick = function(e) {
                        e.preventDefault();
                        const a = document.createElement('a');
                        a.href = filePath;
                        a.download = data.file_name; // This forces the download
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    };

                    const fileExtension = data.file_name.split('.').pop().toLowerCase();
                    
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                        const img = document.createElement('img');
                        img.src = filePath;
                        img.alt = data.title;
                        img.className = 'max-w-full max-h-[70vh]';
                        previewContent.appendChild(img);
                    } else if (fileExtension === 'pdf') {
                        const object = document.createElement('object');
                        object.data = filePath + '#toolbar=0&navpanes=0';
                        object.type = 'application/pdf';
                        object.className = 'w-full h-[70vh]';
                        object.innerHTML = '<p>Your browser does not support PDFs. Please download the PDF to view it.</p>';
                        previewContent.appendChild(object);
                    } else if (['mp4', 'webm', 'ogg'].includes(fileExtension)) {
                        const video = document.createElement('video');
                        video.src = filePath;
                        video.controls = true;
                        video.className = 'max-w-full max-h-[70vh]';
                        previewContent.appendChild(video);
                    } else {
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-file text-6xl text-gray-400 mb-4';
                        
                        const message = document.createElement('p');
                        message.textContent = 'Preview not available for this file type. Please download the file to view it.';
                        message.className = 'text-gray-600';
                        
                        previewContent.appendChild(icon);
                        previewContent.appendChild(message);
                    }

                    previewModal.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load resource details: ' + error.message);
                });
        }
    });
    </script>
</body>
</html>