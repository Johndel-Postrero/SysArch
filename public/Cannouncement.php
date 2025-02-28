<?php
// Start session at the very top
session_start();

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

require __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: login.php"); // Redirect to login page
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
        $query = $conn->prepare("SELECT attachment FROM announcements WHERE id = ?");
        $query->bind_param("i", $post_id);
        $query->execute();
        $result = $query->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $attachment = $row['attachment'];
        }
        $query->close();
    }

    // File Upload Handling
    if (!empty($_FILES['attachment']['name'])) {
        $targetDir = __DIR__ . '/../public/announce/';
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
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, description = ?, attachment = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $description, $attachment, $post_id);
    } else {
        // Insert new post
        $stmt = $conn->prepare("INSERT INTO announcements (title, description, attachment, admin_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $description, $attachment, $admin_id);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Post " . ($post_id ? "updated" : "added") . " successfully!'); window.location='Cannouncement.php';</script>";
    } else {
        echo "<script>alert('Error " . ($post_id ? "updating" : "adding") . " post!');</script>";
    }
    $stmt->close();
}

// Fetch announcements from the database (this runs regardless of form submission)
$query = "SELECT * FROM announcements ORDER BY created_at DESC";
$result = $conn->query($query);

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
        }
        #overlay-content {
            position: relative; /* Ensures child absolute elements are positioned correctly */
            max-height: 80vh;
            overflow-y: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
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
            <div class="flex-1 p-6 flex justify-center items-center">
                <div class="main-con p-6 max-w-4xl w-full">
                    <!-- Search and Filter -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-4">
                            <div class="relative z-[-1]">
                                <input class="w-64 py-2 pl-10 pr-4 rounded-full border border-gray-300 focus:outline-none focus:ring-2 focus:ring-violet-500" placeholder="Search" type="text"/>
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <div class="relative dropdown flex flex-col items-center">
                                <button class="flex items-center space-x-2 text-gray-600 relative z-[-1]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                    <span>Sort</span>
                                </button>
                                <div class="dropdown-content absolute mt-7 bg-white rounded-lg shadow-lg border border-gray-200 w-32 ">
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">A-Z</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Z-A</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Newest</a>
                                    <a href="#" class="block px-4 py-2 hover:bg-gray-100">Oldest</a>
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
        <button id="closeOverlay" class="absolute top-3 right-3 text-gray-600 hover:text-gray-800" onclick="closeModal()">
            <i class="fas fa-times text-xl"></i>
        </button>
        <h2 id="modalTitle" class="text-xl font-bold text-center mb-4">Add Post</h2>
        <form method="POST" enctype="multipart/form-data">
            <!-- Hidden input for post ID (used in update mode) -->
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
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="bg-white rounded-lg shadow p-6 mb-4 cursor-pointer" onclick="openUpdateModal(<?php echo $row['id']; ?>)">
    <div class="flex items-center mb-4">
        <div class="w-12 h-12 flex items-center justify-center text-black font-semibold rounded-full mr-2 text-lg border-2 border-gray">
            <?php 
            if (isset($_SESSION['profile_picture']) && file_exists(__DIR__ . '/../public/upload/' . $_SESSION['profile_picture'])) {
                echo '<img src="upload/' . htmlspecialchars($_SESSION['profile_picture']) . '" alt="Profile Picture" class="w-full h-full object-cover rounded-full">';
            } else {
                echo $initials;
            }
            ?>
        </div>
        <div class="ml-4">
            <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['middlename'] . '. ' . $_SESSION['lastname']); ?> · Admin</p>
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
                class="text-blue-500 hover:text-blue-700 underline"
                onclick="event.stopPropagation()">
                    <?php echo htmlspecialchars($row['attachment']); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <button class="flex items-center mt-5 space-x-2 text-gray-600" onclick="event.stopPropagation()">
        <i class="fas fa-comment"></i>
        <span>Comment</span>
    </button>
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
        closeOverlayBtn.addEventListener("click", () => overlay.style.display = "none");

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
        fetch(`get_post.php?id=${postId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById("modalTitle").textContent = "Update Post";
                document.getElementById("submitButton").textContent = "Update";
                document.getElementById("post_id").value = data.id; // Set post ID
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
                        img.src = `public/announce/${data.attachment}`;
                        img.alt = "Attachment Preview";
                        img.classList.add("w-full", "rounded-lg", "mb-4");
                        preview.appendChild(img);
                    } else {
                        // Display download link for non-image files
                        const link = document.createElement("a");
                        link.href = `public/announce/${data.attachment}`;
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
</script>
</body>
</html>
