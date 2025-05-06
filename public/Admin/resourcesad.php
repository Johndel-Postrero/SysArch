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
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

// Database connection
require __DIR__ . '/../../config/db.php';

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
// Update the file upload handling section to this:
} elseif (isset($_FILES['file_upload'])) {
    // Handle multiple file uploads
    $uploaded_files = $_FILES['file_upload'];
    $admin_id = $_SESSION['user_id'];
    $parent_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
    $target_dir = __DIR__ . '/../resources/';
    $success_count = 0;
    $errors = [];
    
    // Create target directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    foreach ($uploaded_files['name'] as $key => $name) {
        if ($uploaded_files['error'][$key] !== UPLOAD_ERR_OK) {
            $errors[] = "File {$name} upload failed with error code: " . $uploaded_files['error'][$key];
            continue;
        }
        
        $title = pathinfo($name, PATHINFO_FILENAME);
        $file_name = time().'_'.basename($name);
        $file_size = $uploaded_files['size'][$key];
        $file_type = $uploaded_files['type'][$key];
        $tmp_name = $uploaded_files['tmp_name'][$key];
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($tmp_name, $target_file)) {
            $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, file_name, file_size, file_type, is_folder, parent_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("issisi", $admin_id, $title, $file_name, $file_size, $file_type, $parent_id);
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $errors[] = "Database error for file {$name}: " . $stmt->error;
                // Remove the uploaded file if database insertion failed
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to move uploaded file {$name}";
        }
    }
    
    if ($success_count > 0) {
        if (!empty($errors)) {
            // Store errors in session to display after redirect
            $_SESSION['upload_errors'] = $errors;
        }
        header("Location: ".$_SERVER['PHP_SELF'].($parent_id ? "?folder=$parent_id" : ""));
        exit();
    } else {
        echo "File upload failed. Errors:<br>" . implode("<br>", $errors);
    }


    } elseif (isset($_POST['process_folder_upload'])) {
        // Handle folder upload processing
        $admin_id = $_SESSION['user_id'];
        $parent_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
        $folder_structure = json_decode($_POST['folder_structure'], true);
        $base_folder_name = $_POST['base_folder_name'];
        
        // First, create the base folder
        $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, is_folder, parent_id) VALUES (?, ?, 1, ?)");
        $stmt->bind_param("isi", $admin_id, $base_folder_name, $parent_id);
        
        if (!$stmt->execute()) {
            echo "Error creating base folder: " . $stmt->error;
            exit;
        }
        
        $base_folder_id = $conn->insert_id;
        $stmt->close();
        
        // Process the folder structure recursively
        processFolderStructure($folder_structure, $base_folder_id, $admin_id, $conn);
        
        header("Location: ".$_SERVER['PHP_SELF'].($parent_id ? "?folder=$parent_id" : ""));
        exit();
    }
    elseif (isset($_POST['rename_resource'])) {
        // Handle rename
        $id = (int)$_POST['resource_id'];
        $new_name = trim($_POST['new_name']);
        $admin_id = $_SESSION['user_id'];
        
        // Verify ownership
        $check = $conn->prepare("SELECT resource_id FROM resources WHERE resource_id = ? AND admin_id = ?");
        $check->bind_param("ii", $id, $admin_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE resources SET title = ? WHERE resource_id = ?");
            $stmt->bind_param("si", $new_name, $id);
            $stmt->execute();
            $stmt->close();
        }
        $check->close();
        
        header("Location: ".$_SERVER['PHP_SELF'].(isset($_GET['folder']) ? "?folder=".$_GET['folder'] : ""));
        exit();
    }
}

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

