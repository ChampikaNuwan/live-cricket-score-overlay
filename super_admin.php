<?php
/**
 * ============================================================================
 * super_admin.php — Modern Animated Super Admin Dashboard
 * ============================================================================
 * Company & License management, staff CRUD, team & player CRUD, matches.
 * Responsive design, dark/light theme toggle, glass-morphism UI.
 * Requires 'super_admin' role.
 * ============================================================================
 */
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php?error=unauthorized'); exit;
}
$user = ['id'=>(int)$_SESSION['user_id'],'username'=>$_SESSION['username'],'role'=>$_SESSION['role']];
$db     = getDB();
$action = $_GET['section'] ?? 'dashboard';
$msg = $err = '';

// ========== POST HANDLERS ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sub = $_POST['sub_action'] ?? '';

    // ---- UPDATE ACCOUNT ----
    if ($sub === 'update_account') {
        $disp = trim($_POST['acc_display'] ?? '');
        $cpw = $_POST['acc_current_pw'] ?? '';
        $npw = $_POST['acc_new_pw'] ?? '';
        $uid = (int)$_SESSION['user_id'];
        if ($npw && strlen($npw) < 6) $err = 'New password must be 6+ characters.';
        elseif ($npw || $disp) {
            $chk = $db->prepare('SELECT password_hash FROM users WHERE id=?');
            $chk->execute([$uid]); $row = $chk->fetch();
            if ($npw && (!password_verify($cpw, $row['password_hash']))) $err = 'Current password is incorrect.';
            else {
                if ($npw) { $hash = password_hash($npw, PASSWORD_BCRYPT, ['cost'=>12]); $db->prepare('UPDATE users SET password_hash=?, display_name=? WHERE id=?')->execute([$hash, $disp?:$_SESSION['username'], $uid]); }
                else { $db->prepare('UPDATE users SET display_name=? WHERE id=?')->execute([$disp?:$_SESSION['username'], $uid]); }
                $_SESSION['display_name'] = $disp;
                $msg = 'Account updated.';
            }
        }
    }

    // ---- CREATE COMPANY ----
    if ($sub === 'create_company') {
        $name  = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['company_email'] ?? '');
        $phone = trim($_POST['company_phone'] ?? '');
        $adminUser = trim($_POST['company_admin_user'] ?? '');
        $adminPass = $_POST['company_admin_pass'] ?? '';
        if (strlen($name) < 2) $err = 'Company name required.';
        elseif (strlen($adminUser) < 3 || strlen($adminPass) < 6) $err = 'Admin username (3+) and password (6+) required.';
        else {
            // Check username not taken
            $chk = $db->prepare('SELECT COUNT(*) FROM users WHERE username=?');
            $chk->execute([$adminUser]);
            if ((int)$chk->fetchColumn() > 0) $err = 'Admin username already exists.';
            else {
                $db->beginTransaction();
                try {
                    // Create company
                    $stmt = $db->prepare('INSERT INTO companies (name, contact_email, contact_phone) VALUES (?,?,?)');
                    $stmt->execute([$name, $email ?: null, $phone ?: null]);
                    $cid = (int)$db->lastInsertId(defined('DB_ENGINE') && DB_ENGINE === 'pgsql' ? 'companies_id_seq' : null);
                    // Create admin user
                    $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost'=>12]);
                    $stmt = $db->prepare('INSERT INTO users (username,email,password_hash,role,display_name,company_id) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$adminUser, $email?:null, $hash, 'company_admin', $name.' Admin', $cid]);
                    // Generate license key (1 year)
                    $key = strtoupper(substr(bin2hex(random_bytes(2)),0,4).'-'.substr(bin2hex(random_bytes(2)),0,4).'-'.
                           substr(bin2hex(random_bytes(2)),0,4).'-'.substr(bin2hex(random_bytes(2)),0,4));
                    $isPg = defined('DB_ENGINE') && DB_ENGINE === 'pgsql';
                    $sqlLicense = $isPg
                        ? "INSERT INTO licenses (company_id, license_key, valid_from, valid_until, max_users) VALUES (?,?,CURRENT_DATE,CURRENT_DATE + INTERVAL '1 year',20)"
                        : "INSERT INTO licenses (company_id, license_key, valid_from, valid_until, max_users) VALUES (?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),20)";
                    $stmt = $db->prepare($sqlLicense);
                    $stmt->execute([$cid, $key]);
                    $db->commit();
                    $msg = "Company <strong>{$name}</strong> created! Login: <strong>{$adminUser}</strong> / <strong>{$adminPass}</strong> &middot; Key: <strong>{$key}</strong>";
                } catch (Exception $e) { $db->rollBack(); $err = 'Failed: '.$e->getMessage(); }
            }
        }
    }

    // ---- GENERATE LICENSE KEY ----
    if ($sub === 'create_license') {
        $cid = (int)($_POST['license_company_id'] ?? 0);
        $vfrom = $_POST['license_valid_from'] ?? '';
        $vuntil = $_POST['license_valid_until'] ?? '';
        $maxu = (int)($_POST['license_max_users'] ?? 10);
        if (!$cid || !$vfrom || !$vuntil) $err = 'Company and validity dates required.';
        elseif (strtotime($vuntil) < strtotime($vfrom)) $err = 'Valid until must be after valid from.';
        else {
            $key = strtoupper(substr(bin2hex(random_bytes(2)),0,4).'-'.substr(bin2hex(random_bytes(2)),0,4).'-'.
                   substr(bin2hex(random_bytes(2)),0,4).'-'.substr(bin2hex(random_bytes(2)),0,4));
            $stmt = $db->prepare('INSERT INTO licenses (company_id, license_key, valid_from, valid_until, max_users) VALUES (?,?,?,?,?)');
            $stmt->execute([$cid, $key, $vfrom, $vuntil, max(1, $maxu)]);
            $msg = "License <strong>{$key}</strong> generated.";
        }
    }

    // ---- DELETE LICENSE ----
    if ($sub === 'delete_license') {
        $lid = (int)($_POST['license_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM licenses WHERE id=?');
        $stmt->execute([$lid]);
        $msg = 'License revoked.';
    }

    // ---- DELETE COMPANY ----
    if ($sub === 'delete_company') {
        $cid = (int)($_POST['company_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM licenses WHERE company_id=?');
        $stmt->execute([$cid]);
        $stmt = $db->prepare('DELETE FROM users WHERE company_id=?');
        $stmt->execute([$cid]);
        $stmt = $db->prepare('DELETE FROM companies WHERE id=?');
        $stmt->execute([$cid]);
        $msg = 'Company and its licenses deleted.';
    }

    // ---- UPDATE COMPANY ----
    if ($sub === 'update_company') {
        $cid = (int)($_POST['company_id'] ?? 0);
        $name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['company_email'] ?? '');
        $phone = trim($_POST['company_phone'] ?? '');
        $active = (int)($_POST['company_active'] ?? 1);
        if (!$cid || empty($name)) $err = 'Company ID and name required.';
        else {
            $stmt = $db->prepare('UPDATE companies SET name=?, contact_email=?, contact_phone=?, is_active=? WHERE id=?');
            $stmt->execute([$name, $email?:null, $phone?:null, $active, $cid]);
            $msg = 'Company updated.';
        }
    }

    // ---- CREATE / UPDATE / DELETE TEAM ----
    if ($sub === 'create_team') {
        $name = trim($_POST['team_name'] ?? ''); $shrt = trim($_POST['team_short'] ?? '');
        if (empty($name)||empty($shrt)) $err = 'Name and short name required.';
        else {
            $logo = null;
            if (!empty($_FILES['team_logo']['tmp_name'])) {
                $ve = validateImageUpload($_FILES['team_logo']); if ($ve) $err = $ve;
                else { $ext = ($_FILES['team_logo']['type']==='image/png')?'.png':'.jpg';
                       $fn = randomFilename($ext); $dst = LOGOS_DIR.'/'.$fn;
                       if (move_uploaded_file($_FILES['team_logo']['tmp_name'],$dst)) $logo = 'assets/logos/'.$fn; }
            }
            if (!$err) { $stmt = $db->prepare('INSERT INTO teams (name,short_name,logo_path) VALUES (?,?,?)');
                         $stmt->execute([$name,$shrt,$logo]); $msg = "Team <strong>{$name}</strong> created."; }
        }
    }
    if ($sub === 'update_team') {
        $tid=(int)($_POST['team_id']??0); $name=trim($_POST['team_name']??''); $shrt=trim($_POST['team_short']??'');
        if (!$tid||empty($name)) $err = 'Team ID and name required.';
        else {
            $logo=$_POST['existing_logo']??null;
            if (!empty($_FILES['team_logo']['tmp_name'])) {
                $ve=validateImageUpload($_FILES['team_logo']); if($ve) $err=$ve;
                else { if($logo&&file_exists(APP_ROOT.'/'.$logo)) @unlink(APP_ROOT.'/'.$logo);
                       $ext=($_FILES['team_logo']['type']==='image/png')?'.png':'.jpg';
                       $fn=randomFilename($ext); move_uploaded_file($_FILES['team_logo']['tmp_name'],LOGOS_DIR.'/'.$fn);
                       $logo='assets/logos/'.$fn; }
            }
            if(!$err){ $stmt=$db->prepare('UPDATE teams SET name=?,short_name=?,logo_path=? WHERE id=?');
                       $stmt->execute([$name,$shrt,$logo,$tid]); $msg='Team updated.'; }
        }
    }
    if ($sub === 'delete_team') {
        $tid=(int)($_POST['team_id']??0);
        $stmt=$db->prepare('SELECT logo_path FROM teams WHERE id=?'); $stmt->execute([$tid]); $r=$stmt->fetch();
        if($r&&$r['logo_path']&&file_exists(APP_ROOT.'/'.$r['logo_path'])) @unlink(APP_ROOT.'/'.$r['logo_path']);
        $stmt=$db->prepare('DELETE FROM teams WHERE id=?'); $stmt->execute([$tid]); $msg='Team deleted.';
    }

    // ---- CREATE / UPDATE / DELETE PLAYER ----
    if ($sub === 'create_player') {
        $tid=(int)($_POST['player_team_id']??0); $name=trim($_POST['player_name']??'');
        $role=$_POST['player_role']??'batsman'; $bat=$_POST['player_batting']??''; $bowl=$_POST['player_bowling']??'';
        $age=$_POST['player_age']!==''?(int)$_POST['player_age']:null;
        $school=trim($_POST['player_school']??''); $achievements=trim($_POST['player_achievements']??'');
        if(!$tid||empty($name)) $err='Team and name required.';
        else {
            $photo=null;
            if(!empty($_FILES['player_photo']['tmp_name'])){
                $ve=validateImageUpload($_FILES['player_photo']); if($ve) $err=$ve;
                else { $ext=($_FILES['player_photo']['type']==='image/png')?'.png':'.jpg';
                       $fn=randomFilename($ext); $dst=PHOTOS_DIR.'/'.$fn;
                       if(move_uploaded_file($_FILES['player_photo']['tmp_name'],$dst)) $photo='assets/photos/'.$fn; }
            }
            if(!$err){ $stmt=$db->prepare('INSERT INTO players (team_id,name,role,batting_style,bowling_style,photo_path,age,school_entry_year,achievements) VALUES (?,?,?,?,?,?,?,?,?)');
                       $stmt->execute([$tid,$name,$role,$bat,$bowl,$photo,$age,$school,$achievements]); $msg="Player <strong>{$name}</strong> added."; }
        }
    }
    if ($sub === 'update_player') {
        $pid=(int)($_POST['player_id']??0); $tid=(int)($_POST['player_team_id']??0); $name=trim($_POST['player_name']??'');
        $role=$_POST['player_role']??'batsman'; $bat=$_POST['player_batting']??''; $bowl=$_POST['player_bowling']??'';
        $age=$_POST['player_age']!==''?(int)$_POST['player_age']:null;
        $school=trim($_POST['player_school']??''); $achievements=trim($_POST['player_achievements']??'');
        if(!$pid||!$tid||empty($name)) $err='All fields required.';
        else {
            $photo=$_POST['existing_photo']??null;
            if(!empty($_FILES['player_photo']['tmp_name'])){
                $ve=validateImageUpload($_FILES['player_photo']); if($ve) $err=$ve;
                else { if($photo&&file_exists(APP_ROOT.'/'.$photo)) @unlink(APP_ROOT.'/'.$photo);
                       $ext=($_FILES['player_photo']['type']==='image/png')?'.png':'.jpg';
                       $fn=randomFilename($ext); move_uploaded_file($_FILES['player_photo']['tmp_name'],PHOTOS_DIR.'/'.$fn);
                       $photo='assets/photos/'.$fn; }
            }
            if(!$err){ $stmt=$db->prepare('UPDATE players SET team_id=?,name=?,role=?,batting_style=?,bowling_style=?,photo_path=?,age=?,school_entry_year=?,achievements=? WHERE id=?');
                       $stmt->execute([$tid,$name,$role,$bat,$bowl,$photo,$age,$school,$achievements,$pid]); $msg='Player updated.'; }
        }
    }
    if ($sub === 'delete_player') {
        $pid=(int)($_POST['player_id']??0);
        $stmt=$db->prepare('SELECT photo_path FROM players WHERE id=?'); $stmt->execute([$pid]); $r=$stmt->fetch();
        if($r&&$r['photo_path']&&file_exists(APP_ROOT.'/'.$r['photo_path'])) @unlink(APP_ROOT.'/'.$r['photo_path']);
        $stmt=$db->prepare('UPDATE players SET is_active=0 WHERE id=?'); $stmt->execute([$pid]); $msg='Player deactivated.';
    }

    // ---- CREATE / UPDATE / DELETE MATCH ----
    if ($sub === 'create_match') {
        $ta=(int)($_POST['match_team_a']??0); $tb=(int)($_POST['match_team_b']??0);
        $ti=trim($_POST['match_title']??''); $loc=trim($_POST['match_location']??'');
        $tw=(int)($_POST['match_toss_won']??0); $td=$_POST['match_toss_dec']??'';
        $fmt=$_POST['match_format']??'t20i'; $maxOvers=['t20i'=>20,'odi'=>50,'test'=>450][$fmt]??20;
        if(!$ta||!$tb||$ta===$tb) $err='Two different teams required.';
        else {
            if(empty($ti)){ $stmt=$db->prepare('SELECT short_name FROM teams WHERE id IN (?,?)'); $stmt->execute([$ta,$tb]); $ns=$stmt->fetchAll(PDO::FETCH_COLUMN); $ti=($ns[0]??'A').' vs '.($ns[1]??'B'); }
            $matchLogo=null;
            if(!$err&&!empty($_FILES['match_logo']['tmp_name'])){
                $ve=validateImageUpload($_FILES['match_logo']); if($ve) $err=$ve;
                else { $ext=($_FILES['match_logo']['type']==='image/png')?'.png':'.jpg';
                       $fn=randomFilename($ext); $dst=LOGOS_DIR.'/'.$fn;
                       if(move_uploaded_file($_FILES['match_logo']['tmp_name'],$dst)) $matchLogo='assets/logos/'.$fn; }
            }
            if(!$err){ $batFirst=null; if($tw&&in_array($td,['bat','bowl'])){ $batFirst=($td==='bat')?$tw:($tw===$ta?$tb:$ta); }
                       $stmt=$db->prepare('INSERT INTO matches (team_a_id,team_b_id,match_title,location,match_format,total_overs,toss_won_by,toss_decision,batting_first,match_logo) VALUES (?,?,?,?,?,?,?,?,?,?)');
                       $stmt->execute([$ta,$tb,$ti,$loc?:null,$fmt,$maxOvers,$tw?:null,$td?:null,$batFirst,$matchLogo]); $msg="Match <strong>{$ti}</strong> created."; }
        }
    }
    if ($sub === 'update_match') {
        $mid=(int)($_POST['match_id']??0); $ti=trim($_POST['match_title']??''); $loc=trim($_POST['match_location']??'');
        if(!$mid) $err='Match ID required.';
        else {
            $matchLogo=$_POST['existing_match_logo']??null;
            if(!empty($_FILES['match_logo']['tmp_name'])){
                $ve=validateImageUpload($_FILES['match_logo']); if($ve) $err=$ve;
                else { if($matchLogo&&file_exists(APP_ROOT.'/'.$matchLogo)) @unlink(APP_ROOT.'/'.$matchLogo);
                       $ext=($_FILES['match_logo']['type']==='image/png')?'.png':'.jpg';
                       $fn=randomFilename($ext); move_uploaded_file($_FILES['match_logo']['tmp_name'],LOGOS_DIR.'/'.$fn);
                       $matchLogo='assets/logos/'.$fn; }
            }
            if(!$err){ $stmt=$db->prepare('UPDATE matches SET match_title=?,location=?,match_logo=? WHERE id=?');
                       $stmt->execute([$ti?:null,$loc?:null,$matchLogo,$mid]); $msg='Match updated.'; }
        }
    }
    if ($sub === 'update_match_status') {
        $mid=(int)($_POST['match_id']??0); $st=$_POST['match_status']??'';
        if($mid&&in_array($st,['upcoming','live','completed'])){
            $stmt=$db->prepare('UPDATE matches SET status=? WHERE id=?'); $stmt->execute([$st,$mid]);
            if($st==='completed'){ $stmt=$db->prepare('DELETE FROM live_state WHERE match_id=?'); $stmt->execute([$mid]); }
            $msg='Match status updated.';
        }
    }
    if ($sub === 'delete_match') {
        $mid=(int)($_POST['match_id']??0);
        $stmt=$db->prepare('SELECT match_logo FROM matches WHERE id=?'); $stmt->execute([$mid]); $r=$stmt->fetch();
        if($r&&$r['match_logo']&&file_exists(APP_ROOT.'/'.$r['match_logo'])) @unlink(APP_ROOT.'/'.$r['match_logo']);
        foreach(['live_state','ball_timeline','batsman_innings','bowler_spells','match_playing_xi'] as $tbl)
            $db->exec("DELETE FROM $tbl WHERE match_id=$mid");
        $stmt=$db->prepare('DELETE FROM matches WHERE id=?'); $stmt->execute([$mid]);
        $msg='Match and all related data deleted.';
    }
}

