<?php
date_default_timezone_set('Asia/Manila'); // Set to Philippine time
// Start session at the very top
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

require __DIR__ . '/../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php"); // Redirect to login page
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $admin_id = $_SESSION['user_id'];
    $attachment = null;
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;

    // Fetch existing attachment if no new file is uploaded
    if ($post_id && empty($_FILES['attachment']['name'])) {
        $query = $conn->prepare("SELECT attachment FROM announcements WHERE announcement_id = ?");
        if ($query) {
            $query->bind_param("i", $post_id);
            $query->execute();
            $result = $query->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $attachment = $row['attachment'];
            }
            $query->close();
        } else {
            // Handle query preparation error
            echo "<script>alert('Error preparing query: " . $conn->error . "');</script>";
        }
    }

    // File Upload Handling
    if (!empty($_FILES['attachment']['name'])) {
        $targetDir = __DIR__ . '/../announce/';
        $file_name = basename($_FILES["attachment"]["name"]);
        $new_file_name = time() . "_" . $file_name; // Prevent conflicts
        $target_file = $targetDir . $new_file_name; // Full path for moving file
        
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            $attachment = $new_file_name; // Store only the filename
        } else {
            echo "<script>alert('File upload failed!');</script>";
        }
    }

    if ($post_id) {
        // Update existing post
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, description = ?, attachment = ? WHERE announcement_id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $title, $description, $attachment, $post_id);
        } else {
            // Handle statement preparation error
            echo "<script>alert('Error preparing update statement: " . $conn->error . "');</script>";
        }
    } else {
        // Insert new post
        $stmt = $conn->prepare("INSERT INTO announcements (title, description, attachment, admin_id) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssi", $title, $description, $attachment, $admin_id);
        } else {
            // Handle statement preparation error
            echo "<script>alert('Error preparing insert statement: " . $conn->error . "');</script>";
        }
    }

    if (isset($stmt) && $stmt) {
        if ($stmt->execute()) {
            $_SESSION['success'] = "Post " . ($post_id ? "updated" : "added") . " successfully!";
            header("Location: Cannouncement.php");
            exit();
        } else {
            $_SESSION['error'] = "Error " . ($post_id ? "updating" : "adding") . " post: " . $stmt->error;
            header("Location: Cannouncement.php");
            exit();
        }
        $stmt->close();
    }
}

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
$search_cond = "";
$params = [];
$types = "";

