<?php
/**
 * Player Profile Overlay — 3D Broadcast View
 * Usage: player_profile.php?match_id=X&player_id=Y
 * Responsive 16:9 layout with GSAP 3D animations
 */
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__.'/config.php';

$mid = (int)($_GET['match_id'] ?? 0);
$pid = (int)($_GET['player_id'] ?? 0);

if (!$mid || !$pid) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body style="background:transparent;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;"><div style="background:rgba(0,0,0,0.6);padding:32px;border-radius:16px;text-align:center;"><h1>CricketLive</h1><p>Add ?match_id=X&player_id=Y</p></div></body></html>';
    exit;
}

$db = getDB();

// Fetch match + player info
$stmt = $db->prepare("SELECT m.*, ta.name AS a_name, ta.short_name AS a_short, ta.logo_path AS a_logo,
    tb.name AS b_name, tb.short_name AS b_short, tb.logo_path AS b_logo
    FROM matches m
    JOIN teams ta ON m.team_a_id = ta.id
    JOIN teams tb ON m.team_b_id = tb.id
    WHERE m.id = ?");
$stmt->execute([$mid]);
$m = $stmt->fetch();

if (!$m) { echo 'Match not found'; exit; }

// Fetch player
$stmt = $db->prepare("SELECT p.*, t.name AS team_name, t.short_name AS team_short, t.logo_path AS team_logo
    FROM players p
    JOIN teams t ON p.team_id = t.id
    WHERE p.id = ? AND p.is_active = 1");
$stmt->execute([$pid]);
$player = $stmt->fetch();

if (!$player) { echo 'Player not found'; exit; }

// Determine if player is in team A or B
$playerTeam = $player['team_id'];
$isTeamA = ($playerTeam == $m['team_a_id']);
$isTeamB = ($playerTeam == $m['team_b_id']);
$teamColor = $isTeamA ? 'orange' : 'blue';
$teamColorHex = $isTeamA ? '#FF9800' : '#0288D1';
$teamColorHex2 = $isTeamA ? '#e65100' : '#01579b';
$teamGradient = $isTeamA
    ? 'linear-gradient(135deg, #FF9800 0%, #F57C00 40%, #E65100 100%)'
    : 'linear-gradient(135deg, #0288D1 0%, #0277BD 40%, #01579B 100%)';
$teamGlow = $isTeamA
    ? '0 0 60px rgba(255,152,0,0.50), 0 0 120px rgba(255,152,0,0.25)'
    : '0 0 60px rgba(2,136,209,0.50), 0 0 120px rgba(2,136,209,0.25)';

// Fetch batting stats from match
$batStmt = $db->prepare("SELECT
    SUM(bi.runs_scored) AS total_runs,
    COUNT(DISTINCT bi.innings_number) AS total_innings,
    SUM(bi.balls_faced) AS total_balls,
    SUM(bi.fours) AS total_fours,
    SUM(bi.sixes) AS total_sixes,
    COUNT(DISTINCT CASE WHEN bi.dismissal_type IS NOT NULL AND bi.dismissal_type != '' AND bi.dismissal_type != 'not_out' THEN bi.innings_number END) AS dismissals,
    MAX(bi.runs_scored) AS highest_score
    FROM batsman_innings bi
    WHERE bi.match_id = ? AND bi.batsman_id = ?");
$batStmt->execute([$mid, $pid]);
$batStats = $batStmt->fetch();

// Fetch bowling stats from match
$bowlStmt = $db->prepare("SELECT
    SUM(bs.wickets_taken) AS total_wickets,
    SUM(bs.runs_conceded) AS runs_conceded,
    SUM(bs.maidens) AS maidens
    FROM bowler_spells bs
    WHERE bs.match_id = ? AND bs.bowler_id = ?");
$bowlStmt->execute([$mid, $pid]);
$bowlStats = $bowlStmt->fetch();

// Calculate derived stats
$batRuns = (int)($batStats['total_runs'] ?? 0);
$batInnings = (int)($batStats['total_innings'] ?? 0);
$batBalls = (int)($batStats['total_balls'] ?? 0);
$batFours = (int)($batStats['total_fours'] ?? 0);
$batSixes = (int)($batStats['total_sixes'] ?? 0);
$batDismissals = (int)($batStats['dismissals'] ?? 0);
$batMatches = (int)($batStats['matches_played'] ?? 0);
$batHS = (int)($batStats['highest_score'] ?? 0);
$batAvg = $batDismissals > 0 ? round($batRuns / $batDismissals, 2) : ($batInnings > 0 ? round($batRuns, 2) : 0);
$batSR = $batBalls > 0 ? round(($batRuns / $batBalls) * 100, 2) : 0;

$bowlWickets = (int)($bowlStats['total_wickets'] ?? 0);
$bowlRuns = (int)($bowlStats['runs_conceded'] ?? 0);
$bowlMaidens = (int)($bowlStats['maidens'] ?? 0);
// Fetch individual spells to calculate balls
$spellsStmt = $db->prepare("SELECT overs_bowled FROM bowler_spells WHERE match_id = ? AND bowler_id = ?");
$spellsStmt->execute([$mid, $pid]);
$spells = $spellsStmt->fetchAll();
$bowlBalls = 0;
foreach ($spells as $spell) {
    $ov = (float)($spell['overs_bowled'] ?? 0);
    $bowlBalls += (int)(floor($ov)) * 6 + (int)(round(($ov - floor($ov)) * 10));
}
$bowlOvers = $bowlBalls > 0 ? floor($bowlBalls / 6) + (($bowlBalls % 6) / 10) : 0;
$bowlEcon = $bowlBalls > 0 ? round(($bowlRuns / ($bowlBalls / 6)), 2) : 0;
$bowlAvg = $bowlWickets > 0 ? round($bowlRuns / $bowlWickets, 2) : 0;

// Role display
$role = strtoupper(str_replace('-', ' ', $player['role'] ?? 'Player'));
$battingStyle = $player['batting_style'] ?? '';
$bowlingStyle = $player['bowling_style'] ?? '';
$photoPath = $player['photo_path'] ?? '';
$teamLogo = $player['team_logo'] ?? '';

// No stats fallback data for display
$hasStats = ($batInnings > 0 || $bowlWickets > 0);

// Player name
$playerName = htmlspecialchars($player['name']);
$teamName = htmlspecialchars($player['team_short'] ?? $player['team_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>PLAYER PROFILE — <?php echo $playerName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&family=Teko:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js">
</script>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:100vw;height:100vh;overflow:hidden;font-family:'Hanken Grotesk',sans-serif;color:#fff;-webkit-font-smoothing:antialiased;background:transparent!important}

/* ── MAIN STAGE ── */
#stage{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;perspective:1600px;perspective-origin:center center;background:radial-gradient(ellipse at center, rgba(10,10,30,0.6) 0%, rgba(0,0,0,0.85) 100%)}

#main-card{position:relative;width:min(92vw, 1700px);height:min(90vh, calc(92vw * 9/16));max-height:960px;display:flex;border-radius:28px;overflow:hidden;transform-style:preserve-3d;transform:rotateY(-2deg) rotateX(3deg);box-shadow:
    0 30px 80px rgba(0,0,0,0.60),
    0 0 120px <?php echo $teamColorHex ?>22,
    inset 0 1px 0 rgba(255,255,255,0.08);
transition:transform 0.6s cubic-bezier(0.23, 1, 0.32, 1)}

/* ── LEFT PANEL (35%) ── */
#left-panel{flex:0 0 35%;position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;background:linear-gradient(180deg,
    <?php echo $teamColorHex ?>dd 0%,
    <?php echo $teamColorHex2 ?>ee 40%,
    #0a0a1e 100%);z-index:2;backface-visibility:hidden;transform:translateZ(0)}
#left-panel::before{content:'';position:absolute;inset:0;background:
    radial-gradient(ellipse at 50% 30%, <?php echo $teamColorHex ?>44 0%, transparent 70%),
    radial-gradient(ellipse at 80% 80%, <?php echo $teamColorHex ?>33 0%, transparent 50%),
    radial-gradient(ellipse at 20% 90%, #ffffff11 0%, transparent 40%);
pointer-events:none;z-index:0}
#left-panel::after{content:'';position:absolute;inset:0;background-image:
    repeating-linear-gradient(135deg, rgba(255,255,255,0.03) 0px, rgba(255,255,255,0.03) 1px, transparent 1px, transparent 20px);
pointer-events:none;z-index:0;opacity:0.5}

/* Team logo circle */
#team-badge{position:absolute;top:clamp(20px,4vh,50px);right:clamp(20px,4vh,50px);width:clamp(60px,10vh,110px);height:clamp(60px,10vh,110px);border-radius:50%;background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:3px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;z-index:10;box-shadow:0 8px 32px rgba(0,0,0,0.30)}
#team-badge img{width:80%;height:80%;object-fit:contain}
#team-badge .t-ini{font-size:clamp(18px,3vh,36px);font-weight:800;color:rgba(255,255,255,0.40)}

/* Player image area */
#player-img-wrap{position:relative;width:100%;flex:1;display:flex;align-items:flex-end;justify-content:center;z-index:5;padding:clamp(10px,2vh,20px);min-height:0;filter:drop-shadow(0 20px 50px rgba(0,0,0,0.50))}
#player-img-wrap img{max-width:90%;max-height:100%;object-fit:contain;object-position:center bottom}
#player-img-wrap .p-placeholder{font-size:clamp(100px,18vh,220px);font-weight:900;color:rgba(255,255,255,0.08);display:flex;align-items:flex-end;justify-content:center;height:100%}

/* Name plate */
#name-plate{width:100%;padding:0 clamp(14px,2vw,28px) clamp(14px,2vh,28px);z-index:10;display:flex;flex-direction:column;align-items:center;text-align:center}
#name-plate h1{font-family:'Teko',sans-serif;font-size:clamp(28px,5vh,56px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;line-height:1.05;color:#fff;text-shadow:0 4px 20px rgba(0,0,0,0.35)}
#name-plate .role-tag{display:inline-block;font-family:'Teko',sans-serif;font-size:clamp(14px,2.2vh,22px);font-weight:600;text-transform:uppercase;letter-spacing:0.10em;color:<?php echo $teamColorHex ?>;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.15);padding:clamp(2px,0.3vh,5px) clamp(12px,1.5vw,20px);border-radius:6px;margin-top:clamp(4px,0.8vh,8px)}
#name-plate .team-label{font-size:clamp(10px,1.5vh,14px);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;opacity:0.55;margin-top:clamp(4px,0.8vh,8px)}

/* Floating stats chips on left panel */
.float-stat{position:absolute;z-index:8;backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);background:rgba(10,10,30,0.60);border:1px solid rgba(255,255,255,0.12);border-radius:14px;padding:clamp(8px,1.2vh,14px) clamp(12px,1.6vw,20px);display:flex;flex-direction:column;align-items:center;gap:2px;box-shadow:0 8px 24px rgba(0,0,0,0.35);animation:float 6s ease-in-out infinite}
.float-stat .fs-val{font-family:'Teko',sans-serif;font-size:clamp(18px,2.8vh,34px);font-weight:700;color:<?php echo $teamColorHex ?>;line-height:1;text-shadow:0 0 20px <?php echo $teamColorHex ?>44}
.float-stat .fs-lbl{font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.60}
.fs-1{top:12%;left:clamp(10px,2vw,24px);animation-delay:0s}
.fs-2{top:38%;right:clamp(10px,2vw,24px);animation-delay:-2s}
.fs-3{bottom:20%;left:clamp(14px,2.5vw,30px);animation-delay:-4s}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}

/* ── RIGHT PANEL (65%) ── */
#right-panel{flex:0 0 65%;position:relative;background:linear-gradient(180deg,
    #0f1025 0%,
    #14152e 30%,
    #0a0b20 100%);z-index:2;display:flex;flex-direction:column;padding:clamp(20px,3vh,40px) clamp(24px,3vw,40px);backface-visibility:hidden;transform:translateZ(0);
border-left:1px solid rgba(255,255,255,0.06)}
#right-panel::before{content:'';position:absolute;inset:0;background:
    radial-gradient(ellipse at 80% 20%, <?php echo $teamColorHex ?>15 0%, transparent 50%),
    radial-gradient(ellipse at 20% 80%, #ffffff05 0%, transparent 60%);
pointer-events:none;z-index:0}

/* Right content wrapper */
#right-content{position:relative;z-index:1;flex:1;display:flex;flex-direction:column;gap:clamp(14px,2vh,24px)}

/* ── Header Row ── */
#card-header{display:flex;align-items:center;justify-content:space-between;gap:clamp(10px,1.5vw,20px)}
#card-header .head-left{flex:1}
#card-header .head-left .stat-title{font-family:'Teko',sans-serif;font-size:clamp(20px,3.2vh,36px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#fff}
#card-header .head-left .stat-subtitle{font-size:clamp(9px,1.3vh,13px);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;opacity:0.45;margin-top:2px}
#card-header .head-accents{display:flex;gap:clamp(6px,0.8vw,10px)}
.accent-dot{width:clamp(8px,1.2vh,12px);height:clamp(8px,1.2vh,12px);border-radius:50%;background:<?php echo $teamColorHex ?>;box-shadow:0 0 16px <?php echo $teamColorHex ?>88;animation:pulseDot 1.5s ease-in-out infinite}
.accent-dot:nth-child(2){animation-delay:-0.5s;opacity:0.6}
.accent-dot:nth-child(3){animation-delay:-1s;opacity:0.3}
@keyframes pulseDot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.5);opacity:0.5}}

