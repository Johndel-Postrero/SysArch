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
    <title>Laboratory Rules – CCS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .rules-card{background:var(--bg-card);border:1px solid var(--border);border-radius:22px;padding:36px 40px;backdrop-filter:blur(10px);max-width:740px;margin:0 auto;}
        .top-banner{text-align:center;margin-bottom:24px;}
        .top-banner .uni{font-size:18px;font-weight:700;color:#fff;margin-bottom:4px;}
        .top-banner .dept{font-size:13px;color:var(--text-dim);}
        .section-head{font-family:var(--font-h);font-size:11px;font-weight:700;color:var(--gold);letter-spacing:1.5px;text-transform:uppercase;margin:22px 0 12px;display:flex;align-items:center;gap:10px;}
        .section-head::after{content:'';flex:1;height:1px;background:rgba(212,135,10,0.25);}
        .rules-list{list-style:none;padding:0;margin:0;}
        .rules-list li{display:flex;gap:12px;align-items:flex-start;padding:9px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:14px;color:#D1C7E0;line-height:1.6;}
        .rules-list li:last-child{border-bottom:none;}
        .rule-num{min-width:24px;height:24px;border-radius:50%;background:rgba(139,63,217,0.2);border:1px solid rgba(139,63,217,0.3);color:var(--purple-light);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:3px;}
        .disc-item{display:flex;gap:12px;align-items:flex-start;padding:8px 0;font-size:14px;color:#D1C7E0;}
        .disc-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;margin-top:3px;}
        .disc-1{background:rgba(234,179,8,0.15);color:#eab308;}
        .disc-2{background:rgba(239,68,68,0.15);color:#ef4444;}
        .intro-text{font-size:14px;color:var(--text-dim);margin-bottom:4px;line-height:1.7;}
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

                <p class="intro-text" style="text-align:center;margin-bottom:20px;">To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:</p>

                <div class="section-head"><span><i class="fas fa-desktop" style="margin-right:6px;"></i>Laboratory Rules &amp; Regulations</span></div>
                <?php $rules = [
                    "Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.",
                    "Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.",
                    "Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.",
                    "Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.",
                    "Deleting computer files and changing the set-up of the computer is a major offense.",
                    "Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to \"sit-in\".",
                    "Observe proper decorum while inside the laboratory.",
                    "Do not get inside the lab unless the instructor is present.",
                    "All bags, knapsacks, and the likes must be deposited at the counter.",
                    "Follow the seating arrangement of your instructor.",
                    "At the end of class, all software programs must be closed.",
                    "Return all chairs to their proper places after using.",
                    "Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.",
                    "Anyone causing a continual disturbance will be asked to leave the lab.",
                    "Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.",
                    "Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.",
                    "For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.",
                    "Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately."
                ]; ?>
                <ol class="rules-list">
                    <?php foreach ($rules as $i => $r): ?>
                    <li><span class="rule-num"><?php echo $i+1; ?></span><span><?php echo $r; ?></span></li>
                    <?php endforeach; ?>
                </ol>

                <div class="section-head" style="margin-top:24px;"><span><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>Disciplinary Action</span></div>
                <div class="disc-item"><span class="disc-badge disc-1">1st Offense</span><span style="padding-top:3px;">The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.</span></div>
                <div class="disc-item"><span class="disc-badge disc-2">2nd+ Offense</span><span style="padding-top:3px;">A recommendation for a heavier sanction will be endorsed to the Guidance Center.</span></div>
            </div>
        </div>
    </div>
    <script>
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();
    </script>
</body>
</html>