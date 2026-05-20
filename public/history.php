<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
session_start();
if (!isset($_SESSION['login_user'])) { header("Location: login.php"); exit(); }
require __DIR__ . '/../config/db.php';

function containsFoulWords($message, $foulWords) {
    foreach ($foulWords as $word) {
        if (stripos($message, $word) !== false) return $word;
    }
    return false;
}

function saveAdminNotification($message, $conn) {
    $admin_id = 1;
    $stmt = $conn->prepare("INSERT INTO notifications (message, notification_type, user_id) VALUES (?, 'admin', ?)");
    $stmt->bind_param("si", $message, $admin_id);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitFeedback'])) {
    $userId  = $_SESSION['user_id'];
    $sitinId = intval($_POST['sitin_id']);
    $rating  = intval($_POST['rating']);
    $message = $conn->real_escape_string($_POST['message']);

    if ($rating < 1 || $rating > 5) die("Invalid rating.");

    $checkResult = $conn->query("SELECT sitin_id FROM sitin WHERE sitin_id = '$sitinId'");
    if (!$checkResult || $checkResult->num_rows === 0) die("Invalid sitin_id.");

    $foulWords = ["putang ina","putangina","tang ina","tangina","puta","pota","gago","gaga","bobo","boba","ulol","tarantado","tanga","bwisit","leche","letse","lintik","punyeta","pakyu","pakshet","putragis","hayop","pucha","giatay","atay","pisti","yawa","buang","fuck","shit","bitch","motherfucker","asshole","nigger","dipshit"];
    $detected = containsFoulWords($message, $foulWords);
    if ($detected) {
        saveAdminNotification("Foul language in feedback from id: ".$_SESSION['idno']." - ".$detected, $conn);
        echo "<script>alert('Feedback contains inappropriate language.');</script>";
    }
    $sql = "INSERT INTO feedback (user_id, sitin_id, message, rating) VALUES ('$userId', '$sitinId', '$message', '$rating')";
    if ($conn->query($sql)) { $_SESSION['feedback_success'] = true; header("Location: history.php"); exit(); }
}

$loggedInUserIdno = $_SESSION['idno'];
$sql = "SELECT sitin.sitin_id, sitin.idno, users.lastname, users.firstname, sitin.purpose,
               sitin.lab_number, sitin.pc_number, sitin.time_in, sitin.time_out, sitin.created_at,
               feedback.feedback_id AS feedback_id, feedback.rating, feedback.message,
               (SELECT r.pc_number FROM reservations r 
                WHERE r.idno = sitin.idno AND r.lab_number = sitin.lab_number AND r.reservation_date = DATE(sitin.created_at) 
                ORDER BY r.reservation_id DESC LIMIT 1) as res_pc
        FROM sitin
        JOIN users ON sitin.idno = users.idno
        LEFT JOIN feedback ON sitin.sitin_id = feedback.sitin_id AND feedback.user_id = '{$_SESSION['user_id']}'
        WHERE sitin.time_out IS NOT NULL AND sitin.idno = '$loggedInUserIdno'";
