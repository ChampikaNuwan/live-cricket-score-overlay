<?php
require_once __DIR__.'/config.php';

// Login handling
$error = '';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&isset($_POST['action'])&&$_POST['action']==='login'){
    $username=trim($_POST['username']??'');$password=$_POST['password']??'';
    if($username===''||$password===''){$error='Please enter both username and password.';}
    else{try{$db=getDB();$stmt=$db->prepare('SELECT id,username,password_hash,role,display_name FROM users WHERE username=? AND is_active=1');$stmt->execute([$username]);$user=$stmt->fetch();if($user&&password_verify($password,$user['password_hash'])){session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['username']=$user['username'];$_SESSION['role']=$user['role'];$_SESSION['display_name']=$user['display_name']??$user['username'];$_SESSION['_regenerated_at']=time();$_SESSION['login_time']=time();$_SESSION['company_id']=null;if($user['role']==='super_admin')$redir='super_admin.php';elseif($user['role']==='company_admin')$redir='client_dashboard.php';elseif($user['role']==='scorer')$redir='scorer_panel.php';else $redir='index.php?error=unauthorized';header('Location: '.$redir);exit;}else{usleep(random_int(50000,150000));$error='Invalid username or password.';}}catch(PDOException $e){$error='Database connection failed.';}}
}
$loggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$userRole = $_SESSION['role'] ?? '';
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($title)?> — CricketLive</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root,[data-theme="dark"]{--bg:#050914;--bg2:#0b1020;--card:rgba(15,23,42,0.85);--border:rgba(255,255,255,0.08);--border2:rgba(255,255,255,0.10);--text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;--accent:#f97316;--accent2:#fb923c;--red:#ef4444;--green:#22c55e;--blue:#3b82f6}
[data-theme="light"]{--bg:#eef1f5;--bg2:#e2e6ec;--card:#fff;--border:#dde1e7;--border2:#c8cdd5;--text:#0f172a;--text-dim:#475569;--text-mute:#94a3b8;--accent:#ea580c;--accent2:#f97316;--red:#dc2626;--green:#16a34a;--blue:#2563eb}
body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;transition:background 0.3s,color 0.3s}
nav{background:var(--bg2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:10;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
nav .nav-inner{max-width:1200px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;height:56px}
nav a{text-decoration:none}.brand{display:flex;align-items:center;gap:8px;color:var(--text)}.brand .bi{font-size:24px}.brand .bt{font-size:16px;font-weight:800}.brand .bt span{color:var(--accent)}.nav-right{display:flex;align-items:center;gap:10px}
.nav-r{display:flex;align-items:center;gap:10px}
main{flex:1;max-width:760px;margin:0 auto;padding:32px 20px;width:100%}
h1{font-size:22px;font-weight:800;margin-bottom:8px;color:var(--accent)}
main h3{font-size:14px;font-weight:700;color:var(--text);margin:20px 0 8px}
main h3:first-of-type{margin-top:0}
main p{font-size:13px;color:var(--text-dim);line-height:1.8;margin-bottom:10px}
main ul{margin:8px 0 16px 20px;font-size:13px;color:var(--text-dim);line-height:1.8}
main ul li{margin-bottom:4px}
main a{color:var(--accent);text-decoration:none;font-weight:600}
main a:hover{text-decoration:underline}
.contact-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;margin:12px 0}
.contact-card p{margin-bottom:6px}.contact-card strong{color:var(--text)}.contact-card small{color:var(--text-mute);font-size:11px}

.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;font-family:inherit;border:none;white-space:nowrap}
.btn-o{background:var(--card);color:var(--text);border:1px solid var(--border)}.btn-o:hover{border-color:var(--accent)}
.btn-p{background:var(--accent);color:#fff}.btn-p:hover{background:var(--accent2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(249,115,22,0.25)}
.btn-full{width:100%;justify-content:center}
.btn-th{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--card);color:var(--text-dim);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}

footer{text-align:center;padding:24px 16px;font-size:10px;color:var(--text-mute);line-height:1.6;border-top:1px solid var(--border);background:var(--bg2)}
footer a{color:var(--text-dim);text-decoration:none;margin:0 6px;transition:color 0.2s}footer a:hover{color:var(--accent)}
footer strong{color:var(--text)}footer .heart{color:#ef4444}

.modal-overlay{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:24px;padding:32px;width:100%;max-width:380px;animation:modalIn 0.25s ease-out;backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}
@keyframes modalIn{from{opacity:0;transform:scale(0.94)}to{opacity:1;transform:scale(1)}}
.modal-box h2{font-size:20px;font-weight:800;margin:8px 0 4px;text-align:center}
input{width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:14px;color:var(--text);font-family:inherit;outline:none;transition:border-color 0.2s}input:focus{border-color:var(--accent)}
.alert{background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.30);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--red)}