// Handle file/folder deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $admin_id = $_SESSION['user_id'];
    
    // Check if resource belongs to admin
    $check = $conn->prepare("SELECT resource_id, file_name, is_folder FROM resources WHERE resource_id = ? AND admin_id = ?");
    $check->bind_param("ii", $id, $admin_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $resource = $result->fetch_assoc();
        
        if ($resource['is_folder']) {
            // First get all files in this folder and subfolders to delete from filesystem
            $files_to_delete = [];
            $query = "SELECT resource_id, file_name FROM resources WHERE (resource_id = ? OR parent_id = ?) AND is_folder = 0";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $id, $id);
            $stmt->execute();
            $file_result = $stmt->get_result();
            
            while ($file_row = $file_result->fetch_assoc()) {
                $files_to_delete[] = __DIR__ . '/../resources/' . $file_row['file_name'];
            }
            
            // Delete the folder and its contents from database
            $delete_stmt = $conn->prepare("DELETE FROM resources WHERE resource_id = ? OR parent_id = ?");
            $delete_stmt->bind_param("ii", $id, $id);
            $delete_success = $delete_stmt->execute();
            
            if ($delete_success) {
                // Now delete all the files
                foreach ($files_to_delete as $file_path) {
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            } else {
                $_SESSION['error'] = "Failed to delete folder from database";
            }
        } else {
            // Delete single file
            $file_path = __DIR__ . '/../resources/' . $resource['file_name'];
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    $_SESSION['error'] = "Failed to delete file from filesystem";
                }
            }
            $delete_stmt = $conn->prepare("DELETE FROM resources WHERE resource_id = ?");
            $delete_stmt->bind_param("i", $id);
            if (!$delete_stmt->execute()) {
                $_SESSION['error'] = "Failed to delete file record from database";
            }
        }
    }
    
    header("Location: ".$_SERVER['PHP_SELF'].(isset($_GET['folder']) ? "?folder=".$_GET['folder'] : ""));
    exit();
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
function processFolderStructure($structure, $parent_id, $admin_id, $conn) {
    foreach ($structure as $item) {
        if ($item['type'] === 'folder') {
            // Create subfolder
            $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, is_folder, parent_id) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("isi", $admin_id, $item['name'], $parent_id);
            
            if (!$stmt->execute()) {
                echo "Error creating subfolder: " . $stmt->error;
                continue;
            }
            
            $folder_id = $conn->insert_id;
            $stmt->close();
            
            // Process children recursively
            if (!empty($item['children'])) {
                processFolderStructure($item['children'], $folder_id, $admin_id, $conn);
            }
        } else if ($item['type'] === 'file') {
            // Create file record
            $file_name = time() . '_' . basename($item['name']);
            $title = pathinfo($item['name'], PATHINFO_FILENAME);
            $file_size = $item['size'];
            $file_type = $item['mime'];
            
            // Make sure the target directory exists
            $target_dir = __DIR__ . '/../resources/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // Save the file content
            $target_file = $target_dir . $file_name;
            file_put_contents($target_file, base64_decode($item['content']));
            
            // Create database record
            $stmt = $conn->prepare("INSERT INTO resources (admin_id, title, file_name, file_size, file_type, is_folder, parent_id) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("issisi", $admin_id, $title, $file_name, $file_size, $file_type, $parent_id);
            
            if (!$stmt->execute()) {
                echo "Error creating file record: " . $stmt->error;
            }
            
            $stmt->close();
        }
    }
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
        z-index: 1; /* Base z-index */
    }
    
    /* When menu is open, increase the card's z-index */
    .resource-card.menu-active {
        z-index: 50; /* Higher than other cards */
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
            overflow-y: auto; /* Enable scrolling */
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
        top: calc(100% + 5px); /* Position below with a small gap */
        right: 0;
        background-color: white;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 100; /* Higher than the card */
        border-radius: 8px;
        display: none; /* Hidden by default */
        padding: 4px 0;
    }
    
    /* Make sure this remains visible when positioned */
    .resource-menu.show {
        display: block;
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
        .drag-over {
            border: 2px dashed #3b82f6;
            background-color: rgba(59, 130, 246, 0.1);
        }
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
        /* Add this to your existing styles */
/* Add this to your existing styles */
#filePreview {
    max-height: 400px;
    overflow-y: auto;
}

#filePreview .preview-item {
    transition: all 0.2s ease;
}

#filePreview .preview-item:hover {
    background-color: #f8f9fa;
}

