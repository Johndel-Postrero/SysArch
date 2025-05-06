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
            echo "<script>alert('Post " . ($post_id ? "updated" : "added") . " successfully!'); window.location='Cannouncement.php';</script>";
        } else {
            echo "<script>alert('Error " . ($post_id ? "updating" : "adding") . " post: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// Fetch announcements from the database (this runs regardless of form submission)
$query = "SELECT a.*, u.firstname, u.middlename, u.lastname, u.profile_picture 
          FROM announcements a 
          JOIN users u ON a.admin_id = u.user_id 
          ORDER BY a.created_at DESC";
$result = $conn->query($query);

// Get user initials for avatar fallback
$initials = "";
if (isset($_SESSION['firstname']) && isset($_SESSION['lastname'])) {
    $initials = strtoupper(substr($_SESSION['firstname'], 0, 1) . substr($_SESSION['lastname'], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    
    <!-- Tailwind CSS & FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>

    <style>
        body {
            font-family: "Poppins-Regular";
            color: #333;
            font-size: 16px;
            margin: 0;
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
            margin-left: 16rem; /* Adjust content when sidebar expands */
        }
        /* Modal Styling */
        #overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            display: none;
            z-index: 1000; /* Ensure the modal is on top */
        }

        #overlay-content {
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            z-index: 1001; /* Ensure the modal content is on top */
        }
        #preview img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-top: 10px;
        }
        .clickable-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .clickable-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        /* Style for dropdown content */
        .dropdown-content {
            display: none;
        }
        .dropdown:hover .dropdown-content {
            display: block;
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
            <?php include 'headerad1.php'; ?>
            <div class="flex-1 p-6 flex justify-center items-center">
                <div class="main-con p-6 max-w-4xl w-full">
                    <!-- Search and Filter -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <input id="searchInput" class="w-64 py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" 
                                    placeholder="Search" type="text" oninput="filterAnnouncements()"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>

                            <div class="relative dropdown flex flex-col items-center">
                                <button class="flex items-center space-x-2 text-gray-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <div class="dropdown-content absolute mt-7 bg-white rounded-lg shadow-lg border border-gray-200 w-32">
                                    <a href="#" id="sortAZ" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                    <a href="#" id="sortZA" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                    <a href="#" id="sortNewest" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                    <a href="#" id="sortOldest" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
                                </div>
                            </div>
                        </div>

                        <!-- Add Post Button -->
                        <button id="openOverlay" class="bg-[#002044] text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-plus"></i>
                            <span>Add Post</span>
                        </button>
                    </div>

                    <!-- Modal -->
                    <div id="overlay">
                        <div id="overlay-content">
                            <button id="closeOverlay" class="absolute top-3 right-3 text-gray-600 hover:text-gray-800">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                            <h2 id="modalTitle" class="text-xl font-bold text-center mb-4">Add Post</h2>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="post_id" id="post_id">
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-semibold">Title</label>
                                    <input type="text" name="title" id="modalTitleInput" class="w-full px-3 py-2 border rounded-lg" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-semibold">Description</label>
                                    <textarea name="description" id="modalDescriptionInput" class="w-full px-3 py-2 border rounded-lg" rows="6" required></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 font-semibold">Attachment</label>
                                    <input type="file" name="attachment" id="fileInput" class="w-full border rounded-lg p-2">
                                    <div id="preview" class="mt-2"></div>
                                </div>
                                <button type="submit" name="submit_type" id="submitButton" class="w-full bg-[#002044] text-white py-2 rounded-lg">Post</button>
                            </form>
                        </div>
                    </div>

                    <!-- Announcement Cards -->
                    <div id="announcement-container" class="space-y-4">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <div class="bg-white rounded-lg shadow p-6 mb-4 cursor-pointer clickable-card" onclick="viewAnnouncement(<?php echo $row['announcement_id']; ?>)">
                                    <div class="flex items-center mb-4">
                                        <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
                                            <?php 
                                            if (isset($row['profile_picture']) && file_exists(__DIR__ . '/../upload/' . $row['profile_picture'])) {
                                                echo '<img src="../upload/' . htmlspecialchars($row['profile_picture']) . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
                                            } else {
                                                $userInitials = strtoupper(substr($row['firstname'], 0, 1) . substr($row['lastname'], 0, 1));
                                                echo $userInitials;
                                            }
                                            ?>
                                        </div>
                                        <div class="ml-4">
                                            <p class="font-semibold"><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']); ?> · Admin</p>
                                            <p class="text-sm text-gray-500"><?php echo date("M j, Y", strtotime($row['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <h2 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($row['title']); ?></h2>
                                    <p class="text-gray-700 mb-4"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>

                                    <!-- Display Attachment If Exists -->
                                    <?php if (!empty($row['attachment'])): ?>
                                        <?php
                                        $file_path = "../announce/" . htmlspecialchars($row['attachment']);
                                        $file_extension = strtolower(pathinfo($row['attachment'], PATHINFO_EXTENSION));
                                        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                        ?>
                                        
                                        <div class="mt-4">
                                            <?php if (in_array($file_extension, $image_extensions)): ?>
                                                <img src="<?php echo $file_path; ?>" alt="Announcement Image" class="w-full rounded-lg mb-4">
                                            <?php else: ?>
                                                <a href="<?php echo $file_path; ?>" 
                                                download="<?php echo htmlspecialchars($row['attachment']); ?>" 
                                                class="text-blue-500 hover:text-blue-700 underline"
                                                onclick="event.stopPropagation()">
                                                    <?php echo htmlspecialchars($row['attachment']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                                        <button class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors" 
                                                onclick="toggleComments(<?php echo $row['announcement_id']; ?>, event)">
                                            <i class="fas fa-comment"></i>
                                            <span>Comments</span>
                                        </button>
                                    </div>

                                    <!-- Comments Section (Hidden by default) -->
                                    <div id="comments-<?php echo $row['announcement_id']; ?>" class="comments-section mt-4 hidden">
                                        <div class="comment-form mb-3">
                                            <textarea class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                      id="commentText-<?php echo $row['announcement_id']; ?>" 
                                                      rows="2" 
                                                      placeholder="Write a comment..."></textarea>
                                            <button class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors" 
                                                    onclick="addComment(<?php echo $row['announcement_id']; ?>)">
                                                Post Comment
                                            </button>
                                        </div>
                                        <div id="commentsList-<?php echo $row['announcement_id']; ?>" class="comments-list space-y-4">
                                            <!-- Comments will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-gray-600 text-center">No announcements found.</p>
                        <?php endif; ?>
                    </div>
                </div>
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
                resetModalToAddPostMode(); // Reset modal to "Add Post" mode
                overlay.style.display = "flex";
            });

            // Close modal
            closeOverlayBtn.addEventListener("click", () => {
                overlay.style.display = "none";
            });

            // Handle file preview
            fileInput.addEventListener("change", function (event) {
                preview.innerHTML = "";
                const file = event.target.files[0];
                if (file && file.type.startsWith("image/")) {
                    const img = document.createElement("img");
                    img.src = URL.createObjectURL(file);
                    preview.appendChild(img);
                }
            });
        });

        // Function to reset modal to "Add Post" mode
        function resetModalToAddPostMode() {
            document.getElementById("modalTitle").textContent = "Add Post";
            document.getElementById("submitButton").textContent = "Post";
            document.getElementById("post_id").value = ""; // Clear post ID
            document.getElementById("modalTitleInput").value = ""; // Clear title
            document.getElementById("modalDescriptionInput").value = ""; // Clear description
            document.getElementById("preview").innerHTML = ""; // Clear file preview
            document.getElementById("fileInput").value = ""; // Clear file input
        }

        // Function to open modal in "Update Post" mode
        function openUpdateModal(postId) {
            fetch(`get_post.php?announcement_id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById("modalTitle").textContent = "Update Post";
                    document.getElementById("submitButton").textContent = "Update";
                    document.getElementById("post_id").value = data.announcement_id; // Set post ID
                    document.getElementById("modalTitleInput").value = data.title; // Set title
                    document.getElementById("modalDescriptionInput").value = data.description; // Set description

                    // Display attachment if it exists
                    const preview = document.getElementById("preview");
                    preview.innerHTML = ""; // Clear previous preview
                    if (data.attachment) {
                        const fileExtension = data.attachment.split('.').pop().toLowerCase();
                        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

                        if (imageExtensions.includes(fileExtension)) {
                            // Display image
                            const img = document.createElement("img");
                            img.src = `../announce/${data.attachment}`;
                            img.alt = "Attachment Preview";
                            img.classList.add("w-full", "rounded-lg", "mb-4");
                            preview.appendChild(img);
                        } else {
                            // Display download link for non-image files
                            const link = document.createElement("a");
                            link.href = `../announce/${data.attachment}`;
                            link.download = data.attachment;
                            link.textContent = data.attachment;
                            link.classList.add("text-blue-500", "hover:text-blue-700", "underline");
                            preview.appendChild(link);
                        }
                    }

                    document.getElementById("overlay").style.display = "flex";
                })
                .catch(error => console.error("Error fetching post data:", error));
        }

        // Function to filter announcements based on search input
        function filterAnnouncements() {
            const searchQuery = document.getElementById("searchInput").value.toLowerCase();
            const announcementContainer = document.getElementById("announcement-container");
            const announcements = announcementContainer.getElementsByClassName("clickable-card");

            for (let i = 0; i < announcements.length; i++) {
                const title = announcements[i].querySelector("h2").textContent.toLowerCase();
                const description = announcements[i].querySelector("p.text-gray-700").textContent.toLowerCase();

                if (title.includes(searchQuery) || description.includes(searchQuery)) {
                    announcements[i].style.display = "block";
                } else {
                    announcements[i].style.display = "none";
                }
            }
        }

        let currentAnnouncementId = null;

        function viewAnnouncement(id) {
            currentAnnouncementId = id;
            fetch(`get_post.php?announcement_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.announcement_id) {
                        document.getElementById('viewAnnouncementTitle').textContent = data.title;
                        document.getElementById('viewAnnouncementDate').textContent = new Date(data.created_at).toLocaleString();
                        document.getElementById('viewAnnouncementContent').innerHTML = data.description;
                        
                        // Load comments
                        loadComments(id);
                        
                        new bootstrap.Modal(document.getElementById('viewAnnouncementModal')).show();
                    }
                });
        }

        function toggleComments(announcementId, event) {
            event.stopPropagation(); // Prevent the card click event
            const commentsSection = document.getElementById(`comments-${announcementId}`);
            commentsSection.classList.toggle('hidden');
            
            if (!commentsSection.classList.contains('hidden')) {
                loadComments(announcementId);
            }
        }

        function loadComments(announcementId) {
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
                    const commentsList = document.getElementById(`commentsList-${announcementId}`);
                    commentsList.innerHTML = '';
                    
                    data.comments.forEach(comment => {
                        const commentElement = createCommentElement(comment);
                        commentsList.appendChild(commentElement);
                    });
                }
            });
        }

        function createCommentElement(comment) {
            const div = document.createElement('div');
            div.className = 'comment-item bg-gray-50 rounded-lg p-3';
            div.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 flex-grow">
                        <img src="${comment.profile_picture ? '../upload/' + comment.profile_picture : '../assets/img/default-avatar.png'}" 
                             class="w-10 h-10 rounded-full object-cover border-2 border-gray-200">
                        <div class="flex-grow">
                            <div class="flex items-center space-x-2">
                                <strong>${comment.firstname} ${comment.middlename ? comment.middlename + ' ' : ''}${comment.lastname}</strong>
                                <span class="text-sm text-gray-500">${comment.role}</span>
                                ${(comment.user_id == <?php echo $_SESSION['user_id']; ?> || <?php echo $_SESSION['role'] === 'admin' ? 'true' : 'false'; ?>) ? 
                                    `<div class="relative ml-auto">
                                        <button class="text-gray-500 hover:text-gray-700 focus:outline-none" onclick="toggleCommentMenu(${comment.comment_id})">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div id="commentMenu-${comment.comment_id}" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                            <div class="py-1">
                                                <button onclick="editComment(${comment.comment_id}, ${comment.announcement_id})" 
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-edit mr-2"></i>Edit
                                                </button>
                                                <button onclick="deleteComment(${comment.comment_id}, ${comment.announcement_id})" 
                                                        class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                    <i class="fas fa-trash mr-2"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>` : ''}
                            </div>
                            <div class="text-sm text-gray-500">${new Date(comment.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
                <p class="mt-2 ml-13" id="comment-text-${comment.comment_id}">${comment.comment_text}</p>
            `;
            return div;
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
            if (!event.target.closest('[id^="commentMenu-"]') && !event.target.closest('.fa-ellipsis-v')) {
                document.querySelectorAll('[id^="commentMenu-"]').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        function editComment(commentId, announcementId) {
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
                    commentTextElement.textContent = newText;
                    // Close the menu after successful edit
                    document.getElementById(`commentMenu-${commentId}`).classList.add('hidden');
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
            const commentText = document.getElementById(`commentText-${announcementId}`).value.trim();
            if (!commentText) return;

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
                    document.getElementById(`commentText-${announcementId}`).value = '';
                    const commentsList = document.getElementById(`commentsList-${announcementId}`);
                    const commentElement = createCommentElement(data.comment);
                    commentsList.insertBefore(commentElement, commentsList.firstChild);
                }
            });
        }

        function deleteComment(commentId, announcementId) {
            if (!confirm('Are you sure you want to delete this comment?')) return;

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
                    loadComments(announcementId);
                }
            });
        }
    </script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Add event listeners for sorting
            document.getElementById("sortAZ").addEventListener("click", (e) => {
                e.preventDefault();
                sortAnnouncements("title", "asc");
            });
            document.getElementById("sortZA").addEventListener("click", (e) => {
                e.preventDefault();
                sortAnnouncements("title", "desc");
            });
            document.getElementById("sortNewest").addEventListener("click", (e) => {
                e.preventDefault();
                sortAnnouncements("date", "desc");
            });
            document.getElementById("sortOldest").addEventListener("click", (e) => {
                e.preventDefault();
                sortAnnouncements("date", "asc");
            });
        });

        function sortAnnouncements(sortBy, order) {
            const announcementContainer = document.getElementById("announcement-container");
            const announcements = Array.from(announcementContainer.getElementsByClassName("clickable-card"));

            announcements.sort((a, b) => {
                let aValue, bValue;

                if (sortBy === "title") {
                    // Sort by title
                    aValue = a.querySelector("h2").textContent.toLowerCase();
                    bValue = b.querySelector("h2").textContent.toLowerCase();
                } else if (sortBy === "date") {
                    // Sort by date
                    aValue = new Date(a.querySelector("p.text-sm.text-gray-500").textContent);
                    bValue = new Date(b.querySelector("p.text-sm.text-gray-500").textContent);
                }

                if (order === "asc") {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });

            // Clear the container and re-append sorted announcements
            announcementContainer.innerHTML = "";
            announcements.forEach(announcement => announcementContainer.appendChild(announcement));
        }
    </script>
</body>
</html>