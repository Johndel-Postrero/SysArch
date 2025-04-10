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

// Handle file/folder upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_folder'])) {
        // Create new folder
        $title = trim($_POST['folder_name']);
        $admin_id = $_SESSION['user_id'];
        $parent_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
        
        $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, is_folder, parent_id) VALUES (?, ?, 1, ?)");
        $stmt->bind_param("isi", $admin_id, $title, $parent_id);
        
        if (!$stmt->execute()) {
            echo "Database error: " . $stmt->error;
        } else {
            header("Location: ".$_SERVER['PHP_SELF'].($parent_id ? "?folder=$parent_id" : ""));
            exit();
        }
        $stmt->close();
    } elseif (isset($_FILES['file_upload'])) {
        // Handle file upload
        $title = pathinfo($_FILES['file_upload']['name'], PATHINFO_FILENAME);
        $file_name = time().'_'.basename($_FILES['file_upload']['name']);
        $file_size = $_FILES['file_upload']['size'];
        $file_type = $_FILES['file_upload']['type'];
        $admin_id = $_SESSION['user_id'];
        $parent_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
        
        $target_dir = __DIR__ . '/../public/resources/';
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, file_name, file_size, file_type, is_folder, parent_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("issisi", $admin_id, $title, $file_name, $file_size, $file_type, $parent_id);
            
            if (!$stmt->execute()) {
                echo "Database error: " . $stmt->error;
            } else {
                header("Location: ".$_SERVER['PHP_SELF'].($parent_id ? "?folder=$parent_id" : ""));
                exit();
            }
            $stmt->close();
        } else {
            echo "File upload failed. Error code: " . $_FILES['file_upload']['error'];
        }
    }
}

// Get current folder (if any)
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Fetch resources from the database - FIXED QUERY
$query = "
    SELECT r.*, u.firstname, u.lastname 
    FROM resources r
    JOIN users u ON r.admin_id = u.id
    WHERE r.parent_id " . ($current_folder ? "= $current_folder" : "IS NULL") . "
    ORDER BY r.is_folder DESC, r.uploaded_at DESC
";
$result = $conn->query($query);

if (!$result) {
    echo "Query error: " . $conn->error;
}

// Handle file/folder deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $admin_id = $_SESSION['user_id'];
    
    // Check if resource belongs to admin
    $check = $conn->prepare("SELECT id, file_name, is_folder FROM resources WHERE id = ? AND admin_id = ?");
    $check->bind_param("ii", $id, $admin_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $resource = $result->fetch_assoc();
        
        if ($resource['is_folder']) {
            // Delete folder and its contents recursively
            $conn->query("DELETE FROM resources WHERE id = $id OR parent_id = $id");
        } else {
            // Delete file
            $file_path = __DIR__ . '/../public/resources/' . $resource['file_name'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $conn->query("DELETE FROM resources WHERE id = $id");
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF'].(isset($_GET['folder']) ? "?folder=".$_GET['folder'] : ""));
    exit();
}


$result = $conn->query($query);

// Fetch breadcrumbs if in a folder
$breadcrumbs = [];
if ($current_folder) {
    $folder_id = $current_folder;
    while ($folder_id) {
        $folder_query = "SELECT id, title, parent_id FROM resources WHERE id = $folder_id";
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
    <title>Admin Resources</title>
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
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
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
                        <div class="flex items-center text-sm text-gray-600 mb-4">
                            <a href="resources.php" class="text-violet-600 hover:text-violet-800">
                                <i class="fas fa-home"></i>
                            </a>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <span class="breadcrumb-item">
                                    <a href="resources.php?folder=<?php echo $crumb['id']; ?>" 
                                       class="text-violet-600 hover:text-violet-800">
                                        <?php echo htmlspecialchars($crumb['title']); ?>
                                    </a>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="flex justify-between items-center mb-6">
                        <!-- Search -->
                        <div class="relative flex-1 mr-4">
                            <input class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" 
                                   placeholder="Search resources..." type="text" id="searchInput"/>
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        
                        <!-- Sort and Filter -->
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
                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="resourcesContainer">
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
                                
                                <div class="bg-white rounded-lg shadow p-4 resource-card transition-all duration-200 ease-in-out" 
                                     data-file-type="<?php echo $file_type; ?>"
                                     data-name="<?php echo htmlspecialchars(strtolower($row['title'])); ?>"
                                     data-date="<?php echo strtotime($row['uploaded_at']); ?>"
                                     data-size="<?php echo $row['file_size'] ?? 0; ?>">
                                    <div class="flex items-start h-full">
                                        <div class="flex-shrink-0">
                                            <i class="fas <?php echo $icon; ?> <?php echo $icon_class; ?> file-icon"></i>
                                        </div>
                                        <div class="ml-3 flex-1 flex flex-col h-full">
                                            <div class="flex-1">
                                                <?php if ($is_folder): ?>
                                                    <a href="resources.php?folder=<?php echo $row['id']; ?>" class="block">
                                                        <h3 class="font-bold text-lg text-gray-800 hover:text-violet-600"><?php echo htmlspecialchars($row['title']); ?></h3>
                                                    </a>
                                                <?php else: ?>
                                                    <h3 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($row['title']); ?></h3>
                                                <?php endif; ?>
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
                                            
                                            <div class="mt-3 flex space-x-2">
                                                <?php if ($is_folder): ?>
                                                    <a href="resources.php?folder=<?php echo $row['id']; ?>" 
                                                       class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-800 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                                                        <i class="fas fa-folder-open mr-1"></i>
                                                        Open
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?php echo $file_path; ?>" 
                                                       download="<?php echo htmlspecialchars($row['file_name']); ?>" 
                                                       class="inline-flex items-center px-3 py-1 bg-violet-100 text-violet-800 rounded-lg hover:bg-violet-200 transition-colors text-sm">
                                                        <i class="fas fa-download mr-1"></i>
                                                        Download
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow p-8 text-center">
                            <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600">This folder is empty</h3>
                            <p class="text-gray-500">No resources found in this location</p>
                        </div>
                    <?php endif; ?>
                </div>
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
        const addFolderBtn = document.getElementById('addFolderBtn');
        const uploadFileBtn = document.getElementById('uploadFileBtn');
        const folderModal = document.getElementById('folderModal');
        const uploadModal = document.getElementById('uploadModal');
        const closeFolderModal = document.getElementById('closeFolderModal');
        const closeUploadModal = document.getElementById('closeUploadModal');
        const cancelFolder = document.getElementById('cancelFolder');
        const cancelUpload = document.getElementById('cancelUpload');
        const deleteButtons = document.querySelectorAll('.delete-resource');

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

        // Folder Modal
        addFolderBtn.addEventListener('click', function() {
            folderModal.style.display = 'block';
        });

        closeFolderModal.addEventListener('click', function() {
            folderModal.style.display = 'none';
        });

        cancelFolder.addEventListener('click', function() {
            folderModal.style.display = 'none';
        });

    });


    </script>
</body>
</html>