#filePreview .remove-btn {
    opacity: 0;
    transition: opacity 0.2s ease;
}

#filePreview .preview-item:hover .remove-btn {
    opacity: 1;
}

#filePreview img {
    max-width: 100%;
    max-height: 300px;
    object-fit: contain;
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
            
            <div class="flex-1 overflow-auto">
                <div class="p-6 max-w-5xl mx-auto w-full">
                    <!-- Breadcrumbs -->
                    <?php if (!empty($breadcrumbs)): ?>
                        <div class="flex items-center text-sm text-gray-600 mb-4 gap-2">
                            <a href="resourcesad.php" class="text-violet-600 hover:text-violet-800">
                                <i class="fas fa-home"></i>
                            </a>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <span class="breadcrumb-item">
                                    <a href="resourcesad.php?folder=<?php echo $crumb['resource_id']; ?>" 
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
                        
                        <!-- View Toggle -->
                        <div class="flex space-x-2 mr-4">
                            <button id="gridViewBtn" class="p-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button id="listViewBtn" class="p-2 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-300">
                                <i class="fas fa-list"></i>
                            </button>
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
                            
                            <!-- Combined Add Button with Dropdown -->
                            <div class="relative">
                                <button id="addButton" class="flex items-center space-x-2 bg-[#002044] hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-plus"></i>
                                    <span>New</span>
                                    <i class="fas fa-chevron-down ml-1 text-sm"></i>
                                </button>
                                <div id="addDropdown" class="hidden absolute right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 w-48 z-20">
                                    <a href="#" id="addFolderBtn" class="block px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-folder-plus mr-2"></i>New Folder
                                    </a>
                                    <a href="#" id="uploadFileBtn" class="block px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-upload mr-2"></i>File Upload
                                    </a>
                                    <a href="#" id="uploadFolderBtn" class="block px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-folder mr-2"></i>Folder Upload
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resources Grid -->
                    <div id="resourcesContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $file_path = __DIR__ . '/../resources/' . $row['file_name'];
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
                                                <a href="resourcesad.php?folder=<?php echo $row['resource_id']; ?>"><i class="fas fa-folder-open mr-2"></i>Open</a>
                                            <?php else: ?>
                                                <a href="#" class="preview-file" data-id="<?php echo $row['resource_id']; ?>"><i class="fas fa-eye mr-2"></i>Preview</a>
                                                <a href="../resources/<?php echo htmlspecialchars($row['file_name']); ?>" download="<?php echo htmlspecialchars($row['file_name']); ?>"><i class="fas fa-download mr-2"></i>Download</a>
                                            <?php endif; ?>
                                            <a href="#" class="rename-resource" data-id="<?php echo $row['resource_id']; ?>" data-name="<?php echo htmlspecialchars($row['title']); ?>"><i class="fas fa-edit mr-2"></i>Rename</a>
                                            <a href="#" class="delete-resource" data-id="<?php echo $row['resource_id']; ?>"><i class="fas fa-trash mr-2"></i>Delete</a>
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

    <!-- Add Folder Modal -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Create New Folder</h3>
                <button id="closeFolderModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="folderForm" method="POST" action="">
                <div class="mb-4">
                    <label for="folder_name" class="block text-sm font-medium text-gray-700 mb-1">Folder Name</label>
                    <input type="text" id="folder_name" name="folder_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancelFolder" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="create_folder" class="px-4 py-2 bg-violet-600 text-white rounded-md hover:bg-violet-700">Create</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Upload File</h3>
            <button id="closeUploadModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="uploadForm" method="POST" action="" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select File</label>
                <div id="dropZone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center w-full">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600 justify-center">
                            <label for="file_upload" class="relative cursor-pointer bg-white rounded-md font-medium text-violet-600 hover:text-violet-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-violet-500">
                                <span>Upload a file</span>
                                <input id="file_upload" name="file_upload[]" type="file" class="sr-only" accept="image/*,.pdf,.doc,.docx,.txt,.rtf,.odt,video/*" multiple>
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">PDF, DOC, MP4, JPG up to 10MB</p>
                    </div>
                </div>
                
                <!-- File previews container - moved outside the drop zone -->
                <div id="filePreviewContainer" class="mt-4 hidden">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Selected Files:</h4>
                    <div id="filePreview" class="space-y-2 max-h-60 overflow-y-auto p-2 border border-gray-200 rounded-md"></div>
                </div>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancelUpload" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Upload</button>
            </div>
        </form>
    </div>
