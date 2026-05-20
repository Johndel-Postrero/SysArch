<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
session_start();
if (!isset($_SESSION['login_user'])) { header("Location: login.php"); exit(); }
require __DIR__ . '/../config/db.php';

$courses = ['BSIT', 'BSCS', 'HM', 'CRIM', 'CBA'];
$current_idno = $_SESSION['login_user'];
$user_sql = "SELECT * FROM users WHERE idno = ?";
$stmtUser = $conn->prepare($user_sql);
$stmtUser->bind_param("s", $current_idno);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();
if (!$user) { header("Location: logout.php"); exit(); }
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname']  = $user['lastname'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email      = filter_input(INPUT_POST, 'email',      FILTER_SANITIZE_EMAIL);
    $lastname   = filter_input(INPUT_POST, 'lastname',   FILTER_SANITIZE_STRING);
    $firstname  = filter_input(INPUT_POST, 'firstname',  FILTER_SANITIZE_STRING);
    $middlename = filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_STRING);
    $course     = filter_input(INPUT_POST, 'course',     FILTER_SANITIZE_STRING);
    $level      = filter_input(INPUT_POST, 'level',      FILTER_SANITIZE_STRING);
    $password   = filter_input(INPUT_POST, 'password',   FILTER_SANITIZE_STRING);

    $stmtUser = $conn->prepare($user_sql);
    $stmtUser->bind_param("s", $current_idno);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();
    if (!$user) { echo json_encode(["success"=>false,"message"=>"User not found."]); exit(); }

    $idno = $user['idno'];
    $profile_picture = $user['profile_picture'];

    $stmtCheck = $conn->prepare("SELECT idno FROM users WHERE email = ? AND idno != ?");
    $stmtCheck->bind_param("ss", $email, $idno);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
        echo json_encode(["success"=>false,"message"=>"Email already in use."]);
        $stmtCheck->close(); $conn->close(); exit();
    }
    $stmtCheck->close();

    $updatePassword = false;
    if (!empty($password)) { $hashedPassword = password_hash($password, PASSWORD_DEFAULT); $updatePassword = true; }

    if (!empty($_FILES['profile_picture']['name'])) {
        $targetDir = __DIR__ . '/upload/';
        $fileType  = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        if (!in_array($fileType, ["jpg","jpeg","png","gif"])) {
            echo json_encode(["success"=>false,"message"=>"Invalid file format."]); exit();
        }
        $newFileName   = "profile_" . $idno . "_" . time() . "." . $fileType;
        $targetFilePath = $targetDir . $newFileName;
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
            $profile_picture = $newFileName;
        } else { echo json_encode(["success"=>false,"message"=>"Upload error."]); exit(); }
    }

    if ($updatePassword) {
        $stmt = $conn->prepare("UPDATE users SET email=?,lastname=?,firstname=?,middlename=?,course=?,level=?,password=?,profile_picture=? WHERE idno=?");
        $stmt->bind_param("sssssssss", $email,$lastname,$firstname,$middlename,$course,$level,$hashedPassword,$profile_picture,$idno);
    } else {
        $stmt = $conn->prepare("UPDATE users SET email=?,lastname=?,firstname=?,middlename=?,course=?,level=?,profile_picture=? WHERE idno=?");
        $stmt->bind_param("ssssssss", $email,$lastname,$firstname,$middlename,$course,$level,$profile_picture,$idno);
    }
    echo json_encode($stmt->execute()
        ? ["success"=>true,"message"=>"Profile updated!","profile_picture"=>$profile_picture]
        : ["success"=>false,"message"=>"Update error."]);
    $stmt->close(); $conn->close(); exit();
}
$hasProf = !empty($user['profile_picture']) && $user['profile_picture'] !== 'default-profile.png' && file_exists('upload/'.$user['profile_picture']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings – CCS Sit-In</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .profile-banner {
            height: 120px;
            background: linear-gradient(135deg, rgba(139,63,217,0.4) 0%, rgba(192,132,252,0.2) 50%, rgba(212,135,10,0.15) 100%);
            position: relative;
        }
        .avatar-ring {
            width: 110px; height: 110px;
            border-radius: 50%;
            border: 4px solid var(--purple-glow);
            box-shadow: 0 0 20px rgba(139,63,217,0.5);
            overflow: hidden;
            background: rgba(139,63,217,0.15);
            cursor: pointer;
            transition: box-shadow 0.3s;
            position: relative;
        }
        .avatar-ring:hover { box-shadow: 0 0 30px rgba(139,63,217,0.8); }
        .avatar-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s;
            border-radius: 50%;
        }
        .avatar-ring:hover .avatar-overlay { opacity: 1; }
        .dark-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: #fff;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-family: var(--font-b);
            transition: all 0.3s;
            outline: none;
        }
        .dark-input:focus {
            border-color: var(--purple-glow);
            box-shadow: 0 0 15px rgba(139,63,217,0.2);
            background: rgba(255,255,255,0.08);
        }
        .dark-input::placeholder { color: var(--text-dim); }
        .dark-input[readonly] { opacity: 0.5; cursor: not-allowed; }
        .dark-select {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: #fff;
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-family: var(--font-b);
            outline: none;
            cursor: pointer;
            transition: all 0.3s;
            -webkit-appearance: none;
        }
        .dark-select:focus { border-color: var(--purple-glow); box-shadow: 0 0 15px rgba(139,63,217,0.2); }
        .dark-select option { background: #1A1530; }
        .field-label {
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-dim); margin-bottom: 6px;
        }
        .btn-save {
            background: linear-gradient(135deg, var(--purple-glow), var(--purple-light));
            color: #fff; border: none; padding: 12px 32px;
            border-radius: 12px; font-weight: 700; font-size: 14px;
            font-family: var(--font-b); cursor: pointer;
            transition: all 0.3s; box-shadow: 0 4px 20px rgba(139,63,217,0.3);
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(139,63,217,0.5); }
        .btn-cancel {
            background: rgba(255,255,255,0.05); color: var(--text-dim);
            border: 1px solid var(--border); padding: 12px 28px;
            border-radius: 12px; font-weight: 600; font-size: 14px;
            font-family: var(--font-b); cursor: pointer; transition: all 0.3s;
        }
        .btn-cancel:hover { border-color: var(--purple-glow); color: #fff; }
        .toast-dark {
            position: fixed; bottom: 28px; right: 28px; z-index: 9999;
            background: var(--bg-card-solid, #161326);
            border: 1px solid rgba(139,63,217,0.3);
            border-radius: 14px; padding: 16px 22px;
            display: flex; align-items: center; gap: 14px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            transform: translateY(120%); opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            min-width: 280px;
        }
        .toast-dark.show { transform: translateY(0); opacity: 1; }
        .section-divider {
            display: flex; align-items: center; gap: 14px;
            margin: 28px 0 20px;
        }
        .section-divider span {
            font-family: var(--font-h); font-size: 11px;
            font-weight: 700; color: var(--text-dim);
            text-transform: uppercase; letter-spacing: 1.5px;
            white-space: nowrap;
        }
        .section-divider::before, .section-divider::after {
            content: ''; flex: 1; height: 1px;
            background: var(--border);
        }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        <div class="student-content" style="padding: 28px 40px;">
            <div style="max-width: 680px; margin: 0 auto;">
                <div class="profile-card">
                    <!-- Banner + Avatar -->
                    <div class="profile-banner">
                        <div style="position: absolute; bottom: -55px; left: 36px;">
                            <label for="profile-picture-upload" style="display:block;">
                                <div class="avatar-ring">
                                    <img id="profile-picture-preview"
                                         src="<?php echo $hasProf ? 'upload/'.htmlspecialchars($user['profile_picture']) : ''; ?>"
                                         alt="Avatar"
                                         style="width:100%;height:100%;object-fit:cover;<?php echo !$hasProf ? 'display:none;' : ''; ?>">
                                    <?php if (!$hasProf): ?>
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                                         style="width:100%;height:100%;fill:#C084FC;background:rgba(139,63,217,0.15);padding:18px;">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                    <?php endif; ?>
                                    <div class="avatar-overlay">
                                        <i class="fas fa-camera" style="color:#fff;font-size:22px;"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden" accept="image/*" form="profileForm">
                        </div>
                    </div>

                    <div style="padding: 70px 36px 36px;">
                        <!-- Name display -->
                        <div style="margin-bottom: 28px;">
                            <h2 style="font-family:var(--font-h);font-size:20px;color:#fff;margin:0 0 4px;">
                                <?php echo htmlspecialchars($user['firstname'].' '.$user['lastname']); ?>
                            </h2>
                            <p style="color:var(--text-dim);font-size:13px;">
                                <?php echo htmlspecialchars($user['idno']); ?> &nbsp;·&nbsp;
                                <?php echo htmlspecialchars($user['course'] ?? ''); ?> <?php echo htmlspecialchars($user['level'] ?? ''); ?>
                            </p>
                        </div>

                        <form method="post" enctype="multipart/form-data" id="profileForm">
                            <div class="section-divider"><span>Personal Info</span></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                                <div>
                                    <div class="field-label">Last Name</div>
                                    <input class="dark-input" name="lastname" placeholder="Last Name" type="text"
                                           value="<?php echo htmlspecialchars($user['lastname']??''); ?>">
                                </div>
                                <div>
                                    <div class="field-label">First Name</div>
                                    <input class="dark-input" name="firstname" placeholder="First Name" type="text"
                                           value="<?php echo htmlspecialchars($user['firstname']??''); ?>">
                                </div>
                            </div>
                            <div style="margin-bottom:16px;">
                                <div class="field-label">Middle Name</div>
                                <input class="dark-input" name="middlename" placeholder="Middle Name (optional)" type="text"
                                       value="<?php echo htmlspecialchars($user['middlename']??''); ?>">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                                <div>
                                    <div class="field-label">Course</div>
                                    <select class="dark-select" name="course">
                                        <option value="" disabled>Select Course</option>
                                        <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($user['course']===$c)?'selected':''; ?>>
                                            <?php echo $c; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <div class="field-label">Year Level</div>
                                    <select class="dark-select" name="level">
                                        <option value="" disabled>Select Year</option>
                                        <?php for($i=1;$i<=4;$i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($user['level']==$i)?'selected':''; ?>>
                                            Year <?php echo $i; ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="section-divider"><span>Account & Security</span></div>
                            <div style="margin-bottom:16px;">
                                <div class="field-label">ID Number</div>
                                <input class="dark-input" type="text" value="<?php echo htmlspecialchars($user['idno']??''); ?>" readonly>
                            </div>
                            <div style="margin-bottom:16px;">
                                <div class="field-label">Email Address</div>
                                <input class="dark-input" name="email" type="email" placeholder="Email Address"
                                       value="<?php echo htmlspecialchars($user['email']??''); ?>">
                            </div>
                            <div style="margin-bottom:28px;position:relative;">
                                <div class="field-label">New Password <span style="font-weight:400;opacity:0.6;">(leave blank to keep current)</span></div>
                                <input class="dark-input" name="password" id="passwordField" type="password"
                                       placeholder="Enter new password">
                                <button type="button" id="togglePw"
                                        style="position:absolute;right:14px;bottom:12px;background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:15px;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>

                            <div style="display:flex;gap:12px;justify-content:flex-end;">
                                <button type="button" class="btn-cancel" onclick="window.location.href='profile.php'">Cancel</button>
                                <button type="submit" class="btn-save"><i class="fas fa-save" style="margin-right:8px;"></i>Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="profileToast" class="toast-dark">
        <i id="toastIcon" class="fas fa-check-circle" style="font-size:20px;color:#10b981;"></i>
        <div>
            <div id="toastTitle" style="font-family:var(--font-h);font-size:12px;color:#fff;font-weight:700;letter-spacing:0.5px;margin-bottom:3px;">SUCCESS</div>
            <div id="toastMsg" style="font-size:13px;color:var(--text-dim);"></div>
        </div>
    </div>

    <script>
    // Star canvas
    (function(){
        const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');
        let W,H,stars=[];
        function resize(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}
        window.addEventListener('resize',resize);resize();
        for(let i=0;i<120;i++)stars.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});
        function draw(){ctx.clearRect(0,0,W,H);stars.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(draw);}
        draw();
    })();

    // Password toggle
    document.getElementById('togglePw').addEventListener('click',function(){
        const f=document.getElementById('passwordField');
        f.type=f.type==='password'?'text':'password';
        this.querySelector('i').className='fas fa-eye'+(f.type==='text'?'-slash':'');
    });

    // Avatar preview
    document.getElementById('profile-picture-upload').addEventListener('change',function(e){
        const file=e.target.files[0];
        if(!file)return;
        const reader=new FileReader();
        reader.onload=function(ev){
            const img=document.getElementById('profile-picture-preview');
            img.src=ev.target.result;
            img.style.display='block';
        };
        reader.readAsDataURL(file);
    });

    // Form submit
    document.getElementById('profileForm').addEventListener('submit',function(e){
        e.preventDefault();
        const formData=new FormData(this);
        const fileInput = document.getElementById('profile-picture-upload');
        if(fileInput && fileInput.files.length > 0){
            formData.append('profile_picture', fileInput.files[0]);
        }
        fetch('profile.php',{method:'POST',body:formData})
        .then(r=>r.json())
        .then(data=>{
            showToast(data.success,data.message);
            if(data.success){
                if(data.profile_picture){
                    document.getElementById('profile-picture-preview').src='upload/'+data.profile_picture+'?t='+Date.now();
                }
                setTimeout(()=>location.reload(),1500);
            }
        }).catch(()=>showToast(false,'An unexpected error occurred.'));
    });

    function showToast(success,msg){
        const toast=document.getElementById('profileToast');
        document.getElementById('toastMsg').textContent=msg;
        document.getElementById('toastTitle').textContent=success?'SUCCESS':'ERROR';
        document.getElementById('toastIcon').className='fas '+(success?'fa-check-circle':'fa-exclamation-circle');
        document.getElementById('toastIcon').style.color=success?'#10b981':'#ef4444';
        toast.style.borderLeftColor=success?'rgba(16,185,129,0.5)':'rgba(239,68,68,0.5)';
        toast.classList.add('show');
        setTimeout(()=>toast.classList.remove('show'),3500);
    }
    </script>
</body>
</html>