$result = $conn->query($sql);
$sitinData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $sitinData[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In History – CCS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/student-dark.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .history-table { width:100%;border-collapse:separate;border-spacing:0 8px; }
        .history-table thead th { padding:0 14px 10px;text-align:left;font-size:11px;font-weight:700;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border); }
        .history-table tbody tr { transition:background 0.3s; }
        .history-table tbody td { padding:0 14px;height:54px;font-size:13px;vertical-align:middle;background:rgba(255,255,255,0.02);border-top:1px solid transparent;border-bottom:1px solid transparent; }
        .history-table tbody td:first-child { border-radius:12px 0 0 12px;border-left:1px solid transparent; }
        .history-table tbody td:last-child  { border-radius:0 12px 12px 0;border-right:1px solid transparent; }
        .history-table tbody tr:hover td { background:rgba(139,63,217,0.05);border-color:rgba(139,63,217,0.2); }
        .history-table tbody tr:hover td:first-child { border-left:1px solid rgba(139,63,217,0.2); }
        .history-table tbody tr:hover td:last-child  { border-right:1px solid rgba(139,63,217,0.2); }
        .feedback-done-row:hover td { background:rgba(16,185,129,0.05)!important; border-color:rgba(16,185,129,0.2)!important; }
        .feedback-done-row:hover td:first-child { border-left:1px solid rgba(16,185,129,0.2)!important; }
        .feedback-done-row:hover td:last-child  { border-right:1px solid rgba(16,185,129,0.2)!important; }
        .lab-badge { display:inline-flex;align-items:center;justify-content:center;background:rgba(139,63,217,0.12);color:var(--purple-light);border:1px solid rgba(139,63,217,0.2);border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700; }
        .purpose-tag { color:#D1C7E0;font-size:13px; }
        .time-cell { color:var(--text-dim);font-size:12px; }
        .date-cell { color:#fff;font-size:13px;font-weight:500; }
        .btn-feedback { display:inline-flex;align-items:center;gap:6px;background:rgba(139,63,217,0.12);color:var(--purple-light);border:1px solid rgba(139,63,217,0.2);padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.3s;font-family:var(--font-b); }
        .btn-feedback:hover { background:var(--purple-glow);color:#fff; }
        .done-badge { display:inline-flex;align-items:center;gap:5px;color:#10b981;font-size:12px;font-weight:600; }

        /* Controls */
        .ctrl-row { display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px; }
        .ctrl-left { display:flex;align-items:center;gap:10px; }
        .ctrl-right { display:flex;align-items:center;gap:10px; }
        .h-select { background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:8px 12px;border-radius:10px;font-size:13px;font-family:var(--font-b);outline:none;cursor:pointer; }
        .h-search { position:relative; }
        .h-search input { background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#fff;padding:8px 16px 8px 36px;border-radius:10px;font-size:13px;width:220px;outline:none;transition:all 0.3s;font-family:var(--font-b); }
        .h-search input:focus { border-color:var(--purple-glow);box-shadow:0 0 12px rgba(139,63,217,0.2); }
        .h-search input::placeholder { color:var(--text-dim); }
        .h-search i { position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-dim);font-size:12px; }

        /* Pagination */
        .pag-row { display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding:0 4px; }
        .pag-info { color:var(--text-dim);font-size:12px; }
        .pag-btns { display:flex;gap:5px; }
        .pag-btn { min-width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;transition:all 0.3s;border:1px solid var(--border);background:transparent;color:var(--text-dim);font-family:var(--font-b); }
        .pag-btn:hover:not(.active):not(:disabled) { border-color:var(--purple-glow);color:#fff;background:var(--purple-hover); }
        .pag-btn.active { background:var(--purple-glow);color:#fff;border-color:var(--purple-glow); }
        .pag-btn:disabled { opacity:0.4;cursor:not-allowed; }

        /* Feedback Modal */
        .fb-modal { position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:2000; }
        .fb-modal.show { display:flex; }
        .fb-box { background:#161326;border:1px solid rgba(139,63,217,0.35);border-radius:20px;padding:30px;width:100%;max-width:480px;box-shadow:0 30px 60px rgba(0,0,0,0.6); }
        .fb-box h2 { font-family:var(--font-h);font-size:17px;color:#fff;margin:0 0 20px;letter-spacing:1px; }
        .star-rating { display:flex;gap:8px;margin-bottom:16px; }
        .star { font-size:28px;cursor:pointer;color:rgba(255,255,255,0.2);transition:color 0.2s; }
        .star.selected,.star.hover { color:#D4870A; }
        .fb-textarea { width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:12px;color:#fff;padding:12px 16px;font-size:14px;font-family:var(--font-b);resize:vertical;min-height:100px;outline:none;transition:all 0.3s;margin-bottom:18px; }
        .fb-textarea:focus { border-color:var(--purple-glow);box-shadow:0 0 12px rgba(139,63,217,0.2); }
        .fb-textarea::placeholder { color:var(--text-dim); }
        .fb-btns { display:flex;gap:10px;justify-content:flex-end; }
        .fb-submit { background:var(--purple-glow);color:#fff;border:none;padding:10px 24px;border-radius:10px;font-size:13px;font-weight:700;font-family:var(--font-b);cursor:pointer;transition:all 0.3s; }
        .fb-submit:hover { background:var(--purple-light); }
        .fb-cancel { background:rgba(255,255,255,0.05);color:var(--text-dim);border:1px solid var(--border);padding:10px 20px;border-radius:10px;font-size:13px;font-family:var(--font-b);cursor:pointer;transition:all 0.3s; }
        .fb-cancel:hover { border-color:var(--purple-glow);color:#fff; }
    </style>
</head>
<body>
<?php if (isset($_SESSION['feedback_success'])): unset($_SESSION['feedback_success']); ?>
<script>document.addEventListener('DOMContentLoaded',()=>{const t=document.getElementById('confirmModal');if(t){t.style.display='flex';}});</script>
<?php endif; ?>

    <canvas id="star-canvas"></canvas>
    <?php include 'sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'header.php'; ?>
        <div class="student-content">
            <div class="content-card">
                <!-- Controls -->
                <div class="ctrl-row">
                    <div class="ctrl-left" style="display: none;">
                        <label style="color:var(--text-dim);font-size:13px;">Show</label>
                        <select class="h-select" id="entriesSelect">
                            <option value="all" selected>All</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        <span style="color:var(--text-dim);font-size:13px;">entries</span>
                    </div>
                    <div class="ctrl-right">
                        <div class="h-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="histSearch" placeholder="Search…">
                        </div>
                        <div style="position:relative;">
                            <button id="filterBtn" style="display:flex;align-items:center;gap:7px;background:rgba(255,255,255,0.05);border:1px solid var(--border);color:var(--text-dim);padding:8px 14px;border-radius:10px;font-size:13px;font-family:var(--font-b);cursor:pointer;transition:all 0.3s;" onmouseover="this.style.borderColor='var(--purple-glow)'" onmouseout="this.style.borderColor='var(--border)'">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <div id="filterMenu" style="display:none;position:absolute;top:calc(100% + 8px);right:0;background:#161326;border:1px solid rgba(139,63,217,0.3);border-radius:12px;overflow:hidden;min-width:130px;box-shadow:0 20px 40px rgba(0,0,0,0.4);z-index:100;">
                                <?php foreach(['all'=>'All','done'=>'Done','not-done'=>'Pending'] as $val=>$label): ?>
                                <a href="#" class="filter-opt" data-filter="<?php echo $val; ?>" style="display:block;padding:10px 16px;color:#D1C7E0;font-size:13px;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='rgba(139,63,217,0.1)'" onmouseout="this.style.background=''"><?php echo $label; ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div style="flex:1;overflow-x:auto;overflow-y:hidden;">
                    <table class="history-table" id="histTable">
                        <thead>
                            <tr>
                                <th>Laboratory</th>
                                <th>PC</th>
                                <th>Purpose</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Date</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sitinData)): ?>
                                <?php foreach ($sitinData as $sitin): ?>
                                <?php 
                                    $pc_val = $sitin['pc_number'] ? $sitin['pc_number'] : ($sitin['res_pc'] ? $sitin['res_pc'] : (($sitin['sitin_id'] % 30) + 1));
                                ?>
                                <tr data-purpose="<?php echo strtolower(htmlspecialchars($sitin['purpose'])); ?>"
                                    data-date="<?php echo $sitin['created_at']; ?>"
                                    data-feedback="<?php echo !empty($sitin['feedback_id'])?'done':'pending'; ?>"
                                    <?php if (!empty($sitin['feedback_id'])): ?>
                                    class="feedback-done-row cursor-pointer"
                                    data-rating="<?php echo intval($sitin['rating']); ?>"
                                    data-message="<?php echo htmlspecialchars($sitin['message']); ?>"
                                    onclick="openViewFeedback(this)"
                                    <?php endif; ?>>
                                    <td><span class="lab-badge">Lab <?php echo htmlspecialchars($sitin['lab_number']); ?></span></td>
                                    <td style="color:#D1C7E0;font-weight:600;">PC <?php echo htmlspecialchars($pc_val); ?></td>
                                    <td><span class="purpose-tag"><?php echo htmlspecialchars($sitin['purpose']); ?></span></td>
                                    <td><span class="time-cell"><?php echo date('h:i A', strtotime($sitin['time_in'])); ?></span></td>
                                    <td><span class="time-cell"><?php echo date('h:i A', strtotime($sitin['time_out'])); ?></span></td>
                                    <td><span class="date-cell"><?php echo date('M d, Y', strtotime($sitin['created_at'])); ?></span></td>
                                    <td>
                                        <?php if (!empty($sitin['feedback_id'])): ?>
                                            <span class="done-badge"><i class="fas fa-check-circle"></i> Done</span>
                                        <?php else: ?>
                                            <button class="btn-feedback feedback-link" data-id="<?php echo $sitin['sitin_id']; ?>">
                                                <i class="fas fa-star"></i> Rate
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-row"><td colspan="7" style="text-align:center;color:var(--text-dim);padding:50px 0;">
                                    <i class="fas fa-history" style="font-size:32px;opacity:0.3;display:block;margin-bottom:10px;"></i>No sit-in records found.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pag-row">
                    <div class="pag-info" id="pagInfo"></div>
                    <div class="pag-btns" id="pagBtns"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="histToast" style="position:fixed;bottom:28px;right:28px;z-index:9999;background:#161326;border:1px solid rgba(16,185,129,0.4);border-radius:14px;padding:14px 22px;display:flex;align-items:center;gap:12px;box-shadow:0 20px 40px rgba(0,0,0,0.5);transform:translateY(120%);opacity:0;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);min-width:260px;" class="">
        <i class="fas fa-check-circle" style="color:#10b981;font-size:20px;"></i>
        <div>
            <div style="font-family:var(--font-h);font-size:11px;font-weight:700;color:#fff;letter-spacing:0.5px;margin-bottom:2px;">SUCCESS</div>
            <div style="font-size:13px;color:var(--text-dim);">Feedback submitted successfully!</div>
        </div>
    </div>
    <style>.toast-show{transform:translateY(0)!important;opacity:1!important;}</style>
    <script>
    const histToast=document.getElementById('histToast');
    if(histToast&&histToast.classList.contains('show')){histToast.classList.add('toast-show');setTimeout(()=>histToast.classList.remove('toast-show'),3500);}
    </script>

    <!-- Feedback Confirmation Modal/Page Overlay -->
    <div id="confirmModal" class="fb-modal" style="display: none;">
        <div class="fb-box" style="text-align: center; max-width: 440px; border-color: rgba(16, 185, 129, 0.5); padding: 40px 30px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid rgba(16, 185, 129, 0.3); margin-bottom: 24px;">
                <i class="fas fa-check-circle" style="color: #10b981; font-size: 40px;"></i>
            </div>
            <h2 style="font-family: var(--font-h); font-size: 20px; color: #fff; margin-bottom: 12px; letter-spacing: 1px;">FEEDBACK CONFIRMED</h2>
            <p style="font-size: 14px; color: var(--text-dim); line-height: 1.6; margin-bottom: 28px;">
                Thank you for rating your laboratory sit-in session! Your response has been recorded and will help improve sit-in management.
            </p>
            <button type="button" class="fb-submit" id="closeConfirmBtn" style="background: linear-gradient(135deg, #10b981, #059669); border: none; padding: 12px 30px; border-radius: 10px; font-size: 13px; font-weight: 700; width: 100%; box-shadow: 0 4px 15px rgba(16,185,129,0.3); cursor: pointer; transition: all 0.3s;">
                <i class="fas fa-arrow-left" style="margin-right:8px;"></i>Back to History
            </button>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div id="viewFeedbackModal" class="fb-modal">
        <div class="fb-box" style="border-color: rgba(16, 185, 129, 0.35);">
            <h2><i class="fas fa-comment-dots" style="color:#10b981;margin-right:10px;"></i>YOUR FEEDBACK</h2>
            <div class="star-rating" id="viewStarRating" style="pointer-events: none; margin-bottom: 16px;">
                <!-- Stars will be inserted dynamically -->
            </div>
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 6px;">Comments</div>
            <div id="viewFeedbackMessage" style="background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 12px; color: #fff; padding: 12px 16px; font-size: 14px; min-height: 80px; margin-bottom: 18px; white-space: pre-wrap;"></div>
            <div class="fb-btns">
                <button type="button" class="fb-cancel" id="closeViewModal">Close</button>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="fb-modal">
        <div class="fb-box">
            <h2><i class="fas fa-star" style="color:var(--gold);margin-right:10px;"></i>LEAVE FEEDBACK</h2>
            <form method="POST" id="feedbackForm">
                <input type="hidden" name="sitin_id" id="sitinIdInput">
                <div class="star-rating" id="starRating">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <span class="star" data-value="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">
                <textarea class="fb-textarea" name="message" placeholder="Share your experience…" required></textarea>
                <div class="fb-btns">
                    <button type="button" class="fb-cancel" id="closeModal">Cancel</button>
                    <button type="submit" name="submitFeedback" class="fb-submit"><i class="fas fa-paper-plane" style="margin-right:6px;"></i>Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Star canvas
    (function(){const c=document.getElementById('star-canvas'),ctx=c.getContext('2d');let W,H,st=[];function r(){W=c.width=window.innerWidth;H=c.height=window.innerHeight;}window.addEventListener('resize',r);r();for(let i=0;i<120;i++)st.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.2+0.3,a:Math.random(),da:(Math.random()*0.003+0.001)*(Math.random()<.5?1:-1)});function d(){ctx.clearRect(0,0,W,H);st.forEach(s=>{s.a+=s.da;if(s.a<=0||s.a>=1)s.da*=-1;ctx.beginPath();ctx.arc(s.x%W,s.y%H,s.r,0,Math.PI*2);ctx.fillStyle=`rgba(200,180,255,${s.a.toFixed(2)})`;ctx.fill();});requestAnimationFrame(d);}d();})();

    // Pagination logic
    let curPage=1,curFilter='all';
    const allRows=()=>[...document.querySelectorAll('#histTable tbody tr:not(.empty-row)')];

    function getVisible(){
        const q=document.getElementById('histSearch').value.toLowerCase();
        return allRows().filter(r=>{
            const cells=[...r.querySelectorAll('td')].map(c=>c.textContent.toLowerCase()).join(' ');
            const matchQ=!q||cells.includes(q);
            const fb=r.dataset.feedback;
            const matchF=curFilter==='all'||(curFilter==='done'&&fb==='done')||(curFilter==='not-done'&&fb==='pending');
            return matchQ&&matchF;
        });
    }

    function render(){
        const vis=getVisible();
        const epv=document.getElementById('entriesSelect').value;
        allRows().forEach(r=>r.style.display='none');
        if(epv==='all'){
            vis.forEach(r=>r.style.display='');
            document.getElementById('pagInfo').textContent=`Showing all ${vis.length} entries`;
            document.getElementById('pagBtns').innerHTML='';
            return;
        }
        const ep=parseInt(epv);
        const tp=Math.max(1,Math.ceil(vis.length/ep));
        if(curPage>tp)curPage=tp;
        const s=(curPage-1)*ep,e=s+ep;
        vis.slice(s,e).forEach(r=>r.style.display='');
        document.getElementById('pagInfo').textContent=`Showing ${vis.length?s+1:0}–${Math.min(e,vis.length)} of ${vis.length}`;
        buildPag(tp);
    }

    function buildPag(tp){
        const c=document.getElementById('pagBtns');
        c.innerHTML='';
        const mk=(label,pg,cls='')=>{
            const b=document.createElement('button');
            b.className='pag-btn '+(curPage===pg?'active':'')+' '+cls;
            b.innerHTML=label;
            if(pg!==null){b.addEventListener('click',()=>{curPage=pg;render();});}
            return b;
        };
        c.appendChild(mk('<i class="fas fa-chevron-left"></i>',curPage>1?curPage-1:null,(curPage===1?'disabled':'')));
        for(let i=Math.max(1,curPage-2);i<=Math.min(tp,curPage+2);i++)c.appendChild(mk(i,i));
        c.appendChild(mk('<i class="fas fa-chevron-right"></i>',curPage<tp?curPage+1:null,(curPage===tp?'disabled':'')));
    }

    document.getElementById('histSearch').addEventListener('input',()=>{curPage=1;render();});
    document.getElementById('entriesSelect').addEventListener('change',()=>{curPage=1;render();});

    document.getElementById('filterBtn').addEventListener('click',e=>{e.stopPropagation();const m=document.getElementById('filterMenu');m.style.display=m.style.display==='block'?'none':'block';});
    document.querySelectorAll('.filter-opt').forEach(o=>o.addEventListener('click',function(e){e.preventDefault();curFilter=this.dataset.filter;curPage=1;render();document.getElementById('filterMenu').style.display='none';}));
    document.addEventListener('click',()=>document.getElementById('filterMenu').style.display='none');
    render();

    // Feedback modal
    const modal=document.getElementById('feedbackModal');
    const stars=document.querySelectorAll('#starRating .star');
    let rating=0;
    document.querySelectorAll('.feedback-link').forEach(l=>l.addEventListener('click',function(e){
        e.stopPropagation(); // Avoid triggering row click
        document.getElementById('sitinIdInput').value=this.dataset.id;
        rating=0;stars.forEach(s=>s.classList.remove('selected','hover'));
        modal.classList.add('show');
    }));
    document.getElementById('closeModal').addEventListener('click',()=>modal.classList.remove('show'));
    modal.addEventListener('click',e=>{if(e.target===modal)modal.classList.remove('show');});
    stars.forEach(s=>{
        s.addEventListener('mouseover',function(){stars.forEach((x,i)=>x.classList.toggle('hover',i<this.dataset.value));});
        s.addEventListener('mouseout',()=>stars.forEach((x,i)=>x.classList.toggle('hover',i<rating)&&x.classList.remove('hover')));
        s.addEventListener('click',function(){rating=this.dataset.value;document.getElementById('ratingInput').value=rating;stars.forEach((x,i)=>{x.classList.toggle('selected',i<rating);x.classList.remove('hover');});});
    });

    // View Feedback Modal Logic
    const viewModal = document.getElementById('viewFeedbackModal');
    const viewStarsContainer = document.getElementById('viewStarRating');
    const viewMessage = document.getElementById('viewFeedbackMessage');

    function openViewFeedback(row) {
        const ratingVal = parseInt(row.dataset.rating) || 0;
        const msg = row.dataset.message || '';
        
        // Generate stars
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += `<span class="star ${i <= ratingVal ? 'selected' : ''}" style="cursor: default; font-size: 28px; color: rgba(255,255,255,0.2);">★</span>`;
        }
        viewStarsContainer.innerHTML = starsHtml;
        
        // Render rating colors
        const viewStars = viewStarsContainer.querySelectorAll('.star');
        viewStars.forEach((star, index) => {
            if (index < ratingVal) {
                star.style.color = '#D4870A';
            }
        });

        viewMessage.textContent = msg ? msg : 'No comments provided.';
        viewModal.classList.add('show');
    }

    document.getElementById('closeViewModal').addEventListener('click', () => viewModal.classList.remove('show'));
    viewModal.addEventListener('click', e => { if (e.target === viewModal) viewModal.classList.remove('show'); });

    // Confirmation Modal Logic
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) {
        document.getElementById('closeConfirmBtn').addEventListener('click', () => {
            confirmModal.style.display = 'none';
        });
        confirmModal.addEventListener('click', e => {
            if (e.target === confirmModal) confirmModal.style.display = 'none';
        });
    }
    </script>
</body>
</html>