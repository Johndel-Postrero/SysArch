<?php
require __DIR__ . '/../config/db.php';
$error = '';
$error1 = '';
$error2 = '';
$error3 = '';

// Define available courses based on the enum values
$courses = ['BSIT', 'BSCS', 'HM', 'CRIM', 'CBA'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idno = filter_input(INPUT_POST, 'idno');
    $lastname = filter_input(INPUT_POST, 'lastname');
    $firstname = filter_input(INPUT_POST, 'firstname');
    $middlename = filter_input(INPUT_POST, 'middlename');
    $course = filter_input(INPUT_POST, 'course');
    $level = filter_input(INPUT_POST, 'level');
    $email = filter_input(INPUT_POST, 'email');
    $password = $_POST['password'];
    
    // Automatically set role to 'student'
    $role = 'Student';

    // Check for duplicate idno, email, username as before...
    $check_sql = "SELECT * FROM users WHERE idno = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result1 = $stmt->get_result();
    $stmt->close();
    
    if ($result1->num_rows > 0) {
        $error = "Id number already exists!";
    } else {
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result2 = $stmt->get_result();
        $stmt->close();

        if($result2->num_rows > 0){
            $error1 = "Email already exists!";
        } else {
            // Hash the password
            $password = password_hash($password, PASSWORD_DEFAULT);

                // Create a default profile picture filename from initials
                $initials = '';
                if (!empty($firstname)) {
                    $initials .= strtoupper(substr($firstname, 0, 1));
                }
                if (!empty($lastname)) {
                    $initials .= strtoupper(substr($lastname, 0, 1));
                }
                $profile_picture = $initials . '.png';

                // Insert the new user into the database
                $sql = "INSERT INTO users (idno, lastname, firstname, middlename, course, level, email, password, role, profile_picture) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssissss", $idno, $lastname, $firstname, $middlename, $course, $level, $email, $password, $role, $profile_picture);
        
                if ($stmt->execute()) {
                    header("Location: register.php?success=true");
                    exit();
                } else {
                    echo "<p style='color:red;'>Error: " . $stmt->error . "</p>";
                }
                $stmt->close();
            }
        }
    }

    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Sit-In Monitoring</title>
    <link rel="icon" type="image/png" href="resources/ccslogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg:            #0D0B1A;
            --card-bg:       rgba(26, 21, 48, 0.45);
            --purple:        #8B3FD9;
            --purple-lt:     #C084FC;
            --gold:          #D4870A;
            --border:        rgba(139,63,217,0.3);
            --text-main:     #ffffff;
            --text-sub:      #9A8FB0;
            --font-h:        'Orbitron', sans-serif;
            --font-b:        'Inter', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg); 
            font-family: var(--font-b); 
            color: var(--text-main); 
            overflow: hidden; 
            min-height: 100vh;
        }

        #star-canvas {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.15;
            z-index: 0;
        }
        .blob-1 { top: -10%; right: 10%; width: 500px; height: 500px; background: var(--purple-lt); }
        .blob-2 { bottom: -10%; left: -5%; width: 400px; height: 400px; background: var(--gold); }

        .back-btn {
            position: absolute;
            top: 30px; left: 40px;
            width: 45px; height: 45px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid var(--gold);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; text-decoration: none;
            z-index: 10; transition: all 0.3s;
        }
        .back-btn:hover { background: rgba(212,135,10,0.1); box-shadow: 0 0 15px rgba(212,135,10,0.3); transform: scale(1.05); }

        .login-wrapper {
            position: relative;
            z-index: 2;
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* Left Side */
        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            padding-left: 80px;
        }
        .login-logo { width: clamp(160px, 15vw, 220px); margin-bottom: 24px; filter: drop-shadow(0 0 20px rgba(139,63,217,0.5)); animation: float 4s ease-in-out infinite; }
        .login-title { font-family: var(--font-h); font-size: clamp(28px, 3vw, 42px); font-weight: 700; color: var(--purple-lt); text-align: center; letter-spacing: 2px; }
        .login-sub { font-size: 15px; color: var(--text-main); margin-top: 8px; margin-bottom: 40px; }
        .login-gif { width: 85%; max-width: 480px; border-radius: 12px; mix-blend-mode: screen; opacity: 0.95; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Right Side (Form) */
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            padding-right: 80px;
        }
        .login-card {
            width: 100%;
            max-width: 560px;
            height: 635px;
            display: flex;
            flex-direction: column;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 48px;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5), inset 0 0 20px rgba(139,63,217,0.05);
            position: relative;
        }
        
        .card-header { text-align: center; margin-bottom: 24px; flex-shrink: 0; }
        .card-header h2 { font-family: var(--font-h); font-size: 32px; font-weight: 600; }
        .card-header h2 .outline { -webkit-text-stroke: 1px var(--text-main); color: transparent; font-weight: 700; }
        .card-header p { font-size: 13px; color: var(--text-sub); margin-top: 8px; }

        /* Stepper */
        .stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 56px;
            padding: 0 20px;
            position: relative;
        }
        .step {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            z-index: 2;
        }
        .step .circle {
            width: 26px; height: 26px;
            border-radius: 50%;
            border: 2px solid var(--text-sub);
            background: transparent;
            color: var(--text-sub);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700; transition: all 0.3s;
        }
        .step.active .circle, .step.completed .circle {
            border-color: var(--purple-lt);
            background: var(--purple-lt);
            color: #fff;
            box-shadow: 0 0 10px rgba(192, 132, 252, 0.5);
        }
        .step span {
            position: absolute; top: 34px; width: 140px; text-align: center; font-size: 11px; color: var(--text-sub); transition: color 0.3s;
        }
        .step.active span { color: var(--purple-lt); font-weight: 600; }
        
        .step-line {
            flex: 1; height: 2px; background: var(--text-sub); opacity: 0.3; margin: 0 8px; transition: all 0.3s;
        }
        .step-line.active { background: var(--purple-lt); opacity: 1; }

        /* Form Content Area */
        .form-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }
        .form-step {
            display: none;
            animation: fadeIn 0.3s ease;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }
        .form-step.active { display: flex; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-pill {
            align-self: flex-start;
            padding: 8px 16px;
            background: rgba(139, 63, 217, 0.2);
            border: 1px solid var(--purple);
            border-radius: 20px;
            font-size: 12px; font-weight: 600; color: #fff;
            margin-bottom: 32px;
            flex-shrink: 0;
        }

        .input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px 20px;
            margin-bottom: 32px;
            flex-shrink: 1;
            overflow-y: auto;
            padding-right: 10px;
            align-content: start;
            min-height: 0;
        }
        
        /* Custom Scrollbar for inputs */
        .input-grid::-webkit-scrollbar { width: 6px; }
        .input-grid::-webkit-scrollbar-track { background: transparent; }
        .input-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }

        .input-grid .full-width { grid-column: span 2; }
        
        .input-group label { display: block; font-size: 11px; color: var(--text-main); margin-bottom: 6px; }
        .input-group label span { color: #ff6b6b; }
        .input-wrapper { position: relative; }
        .input-wrapper input, .input-wrapper select {
            width: 100%; padding: 16px 16px; background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 8px;
            color: #fff; font-family: var(--font-b); font-size: 14px;
            transition: all 0.3s;
        }
        .input-wrapper select { appearance: none; cursor: pointer; }
        .input-wrapper input:focus, .input-wrapper select:focus { outline: none; border-color: var(--purple-lt); box-shadow: 0 0 0 3px rgba(139,63,217,0.2); }
        .input-wrapper select option { background: #1a152e; color: #fff; }

        .btn-group {
            display: flex; gap: 16px; margin-top: auto; flex-shrink: 0;
        }
        
        .btn-submit {
            flex: 1; padding: 16px; border: none; border-radius: 8px;
            background: linear-gradient(90deg, #8B3FD9, #D4870A);
            color: #fff; font-family: var(--font-b); font-size: 14px; font-weight: 600;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(139,63,217,0.4); text-align: center;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(139,63,217,0.6); }
        
        .btn-submit.outline {
            background: transparent;
            border: 1px solid var(--text-sub);
            box-shadow: none;
            color: var(--text-main);
        }
        .btn-submit.outline:hover {
            border-color: #fff; background: rgba(255,255,255,0.05);
        }

        .register-link { text-align: center; margin-top: 24px; font-size: 13px; color: var(--text-sub); flex-shrink: 0; }
        .register-link a { color: var(--gold); text-decoration: none; font-weight: 600; margin-left: 4px; }
        .register-link a:hover { text-decoration: underline; }

        .error-msg { background: rgba(255,0,0,0.1); color: #ff6b6b; border: 1px solid rgba(255,0,0,0.2); padding: 10px; border-radius: 8px; font-size: 12px; text-align: center; margin-bottom: 16px; }

        .bottom-copy { 
            position: absolute; bottom: 0; width: 100%; 
            background: rgba(13, 11, 26, 0.85); 
            border-top: 1px solid rgba(139, 63, 217, 0.3); 
            padding: 16px 0; 
            text-align: center; font-size: 11px; color: var(--text-sub); z-index: 2; 
        }

        /* Dialog */
        #successDialog { border: none; border-radius: 12px; background: var(--bg); color: #fff; padding: 30px; width: 320px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.8); position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 1px solid var(--border); }
        #successDialog p { font-family: var(--font-h); font-size: 18px; color: var(--purple-lt); margin-bottom: 20px; }
        #closeDialog { background: var(--purple); color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        #successDialog::backdrop { background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }

        @media (max-width: 900px) {
            .login-wrapper { flex-direction: column; overflow-y: auto; height: 100vh;}
            .login-left, .login-right { width: 100%; padding: 20px; }
            .login-left { margin-top: 60px; }
            .login-card { padding: 30px 24px; min-height: 500px; }
            .blob { display: none; }
            .bottom-copy { position: relative; padding-bottom: 20px; }
            body { overflow-y: auto; }
            .input-grid { grid-template-columns: 1fr; }
            .input-grid .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>

<canvas id="star-canvas"></canvas>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>

<a href="landingindex.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>

<div class="login-wrapper">
    <!-- LEFT SIDE -->
    <div class="login-left">
        <img src="resources/ccslogo.png" alt="CCS Logo" class="login-logo">
        <h1 class="login-title">SIT-IN MONITORING</h1>
        <p class="login-sub">Begin Your Journey</p>
        <img src="resources/Woman Coding GIF by Pluralsight.gif" alt="Coding GIF" class="login-gif">
    </div>

    <!-- RIGHT SIDE -->
    <div class="login-right">
        <div class="login-card">
            <div class="card-header">
                <h2>Create Your <span class="outline">Account</span></h2>
                <p>Sit-In Monitoring System — UC CCS</p>
            </div>

            <?php if ($error || $error1 || $error2 || $error3): ?>
                <div class="error-msg">
                    <?php 
                        if($error) echo htmlspecialchars($error) . "<br>";
                        if($error1) echo htmlspecialchars($error1) . "<br>";
                        if($error2) echo htmlspecialchars($error2) . "<br>";
                        if($error3) echo htmlspecialchars($error3) . "<br>";
                    ?>
                </div>
            <?php endif; ?>

            <!-- Stepper -->
            <div class="stepper">
                <div class="step active" id="indicator-1" onclick="navToStep(1)">
                    <div class="circle">1</div>
                    <span>Personal Information</span>
                </div>
                <div class="step-line" id="line-1"></div>
                <div class="step" id="indicator-2" onclick="navToStep(2)">
                    <div class="circle">○</div>
                    <span>Academic Info</span>
                </div>
                <div class="step-line" id="line-2"></div>
                <div class="step" id="indicator-3" onclick="navToStep(3)">
                    <div class="circle">○</div>
                    <span>Account Setup</span>
                </div>
            </div>

            <form action="register.php" method="post" id="regForm" class="form-content">
                
                <!-- STEP 1: Personal Info -->
                <div class="form-step active" id="step1">
                    <div class="step-pill">Personal Information</div>
                    <div class="input-grid">
                        <div class="input-group">
                            <label>Last Name <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="text" name="lastname" required placeholder="Enter your Last Name" value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>First Name <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="text" name="firstname" required placeholder="Enter your First Name" value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Middle Name <span style="color:var(--text-sub)">(optional)</span></label>
                            <div class="input-wrapper">
                                <input type="text" name="middlename" placeholder="Enter your Middle Name" value="<?php echo isset($_POST['middlename']) ? htmlspecialchars($_POST['middlename']) : ''; ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Address <span style="color:var(--text-sub)">(optional)</span></label>
                            <div class="input-wrapper">
                                <input type="text" name="address" placeholder="Enter your Address">
                            </div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn-submit" onclick="navToStep(2)">Next Step</button>
                    </div>
                </div>

                <!-- STEP 2: Academic Info -->
                <div class="form-step" id="step2">
                    <div class="step-pill">Academic Information</div>
                    <div class="input-grid">
                        <div class="input-group full-width">
                            <label>ID Number <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="number" name="idno" required placeholder="Enter your ID Number" value="<?php echo isset($_POST['idno']) ? htmlspecialchars($_POST['idno']) : ''; ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Course <span>*</span></label>
                            <div class="input-wrapper">
                                <select name="course" required>
                                    <option value="" disabled <?php echo !isset($_POST['course']) ? 'selected' : ''; ?>>Select Course</option>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo isset($_POST['course']) && $_POST['course'] == $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Course Level <span>*</span></label>
                            <div class="input-wrapper">
                                <select name="level" required>
                                    <option value="" disabled <?php echo !isset($_POST['level']) ? 'selected' : ''; ?>>Year Level</option>
                                    <option value="1" <?php echo isset($_POST['level']) && $_POST['level'] == '1' ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo isset($_POST['level']) && $_POST['level'] == '2' ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo isset($_POST['level']) && $_POST['level'] == '3' ? 'selected' : ''; ?>>3</option>
                                    <option value="4" <?php echo isset($_POST['level']) && $_POST['level'] == '4' ? 'selected' : ''; ?>>4</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn-submit outline" onclick="navToStep(1)">Back</button>
                        <button type="button" class="btn-submit" onclick="navToStep(3)">Next Step</button>
                    </div>
                </div>

                <!-- STEP 3: Account Setup -->
                <div class="form-step" id="step3">
                    <div class="step-pill">Account Setup</div>
                    <div class="input-grid">
                        <div class="input-group full-width">
                            <label>Email Address <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="email" name="email" required placeholder="Enter your email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Password <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="password" name="password" id="password" required placeholder="Create a password">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Repeat Password <span>*</span></label>
                            <div class="input-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repeat password">
                            </div>
                        </div>
                    </div>
                    <div class="btn-group" style="align-items: center; margin-bottom: 12px; padding-top:0;">
                        <label style="font-size:12px; color:var(--text-sub); display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" required style="accent-color: var(--gold);"> I agree to the terms of service
                        </label>
                    </div>
                    <div class="btn-group" style="margin-top: 0; padding-top:0;">
                        <button type="button" class="btn-submit outline" onclick="navToStep(2)">Back</button>
                        <button type="submit" class="btn-submit" id="finalSubmit">Register Account</button>
                    </div>
                </div>

            </form>

            <div class="register-link">
                Already have an account? <a href="login.php">Login Here</a>
            </div>
        </div>
    </div>
</div>

<div class="bottom-copy">
    © 2026 University of Cebu - College of Computer Studies. All rights reserved.
</div>

<dialog id="successDialog">
    <p>Registration Successful!</p>
    <button id="closeDialog">Login Now</button>
</dialog>

<script>
    // Multi-Step Form Logic
    let currentStep = 1;
    function navToStep(step) {
        if (step > currentStep) {
            for (let i = currentStep; i < step; i++) {
                const fields = document.querySelectorAll(`#step${i} input[required], #step${i} select[required]`);
                let isValid = true;
                fields.forEach(field => {
                    if (!field.value) {
                        field.style.borderColor = '#ff6b6b';
                        isValid = false;
                    } else {
                        field.style.borderColor = 'rgba(255,255,255,0.15)';
                    }
                });
                if (!isValid) return; // Prevent going forward if fields are empty
            }
        }
        goToStep(step);
    }

    function goToStep(step) {
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');

        // Update stepper UI
        for (let i = 1; i <= 3; i++) {
            const indicator = document.getElementById(`indicator-${i}`);
            const line = document.getElementById(`line-${i-1}`);
            
            if (i < step) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
                indicator.querySelector('.circle').innerText = '✓';
                if (line) line.classList.add('active');
            } else if (i === step) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
                indicator.querySelector('.circle').innerText = i;
                if (line) line.classList.add('active');
            } else {
                indicator.classList.remove('active', 'completed');
                indicator.querySelector('.circle').innerText = '○';
                if (line) line.classList.remove('active');
            }
        }
        currentStep = step;
    }

    // Password matching validation
    document.getElementById('regForm').addEventListener('submit', function(e) {
        const p1 = document.getElementById('password').value;
        const p2 = document.getElementById('confirm_password').value;
        if (p1 !== p2) {
            e.preventDefault();
            alert("Passwords do not match!");
            document.getElementById('confirm_password').style.borderColor = '#ff6b6b';
        }
    });

    // Success Dialog
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("success") === "true") {
        const dialog = document.getElementById("successDialog");
        if (dialog) {
            dialog.showModal();
            window.history.replaceState({}, document.title, "register.php");
        }
    }
    document.getElementById("closeDialog")?.addEventListener("click", function () {
        document.getElementById("successDialog").close();
        window.location.href = "login.php"; 
    });

    // Canvas Animation
    const canvas = document.getElementById('star-canvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    function resize() { width = canvas.width = window.innerWidth; height = canvas.height = window.innerHeight; }
    window.addEventListener('resize', resize); resize();
    const stars = [];
    for(let i=0; i<180; i++) {
        stars.push({ x: Math.random()*width, y: Math.random()*height, r: Math.random()*1.5, opacity: Math.random(), speed: (Math.random()*0.02)+0.005, dir: Math.random()>0.5?1:-1 });
    }
    const shootingStars = [];
    function spawnShootingStar() {
        shootingStars.push({ x: Math.random() * width * 1.5, y: 0, len: (Math.random() * 80) + 40, speed: (Math.random() * 8) + 6, opacity: 1 });
        setTimeout(spawnShootingStar, (Math.random() * 4000) + 2000);
    }
    setTimeout(spawnShootingStar, 1000);
    function animate() {
        ctx.clearRect(0, 0, width, height);
        stars.forEach(s => {
            s.opacity += s.speed * s.dir;
            if(s.opacity >= 1) { s.opacity = 1; s.dir = -1; }
            else if(s.opacity <= 0.1) { s.opacity = 0.1; s.dir = 1; }
            ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${s.opacity})`; ctx.fill();
        });
        for(let i=shootingStars.length-1; i>=0; i--) {
            let ss = shootingStars[i];
            ss.x -= ss.speed; ss.y += ss.speed; ss.opacity -= 0.01;
            if(ss.opacity <= 0) { shootingStars.splice(i, 1); continue; }
            const grad = ctx.createLinearGradient(ss.x, ss.y, ss.x + ss.len, ss.y - ss.len);
            grad.addColorStop(0, `rgba(255, 255, 255, ${ss.opacity})`);
            grad.addColorStop(0.3, `rgba(212, 135, 10, ${ss.opacity * 0.8})`);
            grad.addColorStop(1, 'rgba(139, 63, 217, 0)');
            ctx.beginPath(); ctx.moveTo(ss.x, ss.y); ctx.lineTo(ss.x + ss.len, ss.y - ss.len);
            ctx.strokeStyle = grad; ctx.lineWidth = 1.5; ctx.stroke();
        }
        requestAnimationFrame(animate);
    }
    animate();
</script>
</body>
</html>
