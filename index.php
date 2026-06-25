<?php
require_once __DIR__.'/config.php';
$error=$success='';
$redirectError=$_GET['error']??'';$redirectMsg=$_GET['msg']??'';
if($redirectError==='unauthorized')$error='Please sign in to access that page.';
if($redirectError==='session_expired')$error='Your session has expired. Please sign in again.';
if($redirectMsg==='logged_out')$success='You have been logged out successfully.';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['action'])&&$_POST['action']==='login'){
    $username=trim($_POST['username']??'');$password=$_POST['password']??'';
    if($username===''||$password===''){$error='Please enter both username and password.';}
    else{try{$db=getDB();$stmt=$db->prepare('SELECT id,username,password_hash,role,display_name,company_id FROM users WHERE username=? AND is_active=1');$stmt->execute([$username]);$user=$stmt->fetch();if($user&&password_verify($password,$user['password_hash'])){session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['username']=$user['username'];$_SESSION['role']=$user['role'];$_SESSION['display_name']=$user['display_name']??$user['username'];$_SESSION['_regenerated_at']=time();$_SESSION['login_time']=time();$_SESSION['company_id']=$user['company_id']??null;if($user['role']==='super_admin')$redir='super_admin.php';elseif($user['role']==='company_admin')$redir='client_dashboard.php';elseif($user['role']==='scorer')$redir='scorer_panel.php';else $redir='index.php?error=unauthorized';header('Location: '.$redir);exit;}else{usleep(random_int(50000,150000));$error='Invalid username or password.';}}catch(PDOException $e){$error='Database connection failed.';}}
}
$loggedIn=!empty($_SESSION['user_id']);$userRole=$_SESSION['role']??'';$userName=$_SESSION['display_name']??$_SESSION['username']??'';
$liveCount=0;try{$db2=getDB();$liveCount=(int)$db2->query("SELECT COUNT(*) FROM matches WHERE status='live'")->fetchColumn();}catch(Exception $e){}
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CricketLive — Broadcast Scoreboard System</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root,[data-theme="dark"]{--bg:#050914;--bg2:#0b1020;--card:rgba(15,23,42,0.85);--border:rgba(255,255,255,0.06);--border2:rgba(255,255,255,0.10);--text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;--accent:#f97316;--accent2:#fb923c;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;--sb-track:#0b1020;--sb-thumb:#1e293b}
[data-theme="light"]{--bg:#eef1f5;--bg2:#e2e6ec;--card:#fff;--border:#dde1e7;--border2:#c8cdd5;--text:#0f172a;--text-dim:#475569;--text-mute:#94a3b8;--accent:#ea580c;--accent2:#f97316;--red:#dc2626;--green:#16a34a;--blue:#2563eb;--purple:#7c3aed;--sb-track:#eef1f5;--sb-thumb:#b0b8c4}
html,body{width:100%;font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;transition:background 0.3s,color 0.3s}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--sb-track)}::-webkit-scrollbar-thumb{background:var(--sb-thumb);border-radius:3px}