if ($search !== "") {
    $search_cond = " WHERE a.title LIKE ? OR a.description LIKE ? ";
    $search_like = "%" . $search . "%";
    $params = [$search_like, $search_like];
    $types = "ss";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM announcements a" . $search_cond;
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
          (SELECT COUNT(*) FROM comments c WHERE c.announcement_id = a.announcement_id) as comment_count 
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

// Get user initials for avatar fallback
$initials = "";
if (isset($_SESSION['firstname']) && isset($_SESSION['lastname'])) {
    $initials = strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1));
}
$postCount = $total_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    
    <link rel="stylesheet" href="../css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { margin: 0; overflow-x: hidden; background: #0D0B1A; }
        
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }

        body.sidebar-minimized .main-wrapper { margin-left: 80px; }

        .page-content { padding: 30px 40px; flex: 1; max-width: 1200px; margin: 0 auto; width: 100%; }

        /* Top Controls */
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
        }

        .controls-left { display: flex; gap: 12px; flex: 1; }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 10px 16px 10px 40px;
            border-radius: 12px;
            font-size: 13px;
            transition: all 0.3s;
        }
        .search-box input:focus {
            background: rgba(139, 63, 217, 0.05);
            border-color: rgba(139, 63, 217, 0.4);
            outline: none;
        }
        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 14px;
        }

        .dark-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .dark-btn:hover { background: rgba(255, 255, 255, 0.08); }

        .btn-glow {
            background: rgba(139, 63, 217, 0.1);
            border: 1px solid rgba(139, 63, 217, 0.4);
            color: #fff;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.2);
        }
        .btn-glow:hover {
            background: rgba(139, 63, 217, 0.2);
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.4);
            transform: translateY(-1px);
        }
        .btn-glow-solid {
            background: var(--purple-glow);
            border: 1px solid rgba(139, 63, 217, 0.6);
            color: #fff;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.4);
        }
        .btn-glow-solid:hover {
            background: #9d50ea;
            box-shadow: 0 0 30px rgba(139, 63, 217, 0.6);
            transform: translateY(-2px);
        }

        /* Dropdown content — scoped to page-content to avoid sidebar conflict */
        .page-content .dropdown { position: relative; }
        .page-content .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: #151226;
            border: 1px solid rgba(139, 63, 217, 0.2);
            border-radius: 12px;
            min-width: 150px;
            overflow: hidden;
            z-index: 50;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .page-content .dropdown:hover .dropdown-content { display: block; }
        .page-content .dropdown-content a {
            color: #fff;
            padding: 10px 16px;
            display: block;
            font-size: 13px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .page-content .dropdown-content a:hover { background: rgba(139, 63, 217, 0.15); }

        /* ========== ANNOUNCEMENT TABLE ========== */
        .announce-table-wrap {
            min-height: 480px;
            position: relative;
            background: rgba(22, 19, 38, 0.4);
            border: 1px solid rgba(139, 63, 217, 0.1);
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
        }
        .announce-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            padding: 0 16px;
        }
        .announce-table thead th {
            padding: 16px 20px 10px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .announce-table tbody tr {
            cursor: pointer;
            transition: background 0.3s;
        }
        .announce-table tbody tr:hover td {
            background: rgba(139, 63, 217, 0.05);
            border-top: 1px solid rgba(139, 63, 217, 0.2);
            border-bottom: 1px solid rgba(139, 63, 217, 0.2);
        }
        .announce-table tbody td {
            padding: 14px 20px;
            font-size: 13px;
            vertical-align: middle;
            background: rgba(255, 255, 255, 0.02);
            border-top: 1px solid transparent;
            border-bottom: 1px solid transparent;
            height: 52px;
        }
        .announce-table tbody td:first-child { border-radius: 12px 0 0 12px; border-left: 1px solid transparent; }
        .announce-table tbody td:last-child { border-radius: 0 12px 12px 0; border-right: 1px solid transparent; }
        .announce-table tbody tr:hover td:first-child { border-left: 1px solid rgba(139, 63, 217, 0.2); }
        .announce-table tbody tr:hover td:last-child { border-right: 1px solid rgba(139, 63, 217, 0.2); }

        .announce-table .row-num { color: var(--gold); font-weight: 700; font-size: 13px; width: 60px; }
        .announce-table .row-title {
            color: #fff; font-weight: 500; max-width: 350px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .dark-table .row-date { color: var(--text-dim); font-size: 12px; white-space: nowrap; }
        .dark-table .row-comments { color: var(--text-dim); font-size: 12px; text-align: center; }
        .dark-table .row-attachment { text-align: center; color: var(--text-dim); font-size: 13px; }
        .dark-table .row-attachment i { color: var(--purple-light); }
        .dark-table .row-actions { width: 50px; text-align: center; position: relative; }
        
        .content-card {
            overflow: visible !important;
        }
        .dark-table-wrap {
            overflow: visible !important;
        }

        /* 3-dot action button */
        .row-action-btn {
            background: none; border: none; color: var(--text-dim);
            cursor: pointer; padding: 6px 10px; border-radius: 8px;
            font-size: 16px; transition: all 0.2s; line-height: 1;
        }
        .row-action-btn:hover { color: #fff; background: rgba(139, 63, 217, 0.15); }
        .row-action-dropdown {
            display: none; position: absolute; right: 10px; top: 100%;
            min-width: 140px; background: #151226;
            border: 1px solid rgba(139, 63, 217, 0.3); border-radius: 12px;
            overflow: hidden; z-index: 9999; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .row-action-dropdown.show { display: block; }
        .row-action-dropdown a {
            color: #fff; padding: 10px 16px; display: block;
            font-size: 13px; text-decoration: none; transition: background 0.2s;
        }
        .row-action-dropdown a:hover { background: rgba(139, 63, 217, 0.15); }
        .row-action-dropdown a.delete-opt { color: #ef4444; }

        /* Empty State — inside the same fixed-height box */
        .empty-container {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 420px; text-align: center; padding: 40px 20px;
        }
        .empty-graphic {
            position: relative; width: 140px; height: 140px;
            margin: 0 auto 30px; display: flex; align-items: center; justify-content: center;
        }
        .empty-graphic .circle {
            position: absolute; border: 1px dashed rgba(139, 63, 217, 0.2);
            border-radius: 50%; top: 50%; left: 50%; transform: translate(-50%, -50%);
        }
        .empty-graphic .circle-1 { width: 100%; height: 100%; animation: spin 20s linear infinite; }
        .empty-graphic .circle-2 { width: 180%; height: 180%; border: 1px dashed rgba(139, 63, 217, 0.1); animation: spin 30s linear infinite reverse; }
        .empty-graphic .icon-box {
            width: 70px; height: 70px; background: rgba(139, 63, 217, 0.1);
            border: 1px solid rgba(139, 63, 217, 0.3); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 30px rgba(139, 63, 217, 0.2); z-index: 2;
        }
        .empty-graphic i { font-size: 28px; color: var(--purple-glow); text-shadow: 0 0 15px rgba(139, 63, 217, 0.5); }
        .empty-title { font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 12px; font-family: var(--font-h); letter-spacing: 1px; }
        .empty-desc { color: var(--text-dim); font-size: 14px; margin-bottom: 30px; max-width: 400px; margin-left: auto; margin-right: auto; line-height: 1.6; }
        .empty-footer { margin-top: 40px; color: rgba(255,255,255,0.3); font-size: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        @keyframes spin { 100% { transform: translate(-50%, -50%) rotate(360deg); } }

        /* Modal-only post display styles */
        .post-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .post-author { display: flex; align-items: center; gap: 12px; }
        .post-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: rgba(139, 63, 217, 0.2); border: 1px solid rgba(139, 63, 217, 0.4);
            display: flex; align-items: center; justify-content: center;
            color: #C084FC; font-weight: 600; font-size: 14px; object-fit: cover; overflow: hidden;
        }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .post-author-info h3 { margin: 0; color: #fff; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .admin-badge { background: rgba(139, 63, 217, 0.2); color: #C084FC; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
        .post-date { color: var(--gold); font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top: 4px; }
        .post-options { color: var(--text-dim); cursor: pointer; padding: 4px 8px; transition: color 0.2s; }
        .post-options:hover { color: #fff; }
        .post-title { font-size: 22px; font-weight: 600; color: #fff; margin: 0 0 16px 0; font-family: var(--font-h); }
        .post-body { color: var(--text-dim); font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .post-attachment { max-width: 100%; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05); }

        /* Comments Section (modal) */
        .comments-section {
            margin-top: 16px; background: rgba(0, 0, 0, 0.2);
            border-radius: 12px; border: 1px solid rgba(255,255,255,0.03); padding: 16px;
        }
        .comment-item { display: flex; gap: 12px; margin-bottom: 16px; }
        .comment-item:last-child { margin-bottom: 0; }
        .comment-avatar {
            width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px;
            object-fit: cover; flex-shrink: 0;
        }
        .comment-content { flex: 1; }
        .comment-header { display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px; }
        .comment-author { font-size: 13px; font-weight: 600; color: #fff; }
        .comment-time { font-size: 11px; color: var(--gold); }
        .comment-text { font-size: 13px; color: var(--text-dim); line-height: 1.5; }
        .comment-input-box {
            display: flex; gap: 12px; align-items: flex-start;
            margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.05);
        }
        .comment-input-box textarea {
            flex: 1; background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1); color: #fff;
            padding: 12px 16px; border-radius: 12px; font-size: 13px; resize: none; transition: all 0.3s;
        }
        .comment-input-box textarea:focus { background: rgba(139, 63, 217, 0.05); border-color: rgba(139, 63, 217, 0.4); outline: none; }
        .comment-submit { background: transparent; border: none; color: var(--purple-glow); cursor: pointer; padding: 10px; transition: color 0.2s; }
        .comment-submit:hover { color: #C084FC; text-shadow: 0 0 10px rgba(139, 63, 217, 0.5); }

        /* Modal / Overlay matching Dashboard design */
        #overlay {
            position: fixed; inset: 0;
            background: rgba(6, 4, 17, 0.8);
            backdrop-filter: blur(8px);
            display: none; justify-content: center; align-items: center; z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #overlay.show {
            display: flex;
            opacity: 1;
        }
        #overlay-content {
            background: rgba(22, 19, 38, 0.95);
            border: 1px solid rgba(139, 63, 217, 0.3);
            border-radius: 24px;
            padding: 32px; width: 90%; max-width: 680px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 30px rgba(139, 63, 217, 0.2);
            color: #fff;
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        #overlay.show #overlay-content {
            transform: scale(1);
        }
        
        /* Custom scrollbars */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: rgba(139, 63, 217, 0.4); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(139, 63, 217, 0.8); }

        #overlay-content::-webkit-scrollbar { width: 6px; }
        #overlay-content::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); border-radius: 10px; }
        #overlay-content::-webkit-scrollbar-thumb { background: rgba(139, 63, 217, 0.4); border-radius: 10px; }
        #overlay-content::-webkit-scrollbar-thumb:hover { background: rgba(139, 63, 217, 0.8); }

        /* Close button — circular style */
        #closeOverlay {
            position: absolute; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-dim);
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 14px; transition: all 0.2s; z-index: 10;
        }
        #closeOverlay:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.3);
        }

        #modalTitle {
            font-family: var(--font-h); font-size: 20px; font-weight: 700;
            margin-bottom: 24px; color: #fff; letter-spacing: 1px;
            display: flex; align-items: center; gap: 12px;
        }

        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label {
            display: block; font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-dim); margin-bottom: 8px;
        }
        .form-input {
            width: 100%; background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(139, 63, 217, 0.2); color: #fff;
            padding: 12px 16px; border-radius: 12px; font-size: 14px;
            font-family: var(--font-b); transition: all 0.3s;
        }
        .form-input:focus {
            background: rgba(139, 63, 217, 0.05);
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139, 63, 217, 0.15);
            outline: none;
        }

        /* Upload Area */
        .upload-area {
            border: 2px dashed rgba(139, 63, 217, 0.3);
            background: rgba(255, 255, 255, 0.01);
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: var(--purple-glow);
            background: rgba(139, 63, 217, 0.05);
        }
        .upload-area i.upload-icon {
            font-size: 28px;
            color: var(--purple-light);
            margin-bottom: 10px;
            display: block;
        }
        .upload-area .upload-text {
            margin: 0;
            font-size: 13px;
            color: var(--text-dim);
        }
        .upload-area .upload-hint {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.25);
            display: block;
            margin-top: 6px;
        }
        .upload-area.has-file {
            border-color: rgba(139, 63, 217, 0.5);
            background: rgba(139, 63, 217, 0.05);
        }
        .upload-area.has-file .upload-text {
            color: #C084FC;
            font-weight: 500;
        }

        /* File preview inside upload area */
        .upload-preview {
            margin-top: 12px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(139, 63, 217, 0.2);
        }
        .upload-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            object-fit: contain;
        }

        /* Submit button — gradient */
        .modal-submit-btn {
            background: linear-gradient(90deg, var(--purple-glow), #9D50EA);
            color: #fff;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            width: 100%;
            border: none;
            letter-spacing: 1px;
            font-family: var(--font-h);
            cursor: pointer;
            box-shadow: 0 0 20px rgba(139, 63, 217, 0.3);
            transition: all 0.3s;
            margin-top: 8px;
        }
        .modal-submit-btn:hover {
            box-shadow: 0 0 25px rgba(139, 63, 217, 0.5);
            transform: translateY(-1px);
        }

        #preview img { max-width: 100%; border-radius: 8px; margin-top: 10px; }

        /* Pagination Styles */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 30px; }
        .page-item { display: inline-block; }
        .page-link {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff; padding: 8px 14px; border-radius: 8px; font-size: 13px; text-decoration: none;
            transition: all 0.3s;
        }
        .page-link:hover { background: rgba(139, 63, 217, 0.1); border-color: rgba(139, 63, 217, 0.3); }
        .page-item.active .page-link { background: var(--purple-glow); border-color: rgba(139, 63, 217, 0.6); font-weight: 600; }
        .page-item.disabled .page-link { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>
    <canvas id="star-canvas" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none !important; z-index: -1;"></canvas>
    
    <div class="main-wrapper">
        <?php include 'sidebarad.php'; ?>
        <?php include 'headerad.php'; ?>
        
        <div class="student-content">
            <!-- Success / Error notification banner -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-between text-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            
            <!-- Controls Row -->
            <div class="controls-row">
                <div class="controls-left">
                    <div class="dark-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="text" placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>" oninput="liveFilterAnnouncements()" onkeypress="handleSearchKeyPress(event)"/>
                    </div>
                    
                    <div style="position: relative;">
                        <button class="filter-btn" onclick="toggleSortDropdown(event)">
                            <i class="fas fa-sort-amount-down"></i>
                            <span>Sort: <?php 
                                if ($sort === 'oldest') echo 'Oldest First';
                                elseif ($sort === 'az') echo 'A - Z';
                                elseif ($sort === 'za') echo 'Z - A';
                                else echo 'Newest First';
                            ?></span>
                            <i class="fas fa-chevron-down text-[10px] ml-1"></i>
                        </button>
                        <div id="sortDropdown" class="filter-dropdown hidden animate-fade-in" style="min-width: 160px; right: 0;">
                            <a href="?page=1&sort=newest&search=<?php echo urlencode($search); ?>" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Newest First</a>
                            <a href="?page=1&sort=oldest&search=<?php echo urlencode($search); ?>" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Oldest First</a>
                            <a href="?page=1&sort=az&search=<?php echo urlencode($search); ?>" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">A - Z</a>
                            <a href="?page=1&sort=za&search=<?php echo urlencode($search); ?>" class="dropdown-item py-2 px-3 block hover:bg-white/5 rounded text-sm text-gray-300 hover:text-white">Z - A</a>
                        </div>
                    </div>
                </div>

                <button id="openOverlay" class="filter-btn" style="background: rgba(139,63,217,0.15); border: 1px solid var(--purple); color: var(--purple-light);">
                    <i class="fas fa-plus"></i> <span>Add Post</span>
                </button>
            </div>

            <!-- Modal -->
            <div id="overlay">
                <div id="overlay-content">
                    <button id="closeOverlay"><i class="fas fa-times"></i></button>

                    <!-- View Post Section -->
                    <div id="viewPostSection" style="display: none;">
                        <div class="post-header mb-4 mt-2">
                            <div class="post-author">
                                <div class="post-avatar" id="modalViewAvatar"></div>
                                <div class="post-author-info">
                                    <h3 id="modalViewName" style="color: #fff; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0;"></h3>
                                    <div class="post-date" id="modalViewDate" style="color: var(--gold); font-size: 11px; display: flex; align-items: center; gap: 4px; margin-top: 4px;"></div>
                                </div>
                            </div>
                            <!-- Hidden programmatically for row Edit triggers, visually removed from popup -->
                            <div class="relative dropdown" style="display: none;">
                                <a href="#" id="modalEditBtn">Edit Post</a>
                                <a href="#" id="modalDeleteBtn">Delete</a>
                            </div>
                        </div>
                        <h2 class="post-title" id="modalViewTitle" style="font-size: 22px; font-weight: 600; color: #fff; margin: 0 0 16px 0; font-family: var(--font-h);"></h2>
                        <div class="post-body" id="modalViewBody" style="color: var(--text-dim); font-size: 15px; line-height: 1.6; margin-bottom: 24px;"></div>
                        <div id="modalViewAttachment" style="margin-bottom: 24px;"></div>

                        <!-- Announcement Likes Row -->
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <button id="modalLikePostBtn" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: #9A8FB0; padding: 8px 16px; border-radius: 10px; font-size: 13px; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.borderColor='rgba(139,63,217,0.5)'" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)'">
                                <i class="far fa-heart" id="modalLikePostIcon"></i> Like (<span id="modalPostLikesCount">0</span>)
                            </button>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.05); margin-bottom: 20px;">

                        <!-- Comments Section inside Modal -->
                        <div class="comments-section" style="display: block; background: transparent; padding: 0; border: none; margin-top: 0;">
                            <h3 style="color: white; font-size: 15px; margin-bottom: 16px; font-weight: 600;">Comments</h3>
                            <div id="modalCommentsList" class="space-y-4 mb-4"></div>
                            
                            <div class="comment-input-box" style="margin-top: 0; padding-top: 16px;">
                                <div class="comment-avatar"><?php echo $initials; ?></div>
                                <textarea id="modalCommentInput" rows="1" placeholder="Write a comment..." oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'"></textarea>
                                <button class="comment-submit" onclick="addModalComment()">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Post Section (Add/Edit Form) -->
                    <div id="editPostSection" style="display: none;">
                        <h2 id="modalTitle"><i class="fas fa-bullhorn text-purple-400"></i> Add Post</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="post_id" id="post_id">
                            <div class="form-group">
                                <label for="modalTitleInput">Title</label>
                                <input type="text" name="title" id="modalTitleInput" class="form-input" placeholder="Enter announcement title..." required>
                            </div>
                            <div class="form-group">
                                <label for="modalDescriptionInput">Description</label>
                                <textarea name="description" id="modalDescriptionInput" class="form-input" rows="5" placeholder="Write your announcement details here..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label>Attachment (Optional)</label>
                                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <p class="upload-text" id="uploadFileName">Click to browse files</p>
                                    <span class="upload-hint">Images, PDFs, documents up to 5MB</span>
                                    <input type="file" name="attachment" id="fileInput" style="display: none;">
                                    <div id="preview" class="upload-preview" style="display: none;"></div>
                                </div>
                            </div>
                            <button type="submit" name="submit_type" id="submitButton" class="modal-submit-btn">POST ANNOUNCEMENT</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Announcements Content Card -->
            <div class="content-card">
                <div class="records-header">
                    <div class="records-title">
                        <h3>Announcement List</h3>
                    </div>
                </div>
                
                <div class="dark-table-wrap" id="announcement-container" style="height: auto !important; min-height: 370px !important; max-height: none !important; overflow: visible !important;">
                    <?php if ($postCount > 0): ?>
                        <table class="dark-table" id="announceTable">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Post No.</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th style="text-align:center; width: 100px;">Comments</th>
                                    <th style="text-align:center; width: 100px;">Attachment</th>
                                    <th style="text-align:center; width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                        $commentCount = $row['comment_count'];
                                        $dateFormatted = date('Y-m-d', strtotime($row['created_at']));
                                    ?>
                                    <tr class="announce-row animate-fade-in" data-id="<?php echo $row['announcement_id']; ?>" data-title="<?php echo htmlspecialchars($row['title']); ?>" onclick="handleRowClick(event, <?php echo $row['announcement_id']; ?>)">
                                        <td class="row-num">#<?php echo $row['announcement_id']; ?></td>
                                        <td class="row-title" style="color: #fff; font-weight: 500;"><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td class="row-date"><?php echo $dateFormatted; ?></td>
                                        <td class="row-comments" style="text-align:center;"><?php echo $commentCount; ?></td>
                                        <td class="row-attachment" style="text-align:center;">
                                            <?php if (!empty($row['attachment'])): ?>
                                                <i class="fas fa-paperclip text-purple-400" title="Has attachment"></i>
                                            <?php else: ?>
                                                <span style="color: rgba(255,255,255,0.15);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="row-actions" style="text-align:center;">
                                            <button class="row-action-btn" onclick="toggleRowDropdown(event, <?php echo $row['announcement_id']; ?>)" title="Actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div id="row-dd-<?php echo $row['announcement_id']; ?>" class="row-action-dropdown">
                                                <a href="#" onclick="event.stopPropagation(); viewAnnouncement(<?php echo $row['announcement_id']; ?>); setTimeout(() => document.getElementById('modalEditBtn').click(), 100); return false;"><i class="fas fa-edit" style="width:18px;"></i> Edit</a>
                                                <a href="#" class="delete-opt" onclick="event.stopPropagation(); confirmDeletePost(<?php echo $row['announcement_id']; ?>); return false;"><i class="fas fa-trash-alt" style="width:18px;"></i> Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-container">
                            <div class="empty-graphic">
                                <div class="circle circle-1"></div>
                                <div class="circle circle-2"></div>
                                <div class="icon-box"><i class="fas fa-bullhorn"></i></div>
                            </div>
                            <h2 class="empty-title">No Announcements Yet</h2>
                            <p class="empty-desc">Click <strong>+ Add Post</strong> to create the first announcement for your lab members.</p>
                            <button class="btn-glow-solid" onclick="document.getElementById('openOverlay').click()">
                                <i class="fas fa-plus"></i> Add Post
                            </button>
                            <div class="empty-footer">
                                <i class="fas fa-info-circle"></i> Announcements are visible to all registered users.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination Controls inside Content Card -->
                <?php if ($postCount > 0): ?>
                    <?php
                    $start_entry = $total_rows == 0 ? 0 : $offset + 1;
                    $end_entry = min($offset + $limit, $total_rows);
                    ?>
                    <div class="pagination-row">
                        <div class="pagination-info">
                            Showing <?php echo $start_entry; ?> to <?php echo $end_entry; ?> of <?php echo $total_rows; ?> entries
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-controls">
                                <!-- Prev Link -->
                                <a class="page-btn <?php echo ($page_num <= 1) ? 'disabled pointer-events-none opacity-50' : ''; ?>" 
                                   href="?page=<?php echo $page_num - 1; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a class="page-btn <?php echo ($page_num == $i) ? 'active' : ''; ?>" 
                                       href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <!-- Next Link -->
                                <a class="page-btn <?php echo ($page_num >= $total_pages) ? 'disabled pointer-events-none opacity-50' : ''; ?>" 
                                   href="?page=<?php echo $page_num + 1; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Custom Themed Alert Modal -->
    <div id="customAlertModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[360px] bg-[#161326] border border-purple-500/30 rounded-2xl p-6 shadow-[0_0_50px_rgba(139,63,217,0.3)] flex flex-col items-center overflow-hidden transition-all duration-300 transform scale-95 opacity-0" id="customAlertBox">
            <div id="customAlertAccent" class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-500 to-indigo-500"></div>
            <div class="flex flex-col items-center w-full mt-2">
                <div id="customAlertIconWrapper" class="w-14 h-14 rounded-2xl flex items-center justify-center mb-3">
                    <i id="customAlertIcon" class="fas text-2xl"></i>
                </div>
                <h3 id="customAlertTitle" class="text-lg font-bold text-white mb-2">Notification</h3>
                <p id="customAlertMessage" class="text-xs text-gray-400 text-center px-2 leading-relaxed"></p>
            </div>
            <button id="btnCustomAlertClose" class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs transition duration-200 mt-4 shadow-[0_0_15px_rgba(168,85,247,0.3)] hover:shadow-[0_0_20px_rgba(168,85,247,0.5)]">
                OK
            </button>
        </div>
    </div>

    <!-- Custom Themed Confirm Modal -->
    <div id="customConfirmModal" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-black/85 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-[360px] bg-[#161326] border border-red-500/30 rounded-2xl p-6 shadow-[0_0_50px_rgba(239,68,68,0.2)] flex flex-col items-center overflow-hidden transition-all duration-300 transform scale-95 opacity-0" id="customConfirmBox">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 to-orange-500"></div>
            <div class="flex flex-col items-center w-full mt-2">
                <div class="w-14 h-14 rounded-2xl bg-red-500/10 border border-red-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                    <i class="fas fa-trash-alt text-red-400 text-2xl"></i>
                </div>
                <h3 id="customConfirmTitle" class="text-lg font-bold text-white mb-2">Delete Confirmation</h3>
                <p id="customConfirmMessage" class="text-xs text-gray-400 text-center px-2 leading-relaxed"></p>
            </div>
            <div class="flex gap-3 w-full mt-5">
                <button id="btnCustomConfirmNo" class="flex-1 py-2.5 rounded-xl border border-white/10 text-gray-300 font-bold text-xs hover:bg-white/5 transition duration-200">
                    CANCEL
                </button>
                <button id="btnCustomConfirmYes" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs transition duration-200 shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:shadow-[0_0_20px_rgba(239,68,68,0.5)]">
                    DELETE
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const overlay = document.getElementById("overlay");
            const openOverlayBtn = document.getElementById("openOverlay");
            const closeOverlayBtn = document.getElementById("closeOverlay");
            const fileInput = document.getElementById("fileInput");
            const preview = document.getElementById("preview");

            // Open modal in "Add Post" mode
            openOverlayBtn.addEventListener("click", () => {
                resetModalToAddPostMode();
                document.getElementById('viewPostSection').style.display = 'none';
                document.getElementById('editPostSection').style.display = 'block';
                overlay.classList.add('show');
            });

            // Close modal
            closeOverlayBtn.addEventListener("click", () => {
                overlay.classList.remove('show');
            });

            // Close on backdrop click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('show');
                }
            });

            // Handle file preview in upload area
            fileInput.addEventListener("change", function (event) {
                const uploadArea = document.getElementById('uploadArea');
                const uploadFileName = document.getElementById('uploadFileName');
                preview.innerHTML = "";
                const file = event.target.files[0];
                if (file) {
                    uploadArea.classList.add('has-file');
                    uploadFileName.textContent = file.name;
                    if (file.type.startsWith("image/")) {
                        const img = document.createElement("img");
                        img.src = URL.createObjectURL(file);
                        preview.appendChild(img);
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                } else {
                    uploadArea.classList.remove('has-file');
                    uploadFileName.textContent = 'Click to browse files';
                    preview.style.display = 'none';
                }
            });
        });

        // Custom Themed Alert Implementation
        function showCustomAlert(title, message, type = 'success') {
            const modal = document.getElementById('customAlertModal');
            const box = document.getElementById('customAlertBox');
            const closeBtn = document.getElementById('btnCustomAlertClose');
            const icon = document.getElementById('customAlertIcon');
            const iconWrapper = document.getElementById('customAlertIconWrapper');
            const accent = document.getElementById('customAlertAccent');
            
            document.getElementById('customAlertTitle').textContent = title;
            document.getElementById('customAlertMessage').innerHTML = message;
            
            // Set styles based on alert type
            if (type === 'success') {
                icon.className = 'fas fa-check-circle text-emerald-400 text-2xl';
                iconWrapper.className = 'w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(16,185,129,0.2)]';
                accent.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-teal-500';
                closeBtn.className = 'w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition duration-200 mt-4 shadow-[0_0_15px_rgba(16,185,129,0.3)] hover:shadow-[0_0_20px_rgba(16,185,129,0.5)]';
            } else {
                icon.className = 'fas fa-exclamation-circle text-rose-400 text-2xl';
                iconWrapper.className = 'w-14 h-14 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center mb-3 shadow-[0_0_15px_rgba(244,63,94,0.2)]';
                accent.className = 'absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rose-500 to-red-500';
                closeBtn.className = 'w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs transition duration-200 mt-4 shadow-[0_0_15px_rgba(244,63,94,0.3)] hover:shadow-[0_0_20px_rgba(244,63,94,0.5)]';
            }
            
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            setTimeout(() => {
                box.classList.remove('scale-95', 'opacity-0');
            }, 50);
            
            return new Promise((resolve) => {
                const handleClose = () => {
                    box.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.add('hidden');
                        closeBtn.removeEventListener('click', handleClose);
                        resolve();
                    }, 200);
                };
                closeBtn.addEventListener('click', handleClose);
            });
        }

        // Custom Themed Confirm Implementation
        function showCustomConfirm(title, message) {
            const modal = document.getElementById('customConfirmModal');
            const box = document.getElementById('customConfirmBox');
            const confirmBtn = document.getElementById('btnCustomConfirmYes');
            const cancelBtn = document.getElementById('btnCustomConfirmNo');
            
            document.getElementById('customConfirmTitle').textContent = title;
            document.getElementById('customConfirmMessage').innerHTML = message;
            
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
            setTimeout(() => {
                box.classList.remove('scale-95', 'opacity-0');
            }, 50);
            
            return new Promise((resolve) => {
                const cleanUp = (value) => {
                    box.classList.add('scale-95', 'opacity-0');
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.add('hidden');
                        resolve(value);
                    }, 200);
                };
                
                const onYes = () => {
                    confirmBtn.removeEventListener('click', onYes);
                    cancelBtn.removeEventListener('click', onNo);
                    cleanUp(true);
                };
                
                const onNo = () => {
                    confirmBtn.removeEventListener('click', onYes);
                    cancelBtn.removeEventListener('click', onNo);
                    cleanUp(false);
                };
                
                confirmBtn.addEventListener('click', onYes);
                cancelBtn.addEventListener('click', onNo);
            });
        }

        function toggleSortDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('sortDropdown');
            dropdown.classList.toggle('hidden');
        }
        
        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('sortDropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        });

        function handleSearchKeyPress(event) {
            if (event.key === "Enter") {
                const val = document.getElementById("searchInput").value.trim();
                window.location.href = `?page=1&sort=<?php echo $sort; ?>&search=${encodeURIComponent(val)}`;
            }
        }

        function liveFilterAnnouncements() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('#announceTable tbody .announce-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const title = (row.querySelector('.row-title')?.textContent || '').toLowerCase();
                const postNo = (row.querySelector('.row-num')?.textContent || '').toLowerCase();
                const date = (row.querySelector('.row-date')?.textContent || '').toLowerCase();
                const match = title.includes(query) || postNo.includes(query) || date.includes(query);
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Show/hide "no results" message
            let noResultsRow = document.getElementById('liveSearchNoResults');
            if (visibleCount === 0 && rows.length > 0) {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'liveSearchNoResults';
                    noResultsRow.innerHTML = `<td colspan="6" style="text-align:center; padding:60px 20px; color:#9A8FB0;"><i class="fas fa-search" style="font-size:36px; display:block; margin-bottom:12px; opacity:0.3; color:#8B3FD9;"></i><span style="font-size:14px; font-weight:500;">No announcements match your search</span></td>`;
                    document.querySelector('#announceTable tbody').appendChild(noResultsRow);
                }
                noResultsRow.style.display = '';
            } else if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }

            // Update pagination info text dynamically
            const paginationInfo = document.querySelector('.pagination-info');
            const paginationControls = document.querySelector('.pagination-controls');
            if (paginationInfo && query.length > 0) {
                paginationInfo.textContent = `Showing ${visibleCount} of ${rows.length} entries (filtered)`;
                if (paginationControls) paginationControls.style.display = 'none';
            } else if (paginationInfo && query.length === 0) {
                paginationInfo.textContent = `Showing <?php echo ($total_rows == 0 ? 0 : $offset + 1); ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries`;
                if (paginationControls) paginationControls.style.display = '';
            }
        }

        function confirmDeletePost(id) {
            showCustomConfirm("Delete Announcement?", "Are you sure you want to permanently delete this announcement? All comments will also be deleted.").then((confirmed) => {
                if (confirmed) {
                    deleteAnnouncement(id);
                }
            });
        }

        // Function to reset modal to "Add Post" mode
        function resetModalToAddPostMode() {
            document.getElementById("modalTitle").innerHTML = '<i class="fas fa-bullhorn text-purple-400"></i> Add Post';
            document.getElementById("submitButton").textContent = "POST ANNOUNCEMENT";
            document.getElementById("post_id").value = "";
            document.getElementById("modalTitleInput").value = "";
            document.getElementById("modalDescriptionInput").value = "";
            document.getElementById("preview").innerHTML = "";
            document.getElementById("preview").style.display = 'none';
            document.getElementById("fileInput").value = "";
            const uploadArea = document.getElementById('uploadArea');
            const uploadFileName = document.getElementById('uploadFileName');
            if (uploadArea) uploadArea.classList.remove('has-file');
            if (uploadFileName) uploadFileName.textContent = 'Click to browse files';
        }

        // Function to open modal in "Update Post" mode
        function viewAnnouncement(id) {
            currentAnnouncementId = id;
            fetch(`get_post.php?announcement_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.announcement_id) {
                        // Populate View Section
                        document.getElementById('editPostSection').style.display = 'none';
                        document.getElementById('viewPostSection').style.display = 'block';

                        // Avatar
                        const avatarContainer = document.getElementById("modalViewAvatar");
                        if (data.profile_picture && data.profile_picture !== 'default-profile.png') {
                            avatarContainer.innerHTML = `<img src="../upload/${data.profile_picture}" class="w-full h-full rounded-full object-cover">`;
                        } else {
                            avatarContainer.innerHTML = (data.firstname[0] + data.lastname[0]).toUpperCase();
                        }

                        // Name, Title, Body
                        document.getElementById("modalViewName").innerHTML = `${data.firstname} ${data.lastname} <span class="admin-badge">Admin</span>`;
                        document.getElementById("modalViewDate").innerHTML = `<i class="far fa-clock"></i> ${new Date(data.created_at).toLocaleString()}`;
                        document.getElementById("modalViewTitle").textContent = data.title;
                        document.getElementById("modalViewBody").innerHTML = data.description.replace(/\n/g, "<br>");

                        // Attachment
                        const attachmentContainer = document.getElementById("modalViewAttachment");
                        attachmentContainer.innerHTML = "";
                        if (data.attachment) {
                            const fileExtension = data.attachment.split('.').pop().toLowerCase();
                            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

                            if (imageExtensions.includes(fileExtension)) {
                                attachmentContainer.innerHTML = `<img src="../announce/${data.attachment}" class="w-full rounded-lg mt-4 border border-white/5">`;
                            } else {
                                attachmentContainer.innerHTML = `<a href="../announce/${data.attachment}" download class="text-purple-400 hover:text-purple-300 underline text-sm flex items-center gap-2 mt-4"><i class="fas fa-paperclip"></i> ${data.attachment}</a>`;
                            }
                        }

                        // Set up Edit Button Action
                        const editBtn = document.getElementById("modalEditBtn");
                        editBtn.onclick = function(e) {
                            e.preventDefault();
                            document.getElementById('viewPostSection').style.display = 'none';
                            document.getElementById('editPostSection').style.display = 'block';
                            
                            document.getElementById("modalTitle").innerHTML = '<i class="fas fa-edit text-purple-400"></i> Update Post';
                            document.getElementById("submitButton").textContent = "UPDATE ANNOUNCEMENT";
                            document.getElementById("post_id").value = data.announcement_id;
                            document.getElementById("modalTitleInput").value = data.title;
                            document.getElementById("modalDescriptionInput").value = data.description;
                            
                            const preview = document.getElementById("preview");
                            const uploadArea = document.getElementById('uploadArea');
                            const uploadFileName = document.getElementById('uploadFileName');
                            preview.innerHTML = "";
                            if(data.attachment) {
                                if (uploadArea) uploadArea.classList.add('has-file');
                                if (uploadFileName) uploadFileName.textContent = data.attachment;
                                const fileExt = data.attachment.split('.').pop().toLowerCase();
                                const imgExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                if (imgExts.includes(fileExt)) {
                                    preview.innerHTML = `<img src="../announce/${data.attachment}" class="w-full rounded-lg">`;
                                    preview.style.display = 'block';
                                } else {
                                    preview.innerHTML = `<a href="../announce/${data.attachment}" download class="text-purple-400 hover:text-purple-300 underline text-sm">${data.attachment}</a>`;
                                    preview.style.display = 'block';
                                }
                            } else {
                                if (uploadArea) uploadArea.classList.remove('has-file');
                                if (uploadFileName) uploadFileName.textContent = 'Click to browse files';
                                preview.style.display = 'none';
                            }
                        };

                        // Set up Delete Button Action
                        const deleteBtn = document.getElementById("modalDeleteBtn");
                        deleteBtn.onclick = function(e) {
                            e.preventDefault();
                            if (confirm("Are you sure you want to delete this post?")) {
                                deleteAnnouncement(id);
                            }
                        };

                        // Load Announcement Likes
                        loadAnnouncementLikes(id);
                        
                        const likePostBtn = document.getElementById('modalLikePostBtn');
                        likePostBtn.onclick = function() {
                            const fd = new FormData();
                            fd.append('action', 'like_announcement');
                            fd.append('announcement_id', id);
                            
                            fetch('comment_operations.php', { method: 'POST', body: fd })
                            .then(r => r.json()).then(data => {
                                if (data.success) {
                                    loadAnnouncementLikes(id);
                                }
                            });
                        };

                        // Load Comments into Modal
                        loadComments(id, true);

                        // Show the overlay
                        document.getElementById("overlay").classList.add('show');
                    }
                });
        }

        function deleteAnnouncement(id) {
            fetch(`delete_announcement.php?announcement_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showCustomAlert("Post Deleted", "The announcement has been permanently deleted.", "success").then(() => {
                            window.location.reload();
                        });
                    } else {
                        showCustomAlert("Error", "Error deleting post: " + data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showCustomAlert("Error", "An error occurred while deleting the post", "error");
                });
        }

        function filterAnnouncements() {
            const searchQuery = document.getElementById("searchInput").value.toLowerCase();
            const rows = document.querySelectorAll('.announce-row');
            rows.forEach(row => {
                const title = (row.getAttribute('data-title') || '').toLowerCase();
                row.style.display = title.includes(searchQuery) ? '' : 'none';
            });
        }

        let currentAnnouncementId = null;

        function toggleComments(announcementId, event) {
            event.stopPropagation(); // Prevent the card click event
            const commentsSection = document.getElementById(`comments-${announcementId}`);
            commentsSection.classList.toggle('hidden');
            
            if (!commentsSection.classList.contains('hidden')) {
                loadComments(announcementId);
            }
        }

        function loadAnnouncementLikes(id) {
            const fd = new FormData();
            fd.append('action', 'get_announcement_like');
            fd.append('announcement_id', id);
            
            fetch('comment_operations.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (!data.success) return;
                document.getElementById('modalPostLikesCount').textContent = data.like_count;
                const btn = document.getElementById('modalLikePostBtn');
                const icon = document.getElementById('modalLikePostIcon');
                if (data.user_liked) {
                    icon.className = 'fas fa-heart text-red-500';
                    btn.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                    btn.style.background = 'rgba(239, 68, 68, 0.08)';
                    btn.style.color = '#ef4444';
                } else {
                    icon.className = 'far fa-heart';
                    icon.style.color = '';
                    btn.style.borderColor = 'rgba(255,255,255,0.1)';
                    btn.style.background = 'rgba(255,255,255,0.03)';
                    btn.style.color = '#9A8FB0';
                }
            });
        }

        function loadComments(announcementId, isModal = false) {
            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('announcement_id', announcementId);

            fetch('comment_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const commentsListId = isModal ? "modalCommentsList" : `commentsList-${announcementId}`;
                    const commentsList = document.getElementById(commentsListId);
                    if(commentsList) {
                        commentsList.innerHTML = '';
                        if (data.comments.length > 0) {
                            const parents = data.comments.filter(c => c.parent_id === null);
                            const replies = data.comments.filter(c => c.parent_id !== null);
                            
                            parents.forEach(comment => {
                                const commentElement = createCommentElement(comment, announcementId, isModal);
                                commentsList.appendChild(commentElement);
                            });
                            
                            replies.forEach(reply => {
                                const parentRepliesList = document.getElementById(`replies-list-${reply.parent_id}`);
                                if (parentRepliesList) {
                                    const replyElement = createReplyElement(reply, announcementId, isModal);
                                    parentRepliesList.appendChild(replyElement);
                                }
                            });
                        } else {
                            commentsList.innerHTML = '<div style="text-align:center; padding:20px 0; color:#9A8FB0; font-size:12px;">No comments yet.</div>';
                        }
                    }
                }
            });
        }

        function createCommentElement(comment, announcementId, isModal = false) {
            const div = document.createElement('div');
            div.className = 'comment-item';
            div.style.marginBottom = '16px';
            
            // Visual indicator for hidden comment
            if (parseInt(comment.is_hidden) === 1) {
                div.style.opacity = '0.66';
                div.style.border = '1px dashed rgba(239, 68, 68, 0.4)';
                div.style.background = 'rgba(239, 68, 68, 0.03)';
            }
            
            let timeStr = new Date(comment.created_at).toLocaleString();
            const sessionUser = <?php echo $_SESSION['user_id']; ?>;
            const isAdmin = <?php echo $_SESSION['role'] === 'admin' ? 'true' : 'false'; ?>;
            const canManage = (comment.user_id == sessionUser || isAdmin);
            
            div.innerHTML = `
                <div class="comment-avatar">
                    ${comment.profile_picture ? `<img src="../upload/${comment.profile_picture}" class="w-full h-full rounded-full object-cover">` : 
                    (comment.firstname[0] + comment.lastname[0]).toUpperCase()}
                </div>
                <div class="comment-content" style="flex:1;">
                    <div class="comment-header" style="display:flex; align-items:center; width:100%;">
                        <span class="comment-author">${comment.firstname} ${comment.lastname}</span>
                        <span class="comment-time">${timeStr}</span>
                        ${parseInt(comment.is_hidden) ? `<span class="comment-time" style="color:#f59e0b; font-weight:600; margin-left:8px;"><i class="fas fa-eye-slash"></i> Hidden</span>` : ''}
                        ${canManage ? 
                            `<div class="relative dropdown ml-auto" style="margin-left:auto;">
                                <i class="fas fa-ellipsis-h text-gray-500 hover:text-white cursor-pointer text-xs p-1 dropdown-trigger" onclick="toggleCommentMenu(${comment.comment_id})"></i>
                                <div id="commentMenu-${comment.comment_id}" class="dropdown-content absolute right-0 bg-[#151226] border border-[#8B3FD9]/30 rounded-lg shadow-lg z-10 w-28 hidden">
                                    ${comment.user_id == sessionUser ? `<a href="#" onclick="editComment(${comment.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-white hover:bg-white/10 px-3 py-2 block"><i class="fas fa-edit" style="width:14px; margin-right:4px;"></i> Edit</a>` : ''}
                                    ${isAdmin ? `<a href="#" onclick="toggleHideComment(${comment.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-yellow-400 hover:bg-white/10 px-3 py-2 block"><i class="fas ${parseInt(comment.is_hidden) ? 'fa-eye' : 'fa-eye-slash'}" style="width:14px; margin-right:4px;"></i> ${parseInt(comment.is_hidden) ? 'Unhide' : 'Hide'}</a>` : ''}
                                    <a href="#" onclick="deleteComment(${comment.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-red-400 hover:bg-white/10 px-3 py-2 block"><i class="fas fa-trash-alt" style="width:14px; margin-right:4px;"></i> Delete</a>
                                </div>
                            </div>` : ''}
                    </div>
                    <div class="comment-text" id="comment-text-${comment.comment_id}" style="margin-bottom:8px; color:#D1C7E0;">${comment.comment_text}</div>
                    
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
                        <button onclick="toggleCommentLike(${comment.comment_id}, ${announcementId}, ${isModal})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:12px;color:${parseInt(comment.user_liked) ? '#ef4444' : '#9A8FB0'};cursor:pointer;transition:color 0.2s;">
                            <i class="${parseInt(comment.user_liked) ? 'fas' : 'far'} fa-heart" style="${parseInt(comment.user_liked) ? 'color:#ef4444;' : ''}"></i> Like (${comment.like_count || 0})
                        </button>
                        <button onclick="toggleReplyForm(${comment.comment_id})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:12px;color:#9A8FB0;cursor:pointer;transition:color 0.2s;">
                            <i class="far fa-comment"></i> Reply
                        </button>
                    </div>
                    
                    <!-- Reply Form -->
                    <div id="reply-form-${comment.comment_id}" class="hidden mt-3 pl-4 border-l-2 border-[#8B3FD9]/30" style="display:none;">
                        <div class="flex gap-2 items-center">
                            <input type="text" id="reply-input-${comment.comment_id}" placeholder="Reply to this comment…" class="w-full bg-white/5 border border-white/10 text-white rounded-lg px-3 py-1.5 text-xs outline-none focus:border-[#8B3FD9]/50">
                            <button onclick="postCommentReply(${comment.comment_id}, ${announcementId}, ${isModal})" class="px-3 py-1.5 rounded-lg bg-[#8B3FD9] hover:bg-[#9E52E6] text-white text-xs font-semibold" style="margin-top:0;">Send</button>
                        </div>
                    </div>
                    
                    <!-- Replies List Container -->
                    <div id="replies-list-${comment.comment_id}" class="mt-3 pl-4 border-l border-white/5 space-y-3"></div>
                </div>
            `;
            return div;
        }

        function createReplyElement(reply, announcementId, isModal = false) {
            const div = document.createElement('div');
            div.className = 'comment-item reply-item';
            div.style.background = 'rgba(255,255,255,0.01)';
            div.style.padding = '8px 12px';
            div.style.borderRadius = '8px';
            div.style.marginTop = '8px';
            
            // Visual indicator for hidden reply
            if (parseInt(reply.is_hidden) === 1) {
                div.style.opacity = '0.66';
                div.style.border = '1px dashed rgba(239, 68, 68, 0.4)';
                div.style.background = 'rgba(239, 68, 68, 0.03) !important';
            }
            
            let timeStr = new Date(reply.created_at).toLocaleString();
            const sessionUser = <?php echo $_SESSION['user_id']; ?>;
            const isAdmin = <?php echo $_SESSION['role'] === 'admin' ? 'true' : 'false'; ?>;
            const canManage = (reply.user_id == sessionUser || isAdmin);
            
            div.innerHTML = `
                <div style="display:flex; align-items:flex-start; gap:10px; width:100%;">
                    <div class="comment-avatar" style="width:26px; height:26px; font-size:10px; flex-shrink:0;">
                        ${reply.profile_picture ? `<img src="../upload/${reply.profile_picture}" class="w-full h-full rounded-full object-cover">` : 
                        (reply.firstname[0] + reply.lastname[0]).toUpperCase()}
                    </div>
                    <div class="comment-content" style="flex:1;">
                        <div class="comment-header" style="display:flex; align-items:center;">
                            <span class="comment-author" style="font-size:12px;">${reply.firstname} ${reply.lastname}</span>
                            <span class="comment-time" style="font-size:10px;">${timeStr}</span>
                            ${parseInt(reply.is_hidden) ? `<span class="comment-time" style="color:#f59e0b; font-weight:600; margin-left:8px; font-size:10px;"><i class="fas fa-eye-slash"></i> Hidden</span>` : ''}
                            ${canManage ? 
                                `<div class="relative dropdown ml-auto" style="margin-left:auto;">
                                    <i class="fas fa-ellipsis-h text-gray-500 hover:text-white cursor-pointer text-xs p-1 dropdown-trigger" onclick="toggleCommentMenu(${reply.comment_id})"></i>
                                    <div id="commentMenu-${reply.comment_id}" class="dropdown-content absolute right-0 bg-[#151226] border border-[#8B3FD9]/30 rounded-lg shadow-lg z-10 w-24 hidden">
                                        ${reply.user_id == sessionUser ? `<a href="#" onclick="editComment(${reply.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-white hover:bg-white/10 px-3 py-2 block"><i class="fas fa-edit" style="width:14px; margin-right:4px;"></i> Edit</a>` : ''}
                                        ${isAdmin ? `<a href="#" onclick="toggleHideComment(${reply.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-yellow-400 hover:bg-white/10 px-3 py-2 block"><i class="fas ${parseInt(reply.is_hidden) ? 'fa-eye' : 'fa-eye-slash'}" style="width:14px; margin-right:4px;"></i> ${parseInt(reply.is_hidden) ? 'Unhide' : 'Hide'}</a>` : ''}
                                        <a href="#" onclick="deleteComment(${reply.comment_id}, ${announcementId}, ${isModal}); return false;" class="text-xs text-red-400 hover:bg-white/10 px-3 py-2 block"><i class="fas fa-trash-alt" style="width:14px; margin-right:4px;"></i> Delete</a>
                                    </div>
                                </div>` : ''}
                        </div>
                        <div class="comment-text" id="comment-text-${reply.comment_id}" style="font-size:12px; margin-bottom:6px; color:#D1C7E0;">${reply.comment_text}</div>
                        
                        <div style="display:flex;align-items:center;gap:14px;">
                            <button onclick="toggleCommentLike(${reply.comment_id}, ${announcementId}, ${isModal})" style="background:none;border:none;display:flex;align-items:center;gap:6px;font-size:11px;color:${parseInt(reply.user_liked) ? '#ef4444' : '#9A8FB0'};cursor:pointer;transition:color 0.2s;">
                                <i class="${parseInt(reply.user_liked) ? 'fas' : 'far'} fa-heart" style="${parseInt(reply.user_liked) ? 'color:#ef4444;' : ''}"></i> Like (${reply.like_count || 0})
                            </button>
                        </div>
                    </div>
                </div>
            `;
            return div;
        }

        function toggleCommentLike(commentId, announcementId, isModal = false) {
            const fd = new FormData();
            fd.append('action', 'like_comment');
            fd.append('comment_id', commentId);
            
            fetch('comment_operations.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    loadComments(announcementId, isModal);
                }
            });
        }

        function toggleHideComment(commentId, announcementId, isModal = false) {
            const fd = new FormData();
            fd.append('action', 'toggle_hide');
            fd.append('comment_id', commentId);
            
            fetch('comment_operations.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    loadComments(announcementId, isModal);
                } else {
                    alert('Error toggling comment visibility: ' + data.message);
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

        function postCommentReply(parentCommentId, announcementId, isModal = false) {
            const input = document.getElementById(`reply-input-${parentCommentId}`);
            const text = input.value.trim();
            if (!text) return;
            
            const fd = new FormData();
            fd.append('action', 'add');
            fd.append('announcement_id', announcementId);
            fd.append('comment_text', text);
            fd.append('parent_id', parentCommentId);
            
            fetch('comment_operations.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    input.value = '';
                    loadComments(announcementId, isModal);
                    
                    // Update table comments count cell real-time
                    const tableRow = document.querySelector(`.announce-row[data-id="${announcementId}"]`);
                    if (tableRow) {
                        const cell = tableRow.querySelector('.row-comments');
                        if (cell) {
                            cell.textContent = parseInt(cell.textContent) + 1;
                        }
                    }
                }
            });
        }

        function toggleCommentMenu(commentId) {
            const menu = document.getElementById(`commentMenu-${commentId}`);
            menu.classList.toggle('hidden');
            
            // Close other open menus
            document.querySelectorAll('[id^="commentMenu-"]').forEach(otherMenu => {
                if (otherMenu.id !== `commentMenu-${commentId}`) {
                    otherMenu.classList.add('hidden');
                }
            });
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[id^="commentMenu-"]') && !event.target.closest('.fa-ellipsis-h')) {
                document.querySelectorAll('[id^="commentMenu-"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        function editComment(commentId, announcementId, isModal = false) {
            const commentTextElement = document.getElementById(`comment-text-${commentId}`);
            const currentText = commentTextElement.textContent;
            const newText = prompt('Edit your comment:', currentText);
            
            if (newText === null) return; // User cancelled
            if (newText.trim() === '') {
                alert('Comment cannot be empty');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('comment_id', commentId);
            formData.append('comment_text', newText);

            fetch('comment_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadComments(announcementId, isModal);
                } else {
                    alert('Failed to edit comment: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while editing the comment');
            });
        }

        function addComment(announcementId) {
            const inputElem = document.getElementById(`commentText-${announcementId}`);
            if(!inputElem) return;
            const commentText = inputElem.value.trim();
            if (!commentText) return;
            
            submitCommentData(announcementId, commentText, false);
        }

        function addModalComment() {
            if(!currentAnnouncementId) return;
            const inputElem = document.getElementById('modalCommentInput');
            const commentText = inputElem.value.trim();
            if(!commentText) return;

            submitCommentData(currentAnnouncementId, commentText, true);
        }

        function submitCommentData(announcementId, commentText, isModal) {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('announcement_id', announcementId);
            formData.append('comment_text', commentText);

            fetch('comment_operations.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if(isModal) {
                        document.getElementById('modalCommentInput').value = '';
                        loadComments(announcementId, true);
                    } else {
                        document.getElementById(`commentText-${announcementId}`).value = '';
                        loadComments(announcementId, false);
                    }
                    
                    // Update table comments count cell real-time
                    const tableRow = document.querySelector(`.announce-row[data-id="${announcementId}"]`);
                    if (tableRow) {
                        const cell = tableRow.querySelector('.row-comments');
                        if (cell) {
                            cell.textContent = parseInt(cell.textContent) + 1;
                        }
                    }
                }
            });
        }

        function deleteComment(commentId, announcementId, isModal = false) {
            showCustomConfirm("Delete Comment?", "Are you sure you want to delete this comment? This action cannot be undone.").then((confirmed) => {
                if (!confirmed) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('comment_id', commentId);

                fetch('comment_operations.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadComments(announcementId, isModal);
                        
                        // Decrement comments count cell real-time
                        const tableRow = document.querySelector(`.announce-row[data-id="${announcementId}"]`);
                        if (tableRow) {
                            const cell = tableRow.querySelector('.row-comments');
                            if (cell) {
                                cell.textContent = Math.max(0, parseInt(cell.textContent) - 1);
                            }
                        }
                    }
                });
            });
        }

        function handleRowClick(event, id) {
            // Don't open modal if clicking on the 3-dot button or its dropdown
            if (event.target.closest('.row-action-btn') || event.target.closest('.row-action-dropdown')) {
                return;
            }
            viewAnnouncement(id);
        }

        function toggleRowDropdown(event, id) {
            event.stopPropagation();
            // Close all other dropdowns
            document.querySelectorAll('.row-action-dropdown').forEach(el => {
                if (el.id !== 'row-dd-' + id) {
                    el.classList.remove('show');
                }
            });
            // Toggle current dropdown
            const dropdown = document.getElementById('row-dd-' + id);
            dropdown.classList.toggle('show');
        }

        // Global click listener to close row dropdowns when clicking outside
        window.addEventListener('click', function(event) {
            if (!event.target.closest('.row-action-btn') && !event.target.closest('.row-action-dropdown')) {
                document.querySelectorAll('.row-action-dropdown').forEach(el => {
                    el.classList.remove('show');
                });
            }
        });
    </script>
    <script>
        /* ===== STAR & SHOOTING STAR CANVAS ===== */
        (function(){
            const canvas = document.getElementById('star-canvas');
            if(!canvas) return;
            const ctx = canvas.getContext('2d');
            let W, H, stars = [], shoots = [];

            function resize() {
                W = canvas.width  = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            for (let i = 0; i < 180; i++) {
                stars.push({
                    x: Math.random() * 9999,
                    y: Math.random() * 9999,
                    r: Math.random() * 1.4 + 0.3,
                    a: Math.random(),
                    da: (Math.random() * 0.008 + 0.003) * (Math.random() < .5 ? 1 : -1)
                });
            }

            function spawnShoot() {
                shoots.push({
                    x: Math.random() * W * 1.2,
                    y: Math.random() * H * 0.5,
                    len: Math.random() * 120 + 80,
                    speed: Math.random() * 6 + 4,
                    angle: Math.PI / 4,
                    alpha: 1,
                    tail: []
                });
            }
            setInterval(spawnShoot, 2400);
            spawnShoot();

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
                    s.alpha -= 0.018;

                    const grad = ctx.createLinearGradient(
                        s.x - Math.cos(s.angle) * s.len,
                        s.y - Math.sin(s.angle) * s.len,
                        s.x, s.y
                    );
                    grad.addColorStop(0, `rgba(212,135,10,0)`);
                    grad.addColorStop(0.4, `rgba(200,160,255,${(s.alpha * .6).toFixed(2)})`);
                    grad.addColorStop(1, `rgba(255,255,255,${s.alpha.toFixed(2)})`);

                    ctx.beginPath();
                    ctx.moveTo(s.x - Math.cos(s.angle) * s.len, s.y - Math.sin(s.angle) * s.len);
                    ctx.lineTo(s.x, s.y);
                    ctx.strokeStyle = grad;
                    ctx.lineWidth = 1.5;
                    ctx.stroke();

                    if (s.alpha <= 0 || s.x > W + 200 || s.y > H + 200) {
                        shoots.splice(i, 1);
                    }
                });

                requestAnimationFrame(draw);
            }
            draw();
        })();
    </script>
</body>
</html>