<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

// Fetch current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE IDNum = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found in DB
        session_destroy();
        header("Location: Login.php");
        exit();
    }
    
    $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.png';
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            position: relative;
        }
        
        .dashboard-panel {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(15, 42, 74, 0.08);
            overflow: hidden;
        }

        .panel-header {
            background: var(--primary);
            color: white;
            padding: 16px 20px;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-content {
            padding: 24px;
        }

        .profile-image-container {
            text-align: center;
            margin-bottom: 24px;
        }

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--bg);
            box-shadow: var(--shadow-sm);
        }

        .info-group {
            margin-bottom: 16px; 
            border-bottom: 1px solid var(--bg-alt); 
            padding-bottom: 12px;
        }
        .info-group:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        .announcement-item {
            padding: 16px 0;
            border-bottom: 1px solid var(--bg-alt);
        }
        .announcement-item:last-child {
            border-bottom: none;
        }
        .announcement-title {
            font-weight: 700;
            color: var(--primary);
            font-size: 15px;
            margin-bottom: 8px;
        }
        .announcement-meta {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 42, 74, 0.4);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--surface);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 600px;
            padding: 32px;
            box-shadow: var(--shadow-xl);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
        }
        .modal-title { font-size: 20px; font-weight: 800; color: var(--primary); }
        .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted); }
        .close-btn:hover { color: var(--error); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="navbar-brand">
                <img src="../../wwwroots/favIcon/ccsLogo.png" alt="CCS" class="brand-icon">
                CCS Sit-in Monitoring System
            </a>
            <ul class="navbar-links">
                <li><a href="#" class="nav-active">Dashboard</a></li>
                <li><a href="?logout=1" style="color: #fca5a5;">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content" style="padding: 32px 24px; display: block;">
        
        <?php if (isset($_SESSION['login_success'])): ?>
            <div class="alert alert-success" style="max-width: 1200px; margin: 0 auto 24px auto;">
                <?php 
                    echo $_SESSION['login_success']; 
                    unset($_SESSION['login_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['profile_error'])): ?>
            <div class="alert alert-danger" style="max-width: 1200px; margin: 0 auto 24px auto;">
                <?php 
                    echo $_SESSION['profile_error']; 
                    unset($_SESSION['profile_error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-layout">
            <!-- Left Pane: Student Info -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    Student Information
                </div>
                <div class="panel-content">
                    <div class="profile-image-container">
                        <!-- We use an onerror fallback in case the image doesn't load/exist -->
                        <img src="uploads/<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="profile-img" onerror="this.src='../../wwwroots/favIcon/ccsLogo.png'">
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars(trim($user['FName'] . ' ' . $user['MName'] . ' ' . $user['LName'])); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">ID Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['IDNum']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Course</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['course']); ?></div>
                    </div>
                    <div class="info-group" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <button type="button" class="btn-login" style="margin-top: 24px; margin-bottom: 0;" onclick="document.getElementById('editModal').classList.add('active')">Edit Profile</button>
                </div>
            </div>

            <!-- Right Pane: Announcements -->
            <div class="dashboard-panel">
                <div class="panel-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                    Announcement
                </div>
                <div class="panel-content" style="padding: 0 24px;">
                    <?php
                        try {
                            $annstmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC");
                            $announcements = $annstmt->fetchAll();
                            
                            if (count($announcements) > 0) {
                                foreach ($announcements as $ann) {
                                    $date = date('Y-M-d', strtotime($ann['created_at']));
                                    echo '<div class="announcement-item" style="padding: 16px 0; border-bottom: 1px solid var(--bg-alt);">';
                                    echo '<div class="announcement-title" style="font-weight: 700; color: var(--primary); font-size: 15px; margin-bottom: 8px;">' . htmlspecialchars($ann['author'] . ' | ' . $date) . '</div>';
                                    echo '<div class="announcement-meta" style="font-size: 13px; color: var(--text-muted);">' . nl2br(htmlspecialchars($ann['content'])) . '</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="announcement-item" style="padding: 16px 0;"><div class="announcement-meta" style="font-size: 13px; color: var(--text-muted);">No announcements posted yet.</div></div>';
                            }
                        } catch (PDOException $e) {
                            echo '<div class="announcement-item"><div class="announcement-meta">Unable to load announcements.</div></div>';
                        }
                    ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit Profile</div>
                <button type="button" class="close-btn" onclick="document.getElementById('editModal').classList.remove('active')">&times;</button>
            </div>
            
            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/png, image/jpeg, image/jpg, image/gif" style="background: white; padding: 8px;">
                    <small class="form-helper">Optional. Recommended size: 150x150px.</small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" name="fname" id="fname" value="<?php echo htmlspecialchars($user['FName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" name="lname" id="lname" value="<?php echo htmlspecialchars($user['LName']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="mname">Middle Name</label>
                        <input type="text" name="mname" id="mname" value="<?php echo htmlspecialchars($user['MName']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="course">Course</label>
                        <select name="course" id="course" required>
                            <option value="BSIT" <?php if($user['course']=='BSIT') echo 'selected'; ?>>BSIT</option>
                            <option value="BSCS" <?php if($user['course']=='BSCS') echo 'selected'; ?>>BSCS</option>
                            <option value="BSIS" <?php if($user['course']=='BSIS') echo 'selected'; ?>>BSIS</option>
                            <option value="ACT" <?php if($user['course']=='ACT') echo 'selected'; ?>>ACT</option>
                        </select>
                    </div>
                    <div class="form-group grid-full">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group grid-full">
                        <label for="address">Address</label>
                        <textarea name="address" id="address" required style="min-height: 60px;"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn-secondary btn-cta" style="padding: 10px 24px;" onclick="document.getElementById('editModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn-primary btn-cta" style="padding: 10px 24px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer">
        &copy; 2024 College of Computer Studies &mdash; University of Cebu
    </footer>

</body>
</html>