// ========== FETCH DATA ==========
$companies = $db->query('SELECT c.*, COUNT(l.id) AS license_count FROM companies c LEFT JOIN licenses l ON c.id=l.company_id AND l.is_active=1 GROUP BY c.id ORDER BY c.created_at DESC')->fetchAll();
$teams = $db->query('SELECT id,name,short_name,logo_path FROM teams ORDER BY name')->fetchAll();
$players = $db->query("SELECT p.id,p.name,p.role,p.batting_style,p.bowling_style,p.photo_path,p.age,p.school_entry_year,p.achievements,p.team_id,t.name AS team_name,t.short_name AS team_short FROM players p JOIN teams t ON p.team_id=t.id WHERE p.is_active=1 ORDER BY t.name,p.name")->fetchAll();
$matches = $db->query("SELECT m.*,ta.name AS team_a_name,ta.short_name AS team_a_short,tb.name AS team_b_name,tb.short_name AS team_b_short,COALESCE(tw.short_name,'—') AS toss_winner_short FROM matches m JOIN teams ta ON m.team_a_id=ta.id JOIN teams tb ON m.team_b_id=tb.id LEFT JOIN teams tw ON m.toss_won_by=tw.id ORDER BY m.created_at DESC LIMIT 50")->fetchAll();
$liveCount=count(array_filter($matches,fn($m)=>$m['status']==='live'));
$upcomingCount=count(array_filter($matches,fn($m)=>$m['status']==='upcoming'));
$completedCount=count(array_filter($matches,fn($m)=>$m['status']==='completed'));
$sections=['dashboard','companies','teams','players','matches'];
$teamsForSelect=$teams;
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Super Admin — CricketLive</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ====== RESET & THEME VARIABLES ====== */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root,[data-theme="dark"]{
    --bg:#050914;--bg2:#0b1020;--bg-card:rgba(15,23,42,0.85);--bg-card2:#0f172a;
    --bg-input:#1e293b;--bg-hover:rgba(30,41,59,0.60);--border:rgba(255,255,255,0.06);
    --border2:rgba(255,255,255,0.10);--text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;
    --accent:#f97316;--accent2:#fb923c;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--purple:#8b5cf6;
    --shadow:0 4px 24px rgba(0,0,0,0.40);--glass:rgba(15,23,42,0.70);
    --sb-track:#0b1020;--sb-thumb:#1e293b;
}
[data-theme="light"]{
    --bg:#eef1f5;--bg2:#e2e6ec;--bg-card:#ffffff;--bg-card2:#f8fafc;
    --bg-input:#f1f5f9;--bg-hover:#e9eef3;--border:#dde1e7;--border2:#c8cdd5;
    --text:#0f172a;--text-dim:#475569;--text-mute:#94a3b8;
    --accent:#ea580c;--accent2:#c2410c;--red:#dc2626;--green:#16a34a;--blue:#2563eb;--purple:#7c3aed;
    --shadow:0 1px 3px rgba(0,0,0,0.08);--glass:#ffffff;
    --sb-track:#eef1f5;--sb-thumb:#b0b8c4;
}
html,body{width:100%;height:100%;overflow:hidden;font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;transition:background 0.3s,color 0.3s}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--sb-track)}::-webkit-scrollbar-thumb{background:var(--sb-thumb);border-radius:3px}