</div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal ">
        <div class="modal-content" style="max-width: 800px; width: 90%;">
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

    <!-- Rename Modal -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Rename Resource</h3>
                <button id="closeRenameModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="renameForm" method="POST" action="">
                <input type="hidden" id="resource_id" name="resource_id">
                <div class="mb-4">
                    <label for="new_name" class="block text-sm font-medium text-gray-700 mb-1">New Name</label>
                    <input type="text" id="new_name" name="new_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-violet-500" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancelRename" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="rename_resource" class="px-4 py-2 bg-violet-600 text-white rounded-md hover:bg-violet-700">Rename</button>
                </div>
            </form>
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
        const addButton = document.getElementById('addButton');
        const addDropdown = document.getElementById('addDropdown');
        const addFolderBtn = document.getElementById('addFolderBtn');
        const uploadFileBtn = document.getElementById('uploadFileBtn');
        const uploadFolderBtn = document.getElementById('uploadFolderBtn');
        const folderModal = document.getElementById('folderModal');
        const uploadModal = document.getElementById('uploadModal');
        const previewModal = document.getElementById('previewModal');
        const renameModal = document.getElementById('renameModal');
        const closeFolderModal = document.getElementById('closeFolderModal');
        const closeUploadModal = document.getElementById('closeUploadModal');
        const closePreviewModal = document.getElementById('closePreviewModal');
        const closeRenameModal = document.getElementById('closeRenameModal');
        const cancelFolder = document.getElementById('cancelFolder');
        const cancelUpload = document.getElementById('cancelUpload');
        const cancelRename = document.getElementById('cancelRename');
        const gridViewBtn = document.getElementById('gridViewBtn');
        const listViewBtn = document.getElementById('listViewBtn');
        const dropZone = document.getElementById('dropZone');
        const fileUpload = document.getElementById('file_upload');
        const filePreview = document.getElementById('filePreview');
        const uploadArea = document.getElementById('uploadArea');
        const imagePreview = document.getElementById('imagePreview');
        const documentPreview = document.getElementById('documentPreview');
        const documentIcon = document.getElementById('documentIcon');
        const documentName = document.getElementById('documentName');
        const documentSize = document.getElementById('documentSize');
        const videoPreview = document.getElementById('videoPreview');
        const previewTitle = document.getElementById('previewTitle');
        const previewContent = document.getElementById('previewContent');
        const printPreview = document.getElementById('printPreview');
        const downloadPreview = document.getElementById('downloadPreview');
        const renameForm = document.getElementById('renameForm');
        const resourceIdInput = document.getElementById('resource_id');
        const newNameInput = document.getElementById('new_name');

        // Toggle dropdown menus
        sortButton.addEventListener('click', function (e) {
            e.stopPropagation();
            sortDropdown.classList.toggle('hidden');
            filterDropdown.classList.add('hidden');
            addDropdown.classList.add('hidden');
        });

        filterButton.addEventListener('click', function (e) {
            e.stopPropagation();
            filterDropdown.classList.toggle('hidden');
            sortDropdown.classList.add('hidden');
            addDropdown.classList.add('hidden');
        });

        addButton.addEventListener('click', function (e) {
            e.stopPropagation();
            addDropdown.classList.toggle('hidden');
            sortDropdown.classList.add('hidden');
            filterDropdown.classList.add('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function (e) {
            if (!sortButton.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortDropdown.classList.add('hidden');
            }
            if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                filterDropdown.classList.add('hidden');
            }
            if (!addButton.contains(e.target) && !addDropdown.contains(e.target)) {
                addDropdown.classList.add('hidden');
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

        // Folder Modal
        addFolderBtn.addEventListener('click', function() {
            folderModal.style.display = 'block';
            addDropdown.classList.add('hidden');
        });

        closeFolderModal.addEventListener('click', function() {
            folderModal.style.display = 'none';
        });

        cancelFolder.addEventListener('click', function() {
            folderModal.style.display = 'none';
        });

        // Upload Modal
        uploadFileBtn.addEventListener('click', function() {
            uploadModal.style.display = 'block';
            addDropdown.classList.add('hidden');
        });

        closeUploadModal.addEventListener('click', function() {
            uploadModal.style.display = 'none';
        });

        cancelUpload.addEventListener('click', function() {
            uploadModal.style.display = 'none';
        });

        // Preview Modal
        closePreviewModal.addEventListener('click', function() {
            previewModal.style.display = 'none';
        });

        // Rename Modal
        closeRenameModal.addEventListener('click', function() {
            renameModal.style.display = 'none';
        });

        cancelRename.addEventListener('click', function() {
            renameModal.style.display = 'none';
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === folderModal) {
                folderModal.style.display = 'none';
            }
            if (event.target === uploadModal) {
                uploadModal.style.display = 'none';
            }
            if (event.target === previewModal) {
                previewModal.style.display = 'none';
            }
            if (event.target === renameModal) {
                renameModal.style.display = 'none';
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
    
    // Also modify the document click handler to remove menu-active class
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
                    window.location.href = 'resourcesad.php?folder=' + resourceId;
                } else {
                    // Show preview for files
                    previewResource(resourceId);
                }
            });
        });

        // Delete resource
        document.querySelectorAll('.delete-resource').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const resourceId = this.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this resource?')) {
                    window.location.href = 'resourcesad.php?delete=' + resourceId + 
                        (<?php echo $current_folder ? "'&folder=$current_folder'" : "''"; ?>);
                }
            });
        });

        // Rename resource
        document.querySelectorAll('.rename-resource').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const resourceId = this.getAttribute('data-id');
                const currentName = this.getAttribute('data-name');
                
                resourceIdInput.value = resourceId;
                newNameInput.value = currentName;
                renameModal.style.display = 'block';
                
                // Close any open context menus
                document.querySelectorAll('.resource-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
        });

        // Preview file
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

        // Print preview
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

        // Download from preview
        downloadPreview.addEventListener('click', function() {
            const downloadLink = this.getAttribute('data-url');
            if (downloadLink) {
                window.location.href = downloadLink;
            }
        });

        // Function to preview a resource
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
            const filePath = '../resources/' + data.file_name;
            
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

    // File upload preview functionality
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    
    fileUpload.addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        if (files.length === 0) {
            filePreviewContainer.classList.add('hidden');
            return;
        }

        // Show preview container
        filePreviewContainer.classList.remove('hidden');
        
        // Clear previous previews
        filePreview.innerHTML = '';
        
        // Create preview items for each file
        files.forEach((file, index) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'flex items-center justify-between p-2 bg-gray-50 rounded hover:bg-gray-100';
            previewItem.dataset.index = index;
            
            // File icon and info
            const fileInfo = document.createElement('div');
            fileInfo.className = 'flex items-center flex-1 min-w-0';
            
            // File icon based on type
            const icon = document.createElement('i');
            icon.className = 'fas fa-file text-lg mr-3 text-gray-500';
            
            if (file.type.match('image.*')) {
                icon.className = 'fas fa-file-image text-lg mr-3 text-blue-500';
            } else if (file.type.match('video.*')) {
                icon.className = 'fas fa-file-video text-lg mr-3 text-red-500';
            } else if (file.name.match(/\.pdf$/i)) {
                icon.className = 'fas fa-file-pdf text-lg mr-3 text-red-500';
            } else if (file.name.match(/\.(docx?|odt|rtf)$/i)) {
                icon.className = 'fas fa-file-word text-lg mr-3 text-blue-500';
            } else if (file.name.match(/\.(xlsx?|csv)$/i)) {
                icon.className = 'fas fa-file-excel text-lg mr-3 text-green-500';
            } else if (file.name.match(/\.(pptx?|odp)$/i)) {
                icon.className = 'fas fa-file-powerpoint text-lg mr-3 text-orange-500';
            }
            
            // File name and size
            const fileDetails = document.createElement('div');
            fileDetails.className = 'min-w-0';
            fileDetails.innerHTML = `
                <div class="font-medium truncate">${file.name}</div>
                <div class="text-xs text-gray-500">${formatFileSize(file.size)}</div>
            `;
            
            fileInfo.appendChild(icon);
            fileInfo.appendChild(fileDetails);
            
            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.className = 'ml-2 text-red-500 hover:text-red-700';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.title = 'Remove file';
            removeBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Remove the file from the input
                const newFiles = Array.from(fileUpload.files).filter((_, i) => i !== parseInt(previewItem.dataset.index));
                const dataTransfer = new DataTransfer();
                newFiles.forEach(file => dataTransfer.items.add(file));
                fileUpload.files = dataTransfer.files;
                
                // Remove the preview item
                previewItem.remove();
                
                // If no files left, hide the preview container
                if (newFiles.length === 0) {
                    filePreviewContainer.classList.add('hidden');
                }
                
                // Trigger change event to update the UI
                const event = new Event('change');
                fileUpload.dispatchEvent(event);
            };
            
            previewItem.appendChild(fileInfo);
            previewItem.appendChild(removeBtn);
            filePreview.appendChild(previewItem);
        });
    });

    // Reset preview when modal is closed
    [closeUploadModal, cancelUpload].forEach(button => {
        button.addEventListener('click', function() {
            filePreviewContainer.classList.add('hidden');
            filePreview.innerHTML = '';
            fileUpload.value = '';
            
            // Also reset the file input
            const newInput = document.createElement('input');
            newInput.type = 'file';
            newInput.id = 'file_upload';
            newInput.name = 'file_upload[]';
            newInput.className = 'sr-only';
            newInput.multiple = true;
            newInput.accept = 'image/*,.pdf,.doc,.docx,.txt,.rtf,.odt,video/*';
            
            fileUpload.replaceWith(newInput);
            fileUpload = newInput;
            fileUpload.addEventListener('change', handleFileUploadChange);
        });
    });

        // Drag and drop functionality
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.classList.add('drag-over');
        }

        function unhighlight() {
            dropZone.classList.remove('drag-over');
        }

