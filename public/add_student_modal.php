<?php
// Include the database connection
require __DIR__ . '/../config/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $username = $_POST['username'];
    $course = $_POST['course'];
    $level = $_POST['level'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $profile_picture = 'default-profile.png'; // Default profile picture

    // Handle file upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../public/upload/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $profile_picture = basename($_FILES['profile_picture']['name']);
        $uploadFile = $uploadDir . $profile_picture;
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadFile)) {
            $profile_picture = 'default-profile.png'; // Fallback to default
        }
    }

    // Insert into database
    $sql = "INSERT INTO users (idno, lastname, firstname, middlename, username, course, level, email, password, profile_picture, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'student')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conn->error]);
        exit();
    }

    $stmt->bind_param("isssssssss", $idno, $lastname, $firstname, $middlename, $username, $course, $level, $email, $password, $profile_picture);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <!-- Add Student Modal -->
<div id="addStudentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
        <h2 class="text-xl font-bold mb-4">Add Student</h2>
        <form id="addStudentForm" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Profile Picture -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Profile Picture</label>
                    <input type="file" id="profilePicture" name="profile_picture" class="w-full p-2 border border-gray-300 rounded-md">
                    <img id="profilePicturePreview" src="default-profile.png" alt="Profile Picture" class="w-24 h-24 rounded-full mt-2">
                </div>
                <!-- ID Number -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">ID Number</label>
                    <input type="text" id="idno" name="idno" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- First Name -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" id="firstname" name="firstname" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- Middle Name -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                    <input type="text" id="middlename" name="middlename" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- Last Name -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" id="lastname" name="lastname" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- Username -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- Course -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Course</label>
                    <select id="course" name="course" class="w-full p-2 border border-gray-300 rounded-md" required>
                        <option value="BSIT">BSIT</option>
                        <option value="BSCS">BSCS</option>
                        <option value="HM">HM</option>
                        <option value="CRIM">CRIM</option>
                        <option value="CBA">CBA</option>
                    </select>
                </div>
                <!-- Level -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Level</label>
                    <select id="level" name="level" class="w-full p-2 border border-gray-300 rounded-md" required>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>
                <!-- Email -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
                <!-- Password -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="w-full p-2 border border-gray-300 rounded-md" required>
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-4">
        <button type="button" onclick="closeModal('addStudentModal')" class="bg-gray-500 text-white px-4 py-2 rounded-md">Cancel</button>
        <button type="submit" class="bg-[#002044] text-white px-4 py-2 rounded-md">Add Student</button>  
            </div>
        </form>
    </div>
<script>
    // Function to open modal
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

// Function to close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Handle Add Student Form Submission
document.getElementById('addStudentForm').addEventListener('submit', function(event) {
    event.preventDefault();
    console.log("Form submitted!");

    const formData = new FormData(this);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check if the response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error('Expected JSON, got: ' + text);
            });
        }
    })
    .then(data => {
        console.log(data); // Debugging: Log the response data
        if (data.success) {
            alert('Student added successfully!');
            closeModal('addStudentModal');
            setTimeout(() => {
                location.reload(); // Reload the page after a short delay
            }, 1000); // 1 second delay
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please check the console for details.');
    });
});
</script>
</div>
</body>
</html>
