<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_start();

// Check if the user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require __DIR__ . '/../config/db.php';

// Fetch announcements from the database along with admin details
$query = "
    SELECT a.*, u.firstname, u.middlename, u.lastname, u.profile_picture 
    FROM announcements a
    JOIN users u ON a.admin_id = u.user_id
    WHERE u.role = 'admin'
    ORDER BY a.created_at DESC
";
$result = $conn->query($query);
?>
<html>
<head>
    <title>Announcements</title>
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
            width: 5rem; /* Default width */
            transition: all 0.3s ease-in-out;
        }
        .sidebar:hover {
            width: 16rem; /* Expanded width */
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
            justify-content: center; /* Centers the icons */
            padding: 1rem;
        }
        .sidebar:hover a {
            justify-content: flex-start; /* Aligns text to the left on hover */
        }
        .sidebar i {
            font-size: 1.5rem; /* Slightly larger icons */
        }
        .main-content {
            margin-left: 5rem; /* Adjust based on the sidebar width */
            transition: margin-left 0.3s ease-in-out; /* Smooth transition */
        }
        .sidebar:hover + .main-content {
            margin-left: 16rem; /* Adjust content when sidebar expands */
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
            <div class="flex-1 p-6 flex justify-center items-center">
                <div class="main-con p-6 max-w-4xl w-full">
                    <!-- Search and Filter -->
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="relative flex-1">
                            <input class="w-full py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <!-- Sort Dropdown -->
                        <div class="relative dropdown">
                            <!-- Dropdown Button -->
                            <button id="sortButton" class="flex items-center space-x-2 text-gray-600 relative focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                </svg>
                                <span>Sort</span>
                            </button>

                            <!-- Dropdown Menu -->
                            <div id="sortDropdown" class="hidden absolute left-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 w-32">
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="A-Z">A-Z</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="Z-A">Z-A</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="Newest">Newest</a>
                                <a href="#" class="block px-4 py-2 hover:bg-gray-100" data-sort="Oldest">Oldest</a>
                            </div>
                        </div>

                    </div>

                    <!-- Announcement Cards -->
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-4">
                                <div class="flex items-center mb-4">
                                    <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
                                        <?php 
                                        if (!empty($row['profile_picture']) && file_exists(__DIR__ . '/../public/upload/' . $row['profile_picture'])) {
                                            echo '<img src="upload/' . htmlspecialchars($row['profile_picture']) . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
                                        } else {
                                            // Display initials or a default profile picture
                                            $initials = strtoupper(substr($row['firstname'], 0, 1) . substr($row['lastname'], 0, 1));
                                            echo $initials;
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
                                    $file_path = "public/announce/" . htmlspecialchars($row['attachment']);
                                    $file_extension = strtolower(pathinfo($row['attachment'], PATHINFO_EXTENSION));
                                    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                                    ?>
                                    
                                    <div class="mt-4">
                                        <?php if (in_array($file_extension, $image_extensions)): ?>
                                            <!-- Display Image -->
                                            <img src="<?php echo $file_path; ?>" alt="Announcement Image" class="w-full rounded-lg mb-4">
                                        <?php else: ?>
                                            <!-- Display Download Link for Non-Image Files -->
                                            <a href="<?php echo $file_path; ?>" 
                                            download="<?php echo htmlspecialchars($row['attachment']); ?>" 
                                            class="text-blue-500 hover:text-blue-700 underline">
                                                <?php echo htmlspecialchars($row['attachment']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                                    <button class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors" 
                                            onclick="toggleComments(<?php echo $row['announcement_id']; ?>)">
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

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.querySelector('input[type="text"]');
        const sortButton = document.getElementById('sortButton');
        const sortDropdown = document.getElementById('sortDropdown');
        const sortOptions = sortDropdown.querySelectorAll('a');
        const announcementContainer = document.querySelector('.main-con'); // Holds both search & announcements
        const announcementCards = document.querySelectorAll('.bg-white.rounded-lg.shadow.p-6.mb-4');

        // Keep a reference to the original search & filter UI
        const filterSection = document.querySelector('.flex.items-center.space-x-4.mb-6');

        // Toggle dropdown menu visibility
        sortButton.addEventListener('click', function (e) {
            e.stopPropagation();
            sortDropdown.classList.toggle('hidden');
        });

        // Prevent dropdown from closing when clicking inside it
        sortDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!sortButton.contains(e.target) && !sortDropdown.contains(e.target)) {
                sortDropdown.classList.add('hidden');
            }
        });

        // Search Functionality
        searchInput.addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            announcementCards.forEach(card => {
                const title = card.querySelector('h2').textContent.toLowerCase();
                const description = card.querySelector('p.text-gray-700').textContent.toLowerCase();

                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Sort Functionality
        sortOptions.forEach(option => {
            option.addEventListener('click', function (e) {
                e.preventDefault();
                sortDropdown.classList.add('hidden'); // Hide dropdown after selection

                const sortOption = this.getAttribute('data-sort');
                const cardsArray = Array.from(announcementCards);

                cardsArray.sort((a, b) => {
                    const titleA = a.querySelector('h2').textContent.toLowerCase();
                    const titleB = b.querySelector('h2').textContent.toLowerCase();
                    const dateA = new Date(a.querySelector('p.text-sm.text-gray-500').textContent);
                    const dateB = new Date(b.querySelector('p.text-sm.text-gray-500').textContent);

                    switch (sortOption) {
                        case 'A-Z':
                            return titleA.localeCompare(titleB);
                        case 'Z-A':
                            return titleB.localeCompare(titleA);
                        case 'Newest':
                            return dateB - dateA;
                        case 'Oldest':
                            return dateA - dateB;
                        default:
                            return 0;
                    }
                });

                // Clear only announcement cards (preserving search and sort UI)
                announcementContainer.innerHTML = ''; // Remove all content
                announcementContainer.appendChild(filterSection); // Re-add search & sort UI

                // Re-append sorted cards
                cardsArray.forEach(card => announcementContainer.appendChild(card));
            });
        });
    });

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

    function toggleComments(announcementId) {
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

        fetch('Admin/comment_operations.php', {
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
                    <img src="${comment.profile_picture ? 'upload/' + comment.profile_picture : 'assets/img/default-avatar.png'}" 
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

        fetch('Admin/comment_operations.php', {
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

        fetch('Admin/comment_operations.php', {
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

        fetch('Admin/comment_operations.php', {
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
</body>
</html>