<?php
// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['login_user'])) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . '/../../config/db.php';

// Fetch user details
$current_idno = $_SESSION['login_user'];
$user_sql = "SELECT * FROM users WHERE idno = ?";
$stmtUser = $conn->prepare($user_sql);
$stmtUser->bind_param("s", $current_idno);
$stmtUser->execute();
$user_result = $stmtUser->get_result();
$user = $user_result->fetch_assoc();
$stmtUser->close();

if (!$user) {
    header("Location: ../logout.php");
    exit();
}

// Update session variables
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['middlename'] = $user['middlename'];
$_SESSION['lastname'] = $user['lastname'];
$_SESSION['profile_picture'] = $user['profile_picture'];
$_SESSION['role'] = $user['role'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_DEFAULT);
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_DEFAULT);
    $middlename = filter_input(INPUT_POST, 'middlename', FILTER_DEFAULT);
    $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
    $idno = filter_input(INPUT_POST, 'idno', FILTER_DEFAULT);
    $profile_picture = $user['profile_picture'];

    // Check if the email address is already in use by another user
    $check_email_sql = "SELECT idno FROM users WHERE email = ? AND idno != ?";
    $stmtCheck = $conn->prepare($check_email_sql);
    $stmtCheck->bind_param("ss", $email, $idno);
    $stmtCheck->execute();
    $check_res = $stmtCheck->get_result();
    if ($check_res->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "This email address is already in use by another account."]);
        $stmtCheck->close();
        $conn->close();
        exit();
    }
    $stmtCheck->close();

    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updatePassword = true;
    } else {
        $updatePassword = false;
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        $targetDir = __DIR__ . '/../upload/';
        $fileType = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        $allowedTypes = ["jpg", "jpeg", "png", "gif"];

        if (!in_array($fileType, $allowedTypes)) {
            echo json_encode(["success" => false, "message" => "Invalid file format. Only JPG, JPEG, PNG, and GIF allowed."]);
            exit();
        }

        $newFileName = "profile_" . $idno . "_" . time() . "." . $fileType;
        $targetFilePath = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            // Delete old profile picture if not default
            if ($user['profile_picture'] && $user['profile_picture'] !== 'default-profile.png') {
                $oldFile = $targetDir . $user['profile_picture'];
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $profile_picture = $newFileName;
        } else {
            echo json_encode(["success" => false, "message" => "Error uploading profile picture."]);
            exit();
        }
    }

    if ($updatePassword) {
        $update_sql = "UPDATE users SET email=?, lastname=?, firstname=?, middlename=?, password=?, profile_picture=? WHERE idno=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssss", $email, $lastname, $firstname, $middlename, $hashedPassword, $profile_picture, $idno);
    } else {
        $update_sql = "UPDATE users SET email=?, lastname=?, firstname=?, middlename=?, profile_picture=? WHERE idno=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssss", $email, $lastname, $firstname, $middlename, $profile_picture, $idno);
    }

    if ($stmt->execute()) {
      $_SESSION['login_user'] = $idno;
      $_SESSION['profile_picture'] = $profile_picture;
      $_SESSION['firstname'] = $firstname;
      $_SESSION['lastname'] = $lastname;
      $_SESSION['middlename'] = $middlename;
      echo json_encode(["success" => true, "message" => "Profile updated successfully!", "profile_picture" => $profile_picture]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating profile: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Admin Portal</title>
    
    <!-- External Resources -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../fonts/material-design-iconic-font/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="../css/student-dark.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        /* Custom layout scrollbars matching overall theme */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(15, 10, 25, 0.4);
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #8B3FD9 0%, #C084FC 100%);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #7C2D12 0%, #D4870A 100%);
        }

        body {
            background-color: #0c071a !important;
            font-family: 'Inter', sans-serif;
            color: #e2e8f0;
            overflow-x: hidden;
            overflow-y: auto;
            min-height: 100vh;
        }

        /* Star Canvas Background for Visual Consistency */
        #star-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        /* Responsive Main Wrapper Margin matching sidebar states */
        .main-wrapper {
            margin-left: 280px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 10;
        }

        body.sidebar-minimized .main-wrapper {
            margin-left: 80px;
        }

        /* Profile Glassmorphism Card styling */
        .profile-card {
            background: rgba(30, 25, 50, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(139, 63, 217, 0.05) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Breathtaking Profile Avatar Frame */
        .profile-avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 35px auto;
            z-index: 1;
        }

        .profile-avatar-ring {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            padding: 4px;
            background: linear-gradient(135deg, #8B3FD9 0%, #D4870A 100%);
            box-shadow: 0 0 25px rgba(139, 63, 217, 0.45);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .profile-avatar-container:hover .profile-avatar-ring {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(212, 135, 10, 0.65);
        }

        .profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #110c26;
        }

        .avatar-edit-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(15, 10, 28, 0.75);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            border: 3px solid #110c26;
        }

        .profile-avatar-container:hover .avatar-edit-overlay {
            opacity: 1;
        }

        /* Sleek form styling */
        .input-group {
            position: relative;
            margin-bottom: 24px;
            z-index: 1;
        }

        .input-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #9a8fb0;
            margin-bottom: 8px;
        }

        .input-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #8B3FD9;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .input-field {
            width: 100%;
            background: rgba(15, 10, 25, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 10px !important;
            padding: 12px 16px 12px 48px !important;
            color: #f8fafc !important;
            font-size: 0.95rem !important;
            height: 48px !important;
            transition: all 0.3s ease !important;
        }

        .input-field:focus {
            border-color: #8B3FD9 !important;
            box-shadow: 0 0 14px rgba(139, 63, 217, 0.4) !important;
            background: rgba(15, 10, 25, 0.75) !important;
            outline: none !important;
        }

        .input-field:focus + .input-icon {
            color: #C084FC;
        }

        .input-field:disabled {
            background: rgba(255, 255, 255, 0.02) !important;
            color: #64748b !important;
            cursor: not-allowed;
            border-color: rgba(255, 255, 255, 0.03) !important;
        }

        /* Premium Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #8B3FD9 0%, #C084FC 100%);
            color: white;
            font-weight: 600;
            letter-spacing: 0.03em;
            border-radius: 10px;
            padding: 12px 28px;
            box-shadow: 0 4px 15px rgba(139, 63, 217, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            width: 100%;
            justify-content: center;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 63, 217, 0.55);
        }

        .btn-outline {
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #9a8fb0;
            font-weight: 500;
            border-radius: 10px;
            padding: 12px 28px;
            background: transparent;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.04);
            color: #f8fafc;
            border-color: rgba(255, 255, 255, 0.25);
        }

        /* Beautiful floating toast notification */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 12px;
            background: rgba(21, 15, 46, 0.95);
            border-left: 5px solid #8B3FD9;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            color: #f8fafc;
            z-index: 10000;
            transform: translateY(150%);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
        }
        .toast.success {
            border-left-color: #10B981;
        }
        .toast.error {
            border-left-color: #EF4444;
        }
        .toast.show {
            transform: translateY(0);
        }
    </style>
