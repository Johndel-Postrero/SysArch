<?php
session_start();
require_once 'db_config.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idNum = $_POST['IDNum'];
    $lName = $_POST['LName'];
    $fName = $_POST['FName'];
    $mName = $_POST['MName'];
    $level = $_POST['level'];
    $password = $_POST['password'];
    $repeatPassword = $_POST['repeat_password'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $address = $_POST['address'];

    if (empty($idNum) || empty($lName) || empty($fName) || empty($level) || empty($password) || empty($repeatPassword) || empty($email) || empty($course) || empty($address)) {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    } elseif ($password !== $repeatPassword) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE IDNum = ? OR email = ?");
            $stmt->execute([$idNum, $email]);
            if ($stmt->rowCount() > 0) {
                $message = "ID Number or Email already registered.";
                $messageType = "danger";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (IDNum, LName, FName, MName, level, password, email, course, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$idNum, $lName, $fName, $mName, $level, $hashedPassword, $email, $course, $address])) {
                    $message = "Registration successful! You can now <a href='Login.php' style='color: inherit; text-decoration: underline; font-weight: 700;'>login</a>.";
                    $messageType = "success";
                } else {
                    $message = "Something went wrong. Please try again.";
                    $messageType = "danger";
                }
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CCS Sit-in Monitoring System</title>
    <meta name="description" content="Register for the College of Computer Studies Sit-in Monitoring System">
    <link rel="stylesheet" href="../../wwwroots/ccs/site.css">
    <link rel="icon" type="image/png" href="../../wwwroots/favIcon/ccsLogo.png">
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="navbar-brand">
                <img src="../../wwwroots/favIcon/ccsLogo.png" alt="CCS" class="brand-icon">
                CCS Sit-in Monitoring System
            </a>
            <ul class="navbar-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="Login.php">Login</a></li>
                <li><a href="Register.php" class="nav-active">Register</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="login-container register-layout">

            <div class="logo-section">
                <img src="../../wwwroots/img/registration-illustration.png" alt="Registration Illustration" style="width: 440px;">
            </div>

            <div class="form-section register-width" style="position: relative;">
                <a href="index.php" class="btn-back">&larr;</a>
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Join the CCS Sit-in Monitoring System</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" id="registerForm">
                    <span class="form-section-label">Student Information</span>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="idNumber">ID Number</label>
                            <input type="text" id="idNumber" name="IDNum" placeholder="e.g. 21411277" pattern="\d{8}" title="Must be exactly 8 digits" maxlength="8" required value="<?php echo isset($_POST['IDNum']) ? htmlspecialchars($_POST['IDNum']) : ''; ?>">
                            <small class="form-helper">Format: xxxxxxxx (e.g., 21411277)</small>
                        </div>

                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="LName" placeholder="Surname" required value="<?php echo isset($_POST['LName']) ? htmlspecialchars($_POST['LName']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="FName" placeholder="Given Name" required value="<?php echo isset($_POST['FName']) ? htmlspecialchars($_POST['FName']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="MName" placeholder="Optional" value="<?php echo isset($_POST['MName']) ? htmlspecialchars($_POST['MName']) : ''; ?>">
                        </div>
                    </div>

                    <span class="form-section-label">Academic Details</span>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="level">Year Level</label>
                            <select id="level" name="level" required>
                                <option value="" disabled <?php echo !isset($_POST['level']) ? 'selected' : ''; ?>>Select level</option>
                                <option value="1" <?php echo (isset($_POST['level']) && $_POST['level'] == '1') ? 'selected' : ''; ?>>1st Year</option>
                                <option value="2" <?php echo (isset($_POST['level']) && $_POST['level'] == '2') ? 'selected' : ''; ?>>2nd Year</option>
                                <option value="3" <?php echo (isset($_POST['level']) && $_POST['level'] == '3') ? 'selected' : ''; ?>>3rd Year</option>
                                <option value="4" <?php echo (isset($_POST['level']) && $_POST['level'] == '4') ? 'selected' : ''; ?>>4th Year</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="course">Course</label>
                            <select id="course" name="course" required>
                                <option value="" disabled <?php echo !isset($_POST['course']) ? 'selected' : ''; ?>>Select course</option>
                                <option value="BSIT" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIT') ? 'selected' : ''; ?>>BS Information Technology</option>
                                <option value="BSCS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSCS') ? 'selected' : ''; ?>>BS Computer Science</option>
                                <option value="BSIS" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BSIS') ? 'selected' : ''; ?>>BS Information Systems</option>
                            </select>
                        </div>
                    </div>

                    <span class="form-section-label">Account Details</span>
                    <div class="form-grid">
                        <div class="form-group grid-full">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="student@university.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required>
                        </div>

                        <div class="form-group">
                            <label for="repeat_password">Confirm Password</label>
                            <input type="password" id="repeat_password" name="repeat_password" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <span class="form-section-label">Address</span>
                    <div class="form-group">
                        <label for="address">Home Address</label>
                        <textarea id="address" name="address" placeholder="St., Brgy, City, Province" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <button type="submit" class="btn-login">Create Account</button>

                    <p class="register-text">Already have an account? <a href="Login.php">Login</a></p>
                </form>
            </div>

        </div>
    </div>

    <footer class="footer">
        &copy; 2024 College of Computer Studies &mdash; University of Cebu
    </footer>

</body>
</html>