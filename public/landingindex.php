<?php
session_start();
if (isset($_SESSION['login_user'])) {
    $isAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
    header('Location: ' . ($isAdmin ? 'Admin/adminIndex.php' : 'index.php'));
    exit();
}

// Fetch Top 3 Students for Leaderboard Podium on Landing Page
require __DIR__ . '/../config/db.php';
$leaderboardSql = "SELECT 
    u.firstname, 
    u.lastname, 
    u.profile_picture,
    COALESCE(rp.reward_count, 0) AS reward_points,
    COALESCE(rp.total_hours, 0.00) AS total_hours,
    COALESCE(rp.tasks_completed, 0) AS tasks_completed,
    COALESCE(rp.total_score, 0.00) AS total_points
FROM users u
LEFT JOIN (
    SELECT 
        idno, 
        COUNT(reward_id) AS reward_count,
        SUM(hours_used) AS total_hours,
        SUM(task_completed) AS tasks_completed,
        SUM(leaderboard_score) AS total_score
    FROM rewards
    GROUP BY idno
) rp ON u.idno = rp.idno
WHERE u.role = 'student'
ORDER BY total_points DESC, u.lastname ASC
LIMIT 3";
$leaderboardResult = $conn->query($leaderboardSql);
$leaderboardData = [];
if ($leaderboardResult && $leaderboardResult->num_rows > 0) {
    while ($row = $leaderboardResult->fetch_assoc()) { 
        $leaderboardData[] = $row; 
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Sit-In Monitoring System – UC CCS</title>
<meta name="description" content="A role-based digital solution for tracking and managing student sit-in sessions in the College of Computer Studies.">
<link rel="icon" type="image/png" href="resources/ccslogo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ===== CSS VARIABLES ===== */
:root {
    --bg:            #0D0B1A;
    --card-purple:   #1A1530;
    --card-brown:    #1E1208;
    --purple:        #8B3FD9;
    --purple-dk:     #7B2FBE;
    --gold:          #D4870A;
    --gold-lt:       #E09B1A;
    --heading:       #C084FC;
    --body-text:     #D1C7E0;
    --sub:           #9A8FB0;
    --border:        rgba(139,63,217,0.3);
    --border-gold:   rgba(212,135,10,0.3);
    --font-h:        'Orbitron', sans-serif;
    --font-b:        'Inter', sans-serif;
    --side-pad:      120px;
}

/* ===== RESET ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; }

/* ===== SCROLL SNAP CONTAINER ===== */
.snap-wrap {
    height: 100vh;
    overflow-y: scroll;
    scroll-snap-type: y mandatory;
    scroll-behavior: smooth;
}
.snap-wrap::-webkit-scrollbar { width: 5px; }
.snap-wrap::-webkit-scrollbar-track { background: var(--bg); }
.snap-wrap::-webkit-scrollbar-thumb { background: var(--purple); border-radius: 3px; }

/* ===== SECTION BASE ===== */
.snap-section {
    scroll-snap-align: start;
    min-height: 100vh;
    height: 100vh;
    background: transparent;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ===== INNER CONTENT MAX-WIDTH ===== */
.inner {
    width: 100%;
    padding: 0 var(--side-pad);
}

/* ===== ANIMATIONS ===== */
.fade-up {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s ease, transform 0.65s ease;
}
.fade-up.visible {
    opacity: 1;
    transform: translateY(0);
}
.fade-up:nth-child(2) { transition-delay: 0.1s; }
.fade-up:nth-child(3) { transition-delay: 0.2s; }
.fade-up:nth-child(4) { transition-delay: 0.3s; }
.fade-up:nth-child(5) { transition-delay: 0.4s; }

/* ===== NAVBAR ===== */
.navbar {
    background: rgba(13,11,26,0.95);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 14px 0;
    flex-shrink: 0;
}
.nav-inner {
    width: 100%;
    padding: 0 var(--side-pad);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.brand img { width: 32px; height: 32px; object-fit: contain; }
.brand-texts { display: flex; flex-direction: column; line-height: 1.15; }
.brand-name { font-family: var(--font-h); font-size: 12px; font-weight: 700; color: white; letter-spacing: 1.5px; }
.brand-sub  { font-size: 9px; color: var(--sub); letter-spacing: 0.4px; }
.nav-btns { display: flex; gap: 10px; align-items: center; }
.btn-outline {
    padding: 7px 22px;
    border: 1px solid rgba(139,63,217, 0.7);
    border-radius: 20px;
    background: transparent;
    color: #fff;
    font-family: var(--font-b);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-outline:hover { background: rgba(139,63,217, 0.15); border-color: #8B3FD9; }
.btn-gold {
    padding: 7px 22px;
    border: none;
    border-radius: 20px;
    background: #D4870A;
    color: #fff;
    font-family: var(--font-b);
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-gold:hover { background: #E09B1A; box-shadow: 0 0 16px rgba(212,135,10,0.55); }

/* ===== SECTION 1: HERO ===== */
#hero {
    flex-direction: column;
}
.hero-body {
    flex: 1;
    display: flex;
    align-items: center;
}
.hero-grid {
    width: 100%;
    padding: 0 var(--side-pad);
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    align-items: stretch;
    gap: clamp(40px, 5vw, 80px);
}
.hero-left {
    display: flex;
    flex-direction: column;
    gap: clamp(18px, 2.5vh, 28px);
    justify-content: center;
}

.badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 16px; background: var(--card-purple);
    border: 1px solid var(--border); border-left: 3px solid var(--purple);
    border-radius: 20px; font-size: 11px; font-weight: 600;
    color: var(--body-text); letter-spacing: 1px; width: fit-content;
}
.badge i { color: var(--purple); font-size: 12px; }

.hero-title {
    font-family: var(--font-h);
    font-size: clamp(58px, 7vw, 88px);
    font-weight: 900;
    line-height: 1.05;
    color: var(--heading);
}

.hero-desc {
    font-family: var(--font-b);
    font-size: clamp(15px, 1.3vw, 17px);
    color: var(--sub);
    line-height: 1.85;
    max-width: 520px;
}

.cta-row { display: flex; gap: 14px; flex-wrap: wrap; }
.btn-cta.purple {
    background: #8B3FD9; color: #fff;
    border: 1px solid #8B3FD9; border-radius: 8px;
    height: 56px; padding: 0 34px;
    font-family: var(--font-b); font-weight: 600; font-size: 16px;
    display: inline-flex; align-items: center; gap: 9px;
    text-decoration: none; transition: all .25s; flex-shrink: 0;
}
.btn-cta.purple:hover { background: #A855F7; box-shadow: 0 4px 24px rgba(139,63,217,.5); transform: translateY(-2px); }
.btn-cta.gold-outline {
    background: transparent; color: #D4870A;
    border: 1.5px solid #D4870A; border-radius: 8px;
    height: 56px; padding: 0 34px;
    font-family: var(--font-b); font-weight: 600; font-size: 16px;
    display: inline-flex; align-items: center; gap: 9px;
    text-decoration: none; transition: all .25s; flex-shrink: 0;
}
.btn-cta.gold-outline:hover { background: rgba(212,135,10,.08); border-color: #E09B1A; color: #E09B1A; transform: translateY(-2px); }

.role-dots { display: flex; gap: 22px; flex-wrap: wrap; }
.rdot { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--sub); }
.dot { width: 7px; height: 7px; border-radius: 50%; }
.dot.p { background: var(--purple); }
.dot.g { background: var(--gold); }

.hero-right {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}
.shield {
    height: min(85vh, 680px);
    width: auto;
    max-width: 100%;
    object-fit: contain;
    filter: brightness(0.75) drop-shadow(0 0 40px rgba(255, 255, 255, 0.35));
    animation: float 4s ease-in-out infinite;
}
@keyframes float {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-10px); }
}

/* ===== SECTION 2: FEATURES ===== */
#features {
    justify-content: center;
    align-items: center;
}
.sec-head { text-align: center; }
#features .sec-head { margin-bottom: 48px; }
#access .sec-head { margin-bottom: 44px; }
.sec-title {
    font-family: var(--font-h);
    font-size: clamp(36px, 4.5vw, 54px);
    font-weight: 700;
    color: #C084FC;
    margin-bottom: 10px;
}
.sec-title.gold-t { color: #D4870A; }
.sec-sub { font-size: 14px; color: var(--sub); }

.features-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}
.fcard {
    display: flex; align-items: flex-start; gap: 24px;
    padding: 48px 40px; border-radius: 14px;
    border: 1px solid var(--border);
    transition: transform .25s, box-shadow .25s;
}
.fcard:hover { transform: translateY(-4px); }
.fcard.purple { 
    background: #1A1530; 
    border-color: rgba(139,63,217,0.4); 
    box-shadow: 0 0 30px rgba(139,63,217,0.15), inset 0 0 20px rgba(139,63,217,0.05);
}
.fcard.purple:hover { box-shadow: 0 0 40px rgba(139,63,217,0.25), inset 0 0 20px rgba(139,63,217,0.1); }
.fcard.brown  { 
    background: #1E1208; 
    border-color: rgba(212,135,10,0.4); 
    box-shadow: 0 0 30px rgba(212,135,10,0.15), inset 0 0 20px rgba(212,135,10,0.05);
}
.fcard.brown:hover { box-shadow: 0 0 40px rgba(212,135,10,0.25), inset 0 0 20px rgba(212,135,10,0.1); }
.fcard-icon {
    flex-shrink: 0; width: 52px; height: 52px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.fcard-icon.p { background: #8B3FD9; }
.fcard-icon.g { background: #D4870A; }
.fcard-icon i { color: #fff; font-size: 24px; }
.fcard-body { flex: 1; }
.fcard-title { font-family: var(--font-h); font-size: clamp(17px, 1.5vw, 21px); font-weight: 600; margin-bottom: 8px; }
.fcard.purple .fcard-title { color: #ffffff; }
.fcard.brown  .fcard-title { color: #D4870A; }
.fcard-desc { font-size: 14px; color: var(--sub); line-height: 1.75; }

/* ===== SECTION 3: ACCESS LEVELS + FOOTER ===== */
#access {
    justify-content: space-between;
}
.access-content {
    flex: 1;
    display: flex;
    align-items: center;
}
.access-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 32px;
    max-width: 900px;
    margin: 0 auto;
}
.acard {
    background: #1A1530;
    border: 1px solid rgba(139,63,217,0.25);
    border-radius: 14px;
    padding: 36px 28px;
    display: flex; flex-direction: column; align-items: center; text-align: center;
    transition: transform .25s, box-shadow .25s;
}
.acard:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(139,63,217,.25); }
.aicon {
    width: 72px; height: 72px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; margin-bottom: 16px;
}
.aicon.p { background: #8B3FD9; }
.aicon.g { background: #D4870A; }
.aicon i { color: #fff; font-size: 30px; }
.acard-title { font-family: var(--font-h); font-size: clamp(18px, 1.6vw, 22px); font-weight: 700; margin-bottom: 20px; }
.acard.white-t .acard-title { color: #ffffff; }
.acard.gold-t  .acard-title { color: #D4870A; }
.checklist { list-style: none; width: 100%; text-align: left; display: flex; flex-direction: column; gap: 12px; }
.checklist li { display: flex; align-items: center; gap: 9px; font-size: 14px; color: var(--body-text); font-family: var(--font-b); }
.checklist.cp li::before { content: '✓'; font-weight: 700; color: #8B3FD9; flex-shrink: 0; }
.checklist.cg li::before { content: '✓'; font-weight: 700; color: #D4870A; flex-shrink: 0; }

/* ===== FOOTER ===== */
footer {
    background: rgba(13, 11, 26, 0.85); 
    border-top: 1px solid rgba(139, 63, 217, 0.3);
    padding: 16px 0;
    flex-shrink: 0;
}
.footer-bottom {
    text-align: center;
    font-size: 11px;
    color: var(--sub);
    font-family: var(--font-b);
}

/* ===== BACK TO TOP ===== */
#back-top {
    position: fixed;
    bottom: 28px; right: 28px;
    width: 46px; height: 46px;
    border-radius: 50%;
    background: var(--gold);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    box-shadow: 0 4px 16px rgba(212,135,10,.4);
    transition: all .25s;
    opacity: 0;
    pointer-events: none;
    z-index: 999;
}
#back-top.show { opacity: 1; pointer-events: auto; }
#back-top:hover { background: var(--gold-lt); box-shadow: 0 4px 24px rgba(212,135,10,.65); transform: translateY(-2px); }

/* ===== DIVIDER ===== */
.divider { height: 1px; background: var(--border); }

/* ===== RESPONSIVE ===== */
@media (min-width: 1440px) {
    :root { --side-pad: 160px; }
    .hero-title { font-size: clamp(56px, 5vw, 76px); }
    .shield { width: clamp(400px, 30vw, 550px); height: clamp(400px, 30vw, 550px); }
}
@media (max-width: 1023px) {
    :root { --side-pad: 80px; }
    .hero-grid { grid-template-columns: 1fr minmax(200px, 260px); gap: 28px; }
    .hero-title { font-size: clamp(36px, 4.5vw, 52px); }
    .access-grid { gap: 12px; }
    .acard { padding: 22px 14px; }
}
@media (max-width: 767px) {
    :root { --side-pad: 24px; }
    .snap-section { height: auto; min-height: 100vh; overflow-y: auto; }
    .snap-wrap { scroll-snap-type: none; }
    .hero-grid { grid-template-columns: 1fr; gap: 0; }
    .hero-right { display: none; }
    .hero-title { font-size: clamp(32px, 8vw, 44px); }
    .hero-desc { max-width: 100%; }
    .cta-row { flex-direction: column; }
    .btn-cta { justify-content: center; width: 100%; }
    .features-grid { grid-template-columns: 1fr; }
    .access-grid { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; gap: 20px; }
    .brand-sub { display: none; }
    #access { height: auto; }
    .sec-title { font-size: clamp(26px, 6vw, 34px); }
}
@media (max-width: 479px) {
    :root { --side-pad: 16px; }
    .hero-title { font-size: 30px; }
    .btn-nav-login { display: none; }
    .brand-name { font-size: 10px; letter-spacing: 1px; }
}
</style>

<style>
/* Star canvas sits behind everything */
#star-canvas {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    z-index: 0;
    display: block;
}
.snap-wrap { position: relative; z-index: 1; background: transparent; }
.navbar { background: rgba(13,11,26,0.85) !important; }
footer  { background: rgba(13,11,26,0.9)  !important; }
.fcard  { backdrop-filter: blur(2px); }
.acard  { backdrop-filter: blur(2px); }

/* ===== INTERACTIVE PANEL MODES (LOGO & LEADERBOARD SWITCHER) ===== */
.btn-toggle-panel {
    background: linear-gradient(135deg, var(--purple), var(--purple-dk));
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 8px 24px rgba(139, 63, 217, 0.6);
    color: #fff;
    font-family: var(--font-h);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 10px 20px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.btn-toggle-panel:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 12px 30px rgba(139, 63, 217, 0.8);
    background: linear-gradient(135deg, #A855F7, var(--purple));
}

.panel-mode {
    position: absolute;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transform: scale(0.92) translateY(10px);
    transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    overflow: visible;
}

.panel-mode.active {
    opacity: 1;
    pointer-events: auto;
    transform: scale(1) translateY(0);
    position: relative;
}

/* ===== LEADERBOARD PODIUM STYLING (MATCHES LEADER.PHP & REWARDS.PHP EXACTLY) ===== */
.leaderboard-podium {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    max-width: 580px;
    margin: 40px auto 45px auto;
    gap: 16px;
    align-items: flex-end;
    justify-items: center;
    overflow: visible;
    width: 100%;
}

.podium-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 150px;
    position: relative;
    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.3s ease;
}

.podium-column:hover {
    transform: translateY(-12px) scale(1.03) !important;
    filter: drop-shadow(0 15px 30px rgba(139, 63, 217, 0.3));
    z-index: 9999 !important;
}

.podium-hover-card {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(-10px);
    background: rgba(22, 19, 38, 0.98);
    border: 1px solid rgba(139, 63, 217, 0.5);
    border-radius: 14px;
    padding: 14px 18px;
    width: 220px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), 0 0 20px rgba(139, 63, 217, 0.35);
    backdrop-filter: blur(15px);
    z-index: 99999 !important;
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    text-align: left;
}

.podium-column:hover .podium-hover-card {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(-50%) translateY(-20px);
}

.podium-hover-card::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 8px;
    border-style: solid;
    border-color: rgba(22, 19, 38, 0.98) transparent transparent transparent;
}

.hover-card-title {
    font-family: var(--font-h);
    font-size: 13px;
    font-weight: 700;
    color: #C084FC;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 6px;
}

.hover-card-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 12px;
}

.hover-card-row:last-child {
    margin-bottom: 0;
}

.hover-card-label {
    color: var(--sub);
    font-weight: 500;
}

.hover-card-val {
    color: #fff;
    font-weight: 700;
}

.podium-column.second { width: 150px; }
.podium-column.first { z-index: 10; width: 170px; }
.podium-column.third { width: 150px; }

.pedestal {
    width: 100%;
    background: linear-gradient(180deg, #1b162b 0%, #0d0a17 100%);
    border: 1px solid rgba(139, 63, 217, 0.25);
    border-bottom: none;
    border-radius: 12px 12px 0 0;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255,255,255,0.05);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 25px 10px;
    position: relative;
}

/* 1st Place Pedestal */
.podium-column.first .pedestal {
    height: 190px;
    border-color: rgba(251, 191, 36, 0.5);
    box-shadow: 0 20px 40px rgba(251, 191, 36, 0.15), 0 15px 35px rgba(0, 0, 0, 0.6);
}

/* 2nd Place Pedestal */
.podium-column.second .pedestal {
    height: 150px;
    border-color: rgba(156, 163, 175, 0.4);
}

/* 3rd Place Pedestal */
.podium-column.third .pedestal {
    height: 120px;
    border-color: rgba(180, 83, 9, 0.4);
}

.podium-avatar-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    width: 100%;
}

.avatar-ring {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Ensure all podium images and placeholders are perfectly circle */
.avatar-ring img, 
.avatar-placeholder {
    border-radius: 50% !important;
    object-fit: cover !important;
}

/* Avatar sizing based on rank */
.podium-column.first .avatar-ring img, .podium-column.first .avatar-placeholder {
    width: 98px; height: 98px;
    border: 4px solid #FBBF24;
}
.podium-column.second .avatar-ring img, .podium-column.second .avatar-placeholder {
    width: 82px; height: 82px;
    border: 4px solid #9CA3AF;
    box-shadow: 0 0 15px rgba(156, 163, 175, 0.25);
}
.podium-column.third .avatar-ring img, .podium-column.third .avatar-placeholder {
    width: 72px; height: 72px;
    border: 4px solid #B45309;
    box-shadow: 0 0 12px rgba(180, 83, 9, 0.2);
}

.avatar-placeholder {
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
}

/* Custom Text and Element Styles for Podium when Tailwind is absent */
.crown-1st {
    color: #FBBF24 !important;
    font-size: 28px !important;
    margin-bottom: 4px !important;
    filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.7));
}

.podium-student-name {
    font-family: var(--font-b);
    font-weight: 700 !important;
    color: #ffffff !important;
    font-size: 13px !important;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 6px;
    width: 130px;
}
.podium-column.first .podium-student-name {
    font-size: 15px !important;
    width: 150px;
}

/* Trophy Styles */
.pedestal i {
    margin-bottom: 6px !important;
    display: inline-block;
}
.trophy-1st {
    color: #FBBF24 !important;
    font-size: 24px !important;
    filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.4));
}
.trophy-2nd {
    color: #9CA3AF !important;
    font-size: 20px !important;
}
.trophy-3rd {
    color: #B45309 !important;
    font-size: 20px !important;
}

/* Rank Title Styles */
.pedestal span {
    display: block;
    font-family: var(--font-h);
}
.rank-title-1st {
    color: #FBBF24 !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.rank-title-2nd {
    color: #9CA3AF !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.rank-title-3rd {
    color: #B45309 !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* XP Value Styles */
.xp-val-1st {
    color: #FBBF24 !important;
    font-size: 18px !important;
    font-weight: 900 !important;
    margin-top: 4px !important;
}
.xp-val-2nd {
    color: #ffffff !important;
    font-size: 16px !important;
    font-weight: 800 !important;
    margin-top: 4px !important;
}
.xp-val-3rd {
    color: #ffffff !important;
    font-size: 16px !important;
    font-weight: 800 !important;
    margin-top: 4px !important;
}
</style>
</head>
<body style="background:#0D0B1A;">

<canvas id="star-canvas"></canvas>

<div class="snap-wrap" id="snapWrap">

    <!-- ===== SECTION 1: HERO ===== -->
    <section class="snap-section" id="hero">
        <!-- Navbar -->
        <nav class="navbar">
            <div class="nav-inner">
                <a class="brand" href="landingindex.php">
                    <img src="resources/ccslogo.png" alt="UC CCS Logo">
                    <div class="brand-texts">
                        <span class="brand-name">SIT-IN MONITORING</span>
                        <span class="brand-sub">UC – College of Computer Studies</span>
                    </div>
                </a>
                <div class="nav-btns">
                    <a href="login.php"    class="btn-outline btn-nav-login" id="nav-login">Login</a>
                    <a href="register.php" class="btn-gold"                  id="nav-reg">Register</a>
                </div>
            </div>
        </nav>

        <!-- Hero Body -->
        <div class="hero-body">
            <div class="hero-grid">
                <div class="hero-left">
                    <div class="badge fade-up">
                        <i class="fas fa-shield-halved"></i>
                        <span>SIT-IN MONITORING</span>
                    </div>

                    <h1 class="hero-title fade-up">Smart Sit-In<br><span style="color: var(--gold);">Monitoring</span></h1>

                    <p class="hero-desc fade-up">
                        A role-based digital solution for tracking and managing student sit-in sessions in the College of Computer Studies. Real-time monitoring, secure authentication, and comprehensive analytics.
                    </p>

                    <div class="cta-row fade-up">
                        <a href="login.php"    class="btn-cta purple"  id="hero-login"><i class="fas fa-arrow-right-to-bracket"></i> Login to System</a>
                        <a href="register.php" class="btn-cta gold-outline"  id="hero-reg"><i class="fas fa-user-plus"></i> Create Account</a>
                    </div>

                    <div class="role-dots fade-up">
                        <div class="rdot"><span class="dot p"></span> Student Access</div>
                        <div class="rdot"><span class="dot p"></span> Admin Dashboard</div>
                    </div>
                </div>

                <div class="hero-right fade-up" style="position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 520px; width: 100%;">
                    <!-- Mode Switcher Button -->
                    <div style="margin-bottom: 25px; z-index: 1; position: relative;">
                        <button id="togglePanelBtn" class="btn-toggle-panel" title="Switch View">
                            <i class="fas fa-trophy mr-1"></i> <span id="toggleText">Leaderboard</span>
                        </button>
                    </div>
                    
                    <!-- Free-floating content container (No outer box!) -->
                    <div style="position: relative; z-index: 5; width: 100%; display: flex; align-items: center; justify-content: center; flex: 1; overflow: visible;">
                        <!-- Logo Mode (Visible by default) -->
                        <div id="panelLogo" class="panel-mode active" style="display: flex; justify-content: center; width: 100%;">
                            <img src="resources/ccslogo.png" alt="UC CCS Shield" class="shield" style="animation: float 4s ease-in-out infinite;">
                        </div>
                        
                        <!-- Leaderboard Mode (No Bounding Card/Box) -->
                        <div id="panelLeaderboard" class="panel-mode" style="width: 100%;">
                            <div class="leaderboard-podium">
                                <!-- 2nd Place -->
                                <?php if (isset($leaderboardData[1])): 
                                    $p2 = $leaderboardData[1];
                                    $p2Avatar = $p2['profile_picture'] && $p2['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p2['profile_picture']) ? "upload/".$p2['profile_picture'] : "";
                                ?>
                                <div class="podium-column second">
                                    <div class="podium-hover-card">
                                        <div class="hover-card-title"><?php echo htmlspecialchars($p2['firstname'] . ' ' . $p2['lastname']); ?></div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Hours</span>
                                            <span class="hover-card-val"><?php echo number_format((float)$p2['total_hours'], 2); ?> hrs</span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Rewards</span>
                                            <span class="hover-card-val"><?php echo (int)$p2['reward_points']; ?></span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Tasks Completed</span>
                                            <span class="hover-card-val"><?php echo (int)$p2['tasks_completed']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="podium-avatar-wrapper">
                                        <div class="avatar-ring mb-2">
                                            <?php if($p2Avatar): ?>
                                                <img src="<?php echo $p2Avatar; ?>" alt="2nd Place">
                                            <?php else: ?>
                                                <div class="avatar-placeholder" style="background: rgba(139, 63, 217, 0.15);">
                                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; padding: 6px; fill: #D1C7E0; border-radius: 50%;">
                                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="podium-student-name"><?php echo htmlspecialchars($p2['firstname'] . ' ' . $p2['lastname']); ?></div>
                                    </div>
                                    
                                    <div class="pedestal">
                                        <i class="fas fa-trophy trophy-2nd"></i>
                                        <span class="rank-title-2nd">2nd Place</span>
                                        <span class="xp-val-2nd"><?php echo number_format($p2['total_points'], 2); ?> XP</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="podium-column second opacity-0 pointer-events-none select-none"></div>
                                <?php endif; ?>

                                <!-- 1st Place -->
                                <?php if (isset($leaderboardData[0])): 
                                    $p1 = $leaderboardData[0];
                                    $p1Avatar = $p1['profile_picture'] && $p1['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p1['profile_picture']) ? "upload/".$p1['profile_picture'] : "";
                                ?>
                                <div class="podium-column first">
                                    <div class="podium-hover-card">
                                        <div class="hover-card-title"><?php echo htmlspecialchars($p1['firstname'] . ' ' . $p1['lastname']); ?></div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Hours</span>
                                            <span class="hover-card-val"><?php echo number_format((float)$p1['total_hours'], 2); ?> hrs</span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Rewards</span>
                                            <span class="hover-card-val"><?php echo (int)$p1['reward_points']; ?></span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Tasks Completed</span>
                                            <span class="hover-card-val"><?php echo (int)$p1['tasks_completed']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="podium-avatar-wrapper">
                                        <i class="fas fa-crown crown-1st"></i>
                                        <div class="avatar-ring mb-2">
                                            <?php if($p1Avatar): ?>
                                                <img src="<?php echo $p1Avatar; ?>" alt="1st Place">
                                            <?php else: ?>
                                                <div class="avatar-placeholder" style="background: rgba(139, 63, 217, 0.15);">
                                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; padding: 8px; fill: #D1C7E0; border-radius: 50%;">
                                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="podium-student-name"><?php echo htmlspecialchars($p1['firstname'] . ' ' . $p1['lastname']); ?></div>
                                    </div>
                                    
                                    <div class="pedestal">
                                        <i class="fas fa-trophy trophy-1st"></i>
                                        <span class="rank-title-1st">Champion</span>
                                        <span class="xp-val-1st"><?php echo number_format($p1['total_points'], 2); ?> XP</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="podium-column first opacity-0 pointer-events-none select-none"></div>
                                <?php endif; ?>

                                <!-- 3rd Place -->
                                <?php if (isset($leaderboardData[2])): 
                                    $p3 = $leaderboardData[2];
                                    $p3Avatar = $p3['profile_picture'] && $p3['profile_picture'] != 'default-profile.png' && file_exists(__DIR__ . '/upload/' . $p3['profile_picture']) ? "upload/".$p3['profile_picture'] : "";
                                ?>
                                <div class="podium-column third">
                                    <div class="podium-hover-card">
                                        <div class="hover-card-title"><?php echo htmlspecialchars($p3['firstname'] . ' ' . $p3['lastname']); ?></div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Hours</span>
                                            <span class="hover-card-val"><?php echo number_format((float)$p3['total_hours'], 2); ?> hrs</span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Total Rewards</span>
                                            <span class="hover-card-val"><?php echo (int)$p3['reward_points']; ?></span>
                                        </div>
                                        <div class="hover-card-row">
                                            <span class="hover-card-label">Tasks Completed</span>
                                            <span class="hover-card-val"><?php echo (int)$p3['tasks_completed']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="podium-avatar-wrapper">
                                        <div class="avatar-ring mb-2">
                                            <?php if($p3Avatar): ?>
                                                <img src="<?php echo $p3Avatar; ?>" alt="3rd Place">
                                            <?php else: ?>
                                                <div class="avatar-placeholder" style="background: rgba(139, 63, 217, 0.15);">
                                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; padding: 5px; fill: #D1C7E0; border-radius: 50%;">
                                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="podium-student-name"><?php echo htmlspecialchars($p3['firstname'] . ' ' . $p3['lastname']); ?></div>
                                    </div>
                                    
                                    <div class="pedestal">
                                        <i class="fas fa-trophy trophy-3rd"></i>
                                        <span class="rank-title-3rd">3rd Place</span>
                                        <span class="xp-val-3rd"><?php echo number_format($p3['total_points'], 2); ?> XP</span>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="podium-column third opacity-0 pointer-events-none select-none"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== SECTION 2: FEATURES ===== -->
    <section class="snap-section" id="features">
        <div class="divider"></div>
        <div style="flex:1;display:flex;align-items:center;">
            <div class="inner" style="width:100%">
                <div class="sec-head">
                    <h2 class="sec-title fade-up">System Features</h2>
                    <p class="sec-sub fade-up">Advanced capabilities designed for seamless sit-in session management</p>
                </div>
                <div class="features-grid">
                    <div class="fcard purple fade-up">
                        <div class="fcard-icon p"><i class="fas fa-display"></i></div>
                        <div class="fcard-body">
                            <h3 class="fcard-title">Real-Time Tracking</h3>
                            <p class="fcard-desc">Monitor all active sit-in sessions in real-time with live status updates and instant notifications.</p>
                        </div>
                    </div>
                    <div class="fcard brown fade-up">
                        <div class="fcard-icon g"><i class="fas fa-users-gear"></i></div>
                        <div class="fcard-body">
                            <h3 class="fcard-title">Role-Based Access</h3>
                            <p class="fcard-desc">Separate portals for Students and Admins with customized permissions and features.</p>
                        </div>
                    </div>
                    <div class="fcard purple fade-up">
                        <div class="fcard-icon p"><i class="fas fa-chart-line"></i></div>
                        <div class="fcard-body">
                            <h3 class="fcard-title">Analytics &amp; Reports</h3>
                            <p class="fcard-desc">Comprehensive session reports, attendance analytics, and exportable data for administrative use.</p>
                        </div>
                    </div>
                    <div class="fcard brown fade-up">
                        <div class="fcard-icon g"><i class="fas fa-lock"></i></div>
                        <div class="fcard-body">
                            <h3 class="fcard-title">Secure Authentication</h3>
                            <p class="fcard-desc">Enterprise-grade security with encrypted credentials, session management, and audit trails.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="divider"></div>
    </section>

    <!-- ===== SECTION 3: ACCESS LEVELS + FOOTER ===== -->
    <section class="snap-section" id="access">
        <div class="access-content">
            <div class="inner" style="width:100%">
                <div class="sec-head">
                    <h2 class="sec-title gold-t fade-up">Access Levels</h2>
                    <p class="sec-sub fade-up">Tailored experiences for every user type in the system</p>
                </div>
                <div class="access-grid">
                    <div class="acard white-t fade-up">
                        <div class="aicon p"><i class="fas fa-user-graduate"></i></div>
                        <h3 class="acard-title">Student</h3>
                        <ul class="checklist cp">
                            <li>Log sit-in sessions</li>
                            <li>View attendance history</li>
                            <li>Track session duration</li>
                            <li>Receive notifications</li>
                        </ul>
                    </div>
                    <div class="acard white-t fade-up">
                        <div class="aicon p"><i class="fas fa-user-shield"></i></div>
                        <h3 class="acard-title">Administrator</h3>
                        <ul class="checklist cp">
                            <li>Full system control</li>
                            <li>User management</li>
                            <li>Analytics dashboard</li>
                            <li>System configuration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer pinned at bottom -->
        <footer>
            <div class="footer-bottom">
                &copy; 2026 University of Cebu - College of Computer Studies. All rights reserved.
            </div>
        </footer>
    </section>

</div><!-- end snap-wrap -->

<!-- Back to Top Button -->
<button id="back-top" aria-label="Back to top" title="Back to top">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
/* ===== STAR & SHOOTING STAR CANVAS ===== */
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

    // Create static stars
    for (let i = 0; i < 180; i++) {
        stars.push({
            x: Math.random() * 9999,
            y: Math.random() * 9999,
            r: Math.random() * 1.4 + 0.3,
            a: Math.random(),
            da: (Math.random() * 0.008 + 0.003) * (Math.random() < .5 ? 1 : -1)
        });
    }

    function spawnShoot() {
        shoots.push({
            x: Math.random() * W * 1.2,
            y: Math.random() * H * 0.5,
            len: Math.random() * 120 + 80,
            speed: Math.random() * 6 + 4,
            angle: Math.PI / 4,
            alpha: 1,
            tail: []
        });
    }
    setInterval(spawnShoot, 2400);
    spawnShoot();

    function draw() {
        ctx.clearRect(0, 0, W, H);

        // Twinkling stars
        stars.forEach(s => {
            s.a += s.da;
            if (s.a <= 0 || s.a >= 1) s.da *= -1;
            ctx.beginPath();
            ctx.arc(s.x % W, s.y % H, s.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200,180,255,${s.a.toFixed(2)})`;
            ctx.fill();
        });

        // Shooting stars
        for (let i = shoots.length - 1; i >= 0; i--) {
            const s = shoots[i];
            s.x += Math.cos(s.angle) * s.speed;
            s.y += Math.sin(s.angle) * s.speed;
            s.alpha -= 0.018;

            const grad = ctx.createLinearGradient(
                s.x - Math.cos(s.angle) * s.len,
                s.y - Math.sin(s.angle) * s.len,
                s.x, s.y
            );
            grad.addColorStop(0, `rgba(212,135,10,0)`);
            grad.addColorStop(0.4, `rgba(200,160,255,${(s.alpha * .6).toFixed(2)})`);
            grad.addColorStop(1, `rgba(255,255,255,${s.alpha.toFixed(2)})`);

            ctx.beginPath();
            ctx.moveTo(s.x - Math.cos(s.angle) * s.len, s.y - Math.sin(s.angle) * s.len);
            ctx.lineTo(s.x, s.y);
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            if (s.alpha <= 0 || s.x > W + 200 || s.y > H + 200) {
                shoots.splice(i, 1);
            }
        }

        requestAnimationFrame(draw);
    }
    draw();
})();

/* ===== BACK TO TOP ===== */
const snapWrap = document.getElementById('snapWrap');
const backTop  = document.getElementById('back-top');
snapWrap.addEventListener('scroll', () => {
    backTop.classList.toggle('show', snapWrap.scrollTop > 80);
});
backTop.addEventListener('click', () => {
    snapWrap.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ===== INTERSECTION OBSERVER ===== */
const fadeEls = document.querySelectorAll('.fade-up');
const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { root: snapWrap, threshold: 0.12 });
fadeEls.forEach(el => io.observe(el));

// ===== INTERACTIVE PANEL MODE SWITCHER =====
(function() {
    const toggleBtn = document.getElementById('togglePanelBtn');
    const toggleText = document.getElementById('toggleText');
    const panelLogo = document.getElementById('panelLogo');
    const panelLeaderboard = document.getElementById('panelLeaderboard');
    
    if (!toggleBtn || !panelLogo || !panelLeaderboard) return;
    
    let currentMode = 'logo'; // 'logo' or 'leaderboard'
    let autoTimer = null;
    let isPaused = false;
    
    function switchMode(mode) {
        if (mode === currentMode) return;
        
        if (mode === 'leaderboard') {
            panelLogo.classList.remove('active');
            setTimeout(() => {
                panelLeaderboard.classList.add('active');
            }, 100);
            toggleText.textContent = 'System Logo';
            toggleBtn.querySelector('i').className = 'fas fa-image mr-1';
            currentMode = 'leaderboard';
        } else {
            panelLeaderboard.classList.remove('active');
            setTimeout(() => {
                panelLogo.classList.add('active');
            }, 100);
            toggleText.textContent = 'Leaderboard';
            toggleBtn.querySelector('i').className = 'fas fa-trophy mr-1';
            currentMode = 'logo';
        }
    }
    
    // Toggle button handler
    toggleBtn.addEventListener('click', () => {
        isPaused = true; // Pause auto-rotation on manual interaction
        if (autoTimer) {
            clearInterval(autoTimer);
            autoTimer = null;
        }
        if (currentMode === 'logo') {
            switchMode('leaderboard');
        } else {
            switchMode('logo');
        }
    });
    
    // Auto Rotation every 8 seconds
    function startAutoRotation() {
        autoTimer = setInterval(() => {
            if (!isPaused) {
                if (currentMode === 'logo') {
                    switchMode('leaderboard');
                } else {
                    switchMode('logo');
                }
            }
        }, 8000);
    }
    
    startAutoRotation();
})();
</script>
</body>
</html>