#app{min-height:100vh;display:flex;flex-direction:column}
.hero-bg{background:radial-gradient(ellipse 80% 60% at 50% 20%,rgba(249,115,22,0.06) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 80% 80%,rgba(59,130,246,0.04) 0%,transparent 60%),var(--bg)}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@keyframes pulse-ring{0%{box-shadow:0 0 0 0 rgba(34,197,94,0.5)}70%{box-shadow:0 0 0 12px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
@keyframes modalIn{from{opacity:0;transform:scale(0.94)}to{opacity:1;transform:scale(1)}}
.fade-up{animation:fadeUp 0.7s ease-out forwards;opacity:0}
.fade-up:nth-child(1){animation-delay:0.1s}.fade-up:nth-child(2){animation-delay:0.25s}.fade-up:nth-child(3){animation-delay:0.4s}.fade-up:nth-child(4){animation-delay:0.55s}
.live-pulse{animation:pulse-ring 2s ease-in-out infinite}
.float{animation:float 4s ease-in-out infinite}

/* Nav */
nav{position:sticky;top:0;z-index:40;background:var(--bg2);border-bottom:1px solid var(--border);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
nav .nav-inner{max-width:1200px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:56px}
nav a{text-decoration:none}.brand{display:flex;align-items:center;gap:8px;color:var(--text)}.brand .bi{font-size:24px}.brand .bt{font-size:16px;font-weight:800}.brand .bt span{color:var(--accent)}.nav-right{display:flex;align-items:center;gap:10px}.nav-user{font-size:12px;color:var(--text-dim)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;font-family:inherit;text-decoration:none;white-space:nowrap}
.btn-p{background:var(--accent);color:#fff}.btn-p:hover{background:var(--accent2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(249,115,22,0.25)}
.btn-full{width:100%;justify-content:center}
.btn-o{background:var(--card);color:var(--text);border:1px solid var(--border)}.btn-o:hover{border-color:var(--border2)}
.btn-th{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text-dim);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}

/* Hero */
.hero{max-width:1200px;margin:0 auto;padding:50px 20px 60px;text-align:center}
.hero .badge{display:inline-flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--border);border-radius:20px;padding:6px 16px;margin-bottom:20px;font-size:13px;color:var(--text-dim)}
.hero .badge .dot{width:8px;height:8px;border-radius:50%;background:var(--green)}.hero h1{font-size:clamp(32px,6vw,56px);font-weight:900;letter-spacing:-0.03em;line-height:1.1;margin:0 0 12px}.hero h1 span{color:var(--accent)}.hero p{font-size:clamp(14px,2vw,17px);color:var(--text-dim);max-width:540px;margin:0 auto 32px;line-height:1.6}.hero .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}

/* Cards */
.features{max-width:1200px;margin:0 auto;padding:0 20px 50px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.fcard{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:28px;transition:all 0.3s;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}.fcard:hover{border-color:rgba(249,115,22,0.25);box-shadow:0 8px 32px rgba(0,0,0,0.12)}.fcard .fic{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:16px}.fcard h3{font-size:17px;font-weight:700;margin:0 0 8px}.fcard p{font-size:13px;color:var(--text-dim);margin:0;line-height:1.6}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px}.modal-overlay.show{display:flex}.modal-box{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:32px;width:100%;max-width:380px;animation:modalIn 0.25s ease-out;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}.modal-box h2{font-size:20px;font-weight:800;margin:8px 0 4px;text-align:center}

input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;color:var(--text);font-family:inherit;outline:none;transition:border-color 0.2s}input:focus{border-color:var(--accent)}
.alert{background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.30);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--red)}
.alert-ok{background:rgba(34,197,94,0.10);border:1px solid rgba(34,197,94,0.30);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--green)}
.toast-banner{position:fixed;top:0;left:0;right:0;z-index:50;text-align:center;padding:10px;font-size:13px;animation:fadeUp 0.3s ease-out}

