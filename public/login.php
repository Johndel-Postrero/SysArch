<?php
require __DIR__ . '/../config/db.php';
session_start();
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idno = filter_input(INPUT_POST, 'idno');
    $password = filter_input(INPUT_POST, 'password');

    $sql = "SELECT * FROM users WHERE idno = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if (password_verify($password, $user['password']) || $password === $user['password']) {
            $_SESSION['login_user'] = $user['idno']; 
            $_SESSION['idno'] = $user['idno']; 
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['middlename'] = $user['middlename'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['login_success'] = true;
            header("Location: login.php");
            exit();
        } else {
            $error = "Invalid ID Number or password";
        }
    } else {
        $error = "Invalid ID Number or password";
    }
    $stmt->close();
    $conn->close();
}
?>

<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In – Sit-In Monitoring</title>
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
        .blob-3 { bottom: 20%; right: 5%; width: 300px; height: 300px; background: var(--gold); }

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
            max-width: 520px;
            min-height: 635px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 48px;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5), inset 0 0 20px rgba(139,63,217,0.05);
        }
        
        .card-header { text-align: center; margin-bottom: 48px; }
        .card-header h2 { font-family: var(--font-h); font-size: 32px; font-weight: 600; }
        .card-header h2 .outline { -webkit-text-stroke: 1px var(--text-main); color: transparent; font-weight: 700; }
        .card-header p { font-size: 13px; color: var(--text-sub); margin-top: 8px; }

        .role-tabs { display: flex; gap: 12px; margin-bottom: 32px; }
        .tab { 
            flex: 1; padding: 12px; text-align: center; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); color: var(--text-sub); transition: all 0.3s; 
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .tab.active { background: linear-gradient(90deg, #6b21a8, #9333ea); border-color: transparent; color: #fff; box-shadow: 0 4px 15px rgba(139,63,217,0.4); }

        .input-group { margin-bottom: 32px; text-align: left; }
        .input-group label { display: block; font-size: 12px; color: var(--text-main); margin-bottom: 8px; }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%; padding: 18px 16px; background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.15); border-radius: 8px;
            color: #fff; font-family: var(--font-b); font-size: 14px;
            transition: all 0.3s;
        }
        .input-wrapper input:focus { outline: none; border-color: var(--purple-lt); box-shadow: 0 0 0 3px rgba(139,63,217,0.2); }
        .input-wrapper .toggle-pwd { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--text-sub); cursor: pointer; }

        .form-opts { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; font-size: 13px; }
        .form-opts label { display: flex; align-items: center; gap: 8px; color: var(--text-sub); cursor: pointer; }
        .form-opts input[type="checkbox"] { accent-color: var(--gold); }
        .form-opts a { color: var(--gold); text-decoration: none; transition: color 0.2s; }
        .form-opts a:hover { color: #E09B1A; text-decoration: underline; }

        .btn-submit {
            width: 100%; padding: 18px; border: none; border-radius: 8px;
            background: linear-gradient(90deg, #8B3FD9, #D4870A);
            color: #fff; font-family: var(--font-b); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(139,63,217,0.4);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(139,63,217,0.6); }

        .register-link { text-align: center; margin-top: 24px; font-size: 12px; color: var(--text-sub); }
        .register-link a { color: var(--gold); text-decoration: none; font-weight: 600; margin-left: 4px; }
        .register-link a:hover { text-decoration: underline; }

        .card-footer { display: flex; justify-content: center; gap: 24px; margin-top: 32px; font-size: 10px; color: var(--text-sub); }
        .card-footer span { display: flex; align-items: center; gap: 6px; }
        .card-footer span i { color: var(--gold); }

        .error-msg { background: rgba(255,0,0,0.1); color: #ff6b6b; border: 1px solid rgba(255,0,0,0.2); padding: 12px; border-radius: 8px; font-size: 13px; text-align: center; margin-bottom: 20px; }

        /* Dialog */
        #successDialog { border: none; border-radius: 12px; background: var(--bg); color: #fff; padding: 30px; width: 320px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.8); position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 1px solid var(--border); }
        #successDialog p { font-family: var(--font-h); font-size: 18px; color: var(--purple-lt); margin-bottom: 20px; }
        #closeDialog { background: var(--purple); color: #fff; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        #successDialog::backdrop { background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }

        .bottom-copy { 
            position: absolute; bottom: 0; width: 100%; 
            background: rgba(13, 11, 26, 0.85); 
            border-top: 1px solid rgba(139, 63, 217, 0.3); 
            padding: 16px 0; 
            text-align: center; font-size: 11px; color: var(--text-sub); z-index: 2; 
        }

        @media (max-width: 900px) {
            .login-wrapper { flex-direction: column; overflow-y: auto; height: 100vh;}
            .login-left, .login-right { width: 100%; padding: 20px; }
            .login-left { margin-top: 60px; }
            .login-card { padding: 30px 24px; }
            .blob { display: none; }
            .bottom-copy { position: relative; padding-bottom: 20px; }
            body { overflow-y: auto; }
        }
    </style>
</head>
<body>

<canvas id="star-canvas"></canvas>
<div class="blob blob-1"></div>
<div class="blob blob-2"></div>
<div class="blob blob-3"></div>

<a href="landingindex.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>

<div class="login-wrapper">
    <!-- LEFT SIDE -->
    <div class="login-left">
        <img src="resources/ccslogo.png" alt="CCS Logo" class="login-logo">
        <h1 class="login-title">SIT-IN MONITORING</h1>
        <p class="login-sub">Continue Your Journey</p>
        <img src="resources/Woman Coding GIF by Pluralsight.gif" alt="Coding GIF" class="login-gif">
    </div>

    <!-- RIGHT SIDE -->
    <div class="login-right">
        <div class="login-card">
            <div class="card-header">
                <h2>Welcome <span class="outline">Back</span></h2>
                <p>Sit-In Monitoring System — UC CCS</p>
            </div>

            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>



            <form action="login.php" method="post">
                <div class="input-group">
                    <label>ID Number</label>
                    <div class="input-wrapper">
                        <input type="text" name="idno" required placeholder="Enter your ID Number">
                    </div>
                </div>
                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" required placeholder="Enter your password">
                        <i class="fas fa-eye toggle-pwd" id="togglePassword"></i>
                    </div>
                </div>
                <div class="form-opts">
                    <label><input type="checkbox"> Remember Me</label>
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="register-link">
                Don't have an account? <a href="register.php">Register Here</a>
            </div>


        </div>
    </div>
</div>

<div class="bottom-copy">
    © 2026 University of Cebu - College of Computer Studies. All rights reserved.
</div>

<dialog id="successDialog">
    <p>Login Successful!</p>
    <button id="closeDialog">Continue</button>
</dialog>

<script>
    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passInput = document.getElementById('password');
        if (passInput.type === 'password') {
            passInput.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });

    // Success dialog logic
    <?php if (isset($_SESSION['login_success']) && $_SESSION['login_success'] === true): ?>
        const dialog = document.getElementById("successDialog");
        if (dialog) { dialog.showModal(); }
        <?php unset($_SESSION['login_success']); ?> 

        document.getElementById("closeDialog").addEventListener("click", function () {
            dialog.close();
            window.location.href = "<?php echo (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') ? './Admin/adminIndex.php' : 'index.php'; ?>";
        });
    <?php endif; ?>

    // Canvas Background Animation
    const canvas = document.getElementById('star-canvas');
    const ctx = canvas.getContext('2d');
    let width, height;
    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();
    const stars = [];
    for(let i=0; i<180; i++) {
        stars.push({
            x: Math.random() * width,
            y: Math.random() * height,
            r: Math.random() * 1.5,
            opacity: Math.random(),
            speed: (Math.random() * 0.02) + 0.005,
            dir: Math.random() > 0.5 ? 1 : -1
        });
    }
    const shootingStars = [];
    function spawnShootingStar() {
        shootingStars.push({
            x: Math.random() * width * 1.5,
            y: 0,
            len: (Math.random() * 80) + 40,
            speed: (Math.random() * 8) + 6,
            opacity: 1
        });
        setTimeout(spawnShootingStar, (Math.random() * 4000) + 2000);
    }
    setTimeout(spawnShootingStar, 1000);
    function animate() {
        ctx.clearRect(0, 0, width, height);
        stars.forEach(s => {
            s.opacity += s.speed * s.dir;
            if(s.opacity >= 1) { s.opacity = 1; s.dir = -1; }
            else if(s.opacity <= 0.1) { s.opacity = 0.1; s.dir = 1; }
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${s.opacity})`;
            ctx.fill();
        });
        for(let i=shootingStars.length-1; i>=0; i--) {
            let ss = shootingStars[i];
            ss.x -= ss.speed;
            ss.y += ss.speed;
            ss.opacity -= 0.01;
            if(ss.opacity <= 0) {
                shootingStars.splice(i, 1);
                continue;
            }
            const grad = ctx.createLinearGradient(ss.x, ss.y, ss.x + ss.len, ss.y - ss.len);
            grad.addColorStop(0, `rgba(255, 255, 255, ${ss.opacity})`);
            grad.addColorStop(0.3, `rgba(212, 135, 10, ${ss.opacity * 0.8})`);
            grad.addColorStop(1, 'rgba(139, 63, 217, 0)');
            ctx.beginPath();
            ctx.moveTo(ss.x, ss.y);
            ctx.lineTo(ss.x + ss.len, ss.y - ss.len);
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1.5;
            ctx.stroke();
        }
        requestAnimationFrame(animate);
    }
    animate();
</script>
</body>
</html>