/* ====== LAYOUT ====== */
#app{display:flex;height:100vh}
#sidebar{width:240px;flex-shrink:0;background:var(--bg2);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:width 0.3s,transform 0.3s;z-index:50;overflow-y:auto;overflow-x:hidden}
#sidebar.collapsed{width:64px}
#sidebar .logo{padding:20px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border);flex-shrink:0}
#sidebar .logo .icon{font-size:24px;flex-shrink:0}
#sidebar .logo .text{font-weight:800;font-size:15px;color:var(--accent);white-space:nowrap;overflow:hidden;transition:opacity 0.2s}
#sidebar.collapsed .logo .text{opacity:0;width:0}
#sidebar nav{flex:1;padding:12px 8px;display:flex;flex-direction:column;gap:2px}
#sidebar nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;text-decoration:none;color:var(--text-dim);font-size:13px;font-weight:500;transition:all 0.2s;white-space:nowrap;overflow:hidden;position:relative}
#sidebar nav a:hover{background:var(--bg-hover);color:var(--text)}
#sidebar nav a.active{background:linear-gradient(135deg,rgba(249,115,22,0.20),rgba(249,115,22,0.05));color:var(--accent);font-weight:600}
#sidebar nav a.active::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:3px;background:var(--accent);border-radius:0 3px 3px 0}
#sidebar nav a .nav-icon{font-size:18px;flex-shrink:0;width:24px;text-align:center}
#sidebar nav a .nav-label{transition:opacity 0.2s}
#sidebar.collapsed nav a .nav-label{opacity:0;width:0}
#sidebar .sidebar-footer{padding:12px;border-top:1px solid var(--border);flex-shrink:0;display:flex;flex-direction:column;gap:8px}
#sidebar .sidebar-footer .sf-user{font-size:11px;color:var(--text-dim);text-align:center}
#sidebar .sidebar-footer .sf-user strong{display:block;color:var(--text);font-size:12px}
#sidebar .sidebar-footer button{width:100%;padding:8px;border-radius:8px;border:1px solid var(--border);background:var(--bg-card);color:var(--text-dim);cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;gap:6px;transition:all 0.2s}
#sidebar .sidebar-footer button:hover{background:var(--bg-hover);color:var(--text)}
#sidebar .sidebar-footer a.btn{text-decoration:none;width:100%}

#main{flex:1;display:flex;flex-direction:column;overflow:hidden}
#topbar{height:56px;flex-shrink:0;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 16px;gap:12px}
#topbar .hamburger{display:none;background:none;border:none;color:var(--text);font-size:22px;cursor:pointer;padding:4px}
#topbar .topbar-right{display:flex;align-items:center;gap:10px;font-size:12px}
#topbar .badge{font-size:10px;padding:3px 10px;border-radius:20px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;background:rgba(249,115,22,0.15);color:var(--accent);border:1px solid rgba(249,115,22,0.25)}
#topbar .topbar-right a{color:var(--text-dim);text-decoration:none;transition:color 0.2s}
#topbar .topbar-right a:hover{color:var(--accent)}
#content{flex:1;overflow-y:auto;padding:20px}

/* ====== CARDS & COMPONENTS ====== */
.card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:var(--shadow);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);animation:cardIn 0.4s ease-out}
@keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes scaleIn{from{opacity:0;transform:scale(0.94)}to{opacity:1;transform:scale(1)}}
.anim-up{animation:fadeUp 0.45s ease-out forwards}
.anim-scale{animation:scaleIn 0.3s ease-out forwards}
h2{font-size:18px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.grid2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}

.stat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:var(--shadow);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);transition:transform 0.2s,border-color 0.2s;cursor:default}
.stat-card:hover{transform:translateY(-2px);border-color:var(--accent)}
.stat-card .stat-label{font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-mute);margin-bottom:6px}
.stat-card .stat-value{font-size:28px;font-weight:900;color:var(--text)}
.stat-card .stat-icon{float:right;font-size:24px;opacity:0.3;margin-top:-8px}