// Update the drag and drop handler to trigger the change event
dropZone.addEventListener('drop', function(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        // Create a new DataTransfer to hold the files
        const dataTransfer = new DataTransfer();
        
        // Add all files to the DataTransfer
        Array.from(files).forEach(file => dataTransfer.items.add(file));
        
        // Assign the files to the input
        fileUpload.files = dataTransfer.files;
        
        // Trigger the change event to show previews
        const event = new Event('change');
        fileUpload.dispatchEvent(event);
    }
});

        function handleFilePreview(file) {
            // Show preview container and hide upload area
            filePreview.classList.remove('hidden');
            uploadArea.classList.add('hidden');
            
            // Hide all previews first
            imagePreview.classList.add('hidden');
            documentPreview.classList.add('hidden');
            videoPreview.classList.add('hidden');
            
            // Check file type and show appropriate preview
            if (file.type.match('image.*')) {
                // Image preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            } else if (file.type.match('video.*')) {
                // Video preview
                videoPreview.src = URL.createObjectURL(file);
                videoPreview.classList.remove('hidden');
            } else {
                // Document preview
                // Set appropriate icon based on file type
                if (file.name.match(/\.pdf$/i)) {
                    documentIcon.className = 'fas fa-file-pdf text-4xl mb-2 text-red-500';
                } else if (file.name.match(/\.(docx?|odt|rtf)$/i)) {
                    documentIcon.className = 'fas fa-file-word text-4xl mb-2 text-blue-500';
                } else if (file.name.match(/\.(xlsx?|csv)$/i)) {
                    documentIcon.className = 'fas fa-file-excel text-4xl mb-2 text-green-500';
                } else if (file.name.match(/\.(pptx?|odp)$/i)) {
                    documentIcon.className = 'fas fa-file-powerpoint text-4xl mb-2 text-orange-500';
                } else if (file.name.match(/\.txt$/i)) {
                    documentIcon.className = 'fas fa-file-alt text-4xl mb-2 text-gray-500';
                } else {
                    documentIcon.className = 'fas fa-file text-4xl mb-2 text-gray-500';
                }
                
                documentName.textContent = file.name;
                documentSize.textContent = formatFileSize(file.size);
                documentPreview.classList.remove('hidden');
            }
        }

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Reset preview when modal is closed
        [closeUploadModal, cancelUpload].forEach(button => {
            button.addEventListener('click', function() {
                filePreview.classList.add('hidden');
                uploadArea.classList.remove('hidden');
                fileUpload.value = '';
            });
        });
    });
    </script>
    <script>
        // Add this code inside your document.addEventListener('DOMContentLoaded', function () {...}) section