@media(max-width:600px){nav .nav-inner{padding:0 12px}.brand .bi{font-size:20px}.brand .bt{font-size:14px}main{padding:24px 12px}}
</style>
</head>
<body>
<nav><div class="nav-inner">
    <a href="index.php" class="brand"><span class="bi">&#127951;</span><span class="bt">Cricket<span>Live</span></span></a>
    <div class="nav-right">
        <button onclick="toggleTheme()" class="btn-th" title="Toggle theme">&#127763;</button>
        <?php if($loggedIn):?>
            <span style="font-size:12px;color:var(--text-dim)"><?=htmlspecialchars($userName)?></span>
            <?php if($userRole==='super_admin'):?><a href="super_admin.php" class="btn btn-o" style="font-size:11px;padding:6px 10px">Admin</a><?php endif;?>
            <?php if($userRole==='company_admin'):?><a href="client_dashboard.php" class="btn btn-o" style="font-size:11px;padding:6px 10px">Dashboard</a><?php endif;?>
            <a href="scorer_panel.php" class="btn btn-p" style="font-size:11px;padding:6px 10px">&#127951; Score</a>
            <a href="api.php?action=logout" style="font-size:11px;color:var(--text-mute);text-decoration:none" onclick="return confirmLogout()">Logout</a>
        <?php else:?>
            <button onclick="openModal()" class="btn btn-p">Sign In</button>
        <?php endif;?>
    </div>
</div></nav>
<main>
    <h1><?=htmlspecialchars($title)?></h1>
    <?=$content?>
    <a href="index.php" class="btn btn-o" style="margin-top:16px">&larr; Back to Home</a>
</main>
<footer>
    <div style="margin-bottom:8px">
        <a href="privacy.php">Privacy Policy</a> |
        <a href="terms.php">Terms of Service</a> |
        <a href="refund.php">Refund Policy</a> |
        <a href="copyright.php">Copyright Notice</a> |
        <a href="about.php">About Us</a> |
        <a href="contact.php">Contact Us</a>
    </div>
    <div>&copy; <?=date('Y')?> <strong>SWARNA MEDIA NETWORK (PVT) Ltd.</strong></div>
    <div>Designed with <span class="heart">&#10084;</span> by <a href="https://wa.me/+94766237857" target="_blank" rel="noopener" style="color:var(--accent);font-weight:600">Champika Nuwan</a></div>
</footer>

<!-- Login Modal -->
<div id="loginModal" class="modal-overlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div style="text-align:center;margin-bottom:24px"><span style="font-size:32px">&#127951;</span><h2>Welcome Back</h2><p style="font-size:12px;color:var(--text-dim);margin-top:4px">Sign in to access the scoring panel</p></div>
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
(function(){var s=localStorage.getItem('cricket-theme')||'dark';document.documentElement.setAttribute('data-theme',s);})();
function toggleTheme(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('cricket-theme',n);}
function openModal(){document.getElementById('loginModal').classList.add('show')}
function closeModal(){document.getElementById('loginModal').classList.remove('show')}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal()});
<?php if($error):?>openModal();<?php endif;?>
function confirmLogout(){return confirm('Are you sure you want to logout?')}
</script>
</body>
</html>