/* ====== FORMS ====== */
.form-group{margin-bottom:12px}
.form-group label{display:block;font-size:11px;font-weight:600;color:var(--text-dim);margin-bottom:4px;text-transform:uppercase;letter-spacing:0.04em}
.form-group label .req{color:var(--red)}
input,select,textarea{width:100%;background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--text);font-family:inherit;transition:all 0.2s;outline:none}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,0.12)}
input::placeholder{color:var(--text-mute)}
select option{background:var(--bg2);color:var(--text)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;font-family:inherit;text-decoration:none}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2);transform:translateY(-1px);box-shadow:0 4px 16px rgba(249,115,22,0.25)}
.btn-primary:active{transform:scale(0.97)}
.btn-secondary{background:var(--bg-hover);color:var(--text);border:1px solid var(--border)}.btn-secondary:hover{background:var(--bg-input)}
.btn-danger{background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.2)}.btn-danger:hover{background:rgba(239,68,68,0.2)}
.btn-sm{padding:6px 12px;font-size:11px;border-radius:8px}
.btn-full{width:100%}
.btn-row{display:flex;gap:8px;flex-wrap:wrap}

/* ====== TABLES ====== */
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:12px}
table th{text-align:left;padding:10px 12px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-mute);font-weight:700;border-bottom:2px solid var(--border);white-space:nowrap}
table td{padding:10px 12px;border-bottom:1px solid var(--border);white-space:nowrap}
table tbody tr{transition:background 0.15s}
table tbody tr:hover{background:var(--bg-hover)}
.badge-sm{font-size:10px;padding:3px 8px;border-radius:20px;font-weight:600;letter-spacing:0.04em}
.badge-live{background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.2);animation:pulseLive 2s ease-in-out infinite}
@keyframes pulseLive{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,0.3)}50%{box-shadow:0 0 0 6px rgba(239,68,68,0)}}
.badge-upcoming{background:rgba(59,130,246,0.12);color:var(--blue);border:1px solid rgba(59,130,246,0.2)}
.badge-done{background:rgba(100,116,139,0.10);color:var(--text-dim);border:1px solid rgba(100,116,139,0.15)}
.badge-green{background:rgba(34,197,94,0.10);color:var(--green)}
.badge-blue{background:rgba(59,130,246,0.10);color:var(--blue)}
.badge-purple{background:rgba(139,92,246,0.10);color:var(--purple)}
.badge-orange{background:rgba(249,115,22,0.10);color:var(--accent)}
.badge-active{background:rgba(34,197,94,0.10);color:var(--green);border:1px solid rgba(34,197,94,0.2)}
.badge-expired{background:rgba(239,68,68,0.10);color:var(--red);border:1px solid rgba(239,68,68,0.2)}

th.r,td.r{text-align:right}th.c,td.c{text-align:center}
.avatar{width:32px;height:32px;border-radius:50%;background:var(--bg-input);display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border);flex-shrink:0;font-size:11px;font-weight:700;color:var(--text-dim)}
.avatar img{width:100%;height:100%;object-fit:cover}

/* ====== LICENSE KEY DISPLAY ====== */
.key-display{font-family:'Courier New',monospace;font-size:14px;font-weight:700;letter-spacing:0.04em;color:var(--accent);background:var(--bg-input);padding:6px 12px;border-radius:8px;display:inline-block;border:1px dashed var(--accent);user-select:all}

