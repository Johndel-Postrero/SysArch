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
<html>
<head>
    <title>Student Resources</title>
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
    <style>
        body {
            font-family: "Poppins-Regular";
            color: #333;
            font-size: 16px;
            margin: 0;
        }
        header {
            z-index: 1;
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
        .file-icon {
            font-size: 2rem;
            margin-right: 1rem;
        }
        .folder-icon { color: #f39c12; }
        .pdf-icon { color: #e74c3c; }
        .doc-icon { color: #3498db; }
        .video-icon { color: #9b59b6; }
        .file-icon { color: #7f8c8d; }
        .breadcrumb-item:after {
            content: '/';
            margin: 0 8px;
            color: #95a5a6;
        }
        .breadcrumb-item:last-child:after {
            content: '';
        }
        .resource-card {
            position: relative;
            z-index: 1;
        }
        .resource-card.menu-active {
            z-index: 50;
        }
        .resource-menu-btn {
            position: relative;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .resource-menu {
            position: absolute;
            top: calc(100% + 5px);
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 100;
            border-radius: 8px;
            display: none;
            padding: 4px 0;
        }
        .resource-menu a {
            padding: 8px 16px;
            display: block;
            text-decoration: none;
            color: #333;
        }
        .resource-menu a:hover {
            background-color: #f1f1f1;
        }
        .show { display: block; }
        .list-view .resource-card {
            flex-direction: row;
            align-items: center;
        }
        .list-view .resource-icon {
            margin-right: 16px;
        }
        .list-view .resource-details {
            flex: 1;
        }
        .list-view .resource-actions {
            margin-left: auto;
        }
        .preview-modal {
            max-width: 90%;
            width: 800px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col">
            <!-- Include Header -->
            <?php include 'header.php'; ?>
            
            <div class="flex-1 overflow-auto">
                <div class="p-6 max-w-5xl mx-auto w-full">
                    <!-- Breadcrumbs -->
                    <?php if (!empty($breadcrumbs)): ?>
                        <div class="flex items-center text-sm text-gray-600 mb-4 gap-2">
                            <a href="resources.php" class="text-violet-600 hover:text-violet-800">
                                <i class="fas fa-home"></i>
                            </a>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <span class="breadcrumb-item">
                                    <a href="resources.php?folder=<?php echo $crumb['resource_id']; ?>" 
                                       class="text-violet-600 hover:text-violet-800">
                                        <?php echo htmlspecialchars($crumb['title']); ?>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons (Simplified for students) -->
                    <div class="flex justify-between items-center mb-6">
                        <!-- Search -->
                        <div class="relative flex-1 mr-4">
                            <input class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" 
                                   placeholder="Search resources..." type="text" id="searchInput"/>
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        
                        <!-- View Toggle -->
                        <div class="flex space-x-2 mr-4">
                            <button id="gridViewBtn" class="p-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button id="listViewBtn" class="p-2 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-300">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                        
                        <!-- Sort and Filter (Only these remain) -->
                        <div class="flex space-x-3">
                            <!-- Sort Dropdown -->
                            <div class="relative">
                                <button id="sortButton" class="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <div id="sortDropdown" class="hidden absolute right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 w-48 z-20">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="name-asc"><i class="fas fa-sort-alpha-down mr-2"></i>Name A-Z</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="name-desc"><i class="fas fa-sort-alpha-up mr-2"></i>Name Z-A</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="date-newest"><i class="fas fa-arrow-down mr-2"></i>Newest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="date-oldest"><i class="fas fa-arrow-up mr-2"></i>Oldest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="size-largest"><i class="fas fa-sort-amount-down mr-2"></i>Largest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="size-smallest"><i class="fas fa-sort-amount-up mr-2"></i>Smallest</a>
                                </div>
                            </div>
                            
                            <!-- Filter Dropdown -->
                            <div class="relative">
                                <button id="filterButton" class="flex items-center space-x-2 bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                                    </svg>
                                    <span>Filter</span>
                                </button>
                                <div id="filterDropdown" class="hidden absolute right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 w-48 z-20">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="all"><i class="fas fa-layer-group mr-2"></i>All Items</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="folder"><i class="fas fa-folder mr-2"></i>Folders</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="pdf"><i class="fas fa-file-pdf mr-2"></i>PDFs</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="doc"><i class="fas fa-file-word mr-2"></i>Documents</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="video"><i class="fas fa-file-video mr-2"></i>Videos</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-filter="other"><i class="fas fa-file-alt mr-2"></i>Other Files</a>
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
                                
                                <div class="resource-card bg-white rounded-lg shadow p-4 relative" 
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
                                                <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($row['title']); ?></h3>
                                            </div>
                                            
                                            <div class="mt-3 text-xs text-gray-500">
                                                <div class="flex items-center">
                                                    <span><?php echo date("M j, Y", strtotime($row['uploaded_at'])); ?></span>
                                                    <?php if (!$is_folder): ?>
                                                        <span class="mx-2">•</span>
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
                            <div class="bg-white rounded-lg shadow p-8 text-center col-span-full">
                                <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-xl font-semibold text-gray-600">This folder is empty</h3>
                                <p class="text-gray-500">No resources found in this location</p>
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
            <div class="flex justify-between items-center mb-4">
                <h3 id="previewTitle" class="text-lg font-semibold"></h3>
                <div>
                    <button id="printPreview" class="text-gray-500 hover:text-gray-700 mr-3">
                        <i class="fas fa-print"></i>
                    </button>
                    <button id="downloadPreview" class="text-gray-500 hover:text-gray-700 mr-3">
                        <i class="fas fa-download"></i>
                    </button>
                    <button id="closePreviewModal" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div id="previewContent" class="flex justify-center items-center min-h-[400px]">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>

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

        // Toggle dropdown menus
        sortButton.addEventListener('click', function (e) {
            e.stopPropagation();
            sortDropdown.classList.toggle('hidden');
            filterDropdown.classList.add('hidden');
        });

        filterButton.addEventListener('click', function (e) {
            e.stopPropagation();
            filterDropdown.classList.toggle('hidden');
            sortDropdown.classList.add('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!sortButton.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortDropdown.classList.add('hidden');
            }
            if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.classList.add('hidden');
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
                sortDropdown.classList.add('hidden');
                
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
                filterDropdown.classList.add('hidden');
                
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
            gridViewBtn.classList.remove('bg-gray-100', 'text-gray-500');
            gridViewBtn.classList.add('bg-gray-200', 'text-gray-700');
            listViewBtn.classList.remove('bg-gray-200', 'text-gray-700');
            listViewBtn.classList.add('bg-gray-100', 'text-gray-500');
        });

        listViewBtn.addEventListener('click', function() {
            resourcesContainer.classList.add('list-view');
            resourcesContainer.className = 'grid grid-cols-1 gap-2 list-view';
            listViewBtn.classList.remove('bg-gray-100', 'text-gray-500');
            listViewBtn.classList.add('bg-gray-200', 'text-gray-700');
            gridViewBtn.classList.remove('bg-gray-200', 'text-gray-700');
            gridViewBtn.classList.add('bg-gray-100', 'text-gray-500');
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
            // Get the preview content
            const previewContent = document.getElementById('previewContent').cloneNode(true);
            
            // Create a temporary container
            const tempDiv = document.createElement('div');
            tempDiv.style.width = '100%';
            tempDiv.style.maxWidth = '800px';
            tempDiv.style.margin = '0 auto';
            
            // Add the preview content
            tempDiv.appendChild(previewContent);
            
            // Print using printJS
            printJS({
                printable: tempDiv.innerHTML,
                type: 'raw-html',
                style: `
                    @media print {
                        body { margin: 20px; }
                        img, video, object { 
                            max-width: 100% !important; 
                            height: auto !important;
                        }
                        object { 
                            width: 100% !important;
                            height: 80vh !important;
                        }
                        .no-preview { 
                            text-align: center;
                            padding: 40px 0;
                        }
                        .no-preview i {
                            font-size: 48px;
                            margin-bottom: 20px;
                        }
                        .no-preview p {
                            font-size: 16px;
                        }
                    }
                `,
                onLoadingEnd: function() {
                    tempDiv.remove();
                }
            });
        });

        // Function to preview a resource
        function previewResource(resourceId) {
            fetch('get_resources.php?id=' + resourceId)
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