/* ── Responsive ── */
@media(max-width:900px){.features{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){
    .hero h1{font-size:clamp(28px,8vw,42px)}
    .hero p{font-size:14px;padding:0 8px}
    .hero .cta{flex-direction:column;align-items:center}
    .hero .cta .btn,.hero .cta a{width:100%;max-width:320px;justify-content:center}
    .features{gap:14px}
    .fcard{padding:22px}
    .nav-right .nav-user{display:none}
}
@media(max-width:600px){
    nav .nav-inner{padding:0 12px}
    .brand .bi{font-size:20px}.brand .bt{font-size:14px}
    .hero{padding:28px 10px 32px}
    .hero .badge{font-size:11px;padding:5px 12px}
    .features{padding:0 12px 30px;grid-template-columns:1fr}
    .fcard{padding:20px}
    .btn{font-size:12px;padding:8px 14px}
}
@media(max-width:400px){
    .brand .bi{font-size:18px}.brand .bt{font-size:13px;gap:4px}
    .hero h1{font-size:clamp(24px,7vw,32px)}
    .hero p{font-size:13px}
    .modal-box{padding:24px 20px}
}
</style>
</head>
<body>
<div id="app" class="hero-bg">
<?php if($success):?><div id="banner" class="toast-banner" style="background:rgba(22,101,52,0.9);color:#bbf7d0">&#10003; <?=htmlspecialchars($success)?></div><?php endif;?>

<nav><div class="nav-inner">
    <a href="index.php" class="brand"><span class="bi">&#127951;</span><span class="bt">Cricket<span>Live</span></span></a>
    <div class="nav-right">
        <button onclick="toggleTheme()" class="btn-th" title="Toggle theme">&#127763;</button>
        <?php if($loggedIn):?>
            <span class="nav-user"><?=htmlspecialchars($userName)?></span>
            <?php if($userRole==='super_admin'):?><a href="super_admin.php" class="btn btn-o" style="font-size:11px;padding:6px 10px">Admin</a><?php endif;?>
            <?php if($userRole==='company_admin'):?><a href="client_dashboard.php" class="btn btn-o" style="font-size:11px;padding:6px 10px">Dashboard</a><?php endif;?>
            <a href="scorer_panel.php" class="btn btn-p" style="font-size:11px;padding:6px 10px">&#127951; Score</a>
            <a href="javascript:void(0)" style="font-size:11px;color:var(--text-mute);text-decoration:none" onclick="showLogoutModal()">Logout</a>
        <?php else:?>
            <button onclick="openModal()" class="btn btn-p">Sign In</button>
        <?php endif;?>
    </div>
</div></nav>

<section class="hero">
    <div class="badge fade-up"><span class="dot live-pulse"></span><?=$liveCount>0?"<strong style='color:var(--green)'>$liveCount match".($liveCount>1?'es':'')." live</strong> right now":'System online — ready to score'?></div>
    <h1 class="fade-up">Live Cricket<br><span>Scoreboard</span></h1>
    <p class="fade-up">Professional broadcast overlay system for OBS Studio &amp; vMix. Real-time scoring engine with glassmorphic overlays, player photos, and GSAP animations.</p>
    <div class="cta fade-up">
        <?php if(!$loggedIn):?><button onclick="openModal()" class="btn btn-p" style="font-size:15px;padding:13px 30px">&#127951; Start Scoring</button>
        <?php else:?><a href="scorer_panel.php" class="btn btn-p" style="font-size:15px;padding:13px 30px">&#127951; Scorer Panel</a><?php endif;?>
        <a href="contact.php" class="btn btn-o" style="font-size:15px;padding:13px 30px"><svg xmlns="http://w3.org" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
</svg> Contact Us</a>
    </div>
</section>

<div class="features">
    <?php
    $feats=[['&#127951;','rgba(249,115,22,0.12)','Live Scoring Engine','Ball-by-ball input with touch-friendly 48px buttons. Runs, extras, wickets — all at your fingertips.'],
    ['&#128250;','rgba(59,130,246,0.12)','Broadcast Overlay','1920×1080 transparent glassmorphic scorebug. Player photos, real-time SSE updates, GSAP animations.'],
    ['&#9881;','rgba(168,85,247,0.12)','Team & Player Management','Create teams with logos, add players with photos. Full CRUD with secure image uploads.'],
    ['&#9889;','rgba(34,197,94,0.12)','Real-Time Sync','Server-Sent Events push live data to all connected overlays. Jittered backoff, auto-reconnect.'],
    ['&#127760;','rgba(234,179,8,0.12)','Dark & Light Theme','Toggle between dark and light mode. Preference saved in your browser. Works on all pages.'],
    ['&#127922;','rgba(236,72,153,0.12)','ICC Rules Compliant','International cricket rules: over math, strike rotation, extras, maiden overs, economy rates.']];
    foreach($feats as $i=>$f):?>
    <div class="fcard fade-up"><div class="fic" style="background:<?=$f[1]?>"><?=$f[0]?></div><h3><?=$f[2]?></h3><p><?=$f[3]?></p></div>
    <?php endforeach;?>
</div>

<!-- FAQ Section -->
<section style="max-width:900px;margin:0 auto;padding:0 20px 60px" class="fade-up">
    <h2 style="text-align:center;font-size:clamp(22px,4vw,32px);font-weight:800;margin-bottom:8px">Frequently Asked Questions</h2>
    <p style="text-align:center;font-size:14px;color:var(--text-dim);margin-bottom:32px">Everything you need to know about CricketLive</p>
    <div style="display:flex;flex-direction:column;gap:10px">
    <?php
    $faqs=[
        ['What is CricketLive?','CricketLive is a professional broadcast scoreboard system that provides real-time cricket scoring with transparent overlays compatible with OBS Studio and vMix. It is designed for live cricket production.'],
        ['How does real-time scoring work?','Our scoring panel features touch-friendly 48px buttons for quick ball-by-ball input. Data is pushed to all connected overlays via Server-Sent Events (SSE) with automatic reconnection.'],
        ['Can I use this for free?','CricketLive operates on a company license model. Each company requires a valid license key. Contact us for pricing and trial options.'],
        ['What equipment do I need?','You need a computer running any modern browser for scoring, and OBS Studio or vMix for broadcasting. The overlay is a transparent HTML page that you add as a browser source. You can also control everything using your mobile phone or tablet. No special hardware is required.'],
        ['Is it ICC rules compliant?','Yes! Our scoring engine follows ICC rules including over math, strike rotation, extras handling, maiden overs, economy rate calculation, and more.'],
        ['How do I add players and teams?','Company admins can create teams with logos and add players with photos through the management dashboard. Full CRUD operations are supported.'],
    ];
    foreach($faqs as $i=>$faq):
    ?>
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:all 0.2s;cursor:pointer" onclick="this.classList.toggle('faq-open')">
        <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px">
            <span style="font-weight:700;font-size:14px;color:var(--text)"><?=$faq[0]?></span>
            <span style="font-size:18px;color:var(--accent);transition:transform 0.3s;flex-shrink:0">&#9660;</span>
        </div>
        <div style="max-height:0;overflow:hidden;transition:max-height 0.35s ease,padding 0.35s ease">
            <p style="padding:0 20px 16px;font-size:13px;color:var(--text-dim);line-height:1.7;margin:0"><?=$faq[1]?></p>
        </div>
    </div>
    <?php endforeach;?>
    </div>
</section>
<style>
.faq-open{background:var(--bg-card)!important;border-color:var(--accent)!important}
.faq-open span:last-child{transform:rotate(180deg)}
.faq-open div:last-child{max-height:200px!important}
</style>

<?php include __DIR__.'/footer.php';?>
</div>

<!-- Login Modal -->
<div id="loginModal" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div style="text-align:center;margin-bottom:24px"><span style="font-size:32px">&#127951;</span><h2>Welcome Back</h2><p style="font-size:12px;color:var(--text-dim)">Sign in to access the scoring panel</p></div>
        <?php if($error):?><div class="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
        <form method="POST" style="display:flex;flex-direction:column;gap:14px">
            <input type="hidden" name="action" value="login">
            <div><label style="display:block;font-size:11px;font-weight:600;color:var(--text-dim);margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em">Username</label><input type="text" name="username" required autocomplete="username" placeholder="Enter username"></div>
            <div><label style="display:block;font-size:11px;font-weight:600;color:var(--text-dim);margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em">Password</label><input type="password" name="password" required autocomplete="current-password" placeholder="Enter password"></div>
            <button type="submit" class="btn btn-p btn-full" style="margin-top:4px">Sign In</button>
        </form>
        <button onclick="closeModal()" style="width:100%;margin-top:12px;font-size:12px;color:var(--text-mute);background:none;border:none;cursor:pointer;padding:6px">Cancel</button>
    </div>
</div>

<script>
(function(){var b=document.getElementById('banner');if(b)setTimeout(function(){b.style.transition='opacity 0.4s';b.style.opacity='0';setTimeout(function(){b.remove()},400)},4000);})();
function openModal(){document.getElementById('loginModal').classList.add('show')}
function closeModal(){document.getElementById('loginModal').classList.remove('show')}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal();document.querySelectorAll('.dyn-modal').forEach(function(d){d.remove()})}});
<?php if($error||$redirectError):?>openModal();<?php endif;?>
(function(){var s=localStorage.getItem('cricket-theme')||'dark';document.documentElement.setAttribute('data-theme',s);})();
function toggleTheme(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('cricket-theme',n);}
function confirmLogout(){return confirm('Are you sure you want to logout?')}
function showLogoutModal(){
 var d=document.createElement('div');d.className='dyn-modal';
 d.innerHTML='<div style="background:var(--card);border:1px solid var(--border);border-radius:18px;width:100%;max-width:380px;text-align:center;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.50)"><div style="padding:36px 28px 24px"><div style="width:64px;height:64px;border-radius:50%;background:rgba(239,68,68,0.10);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px">&#128682;</div><h3 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:8px">Confirm Logout</h3><p style="font-size:13px;color:var(--text-dim);margin-bottom:24px;line-height:1.5">Are you sure you want to sign out?</p><div style="display:flex;gap:10px;justify-content:center"><button type="button" onclick="this.closest(\'.dyn-modal\').remove()" class="btn btn-o">Cancel</button><a href="api.php?action=logout" class="btn btn-p">&#128682; Logout</a></div></div></div>';
 d.style.cssText='position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px;animation:modalIn 0.25s ease-out';
 d.addEventListener('click',function(e){if(e.target===this)this.remove()});
 document.body.appendChild(d);
}
</script>
</body>
</html>