/* ====== MODALS ====== */
.modal-overlay{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal-overlay.show .modal-box{animation:modalPop 0.3s cubic-bezier(0.16,1,0.3,1) forwards}
@keyframes modalPop{from{opacity:0;transform:scale(0.92) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-box{background:var(--bg-card);border:1px solid var(--border);border-radius:18px;width:100%;max-width:460px;max-height:90vh;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.50)}
.modal-header{background:linear-gradient(135deg,var(--accent),var(--accent2));padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.modal-header h3{font-size:14px;font-weight:700;color:#fff;margin:0;display:flex;align-items:center;gap:8px}
.modal-header h3 .micon{font-size:18px}
.modal-close{background:rgba(255,255,255,0.15);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;flex-shrink:0}
.modal-close:hover{background:rgba(255,255,255,0.25)}
.modal-body{padding:20px;overflow-y:auto;max-height:calc(90vh - 60px)}
.modal-body .form-group:last-child{margin-bottom:0}

/* ====== TOAST ====== */
.toast{position:fixed;top:20px;right:20px;z-index:200;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.30);display:flex;align-items:center;gap:10px;max-width:380px;animation:toastIn 0.3s ease-out forwards}
.toast.success{background:#166534;color:#bbf7d0;border:1px solid rgba(34,197,94,0.3)}
.toast.error{background:#7f1d1d;color:#fecaca;border:1px solid rgba(239,68,68,0.3)}
@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}

/* ====== RESPONSIVE ====== */
@media(max-width:1200px){#sidebar{width:200px}.stat-card .stat-value{font-size:24px}}
@media(max-width:1024px){
 .grid4{grid-template-columns:repeat(2,1fr)}.grid3{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}
 #sidebar{width:180px}#sidebar nav a{font-size:12px;padding:8px 10px}
 #content{padding:14px}
 .stat-card{padding:14px}.stat-card .stat-value{font-size:24px}
}
@media(max-width:768px){
 #sidebar{position:fixed;left:0;top:0;bottom:0;transform:translateX(-100%);width:260px;transition:transform 0.3s;z-index:50}
 #sidebar.mobile-open{transform:translateX(0)}
 #topbar .hamburger{display:block}
 .grid4{grid-template-columns:1fr 1fr}
 #content{padding:12px}h2{font-size:16px}
 .card{padding:16px;border-radius:12px}
 .stat-card{padding:12px 14px}.stat-card .stat-value{font-size:22px}
 table{font-size:11px}table th,table td{padding:6px 8px}
 .tbl-wrap{margin:0 -12px;width:calc(100% + 24px)}
 .btn{font-size:11px;padding:7px 12px}
 .btn-sm{padding:5px 10px;font-size:10px}
 .badge-sm{font-size:9px;padding:2px 6px}
 .key-display{font-size:11px;padding:4px 8px}
  .avatar{width:26px;height:26px;font-size:9px}
 }
@media(max-width:480px){
 .grid4,.grid3,.grid2{grid-template-columns:1fr}
 #content{padding:8px}
 h2{font-size:15px}
 .card{padding:12px;border-radius:10px}
 .stat-card{padding:10px 12px}.stat-card .stat-value{font-size:20px}
 .btn{font-size:10px;padding:6px 10px}
 .btn-sm{padding:4px 8px;font-size:8px}
 .badge-sm{font-size:8px}
 table{font-size:10px}table th,table td{padding:4px 6px}
 .form-group{margin-bottom:8px}
 input,select{padding:8px 10px;font-size:12px}
 .key-display{font-size:10px;word-break:break-all}
  #sidebar .sidebar-footer button,#sidebar .sidebar-footer a{font-size:10px;padding:6px}
  /* Teams: single column */
  div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}
  /* Tables: full-width scroll */
  .tbl-wrap{margin:0 -8px;width:calc(100% + 16px)}
  /* Match inline */ td form.inline-flex{flex-wrap:wrap}
 }

/* ====== TABS ====== */
.tab-nav{display:flex;gap:4px;margin-bottom:18px;flex-wrap:wrap}
.tab-nav a{padding:8px 16px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:600;color:var(--text-dim);transition:all 0.2s;border:1px solid transparent}
.tab-nav a:hover{background:var(--bg-hover);color:var(--text)}
.tab-nav a.active{background:rgba(249,115,22,0.12);color:var(--accent);border-color:rgba(249,115,22,0.25)}

/* ====== SECTION HEADERS ====== */
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.section-head h3{font-size:14px;font-weight:700;color:var(--text)}

.note{font-size:10px;color:var(--text-mute);margin-top:8px}
</style>
</head>
<body>
<div id="app">
<!-- ====== SIDEBAR ====== -->
<aside id="sidebar">
    <div class="logo"><span class="icon">&#127951;</span><span class="text">CricketAdmin</span></div>
    <nav>
        <?php
        $navItems = [
            'dashboard' => ['&#128202;','Dashboard'],
            'companies' => ['&#127970;','Companies'],
            'teams'     => ['&#127951;','Teams'],
            'players'   => ['&#128101;','Players'],
            'matches'   => ['&#127922;','Matches'],
        ];
        foreach ($navItems as $sec => $nv):
        ?>
        <a href="?section=<?=$sec?>" class="<?=$action===$sec?'active':''?>">
            <span class="nav-icon"><?=$nv[0]?></span><span class="nav-label"><?=$nv[1]?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sf-user"><strong><?=htmlspecialchars($user['username'])?></strong>Super Admin</div>
        <a href="scorer_panel.php" class="btn btn-primary btn-full" style="font-size:11px;padding:7px">&#127951; Scorer Panel</a>
        <a href="live_output.php" class="btn btn-secondary btn-full" style="font-size:11px;padding:7px">&#128250; Control</a>
        <button onclick="openAccountSettings()" class="btn btn-secondary btn-full" style="font-size:11px;padding:7px">&#9881; Account Settings</button>
        <button onclick="toggleTheme()" class="btn btn-secondary btn-full" style="font-size:11px;padding:7px">&#127763; Theme</button>
        <a href="api.php?action=logout" class="btn btn-danger btn-full" style="font-size:11px;padding:7px" onclick="return confirmLogout()">Logout</a>
    </div>
</aside>

<!-- ====== MAIN ====== -->
<div id="main">
    <header id="topbar">
        <button class="hamburger" onclick="toggleSidebar()">&#9776;</button>
        <div style="display:flex;align-items:center;gap:8px">
            <span class="badge">Super Admin</span>
            <span style="font-size:13px;font-weight:600;color:var(--text)">&#128202; Dashboard</span>
        </div>
        <span></span>
    </header>

    <div id="content">
    <?php if ($msg || $err): ?>
    <div class="toast <?=$msg?'success':'error'?>" id="toastMsg">
        <span><?=$msg?'&#10003;':'&#10007;'?></span>
        <span><?=$msg?:htmlspecialchars($err)?></span>
    </div>
    <?php endif; ?>

    <?php // ======================== DASHBOARD ======================== ?>
    <?php if ($action === 'dashboard'): ?>
    <h2>&#128202; System Dashboard</h2>
    <div class="grid4" style="margin-bottom:20px">
        <?php
        $stats=[
            ['Companies',count($companies),'&#127970;','purple'],
            ['Teams',count($teams),'&#127951;','orange'],
            ['Players',count($players),'&#128101;','green'],
            ['Matches',count($matches),'&#127922;','blue'],
        ];
        foreach($stats as $i=>$s): ?>
        <div class="stat-card anim-up" style="animation-delay:<?=$i*0.08?>s">
            <span class="stat-icon"><?=$s[2]?></span>
            <div class="stat-label"><?=$s[0]?></div>
            <div class="stat-value"><?=$s[1]?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="grid3" style="margin-bottom:20px">
        <div class="stat-card anim-up" style="animation-delay:0.3s"><div class="stat-label">Live Matches</div><div class="stat-value" style="color:var(--red)"><?=$liveCount?></div></div>
        <div class="stat-card anim-up" style="animation-delay:0.38s"><div class="stat-label">Upcoming</div><div class="stat-value" style="color:var(--blue)"><?=$upcomingCount?></div></div>
        <div class="stat-card anim-up" style="animation-delay:0.46s"><div class="stat-label">Completed</div><div class="stat-value" style="color:var(--text-dim)"><?=$completedCount?></div></div>
    </div>
    <div class="card anim-up" style="animation-delay:0.5s">
        <h3 style="margin-bottom:14px;font-size:14px">&#9889; Quick Actions</h3>
        <div class="btn-row">
            <a href="?section=companies" class="btn btn-primary">+ New Company</a>
            <a href="?section=teams" class="btn btn-secondary">+ New Team</a>
            <a href="?section=players" class="btn btn-secondary">+ Add Player</a>
            <a href="?section=matches" class="btn btn-secondary">+ New Match</a>
        </div>
    </div>
    <?php if (count($matches) > 0): ?>
    <div class="card anim-up" style="margin-top:20px;animation-delay:0.6s">
        <div class="section-head"><h3>&#127922; Recent Matches</h3><a href="?section=matches" style="color:var(--accent);font-size:12px;text-decoration:none">View all &#8594;</a></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>Match</th><th>Teams</th><th class="c">Status</th></tr></thead>
            <tbody><?php foreach(array_slice($matches,0,8) as $m): ?>
                <tr><td style="font-weight:600"><?=htmlspecialchars($m['match_title'])?></td><td style="color:var(--text-dim)"><?=htmlspecialchars($m['team_a_short'])?> vs <?=htmlspecialchars($m['team_b_short'])?></td>
                <td class="c"><span class="badge-sm <?=$m['status']==='live'?'badge-live':($m['status']==='completed'?'badge-done':'badge-upcoming')?>"><?=$m['status']?></span></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php // ======================== COMPANIES ======================== ?>
    <?php elseif ($action === 'companies'): ?>
    <h2>&#127970; Company &amp; License Management</h2>
    <div class="grid2">
        <!-- Create Company -->
        <div class="card anim-up">
            <h3 style="font-size:14px;color:var(--accent);margin-bottom:14px">+ Register Company</h3>
            <form method="POST">
                <input type="hidden" name="sub_action" value="create_company">
                <div class="form-group"><label>Company Name <span class="req">*</span></label><input type="text" name="company_name" required placeholder="Sky Sports Broadcasting Ltd."></div>
                <div class="form-group"><label>Contact Email</label><input type="email" name="company_email" placeholder="contact@skysports.com"></div>
                <div class="form-group"><label>Contact Phone</label><input type="text" name="company_phone" placeholder="+44 20 1234 5678"></div>
                <div style="background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:12px">
                    <p style="font-size:11px;font-weight:700;color:var(--accent);margin-bottom:8px">&#128100; Admin Login Credentials</p>
                    <div class="form-group"><label>Username <span class="req">*</span> (min 3)</label><input type="text" name="company_admin_user" required minlength="3" placeholder="sky_admin"></div>
                    <div class="form-group"><label>Password <span class="req">*</span> (min 6)</label><input type="password" name="company_admin_pass" required minlength="6" placeholder="••••••"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Register Company</button>
            </form>
            <p class="note" style="margin-top:8px;font-size:10px;color:var(--text-mute)">Creates company + admin account + 1-year license automatically.</p>
        </div>
        <!-- Generate License -->
        <div class="card anim-up" style="animation-delay:0.1s">
            <h3 style="font-size:14px;color:var(--accent);margin-bottom:14px">&#128273; Generate License Key</h3>
            <form method="POST">
                <input type="hidden" name="sub_action" value="create_license">
                <div class="form-group"><label>Company <span class="req">*</span></label>
                    <select name="license_company_id" required>
                        <option value="">-- Select Company --</option>
                        <?php foreach($companies as $c): if($c['is_active']): ?>
                        <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div class="form-group"><label>Valid From <span class="req">*</span></label><input type="date" name="license_valid_from" required value="<?=date('Y-m-d')?>"></div>
                    <div class="form-group"><label>Valid Until <span class="req">*</span></label><input type="date" name="license_valid_until" required value="<?=date('Y-m-d',strtotime('+1 year'))?>"></div>
                </div>
                <div class="form-group"><label>Max Scorer Users</label><input type="number" name="license_max_users" value="10" min="1" max="500"></div>
                <button type="submit" class="btn btn-primary btn-full">&#128273; Generate License Key</button>
            </form>
        </div>
    </div>
    <!-- Companies Table -->
    <?php if (count($companies) > 0): ?>
    <div class="card anim-up" style="margin-top:20px;animation-delay:0.2s">
        <div class="section-head"><h3>All Companies (<?=count($companies)?>)</h3></div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>Company</th><th>Contact</th><th>Licenses</th><th class="c">Status</th><th class="r">Actions</th></tr></thead>
            <tbody>
            <?php foreach($companies as $c):
                $licenses=$db->prepare('SELECT * FROM licenses WHERE company_id=? ORDER BY created_at DESC'); $licenses->execute([$c['id']]); $lics=$licenses->fetchAll();
            ?>
            <tr>
                <td style="font-weight:600"><?=htmlspecialchars($c['name'])?></td>
                <td style="color:var(--text-dim);font-size:11px"><?=htmlspecialchars($c['contact_email']?:'—')?></td>
                <td>
                    <?php if(count($lics)>0): foreach($lics as $l): $expired=strtotime($l['valid_until'])<time(); ?>
                    <div style="margin:2px 0;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span class="key-display" style="font-size:11px;padding:3px 8px"><?=htmlspecialchars($l['license_key'])?></span>
                        <span class="badge-sm <?=$expired?'badge-expired':'badge-active'?>"><?=$expired?'EXPIRED':'ACTIVE'?></span>
                        <span style="font-size:10px;color:var(--text-mute)"><?=$l['valid_from']?> → <?=$l['valid_until']?></span>
                        <form method="POST" style="display:inline" onsubmit="return delConfirm('Revoke this license?',this)"><input type="hidden" name="sub_action" value="delete_license"><input type="hidden" name="license_id" value="<?=$l['id']?>"><button class="btn btn-danger btn-sm" style="padding:2px 6px;font-size:9px">&#10005;</button></form>
                    </div>
                    <?php endforeach; else: ?><span style="color:var(--text-mute);font-size:11px">No licenses</span><?php endif; ?>
                </td>
                <td class="c"><span class="badge-sm <?=$c['is_active']?'badge-active':'badge-expired'?>"><?=$c['is_active']?'Active':'Inactive'?></span></td>
                <td class="r">
                    <button onclick="openCompanyEdit(<?=$c['id']?>,'<?=htmlspecialchars(addslashes($c['name']))?>','<?=htmlspecialchars(addslashes($c['contact_email']??''))?>','<?=htmlspecialchars(addslashes($c['contact_phone']??''))?>',<?=$c['is_active']?>)" class="btn btn-secondary btn-sm">Edit</button>
                    <form method="POST" onsubmit="return delConfirm('Delete <?=htmlspecialchars($c['name'],ENT_QUOTES)?> and all its licenses?',this)" style="display:inline"><input type="hidden" name="sub_action" value="delete_company"><input type="hidden" name="company_id" value="<?=$c['id']?>"><button class="btn btn-danger btn-sm">Delete</button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <?php // ======================== TEAMS ======================== ?>
    <?php elseif ($action === 'teams'): ?>
    <h2>&#127951; Team Management</h2>
    <div class="grid2">
        <div class="card anim-up">
            <h3 style="font-size:14px;color:var(--accent);margin-bottom:14px">+ Create Team</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="sub_action" value="create_team">
                <div class="form-group"><label>Team Name <span class="req">*</span></label><input type="text" name="team_name" required placeholder="Chennai Super Kings"></div>
                <div class="form-group"><label>Short Name <span class="req">*</span> (max 10)</label><input type="text" name="team_short" required maxlength="10" placeholder="CSK"></div>
                <div class="form-group"><label>Logo (PNG/JPG, max 2MB)</label><input type="file" name="team_logo" accept="image/png,image/jpeg" style="padding:8px"></div>
                <button type="submit" class="btn btn-primary btn-full">Create Team</button>
            </form>
        </div>
        <div class="card anim-up" style="animation-delay:0.1s">
            <div class="section-head"><h3>All Teams (<?=count($teams)?>)</h3></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <?php foreach($teams as $t): ?>
            <div style="background:var(--bg-input);border:1px solid var(--border);border-radius:12px;padding:12px;display:flex;align-items:center;gap:10px;transition:all 0.2s">
                <div class="avatar" style="width:40px;height:40px">
                    <?php if($t['logo_path']): ?><img src="<?=htmlspecialchars($t['logo_path'])?>" alt=""><?php else: ?><?=htmlspecialchars(substr($t['short_name'],0,2))?><?php endif; ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($t['name'])?></div>
                    <div style="color:var(--text-mute);font-size:10px"><?=htmlspecialchars($t['short_name'])?></div>
                </div>
                <div style="display:flex;gap:4px;flex-shrink:0">
                    <button onclick="openTeamEdit(<?=$t['id']?>,'<?=htmlspecialchars(addslashes($t['name']))?>','<?=htmlspecialchars(addslashes($t['short_name']))?>','<?=htmlspecialchars($t['logo_path']??'')?>',this)" class="btn btn-secondary btn-sm">Edit</button>
                    <form method="POST" onsubmit="return delConfirm('Delete <?=htmlspecialchars($t['name'],ENT_QUOTES)?>?',this)" style="display:inline"><input type="hidden" name="sub_action" value="delete_team"><input type="hidden" name="team_id" value="<?=$t['id']?>"><button class="btn btn-danger btn-sm">Del</button></form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($teams)): ?><p style="text-align:center;color:var(--text-mute);grid-column:span 2;padding:30px">No teams yet.</p><?php endif; ?>
            </div>
        </div>
    </div>

    <?php // ======================== PLAYERS ======================== ?>
    <?php elseif ($action === 'players'): ?>
    <h2>&#128101; Player Management</h2>
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <input type="text" id="playerSearch" placeholder="&#128269; Search players..." oninput="filterPlayers()" style="flex:1;min-width:200px;max-width:360px">
        <select id="playerTeamFilter" onchange="filterPlayers()" style="width:200px"><option value="">All Teams</option><?php foreach($teamsForSelect as $t): ?><option value="<?=htmlspecialchars($t['short_name'])?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select>
        <select id="playerRoleFilter" onchange="filterPlayers()" style="width:160px"><option value="">All Roles</option><option value="batsman">Batsman</option><option value="bowler">Bowler</option><option value="all-rounder">All-Rounder</option><option value="wicket-keeper">Wicket-Keeper</option></select>
    </div>
    <div class="grid2">
        <div class="card anim-up">
            <h3 style="font-size:14px;color:var(--accent);margin-bottom:14px">+ Add Player</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="sub_action" value="create_player">
                <div class="form-group"><label>Team <span class="req">*</span></label><select name="player_team_id" required><option value="">-- Select --</option><?php foreach($teamsForSelect as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Player Name <span class="req">*</span></label><input type="text" name="player_name" required placeholder="Virat Kohli"></div>
                <div class="form-group"><label>Role</label><select name="player_role"><option value="batsman">Batsman</option><option value="bowler">Bowler</option><option value="all-rounder">All-Rounder</option><option value="wicket-keeper">Wicket-Keeper</option></select></div>
                <div class="form-group"><label>Batting Style</label><select name="player_batting"><option value="">-- Select --</option><option value="Right-hand bat">Right-hand bat</option><option value="Left-hand bat">Left-hand bat</option></select></div>
                <div class="form-group"><label>Bowling Style</label><select name="player_bowling"><option value="">-- Select --</option><?php foreach(['Right-arm fast','Right-arm medium','Right-arm fast-medium','Right-arm off-break','Right-arm leg-break','Left-arm fast','Left-arm medium','Left-arm orthodox','Left-arm wrist spin'] as $bs): ?><option value="<?=$bs?>"><?=$bs?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Age</label><input type="number" name="player_age" min="5" max="99" placeholder="e.g. 24" style="width:100px"></div>
                <div class="form-group"><label>School Entry Year</label><input type="text" name="player_school" maxlength="4" placeholder="e.g. 2015" style="width:120px"></div>
                <div class="form-group"><label>Achievements / Special Talents</label><textarea name="player_achievements" rows="2" placeholder="Optional – notable sports achievements, captaincy, awards..." style="width:100%;background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--text);font-family:inherit;outline:none;resize:vertical"></textarea></div>
                <div class="form-group"><label>Photo (PNG/JPG, max 2MB)</label><input type="file" name="player_photo" accept="image/png,image/jpeg" style="padding:8px"></div>
                <button type="submit" class="btn btn-primary btn-full">Add Player</button>
            </form>
        </div>
        <div class="card anim-up" style="animation-delay:0.1s">
            <div class="section-head"><h3>All Players (<?=count($players)?>)</h3></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th></th><th>Name</th><th>Team</th><th>Role</th><th>Details</th><th class="r">Actions</th></tr></thead>
                <tbody>
                <?php foreach($players as $p): ?>
                <tr data-name="<?=htmlspecialchars(strtolower($p['name']))?>" data-team="<?=htmlspecialchars($p['team_short'])?>" data-role="<?=$p['role']?>">
                    <td><div class="avatar" style="width:28px;height:28px"><?php if($p['photo_path']): ?><img src="<?=htmlspecialchars($p['photo_path'])?>" alt=""><?php else: ?><?=strtoupper(substr($p['name'],0,1))?><?php endif; ?></div></td>
                    <td style="font-weight:600"><?=htmlspecialchars($p['name'])?></td>
                    <td style="color:var(--text-dim)"><?=htmlspecialchars($p['team_short'])?></td>
                    <td><span class="badge-sm <?=$p['role']==='batsman'?'badge-green':($p['role']==='bowler'?'badge-blue':($p['role']==='all-rounder'?'badge-purple':'badge-orange'))?>"><?=$p['role']?></span></td>
                    <td style="font-size:11px;color:var(--text-dim);max-width:200px">
                        <?php if($p['age']): ?><span>Age: <?=(int)$p['age']?></span><?php endif; ?>
                        <?php if($p['school_entry_year']): ?><?php if($p['age']): ?><br><?php endif; ?><span>School: <?=htmlspecialchars($p['school_entry_year'])?></span><?php endif; ?>
                        <?php if($p['achievements']): ?><?php if($p['age']||$p['school_entry_year']): ?><br><?php endif; ?><span style="color:var(--accent)">&#9733; <?=htmlspecialchars(mb_substr($p['achievements'],0,60))?><?=mb_strlen($p['achievements'])>60?'…':''?></span><?php endif; ?>
                    </td>
                    <td class="r">
                        <button onclick="openPlayerEdit(<?=$p['id']?>,<?=$p['team_id']?>,'<?=htmlspecialchars(addslashes($p['name']))?>','<?=$p['role']?>','<?=htmlspecialchars(addslashes($p['batting_style']??''))?>','<?=htmlspecialchars(addslashes($p['bowling_style']??''))?>','<?=htmlspecialchars($p['photo_path']??'')?>',<?=$p['age']?:'null'?>,'<?=htmlspecialchars(addslashes($p['school_entry_year']??''))?>','<?=htmlspecialchars(addslashes($p['achievements']??''))?>',this)" class="btn btn-secondary btn-sm">Edit</button>
                        <form method="POST" onsubmit="return delConfirm('Deactivate <?=htmlspecialchars($p['name'],ENT_QUOTES)?>?',this)" style="display:inline"><input type="hidden" name="sub_action" value="delete_player"><input type="hidden" name="player_id" value="<?=$p['id']?>"><button class="btn btn-danger btn-sm">Del</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($players)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-mute);padding:30px">No players yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <?php // ======================== MATCHES ======================== ?>
    <?php elseif ($action === 'matches'): ?>
    <h2>&#127922; Match Management</h2>
    <div class="grid2">
        <div class="card anim-up">
            <h3 style="font-size:14px;color:var(--accent);margin-bottom:14px">+ Create Match</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="sub_action" value="create_match">
                <div class="form-group"><label>Match Format <span class="req">*</span></label><select name="match_format" required><option value="t20i">T20I (20 Overs)</option><option value="odi">ODI (50 Overs)</option><option value="test">Test Match</option></select></div>
                <div class="form-group"><label>Team A <span class="req">*</span></label><select name="match_team_a" required><option value="">-- Select --</option><?php foreach($teamsForSelect as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Team B <span class="req">*</span></label><select name="match_team_b" required><option value="">-- Select --</option><?php foreach($teamsForSelect as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Match Title (auto if blank)</label><input type="text" name="match_title" placeholder="3rd ODI — Colombo"></div>
                <div class="form-group"><label>Location / Venue</label><input type="text" name="match_location" placeholder="R.Premadasa Stadium, Colombo"></div>
                <div class="form-group"><label>Match Logo (PNG/JPG, max 2MB)</label><input type="file" name="match_logo" accept="image/png,image/jpeg" style="padding:8px"></div>
                <div style="background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:12px;margin-bottom:12px">
                    <p style="font-size:11px;font-weight:700;color:var(--accent);margin-bottom:8px">&#127936; Toss (optional)</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <select name="match_toss_won"><option value="">Won By</option><?php foreach($teamsForSelect as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['short_name'])?></option><?php endforeach; ?></select>
                        <select name="match_toss_dec"><option value="">Decision</option><option value="bat">Bat First</option><option value="bowl">Bowl First</option></select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Create Match</button>
            </form>
        </div>
        <div class="card anim-up" style="animation-delay:0.1s">
            <div class="section-head"><h3>All Matches (<?=count($matches)?>)</h3></div>
            <div class="tbl-wrap"><table>
                <thead><tr><th>Match</th><th>Teams</th><th class="c">Status</th><th class="c">Change</th><th class="r">Action</th></tr></thead>
                <tbody>
                <?php foreach($matches as $m): ?>
                <tr>
                    <td style="font-weight:600">
                        <div style="display:flex;align-items:center;gap:6px">
                            <div class="avatar" style="width:24px;height:24px;border-radius:6px"><?php if($m['match_logo']): ?><img src="<?=htmlspecialchars($m['match_logo'])?>" alt=""><?php else: ?>&#127922;<?php endif; ?></div>
                            <span style="max-width:160px;overflow:hidden;text-overflow:ellipsis;display:inline-block"><?=htmlspecialchars($m['match_title'])?></span>
                        </div>
                    </td>
                    <td style="color:var(--text-dim)"><?=htmlspecialchars($m['team_a_short'])?> vs <?=htmlspecialchars($m['team_b_short'])?></td>
                    <td class="c"><span class="badge-sm <?=$m['status']==='live'?'badge-live':($m['status']==='completed'?'badge-done':'badge-upcoming')?>"><?=$m['status']?></span></td>
                    <td class="c">
                        <form method="POST" style="display:inline-flex;gap:2px">
                            <input type="hidden" name="sub_action" value="update_match_status"><input type="hidden" name="match_id" value="<?=$m['id']?>">
                            <select name="match_status" style="width:90px;padding:4px 6px;font-size:10px;border-radius:6px"><option value="upcoming" <?=$m['status']==='upcoming'?'selected':''?>>Upcoming</option><option value="live" <?=$m['status']==='live'?'selected':''?>>Live</option><option value="completed" <?=$m['status']==='completed'?'selected':''?>>Completed</option></select>
                            <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 8px;font-size:10px">Set</button>
                        </form>
                    </td>
                    <td class="r">
                        <button onclick="openMatchEdit(<?=$m['id']?>,'<?=htmlspecialchars(addslashes($m['match_title']))?>','<?=htmlspecialchars($m['match_logo']??'')?>','<?=htmlspecialchars(addslashes($m['location']??''))?>',this)" class="btn btn-secondary btn-sm">Edit</button>
                        <form method="POST" onsubmit="return delConfirm('Permanently delete &quot;<?=htmlspecialchars($m['match_title'],ENT_QUOTES)?>&quot;? All data will be lost.',this)" style="display:inline"><input type="hidden" name="sub_action" value="delete_match"><input type="hidden" name="match_id" value="<?=$m['id']?>"><button class="btn btn-danger btn-sm">Del</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($matches)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-mute);padding:30px">No matches yet.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>
    <?php include __DIR__.'/footer.php'; ?>
    </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<!-- ====== MODALS ====== -->
<!-- Team Edit -->
<div id="teamEditModal" class="modal-overlay" onclick="if(event.target===this)closeModal('teamEditModal',this)">
    <div class="modal-box">
        <div class="modal-header"><h3><span class="micon">&#127951;</span> Edit Team</h3><button class="modal-close" onclick="closeModal('teamEditModal',this)">&times;</button></div>
        <div class="modal-body"><form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="sub_action" value="update_team">
            <input type="hidden" name="team_id" id="editTeamId">
            <input type="hidden" name="existing_logo" id="editTeamLogo">
            <div class="form-group"><label>Team Name</label><input type="text" name="team_name" id="editTeamName" required></div>
            <div class="form-group"><label>Short Name</label><input type="text" name="team_short" id="editTeamShort" required maxlength="10"></div>
            <div class="form-group"><label>New Logo (optional)</label><input type="file" name="team_logo" accept="image/png,image/jpeg" style="padding:8px"></div>
            <div class="btn-row" style="justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeModal('teamEditModal',this)" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form></div>
    </div>
</div>
<!-- Player Edit -->
<div id="playerEditModal" class="modal-overlay" onclick="if(event.target===this)closeModal('playerEditModal',this)">
    <div class="modal-box">
        <div class="modal-header"><h3><span class="micon">&#128101;</span> Edit Player</h3><button class="modal-close" onclick="closeModal('playerEditModal',this)">&times;</button></div>
        <div class="modal-body"><form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="sub_action" value="update_player">
            <input type="hidden" name="player_id" id="editPlayerId">
            <input type="hidden" name="existing_photo" id="editPlayerPhoto">
            <div class="form-group"><label>Team</label><select name="player_team_id" id="editPlayerTeam" required><?php foreach($teamsForSelect as $t): ?><option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Name</label><input type="text" name="player_name" id="editPlayerName" required></div>
            <div class="form-group"><label>Role</label><select name="player_role" id="editPlayerRole"><option value="batsman">Batsman</option><option value="bowler">Bowler</option><option value="all-rounder">All-Rounder</option><option value="wicket-keeper">Wicket-Keeper</option></select></div>
            <div class="form-group"><label>Batting Style</label><select name="player_batting" id="editPlayerBatting"><option value="">-- Select --</option><option value="Right-hand bat">Right-hand bat</option><option value="Left-hand bat">Left-hand bat</option></select></div>
            <div class="form-group"><label>Bowling Style</label><select name="player_bowling" id="editPlayerBowling"><option value="">-- Select --</option><?php foreach(['Right-arm fast','Right-arm medium','Right-arm fast-medium','Right-arm off-break','Right-arm leg-break','Left-arm fast','Left-arm medium','Left-arm orthodox','Left-arm wrist spin'] as $bs): ?><option value="<?=$bs?>"><?=$bs?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Age</label><input type="number" name="player_age" id="editPlayerAge" min="5" max="99" style="width:100px"></div>
            <div class="form-group"><label>School Entry Year</label><input type="text" name="player_school" id="editPlayerSchool" maxlength="4" placeholder="e.g. 2015" style="width:120px"></div>
            <div class="form-group"><label>Achievements / Special Talents</label><textarea name="player_achievements" id="editPlayerAchievements" rows="2" placeholder="Optional" style="width:100%;background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--text);font-family:inherit;outline:none;resize:vertical"></textarea></div>
            <div class="form-group"><label>New Photo (optional)</label><input type="file" name="player_photo" accept="image/png,image/jpeg" style="padding:8px"></div>
            <div class="btn-row" style="justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeModal('playerEditModal',this)" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form></div>
    </div>
</div>
<!-- Match Edit -->
<div id="matchEditModal" class="modal-overlay" onclick="if(event.target===this)closeModal('matchEditModal',this)">
    <div class="modal-box">
        <div class="modal-header"><h3><span class="micon">&#127922;</span> Edit Match</h3><button class="modal-close" onclick="closeModal('matchEditModal',this)">&times;</button></div>
        <div class="modal-body"><form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="sub_action" value="update_match">
            <input type="hidden" name="match_id" id="editMatchId">
            <input type="hidden" name="existing_match_logo" id="editMatchLogo">
            <div class="form-group"><label>Match Title</label><input type="text" name="match_title" id="editMatchTitle"></div>
            <div class="form-group"><label>Location / Venue</label><input type="text" name="match_location" id="editMatchLocation" placeholder="R.Premadasa Stadium, Colombo"></div>
            <div class="form-group"><label>Match Logo (optional)</label><input type="file" name="match_logo" accept="image/png,image/jpeg" style="padding:8px"></div>
            <div class="btn-row" style="justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeModal('matchEditModal',this)" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form></div>
    </div>
</div>
<!-- Company Edit -->
<div id="companyEditModal" class="modal-overlay" onclick="if(event.target===this)closeModal('companyEditModal',this)">
    <div class="modal-box">
        <div class="modal-header"><h3><span class="micon">&#127970;</span> Edit Company</h3><button class="modal-close" onclick="closeModal('companyEditModal',this)">&times;</button></div>
        <div class="modal-body"><form method="POST">
            <input type="hidden" name="sub_action" value="update_company">
            <input type="hidden" name="company_id" id="editCompanyId">
            <div class="form-group"><label>Company Name <span class="req">*</span></label><input type="text" name="company_name" id="editCompanyName" required></div>
            <div class="form-group"><label>Contact Email</label><input type="email" name="company_email" id="editCompanyEmail"></div>
            <div class="form-group"><label>Contact Phone</label><input type="text" name="company_phone" id="editCompanyPhone"></div>
            <div class="form-group"><label>Status</label><select name="company_active" id="editCompanyActive"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            <div class="btn-row" style="justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeModal('companyEditModal',this)" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form></div>
    </div>
</div>
<!-- Account Settings -->
<div id="accountModal" class="modal-overlay" onclick="if(event.target===this)closeModal('accountModal',this)">
    <div class="modal-box">
        <div class="modal-header"><h3><span class="micon">&#9881;</span> Account Settings</h3><button class="modal-close" onclick="closeModal('accountModal',this)">&times;</button></div>
        <div class="modal-body"><form method="POST">
            <input type="hidden" name="sub_action" value="update_account">
            <div class="form-group"><label>Display Name</label><input type="text" name="acc_display" value="<?=htmlspecialchars($_SESSION['display_name']??$user['username'])?>"></div>
            <div class="form-group"><label>Current Password (required to change)</label><input type="password" name="acc_current_pw" placeholder="••••••"></div>
            <div class="form-group"><label>New Password (min 6, leave blank to keep)</label><input type="password" name="acc_new_pw" minlength="6" placeholder="••••••"></div>
            <div class="btn-row" style="justify-content:flex-end;margin-top:8px">
                <button type="button" onclick="closeModal('accountModal',this)" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form></div>
    </div>
</div>
<!-- Logout Confirmation -->
<div id="logoutModal" class="modal-overlay" onclick="if(event.target===this)closeModal('logoutModal',this)">
    <div class="modal-box" style="max-width:380px;text-align:center">
        <div style="padding:32px 24px 20px">
            <div style="font-size:48px;margin-bottom:12px">&#128682;</div>
            <h3 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Confirm Logout</h3>
            <p style="font-size:12px;color:var(--text-dim);margin-bottom:20px">Are you sure you want to sign out?</p>
            <div class="btn-row" style="justify-content:center">
                <button type="button" onclick="closeModal('logoutModal',this)" class="btn btn-secondary">Cancel</button>
                <a href="api.php?action=logout" class="btn btn-danger">&#128682; Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
// Toast auto-dismiss
(function(){var t=document.getElementById('toastMsg');if(t)setTimeout(function(){t.style.animation='toastOut 0.25s ease-in forwards';setTimeout(function(){t.remove()},300)},3500);})();

// Theme toggle
(function(){var s=localStorage.getItem('cricket-theme')||'dark';document.documentElement.setAttribute('data-theme',s);})();
function toggleTheme(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('cricket-theme',n);}

// Sidebar mobile toggle
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('mobile-open');}
document.getElementById('content').addEventListener('click',function(){document.getElementById('sidebar').classList.remove('mobile-open');});

// Modal functions
function closeModal(id){document.getElementById(id).classList.remove('show');}
function openTeamEdit(id,name,shrt,logo){document.getElementById('editTeamId').value=id;document.getElementById('editTeamName').value=name;document.getElementById('editTeamShort').value=shrt;document.getElementById('editTeamLogo').value=logo;document.getElementById('teamEditModal').classList.add('show');}
function openPlayerEdit(id,tid,name,role,bat,bowl,photo,age,school,achievements){document.getElementById('editPlayerId').value=id;document.getElementById('editPlayerTeam').value=tid;document.getElementById('editPlayerName').value=name;document.getElementById('editPlayerRole').value=role;document.getElementById('editPlayerBatting').value=bat||'';document.getElementById('editPlayerBowling').value=bowl||'';document.getElementById('editPlayerAge').value=age||'';document.getElementById('editPlayerSchool').value=school||'';document.getElementById('editPlayerAchievements').value=achievements||'';document.getElementById('editPlayerPhoto').value=photo;document.getElementById('playerEditModal').classList.add('show');}
function openMatchEdit(id,title,logo,loc){document.getElementById('editMatchId').value=id;document.getElementById('editMatchTitle').value=title;document.getElementById('editMatchLogo').value=logo||'';document.getElementById('editMatchLocation').value=loc||'';document.getElementById('matchEditModal').classList.add('show');}
function openCompanyEdit(id,name,email,phone,active){document.getElementById('editCompanyId').value=id;document.getElementById('editCompanyName').value=name;document.getElementById('editCompanyEmail').value=email||'';document.getElementById('editCompanyPhone').value=phone||'';document.getElementById('editCompanyActive').value=active;document.getElementById('companyEditModal').classList.add('show');}
function openAccountSettings(){document.getElementById('accountModal').classList.add('show');}

// Escape key to close modals
document.addEventListener('keydown',function(e){if(e.key==='Escape'){['teamEditModal','playerEditModal','matchEditModal','companyEditModal','accountModal','logoutModal'].forEach(function(id){closeModal(id)});}});
function confirmLogout(){document.getElementById('logoutModal').classList.add('show');return false}
function delConfirm(msg,form){var d=document.createElement('div');d.className='dyn-modal';d.innerHTML='<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:18px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.50);text-align:center;max-width:380px;width:100%"><div style="padding:36px 28px 24px"><div style="width:64px;height:64px;border-radius:50%;background:rgba(239,68,68,0.10);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:32px">&#9888;</div><h3 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:8px">Confirm Delete</h3><p style="font-size:13px;color:var(--text-dim);margin-bottom:24px;line-height:1.5">'+msg+'</p><div style="display:flex;gap:10px;justify-content:center"><button type="button" onclick="this.closest(\'.dyn-modal\').remove()" class="btn btn-secondary">Cancel</button><button type="button" onclick="this.closest(\'.dyn-modal\').remove();form.submit()" class="btn btn-danger">&#128465; Yes, Delete</button></div></div></div>';d.style.cssText='position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px';d.addEventListener('click',function(e){if(e.target===this)this.remove()});document.body.appendChild(d);return false}

// Player search/filter
function filterPlayers(){
    var s=(document.getElementById('playerSearch')?.value||'').toLowerCase();
    var t=document.getElementById('playerTeamFilter')?.value||'';
    var r=document.getElementById('playerRoleFilter')?.value||'';
    document.querySelectorAll('table tbody tr[data-name]').forEach(function(tr){
        var nm=tr.getAttribute('data-name')||'',tm=tr.getAttribute('data-team')||'',rm=tr.getAttribute('data-role')||'';
        tr.style.display=(!s||nm.includes(s))&&(!t||tm===t)&&(!r||rm===r)?'':'none';
    });
}
</script>
</body>
</html>