/* ── Stats Grid ── */
#stats-grid{display:grid;grid-template-columns:repeat(3, 1fr);gap:clamp(10px,1.4vh,18px);flex:1}

/* Stat card */
.stat-card{position:relative;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:18px;padding:clamp(14px,2vh,22px);display:flex;flex-direction:column;gap:clamp(6px,1vh,12px);transform-style:preserve-3d;transform:translateZ(0);transition:all 0.35s cubic-bezier(0.23, 1, 0.32, 1);opacity:0;backface-visibility:hidden}
.stat-card:hover{background:rgba(255,255,255,0.06);border-color:<?php echo $teamColorHex ?>44;transform:translateZ(20px) rotateX(-3deg);box-shadow:0 12px 40px rgba(0,0,0,0.30), 0 0 30px <?php echo $teamColorHex ?>15}
.stat-card .sc-header{display:flex;align-items:center;justify-content:space-between}
.stat-card .sc-label{font-size:clamp(10px,1.5vh,14px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.40}
.stat-card .sc-icon{width:clamp(30px,4.5vh,46px);height:clamp(30px,4.5vh,46px);border-radius:12px;display:flex;align-items:center;justify-content:center;background:<?php echo $teamColorHex ?>18;border:1px solid <?php echo $teamColorHex ?>30;font-size:clamp(14px,2vh,22px)}
.stat-card .sc-value{font-family:'Teko',sans-serif;font-size:clamp(32px,5.5vh,68px);font-weight:700;line-height:1;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,0.30)}
.stat-card .sc-value .sc-unit{font-size:40%;font-weight:400;opacity:0.35;margin-left:2px}
.stat-card .sc-sub{font-size:clamp(9px,1.3vh,12px);font-weight:500;opacity:0.40;display:flex;align-items:center;gap:clamp(4px,0.5vw,8px)}
.stat-card .sc-bar-wrap{width:100%;height:clamp(3px,0.4vh,5px);background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden}
.stat-card .sc-bar{height:100%;border-radius:3px;background:linear-gradient(90deg, <?php echo $teamColorHex ?>, <?php echo $teamColorHex2 ?>);box-shadow:0 0 10px <?php echo $teamColorHex ?>55;width:0%;transition:width 1.2s cubic-bezier(0.23, 1, 0.32, 1)}