// Folder upload functionality
const folderUploadModal = document.createElement('div');
folderUploadModal.id = 'folderUploadModal';
folderUploadModal.className = 'modal';
folderUploadModal.innerHTML = `
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Upload Folder</h3>
            <button id="closeFolderUploadModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-4">
            <div id="folderDropZone" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md bg-gray-50">
                <div class="space-y-1 text-center w-full">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex text-sm text-gray-600 justify-center">
                        <label for="folder_upload" class="relative cursor-pointer bg-white rounded-md font-medium text-violet-600 hover:text-violet-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-violet-500">
                            <span>Choose folder</span>
                            <input id="folder_upload" type="file" webkitdirectory directory multiple class="sr-only">
                        </label>
                    </div>
                    <p class="text-xs text-gray-500">Select a folder to upload its entire structure</p>
                </div>
            </div>
            <div id="folderSummary" class="mt-4 hidden">
                <div class="bg-gray-100 p-3 rounded">
                    <div class="font-semibold mb-2" id="folderName"></div>
                    <div class="text-sm text-gray-600">
                        <span id="folderFileCount"></span> files,
                        <span id="folderSubdirCount"></span> folders,
                        <span id="folderTotalSize"></span> total
                    </div>
                </div>
            </div>
        </div>
        <form id="folderUploadForm" action="" method="POST">
            <input type="hidden" name="process_folder_upload" value="1">
            <input type="hidden" name="folder_structure" id="folder_structure">
            <input type="hidden" name="base_folder_name" id="base_folder_name">
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancelFolderUpload" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</button>
                <button type="submit" id="submitFolderUpload" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Upload Folder</button>
            </div>
        </form>
    </div>
`;
document.body.appendChild(folderUploadModal);