</head>
<body class="antialiased">
    <!-- Star Canvas Background for Visual Consistency -->
    <canvas id="star-canvas"></canvas>

    <!-- Include Left Navigation Sidebar -->
    <?php include 'sidebarad.php'; ?>
    
    <!-- Main Content Area with matched responsiveness wrapper -->
    <div class="main-wrapper">
        
        <!-- Matched top header navbar from Dashboard (headerad.php) -->
        <?php include 'headerad.php'; ?>
        
        <!-- Profile Page Content Container -->
        <div class="flex-1 p-8 flex items-center justify-center main-content-scroll">
            <div class="w-full max-w-2xl my-auto">
                <div class="profile-card">
                    
                    <!-- Header title -->
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold tracking-wide text-white uppercase" style="font-family: 'Orbitron', sans-serif;">
                            <i class="fas fa-user-cog text-violet-500 mr-2"></i> Account Profile Settings
                        </h3>
                        <p class="text-sm text-[#9a8fb0] mt-2">Manage your admin login credentials and personal profile.</p>
                    </div>
                    
                    <!-- Main form -->
                    <form id="profileForm" method="post" enctype="multipart/form-data">
                        
                        <!-- Interactive Profile Avatar Upload Container -->
                        <div class="profile-avatar-container">
                            <label for="profile-picture-upload" class="cursor-pointer">
                                <div class="profile-avatar-ring">
                                    <?php 
                                    $hasProf = isset($user['profile_picture']) && $user['profile_picture'] !== '' && $user['profile_picture'] !== 'default-profile.png' && file_exists('../upload/' . $user['profile_picture']);
                                    $defaultAvatarSvg = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23c084fc'><rect width='100%25' height='100%25' fill='%23161326'/><path d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/></svg>";
                                    ?>
                                    <img id="profile-picture-preview" 
                                         src="<?php echo $hasProf ? '../upload/' . htmlspecialchars($user['profile_picture']) : $defaultAvatarSvg; ?>" 
                                         alt="Profile Picture" 
                                         class="profile-avatar-img"/>
                                </div>
                                <div class="avatar-edit-overlay">
                                    <i class="fas fa-camera text-xl text-white mb-1"></i>
                                    <span class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider">Change Photo</span>
                                </div>
                            </label>
                            <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden" accept="image/*"/>
                        </div>
                        
                        <!-- Form Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                            
                            <!-- ID Number -->
                            <div class="input-group md:col-span-2">
                                <label class="input-label">Admin ID Number</label>
                                <div class="input-field-wrapper">
                                    <input class="input-field" id="idno" name="idno" placeholder="ID Number" type="text" 
                                           value="<?php echo isset($user['idno']) ? htmlspecialchars($user['idno']) : ''; ?>" readonly />
                                    <i class="zmdi zmdi-card input-icon"></i>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-1"><i class="fas fa-lock mr-1"></i> ID numbers are permanent and cannot be modified.</p>
                            </div>
                            
                            <!-- First Name -->
                            <div class="input-group">
                                <label class="input-label">First Name</label>
                                <div class="input-field-wrapper">
                                    <input class="input-field" id="firstname" name="firstname" placeholder="First Name" type="text" required
                                           value="<?php echo isset($user['firstname']) ? htmlspecialchars($user['firstname']) : ''; ?>"/>
                                    <i class="zmdi zmdi-account input-icon"></i>
                                </div>
                            </div>

                            <!-- Last Name -->
                            <div class="input-group">
                                <label class="input-label">Last Name</label>
                                <div class="input-field-wrapper">
                                    <input class="input-field" id="lastname" name="lastname" placeholder="Last Name" type="text" required
                                           value="<?php echo isset($user['lastname']) ? htmlspecialchars($user['lastname']) : ''; ?>"/>
                                    <i class="zmdi zmdi-account input-icon"></i>
                                </div>
                            </div>

                            <!-- Middle Name -->
                            <div class="input-group md:col-span-2">
                                <label class="input-label">Middle Name (Optional)</label>
                                <div class="input-field-wrapper">
                                    <input class="input-field" id="middlename" name="middlename" placeholder="Middle Name" type="text"
                                           value="<?php echo isset($user['middlename']) ? htmlspecialchars($user['middlename']) : ''; ?>"/>
                                    <i class="zmdi zmdi-account input-icon"></i>
                                </div>
                            </div>
                            
                            <!-- Email Address -->
                            <div class="input-group md:col-span-2">
                                <label class="input-label">Email Address</label>
                                <div class="input-field-wrapper">
                                    <input class="input-field" id="email" name="email" placeholder="Email Address" type="email" required
                                           value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>"/>
                                    <i class="zmdi zmdi-email input-icon"></i>
                                </div>
                            </div>
                            
                            <!-- New Password -->
                            <div class="input-group md:col-span-2">
                                <label class="input-label">Update Password (Leave blank to keep current)</label>
                                <div class="input-field-wrapper">
                                    <input type="password" name="password" id="password" placeholder="••••••••" 
                                           class="input-field"/>
                                    <i class="zmdi zmdi-lock input-icon"></i>
                                    <i class="fas fa-eye absolute right-4 text-gray-500 cursor-pointer hover:text-white transition-colors" id="togglePassword"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Control Buttons -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6">
                            <button class="btn-outline" type="button" onclick="window.location.href='adminIndex.php'">
                                <i class="fas fa-times-circle mr-2"></i> Cancel
                            </button>
                            <button class="btn-gradient" type="submit" id="btnSave">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Customized Toast Alert Notice Container -->
    <div id="toast" class="toast">
        <i id="toast-icon" class="fas fa-info-circle"></i>
        <span id="toast-message">Notification</span>
    </div>

    <!-- Starfield Animation script matched from Dashboard -->
    <script>
        (function(){
            const canvas = document.getElementById('star-canvas');
            const ctx = canvas.getContext('2d');
            let W, H, stars = [], shoots = [];

            function resize() {
                W = canvas.width  = window.innerWidth;
                H = canvas.height = window.innerHeight;
            }
            window.addEventListener('resize', resize);
            resize();

            for (let i = 0; i < 150; i++) {
                stars.push({
                    x: Math.random() * 9999,
                    y: Math.random() * 9999,
                    r: Math.random() * 1.2 + 0.3,
                    a: Math.random(),
                    da: (Math.random() * 0.005 + 0.002) * (Math.random() < .5 ? 1 : -1)
                });
            }

            function spawnShoot() {
                shoots.push({
                    x: Math.random() * W * 1.2,
                    y: Math.random() * H * 0.5,
                    len: Math.random() * 100 + 50,
                    speed: Math.random() * 5 + 3,
                    angle: Math.PI / 4,
                    alpha: 1
                });
            }
            setInterval(spawnShoot, 3000);

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
                    s.alpha -= 0.015;

                    const grad = ctx.createLinearGradient(
                        s.x - Math.cos(s.angle) * s.len,
                        s.y - Math.sin(s.angle) * s.len,
                        s.x, s.y
                    );
                    grad.addColorStop(0, `rgba(212,135,10,0)`);
                    grad.addColorStop(1, `rgba(200,160,255,${s.alpha.toFixed(2)})`);

                    ctx.beginPath();
                    ctx.moveTo(s.x - Math.cos(s.angle) * s.len, s.y - Math.sin(s.angle) * s.len);
                    ctx.lineTo(s.x, s.y);
                    ctx.strokeStyle = grad;
                    ctx.lineWidth = 1;
                    ctx.stroke();

                    if (s.alpha <= 0) shoots.splice(i, 1);
                });
                requestAnimationFrame(draw);
            }
            draw();
        })();

        // Custom interactive toast notifier
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const msg = document.getElementById('toast-message');
            
            toast.className = 'toast ' + type + ' show';
            msg.textContent = message;
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle text-emerald-400 text-lg';
            } else if (type === 'error') {
                icon.className = 'fas fa-exclamation-circle text-rose-400 text-lg';
            } else {
                icon.className = 'fas fa-info-circle text-blue-400 text-lg';
            }
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Toggle password show/hide eye icon
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("password");
        
        togglePassword.addEventListener("click", function () {
            const type = passwordInput.type === "password" ? "text" : "password";
            passwordInput.type = type;
            this.classList.toggle("fa-eye");
            this.classList.toggle("fa-eye-slash");
        });

        // Instant avatar uploader frame preview
        document.getElementById("profile-picture-upload").addEventListener("change", function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById("profile-picture-preview").src = e.target.result;
                };
                reader.readAsDataURL(file);
                showToast("New profile picture loaded. Click Save Changes to commit!", "info");
            }
        });

        // Async uploader submission utilizing Fetch API
        document.getElementById("profileForm").addEventListener("submit", function(event) {
            event.preventDefault();
            
            const btnSave = document.getElementById("btnSave");
            const originalText = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Saving...';
            
            let formData = new FormData(this);

            fetch('profilead.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btnSave.disabled = false;
                btnSave.innerHTML = originalText;
                
                if (data.success) {
                    showToast(data.message, "success");
                    
                    if (data.profile_picture) {
                        // Dynamically update avatar preview
                        document.getElementById("profile-picture-preview").src = "../upload/" + data.profile_picture + "?t=" + new Date().getTime();
                        
                        // Dynamically update sidebar profile picture
                        const sidebarImg = document.querySelector('.user-avatar img');
                        if (sidebarImg) {
                            sidebarImg.src = "../upload/" + data.profile_picture + "?t=" + new Date().getTime();
                        }
                    }
                    
                    // Reload to update dynamic PHP session states after brief timeout
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message, "error");
                }
            })
            .catch(error => {
                btnSave.disabled = false;
                btnSave.innerHTML = originalText;
                console.error("Fetch error:", error);
                showToast("An unexpected error occurred while saving.", "error");
            });
        });
    </script>
</body>
</html>