/* Highlight border glow */
.stat-card.featured{border-color:<?php echo $teamColorHex ?>55;background:rgba(255,255,255,0.05);box-shadow:inset 0 0 30px <?php echo $teamColorHex ?>0a}
.stat-card.featured::after{content:'';position:absolute;inset:-2px;border-radius:20px;border:2px solid transparent;background:linear-gradient(135deg, <?php echo $teamColorHex ?>88, transparent 50%, <?php echo $teamColorHex ?>44) border-box;-webkit-mask:linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);mask:linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);-webkit-mask-composite:destination-out;mask-composite:exclude;pointer-events:none;animation:borderGlow 3s ease-in-out infinite}
@keyframes borderGlow{0%,100%{opacity:0.3}50%{opacity:1}}

/* Style tags */
.style-tag{display:inline-block;padding:clamp(2px,0.3vh,4px) clamp(8px,1vw,12px);border-radius:6px;font-size:clamp(9px,1.3vh,12px);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04)}
.style-tag.bat{color:#4ade80;border-color:rgba(74,222,128,0.25);background:rgba(74,222,128,0.08)}
.style-tag.bowl{color:#60a5fa;border-color:rgba(96,165,250,0.25);background:rgba(96,165,250,0.08)}

/* ── Info bar at bottom ── */
#info-bar{display:flex;align-items:center;justify-content:space-between;gap:clamp(12px,1.5vw,24px);padding-top:clamp(10px,1.5vh,18px);border-top:1px solid rgba(255,255,255,0.06)}
.info-chip{display:flex;align-items:center;gap:clamp(6px,0.8vw,10px);font-size:clamp(9px,1.4vh,13px);font-weight:600;opacity:0.50}
.info-chip .ic-dot{width:clamp(6px,0.8vh,8px);height:clamp(6px,0.8vh,8px);border-radius:50%;background:<?php echo $teamColorHex ?>;box-shadow:0 0 8px <?php echo $teamColorHex ?>88}

/* ── 3D Depth layers ── */
#depth-layer-1{position:absolute;inset:-20px;border-radius:34px;border:1px solid rgba(255,255,255,0.04);transform:translateZ(-10px);pointer-events:none;z-index:0}
#depth-layer-2{position:absolute;inset:-40px;border-radius:40px;border:1px solid rgba(255,255,255,0.02);transform:translateZ(-20px);pointer-events:none;z-index:0}

/* ── Particle ring ── */
#particle-ring{position:absolute;inset:-60px;border-radius:50px;pointer-events:none;z-index:0;opacity:0.3}
#particle-ring::before{content:'';position:absolute;inset:0;border-radius:inherit;border:2px solid transparent;border-top-color:<?php echo $teamColorHex ?>44;border-right-color:<?php echo $teamColorHex ?>22;animation:ringSpin 20s linear infinite}
#particle-ring::after{content:'';position:absolute;inset:15px;border-radius:inherit;border:1px solid transparent;border-bottom-color:<?php echo $teamColorHex ?>33;border-left-color:<?php echo $teamColorHex ?>18;animation:ringSpin 15s linear infinite reverse}
@keyframes ringSpin{to{transform:rotate(360deg)}}

/* ── Bottom corner glow ── */
#corner-glow{position:fixed;bottom:0;right:0;width:clamp(300px,35vw,600px);height:clamp(200px,25vh,400px);background:radial-gradient(ellipse at 100% 100%, <?php echo $teamColorHex ?>22 0%, transparent 70%);pointer-events:none;z-index:0;animation:cornerPulse 8s ease-in-out infinite}
@keyframes cornerPulse{0%,100%{opacity:0.4}50%{opacity:0.8}}

/* ── RESPONSIVE ── */
/* Aspect ratio lock for 16:9 */
@media (min-aspect-ratio: 16/9) {#main-card{width:auto;height:min(90vh, 960px);aspect-ratio:16/9}}
@media (max-aspect-ratio: 16/9) {#main-card{width:min(92vw, 1700px);height:auto;aspect-ratio:16/9}}

/* Smaller screens */
@media(max-width:1300px){#stats-grid{grid-template-columns:repeat(2, 1fr)}#left-panel{flex:0 0 38%}#right-panel{flex:0 0 62%}}
@media(max-width:900px){#left-panel{flex:0 0 42%}#right-panel{flex:0 0 58%;padding:clamp(14px,2vh,24px) clamp(16px,2vw,24px)}#stats-grid{grid-template-columns:repeat(2, 1fr);gap:clamp(6px,1vh,12px)}.stat-card{padding:clamp(8px,1.2vh,14px);border-radius:14px}.stat-card .sc-value{font-size:clamp(22px,4vh,44px)}.float-stat{padding:clamp(5px,0.8vh,8px) clamp(8px,1vw,12px);border-radius:10px}}
@media(max-width:600px){#main-card{border-radius:18px}#stats-grid{grid-template-columns:1fr 1fr;gap:6px}.stat-card{border-radius:10px;padding:8px}.stat-card .sc-value{font-size:clamp(18px,3vh,32px)}.stat-card .sc-label{font-size:8px}#left-panel{flex:0 0 45%}#right-panel{flex:0 0 55%;padding:10px 12px}#name-plate h1{font-size:clamp(18px,3vh,28px)}#name-plate .role-tag{font-size:clamp(10px,1.5vh,14px)}.float-stat{display:none}#team-badge{width:clamp(36px,7vh,50px);height:clamp(36px,7vh,50px);top:clamp(8px,1.5vh,16px);right:clamp(8px,1.5vh,16px)}#card-header .head-left .stat-title{font-size:clamp(14px,2vh,20px)}}

/* 4K+ */
@media(min-width:2560px){#stats-grid{grid-template-columns:repeat(3, 1fr)}.stat-card .sc-value{font-size:clamp(48px,4vh,80px)}#name-plate h1{font-size:clamp(40px,3vh,64px)}}
</style>
</head>
<body>

<!-- Corner ambient glow -->
<div id="corner-glow"></div>

<!-- Main stage -->
<div id="stage">
    <!-- 3D depth layer ring -->
    <div id="particle-ring"></div>
    <div id="depth-layer-1"></div>
    <div id="depth-layer-2"></div>

    <!-- Main card -->
    <div id="main-card">

        <!-- ── LEFT PANEL ── -->
        <div id="left-panel">

            <!-- Team Badge -->
            <div id="team-badge">
                <?php if ($teamLogo): ?>
                    <img src="<?php echo htmlspecialchars($teamLogo) ?>" alt="<?php echo $teamName ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="t-ini" style="display:none"><?php echo strtoupper(substr($teamName,0,2)) ?></span>
                <?php else: ?>
                    <span class="t-ini"><?php echo strtoupper(substr($teamName,0,2)) ?></span>
                <?php endif; ?>
            </div>

            <!-- Floating stat chip 1 -->
            <div class="float-stat fs-1">
                <span class="fs-val"><?php echo $batMatches ?></span>
                <span class="fs-lbl">Matches</span>
            </div>

            <!-- Floating stat chip 2 -->
            <?php if ($bowlWickets > 0): ?>
            <div class="float-stat fs-2">
                <span class="fs-val"><?php echo $bowlWickets ?></span>
                <span class="fs-lbl">Wickets</span>
            </div>
            <?php endif; ?>

            <!-- Floating stat chip 3 -->
            <?php if ($batHS > 0): ?>
            <div class="float-stat fs-3">
                <span class="fs-val"><?php echo $batHS ?></span>
                <span class="fs-lbl">High Score</span>
            </div>
            <?php endif; ?>

            <!-- Player Image -->
            <div id="player-img-wrap">
                <?php if ($photoPath): ?>
                    <img src="<?php echo htmlspecialchars($photoPath) ?>" alt="<?php echo $playerName ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="p-placeholder" style="display:none"><?php echo strtoupper(substr($player['name'],0,1)) ?></span>
                <?php else: ?>
                    <span class="p-placeholder"><?php echo strtoupper(substr($player['name'],0,1)) ?></span>
                <?php endif; ?>
            </div>

            <!-- Name Plate -->
            <div id="name-plate">
                <h1><?php echo $playerName ?></h1>
                <span class="role-tag"><?php echo $role ?></span>
                <span class="team-label"><?php echo $teamName ?></span>
            </div>
        </div>

        <!-- ── RIGHT PANEL ── -->
        <div id="right-panel">
            <div id="right-content">

                <!-- Header -->
                <div id="card-header">
                    <div class="head-left">
                        <div class="stat-title">Match Statistics</div>
                        <div class="stat-subtitle">
                            <?php echo htmlspecialchars($m['match_title'] ?? 'Match Details') ?> &middot;
                            <?php echo htmlspecialchars($m['a_short'] ?? '') ?> vs <?php echo htmlspecialchars($m['b_short'] ?? '') ?>
                        </div>
                    </div>
                    <div class="head-accents">
                        <div class="accent-dot"></div>
                        <div class="accent-dot"></div>
                        <div class="accent-dot"></div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div id="stats-grid">

                    <?php if ($batInnings > 0): ?>
                    <!-- Batting Stats -->
                    <!-- Runs -->
                    <div class="stat-card featured" data-bar="100">
                        <div class="sc-header">
                            <span class="sc-label">Runs</span>
                            <div class="sc-icon">&#127951;</div>
                        </div>
                        <div class="sc-value"><?php echo $batRuns ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="100"></div></div>
                        <div class="sc-sub"><span>Innings: <?php echo $batInnings ?></span></div>
                    </div>

                    <!-- Balls Faced -->
                    <div class="stat-card" data-bar="85">
                        <div class="sc-header">
                            <span class="sc-label">Balls Faced</span>
                            <div class="sc-icon">&#9917;</div>
                        </div>
                        <div class="sc-value"><?php echo $batBalls ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="85"></div></div>
                    </div>

                    <!-- Average -->
                    <div class="stat-card featured" data-bar="70">
                        <div class="sc-header">
                            <span class="sc-label">Average</span>
                            <div class="sc-icon">&#9733;</div>
                        </div>
                        <div class="sc-value"><?php echo number_format($batAvg, 2) ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $batAvg * 2) ?>"></div></div>
                    </div>

                    <!-- Strike Rate -->
                    <div class="stat-card" data-bar="<?php echo min(100, $batSR * 0.8) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Strike Rate</span>
                            <div class="sc-icon">&#9889;</div>
                        </div>
                        <div class="sc-value"><?php echo number_format($batSR, 2) ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $batSR * 0.8) ?>"></div></div>
                    </div>

                    <!-- 4s -->
                    <div class="stat-card" data-bar="<?php echo min(100, $batFours * 10) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Fours</span>
                            <div class="sc-icon">4</div>
                        </div>
                        <div class="sc-value"><?php echo $batFours ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $batFours * 10) ?>"></div></div>
                    </div>

                    <!-- 6s -->
                    <div class="stat-card" data-bar="<?php echo min(100, $batSixes * 15) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Sixes</span>
                            <div class="sc-icon">6</div>
                        </div>
                        <div class="sc-value"><?php echo $batSixes ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $batSixes * 15) ?>"></div></div>
                    </div>
                    <?php else: ?>
                    <div class="stat-card" style="opacity:0.5;grid-column:span 2">
                        <div class="sc-label" style="opacity:0.4">BATTING</div>
                        <div class="sc-value" style="font-size:clamp(16px,2.5vh,24px);font-family:'Hanken Grotesk',sans-serif;font-weight:500;
                        opacity:0.3"><?php echo $role === 'BOWLER' ? 'Yet to bat' : 'No batting data' ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($bowlWickets > 0): ?>
                    <!-- Bowling Stats -->
                    <div class="stat-card" data-bar="<?php echo min(100, $bowlWickets * 20) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Wickets</span>
                            <div class="sc-icon" style="background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.25);color:#ef4444">W</div>
                        </div>
                        <div class="sc-value"><?php echo $bowlWickets ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);box-shadow:0 0 10px rgba(239,68,68,0.5)" data-target="<?php echo min(100, $bowlWickets * 20) ?>"></div></div>
                    </div>

                    <div class="stat-card" data-bar="<?php echo min(100, $bowlEcon > 0 ? (100 - $bowlEcon * 8) : 50) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Economy</span>
                            <div class="sc-icon">&#9202;</div>
                        </div>
                        <div class="sc-value"><?php echo number_format($bowlEcon, 2) ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $bowlEcon > 0 ? (100 - $bowlEcon * 8) : 50) ?>"></div></div>
                    </div>

                    <div class="stat-card" data-bar="<?php echo min(100, $bowlOvers * 10) ?>">
                        <div class="sc-header">
                            <span class="sc-label">Overs</span>
                            <div class="sc-icon">O</div>
                        </div>
                        <div class="sc-value"><?php echo number_format($bowlOvers, 1) ?></div>
                        <div class="sc-bar-wrap"><div class="sc-bar" data-target="<?php echo min(100, $bowlOvers * 10) ?>"></div></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Info bar -->
                <div id="info-bar">
                    <?php if ($battingStyle): ?>
                    <div class="info-chip">
                        <div class="ic-dot"></div>
                        <span class="style-tag bat"><?php echo htmlspecialchars($battingStyle) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($bowlingStyle): ?>
                    <div class="info-chip">
                        <div class="ic-dot" style="background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,0.8)"></div>
                        <span class="style-tag bowl"><?php echo htmlspecialchars($bowlingStyle) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($player['age']): ?>
                    <div class="info-chip">
                        <span>Age: <?php echo (int)$player['age'] ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($player['school_entry_year']): ?>
                    <div class="info-chip">
                        <span>School: <?php echo htmlspecialchars($player['school_entry_year']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
<?php if (!$hasStats): ?>
// No stats — show clean profile only
document.querySelectorAll('.stat-card').forEach(function(c){c.style.opacity='0.5'});
<?php endif; ?>

// ── GSAP Entrance Animation ──
if(typeof gsap !== 'undefined'){
    var tl = gsap.timeline({defaults:{ease:'power3.out'}});

    // Card entrance
    tl.fromTo('#main-card',
        {opacity:0, rotationX:18, rotationY:16, y:60, scale:0.85, transformOrigin:'center center'},
        {opacity:1, rotationX:3, rotationY:-2, y:0, scale:1, duration:1.1, ease:'back.out(1.4)'}
    );

    // Left panel elements
    tl.fromTo('#left-panel',
        {opacity:0, x:-60},
        {opacity:1, x:0, duration:0.6},
        '-=0.6'
    );

    tl.fromTo('#player-img-wrap',
        {opacity:0, scale:0.8, y:30},
        {opacity:1, scale:1, y:0, duration:0.7},
        '-=0.2'
    );

    tl.fromTo('#name-plate',
        {opacity:0, y:20},
        {opacity:1, y:0, duration:0.5},
        '-=0.3'
    );

    // Floating stats
    tl.fromTo('.float-stat',
        {opacity:0, scale:0.6, rotationZ:-5},
        {opacity:1, scale:1, rotationZ:0, duration:0.5, stagger:0.12},
        '-=0.4'
    );

    // Right panel
    tl.fromTo('#right-panel',
        {opacity:0, x:40},
        {opacity:1, x:0, duration:0.5},
        '-=0.5'
    );

    tl.fromTo('#card-header',
        {opacity:0, y:-15},
        {opacity:1, y:0, duration:0.4},
        '-=0.2'
    );

    // Stat cards stagger with 3D rotation
    tl.fromTo('.stat-card',
        {opacity:0, rotationX:-15, y:20},
        {opacity:1, rotationX:0, y:0, duration:0.5, stagger:0.07},
        '-=0.2'
    );

    // Info bar
    tl.fromTo('#info-bar',
        {opacity:0, y:10},
        {opacity:1, y:0, duration:0.4},
        '-=0.1'
    );

    // Animate stat bars after entrance
    tl.to('.sc-bar', {
        width: function(i, el){ return el.getAttribute('data-target') + '%' },
        duration: 1.4,
        stagger: 0.08,
        ease: 'power3.out'
    }, '-=0.3');

    // Subtle floating animation for main card
    gsap.to('#main-card', {
        y: -8,
        rotationX: 2.5,
        rotationY: -2.5,
        duration: 4,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
        delay: 1.5
    });

    // Corner glow pulse
    gsap.to('#corner-glow', {
        opacity: 0.7,
        duration: 4,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut'
    });

    // ── 3D Mouse Parallax ──
    var card = document.getElementById('main-card');
    var stage = document.getElementById('stage');

    stage.addEventListener('mousemove', function(e){
        var rect = stage.getBoundingClientRect();
        var x = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
        var y = ((e.clientY - rect.top) / rect.height - 0.5) * 2;

        gsap.to(card, {
            rotationY: x * 6,
            rotationX: y * -4 + 3,
            x: x * 15,
            y: y * -10,
            duration: 0.8,
            ease: 'power2.out'
        });

        // Move depth layers for parallax
        gsap.to('#depth-layer-1', {x: x * 25, y: y * -15, duration: 0.8, ease:'power2.out'});
        gsap.to('#depth-layer-2', {x: x * 40, y: y * -25, duration: 0.8, ease:'power2.out'});
    });

    stage.addEventListener('mouseleave', function(){
        gsap.to(card, {
            rotationY: -2,
            rotationX: 3,
            x: 0,
            y: 0,
            duration: 1,
            ease: 'elastic.out(1, 0.6)'
        });
        gsap.to('#depth-layer-1', {x: 0, y: 0, duration: 0.8, ease:'power2.out'});
        gsap.to('#depth-layer-2', {x: 0, y: 0, duration: 0.8, ease:'power2.out'});
    });
}
</script>
</body>
</html>
