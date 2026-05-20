<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
session_start();
if (!isset($_SESSION['login_user'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Rules – CCS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .rules-card { background:var(--bg-card); border:1px solid var(--border); border-radius:22px; padding:36px 40px; backdrop-filter:blur(10px); max-width:720px; margin:0 auto; }
        .rules-card h2 { font-family:var(--font-h); font-size:13px; font-weight:700; color:var(--purple-light); letter-spacing:2px; text-transform:uppercase; text-align:center; margin:0 0 6px; }
        .rules-card h3 { font-size:13px; font-weight:600; color:var(--text-dim); text-align:center; margin:0 0 22px; }
        .section-title { font-family:var(--font-h); font-size:12px; font-weight:700; color:var(--gold); letter-spacing:1.5px; text-transform:uppercase; margin:22px 0 12px; display:flex; align-items:center; gap:10px; }
        .section-title::after { content:''; flex:1; height:1px; background:rgba(212,135,10,0.25); }
        .rules-list { list-style:none; padding:0; margin:0; }
        .rules-list li { display:flex; gap:14px; align-items:flex-start; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:14px; color:#D1C7E0; line-height:1.6; }
        .rules-list li:last-child { border-bottom:none; }
        .rule-num { min-width:26px; height:26px; border-radius:50%; background:rgba(139,63,217,0.2); border:1px solid rgba(139,63,217,0.3); color:var(--purple-light); font-size:12px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }
        .disc-item { display:flex; gap:14px; align-items:flex-start; padding:8px 0; font-size:14px; color:#D1C7E0; }
        .disc-badge { padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; white-space:nowrap; margin-top:3px; }
        .disc-1 { background:rgba(234,179,8,0.15); color:#eab308; }
        .disc-2 { background:rgba(239,130,68,0.15); color:#f97316; }
        .disc-3 { background:rgba(239,68,68,0.15); color:#ef4444; }
        .top-banner { text-align:center; margin-bottom:28px; }
        .top-banner .uni { font-size:18px; font-weight:700; color:#fff; margin-bottom:4px; }
        .top-banner .dept { font-size:13px; color:var(--text-dim); }
    </style>
</head>
<body>
    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        <div class="student-content">
            <div class="rules-card">
                <div class="top-banner">
                    <p class="uni">University of Cebu</p>
                    <p class="dept">College of Information &amp; Computer Studies</p>
                </div>
                <h2><i class="fas fa-gavel" style="margin-right:8px;"></i>Sit-In Rules &amp; Regulations</h2>
                <h3>Please read and observe the following guidelines</h3>

                <div class="section-title"><span>General Rules</span></div>
                <ol class="rules-list">
                    <?php $rules = [
                        "Only authorized sit-in students with prior approval from the instructor are allowed.",
                        "Sit-in students must not disrupt the class or engage in side conversations.",
                        "Mobile phones and other electronic devices must be set to silent mode during the session.",
                        "Sit-in students must not use laboratory computers unless explicitly permitted by the instructor.",
                        "Seats are prioritized for officially enrolled students. Sit-in students should occupy vacant seats only.",
                        "Participation in discussions or activities is allowed only if the instructor permits.",
                        "Eating, drinking, or any form of littering is strictly prohibited inside the classroom.",
                        "Sit-in students must follow the instructor's guidelines and classroom rules at all times.",
                        "Disruptive behavior, including excessive talking, arguing, or any form of distraction, will not be tolerated.",
                        "Failure to follow these rules may result in immediate removal from the class and possible restrictions on future sit-in requests."
                    ]; ?>
                    <?php foreach ($rules as $i => $rule): ?>
                    <li>
                        <span class="rule-num"><?php echo $i+1; ?></span>
                        <span><?php echo $rule; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>

                <div class="section-title"><span>Disciplinary Action</span></div>
                <div>
                    <div class="disc-item"><span class="disc-badge disc-1">1st Offense</span><span style="color:#D1C7E0;font-size:14px;line-height:1.6;padding-top:3px;">A verbal warning will be issued by the instructor.</span></div>
                    <div class="disc-item"><span class="disc-badge disc-2">2nd Offense</span><span style="color:#D1C7E0;font-size:14px;line-height:1.6;padding-top:3px;">The student will be asked to leave and reported to the administration.</span></div>
                    <div class="disc-item"><span class="disc-badge disc-3">3rd Offense</span><span style="color:#D1C7E0;font-size:14px;line-height:1.6;padding-top:3px;">A formal complaint may be filed, leading to further disciplinary actions.</span></div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();
    </script>
</body>
</html>