// Folder upload button event listener
uploadFolderBtn.addEventListener('click', function() {
    folderUploadModal.style.display = 'block';
    addDropdown.classList.add('hidden');
});

// Close folder upload modal
document.getElementById('closeFolderUploadModal').addEventListener('click', function() {
    folderUploadModal.style.display = 'none';
});

document.getElementById('cancelFolderUpload').addEventListener('click', function() {
    folderUploadModal.style.display = 'none';
});

// Close folder upload modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === folderUploadModal) {
        folderUploadModal.style.display = 'none';
    }
});

// Helper function to format file size (same as the one used for files)
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle folder selection
const folderUploadInput = document.getElementById('folder_upload');
const folderSummary = document.getElementById('folderSummary');
const folderName = document.getElementById('folderName');
const folderFileCount = document.getElementById('folderFileCount');
const folderSubdirCount = document.getElementById('folderSubdirCount');
const folderTotalSize = document.getElementById('folderTotalSize');
const folderStructureInput = document.getElementById('folder_structure');
const baseFolderNameInput = document.getElementById('base_folder_name');
const submitFolderUpload = document.getElementById('submitFolderUpload');

// Disable submit button initially
submitFolderUpload.disabled = true;

folderUploadInput.addEventListener('change', async function(e) {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;
    
    // Reset form
    submitFolderUpload.disabled = true;
    folderSummary.classList.add('hidden');
    
    // Process the folder structure
    const structure = {};
    let totalSize = 0;
    let fileCount = 0;
    let subdirCount = 0;
    let baseFolder = '';
    
    // Get the base folder name (first segment of the path)
    if (files.length > 0 && files[0].webkitRelativePath) {
        baseFolder = files[0].webkitRelativePath.split('/')[0];
    }
    
    // First pass: build the structure and count files/folders
    files.forEach(file => {
        const path = file.webkitRelativePath;
        const pathParts = path.split('/');
        
        // Skip the base folder itself (which might appear as an entry)
        if (pathParts.length <= 1) return;
        
        let currentLevel = structure;
        
        // Navigate the path, creating objects for folders
        for (let i = 1; i < pathParts.length - 1; i++) {
            const folderName = pathParts[i];
            
            if (!currentLevel[folderName]) {
                currentLevel[folderName] = { isFolder: true };
                if (i > 1) subdirCount++; // Only count subdirectories, not the base folder
            }
            
            currentLevel = currentLevel[folderName];
        }
        
        // Add the file at the correct level
        const fileName = pathParts[pathParts.length - 1];
        currentLevel[fileName] = {
            isFile: true,
            size: file.size,
            type: file.type,
            file: file  // Store the actual file object for processing
        };
        
        totalSize += file.size;
        fileCount++;
    });
    
    // Second pass: process files and build the structure for submission
    async function processStructure(obj) {
        const result = [];
        
        for (const [name, value] of Object.entries(obj)) {
            if (value.isFile) {
                // Process file
                try {
                    const base64content = await readFileAsBase64(value.file);
                    result.push({
                        type: 'file',
                        name: name,
                        size: value.size,
                        mime: value.type || 'application/octet-stream',
                        content: base64content
                    });
                } catch (error) {
                    console.error('Error reading file:', name, error);
                    // Skip this file but continue with others
                    continue;
                }
            } else if (value.isFolder) {
                // Process folder
                const children = await processStructure(value);
                result.push({
                    type: 'folder',
                    name: name,
                    children: children
                });
            }
        }
        
        return result;
    }
    
    function readFileAsBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = event => resolve(event.target.result.split(',')[1]);
            reader.onerror = error => reject(error);
            reader.readAsDataURL(file);
        });
    }
    
    try {
        const processedData = await processStructure(structure);
        
        // Show the folder summary
        folderName.textContent = baseFolder || 'Unnamed Folder';
        folderFileCount.textContent = fileCount;
        folderSubdirCount.textContent = subdirCount;
        folderTotalSize.textContent = formatFileSize(totalSize);
        folderSummary.classList.remove('hidden');
        
        // Set input values for the form submission
        baseFolderNameInput.value = baseFolder || 'New Folder';
        folderStructureInput.value = JSON.stringify(processedData);
        
        // Enable submit button
        submitFolderUpload.disabled = false;
    } catch (error) {
        console.error('Error processing folder structure:', error);
        alert('Error processing folder. Please check console for details.');
    }
});

// Prevent form submission when processing
document.getElementById('folderUploadForm').addEventListener('submit', function(e) {
    if (submitFolderUpload.disabled) {
        e.preventDefault();
        alert('Please wait while the folder is being processed...');
    }
});

// Folder drop zone handling for drag and drop
const folderDropZone = document.getElementById('folderDropZone');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    folderDropZone.addEventListener(eventName, preventDefaults, false);
});

['dragenter', 'dragover'].forEach(eventName => {
    folderDropZone.addEventListener(eventName, function() {
        folderDropZone.classList.add('drag-over');
    }, false);
});

['dragleave', 'drop'].forEach(eventName => {
    folderDropZone.addEventListener(eventName, function() {
        folderDropZone.classList.remove('drag-over');
    }, false);
});

// Note: Folder drag and drop requires special handling and may not work in all browsers
folderDropZone.addEventListener('drop', function(e) {
    alert("For folder uploads, please use the 'Choose folder' button. Drag and drop for folders is not fully supported in all browsers.");
});
    </script>
</body>
</html>