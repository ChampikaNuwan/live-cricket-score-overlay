<?php
/**
 * ============================================================================
 * live_output.php — Broadcast Control Panel
 * ============================================================================
 * Switches overlay.php views in real-time. Select match, click view tab.
 * Dark/light theme, glass-morphism, responsive, animated.
 * ============================================================================
 */
require_once __DIR__.'/config.php';
if(empty($_SESSION['user_id'])){header('Location:index.php?error=unauthorized');exit;}
$role=$_SESSION['role']??'';
if($role!=='scorer'&&$role!=='super_admin'&&$role!=='company_admin'){header('Location:index.php?error=unauthorized');exit;}
$liveCompanyId=$_SESSION['company_id']??null;
if($liveCompanyId&&$role==='scorer'){$dbTmp=getDB();$licStmt=$dbTmp->prepare("SELECT COUNT(*) FROM licenses WHERE company_id=? AND is_active=1 AND valid_from<=CURRENT_DATE AND valid_until>=CURRENT_DATE");$licStmt->execute([$liveCompanyId]);if((int)$licStmt->fetchColumn()===0){$dbTmp->exec("UPDATE licenses SET is_active=0 WHERE company_id=$liveCompanyId AND is_active=1 AND valid_until<CURRENT_DATE");header('Location:client_dashboard.php?error=license_expired');exit;}}
$user=['id'=>(int)$_SESSION['user_id'],'username'=>$_SESSION['username'],'role'=>$role];
$db=getDB();
$liveCid=$liveCompanyId??null;$cidF=$liveCid?"AND m.company_id=$liveCid":'';
$stmt=$db->query('SELECT m.id,m.match_title,m.status,m.match_format,m.total_overs,m.team_a_id,m.team_b_id,ta.short_name AS a_short,ta.name AS a_name,tb.short_name AS b_short,tb.name AS b_name FROM matches m JOIN teams ta ON m.team_a_id=ta.id JOIN teams tb ON m.team_b_id=tb.id WHERE 1=1 '.$cidF.' ORDER BY m.status DESC,m.created_at DESC LIMIT 30');
$matches=$stmt->fetchAll();
$dashboard=$role==='company_admin'?'client_dashboard.php':'super_admin.php';
?><!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Broadcast Control — CricketLive</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root,[data-theme="dark"]{--bg:#050914;--bg2:#0b1020;--bg-card:rgba(15,23,42,0.85);--bg-input:#1e293b;--bg-hover:rgba(30,41,59,0.60);--border:rgba(255,255,255,0.06);--border2:rgba(255,255,255,0.10);--text:#f1f5f9;--text-dim:#94a3b8;--text-mute:#64748b;--accent:#f97316;--accent2:#fb923c;--red:#ef4444;--green:#22c55e;--blue:#3b82f6;--shadow:0 4px 24px rgba(0,0,0,0.40);--sb-track:#0b1020;--sb-thumb:#1e293b}
[data-theme="light"]{--bg:#eef1f5;--bg2:#e2e6ec;--bg-card:#fff;--bg-input:#f1f5f9;--bg-hover:#e9eef3;--border:#dde1e7;--border2:#c8cdd5;--text:#0f172a;--text-dim:#475569;--text-mute:#94a3b8;--accent:#ea580c;--accent2:#c2410c;--red:#dc2626;--green:#16a34a;--blue:#2563eb;--shadow:0 1px 3px rgba(0,0,0,0.06);--sb-track:#eef1f5;--sb-thumb:#b0b8c4}
html,body{width:100%;height:100%;overflow:hidden;font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;transition:background 0.3s,color 0.3s}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:var(--sb-track)}::-webkit-scrollbar-thumb{background:var(--sb-thumb);border-radius:3px}

#app{display:flex;flex-direction:column;height:100vh}
#topbar{height:52px;flex-shrink:0;background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 16px;gap:10px}
#topbar .brand{font-weight:800;font-size:14px;color:var(--accent);display:flex;align-items:center;gap:8px;white-space:nowrap}
#topbar .brand span{color:var(--text)}
#topbar .top-right{display:flex;align-items:center;gap:8px;font-size:12px;flex-shrink:0}
#topbar a{color:var(--text-dim);text-decoration:none;transition:color 0.2s;font-size:11px;white-space:nowrap}
#topbar a:hover{color:var(--accent)}
#topbar .top-right .btn{padding:5px 8px;font-size:14px}
@media(max-width:600px){
    #topbar{padding:0 8px;height:48px}
    #topbar .brand{font-size:12px;gap:4px}
    #topbar a{font-size:10px}
    #topbar .top-right{gap:4px}
    #topbar .top-right .btn{padding:4px 6px;font-size:12px}
}
#content{flex:1;overflow-y:auto;padding:16px 20px}
@media(max-width:600px){#content{padding:10px}}

#panel{max-width:780px;margin:0 auto}
h1{font-size:18px;font-weight:800;color:var(--accent);text-align:center;margin-bottom:2px}
.sub{font-size:10px;color:var(--text-mute);text-align:center;margin-bottom:14px}

.card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px;box-shadow:var(--shadow);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);margin-bottom:14px}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.anim-up{animation:fadeUp 0.4s ease-out forwards}

select{width:100%;background:var(--bg-input);border:1px solid var(--border);border-radius:10px;padding:10px 14px;font-size:13px;color:var(--text);font-family:inherit;outline:none;transition:border-color 0.2s;cursor:pointer}
select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,0.10)}
select option{background:var(--bg2);color:var(--text)}

#conn{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:14px;font-size:10px;color:var(--text-mute)}
#conn .dot{width:8px;height:8px;border-radius:50%;background:var(--accent);transition:background 0.3s}
#conn .dot.off{background:var(--red)}

#cvBar{background:linear-gradient(90deg,rgba(249,115,22,0.15),rgba(249,115,22,0.05));border:1px solid rgba(249,115,22,0.25);border-radius:10px;padding:10px 14px;margin-bottom:14px;display:none;align-items:center;gap:10px;font-size:11px}
#cvBar .cvDot{width:9px;height:9px;border-radius:50%;background:var(--accent);flex-shrink:0;animation:cvPulse 1.5s ease-in-out infinite}
@keyframes cvPulse{0%,100%{box-shadow:0 0 4px var(--accent)}50%{box-shadow:0 0 16px var(--accent),0 0 24px rgba(249,115,22,0.4)}}
#cvBar .cvLabel{color:var(--text-dim);font-size:9px;text-transform:uppercase;letter-spacing:0.06em}
#cvBar .cvValue{color:var(--text);font-weight:700;font-size:12px}

.grpHdr{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.10em;color:var(--text-mute);margin:14px 0 6px 4px}
#tabs,.tabs{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:6px;margin-bottom:8px}
#tabs button,.tabs button{background:var(--bg-input);color:var(--text-dim);border:1px solid var(--border);border-radius:10px;padding:12px 8px;font-size:11px;font-weight:600;cursor:pointer;transition:all 0.25s;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:4px;white-space:nowrap;position:relative;overflow:hidden}
#tabs button::before,.tabs button::before{content:'';position:absolute;left:0;top:6px;bottom:6px;width:3px;background:transparent;border-radius:0 3px 3px 0;transition:background 0.25s}
#tabs button:hover,.tabs button:hover{background:var(--bg-hover);color:var(--text)}
#tabs button.active,.tabs button.active{background:linear-gradient(135deg,rgba(249,115,22,0.35),rgba(249,115,22,0.15));border-color:var(--accent);color:#fff;box-shadow:0 0 28px rgba(249,115,22,0.35),inset 0 1px 0 rgba(255,255,255,0.10);transform:scale(1.04);z-index:1}
#tabs button.active::before,.tabs button.active::before{background:var(--accent)}
#tabs button.active .icon,.tabs button.active .icon{text-shadow:0 0 10px rgba(249,115,22,0.7);transform:scale(1.15)}
#tabs button.active .label,.tabs button.active .label{color:#fff;font-weight:800}
#tabs button.active .hint,.tabs button.active .hint{color:var(--accent);font-weight:600}
#tabs button .icon,.tabs button .icon{font-size:18px}
#tabs button .label,.tabs button .label{font-size:9px;letter-spacing:0.03em;text-transform:uppercase}
#tabs button .hint,.tabs button .hint{font-size:8px;color:var(--text-mute);margin-top:-1px}
@media(max-width:600px){#tabs,.tabs{grid-template-columns:1fr 1fr}#tabs button,.tabs button{padding:10px 6px}}

#overlayLink{display:flex;align-items:center;justify-content:center;gap:6px;text-align:center;font-size:10px;color:var(--text-dim);margin-top:8px;text-decoration:none;padding:10px;border-radius:10px;background:var(--bg-card);border:1px solid var(--border)}
#overlayLink:hover{color:var(--text);border-color:var(--border2)}
#overlayLink span{color:var(--accent);font-weight:600}

.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%) translateY(30px);background:var(--accent);color:#fff;padding:10px 22px;border-radius:10px;font-size:12px;font-weight:600;opacity:0;transition:all 0.3s;z-index:300;pointer-events:none;box-shadow:0 4px 16px rgba(0,0,0,0.30)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.err{background:var(--red)}

.btn{display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;font-family:inherit;text-decoration:none;background:var(--bg-hover);color:var(--text);border:1px solid var(--border)}
.btn:hover{background:var(--bg-input)}
.btn-row{display:flex;gap:8px;flex-wrap:wrap}
.modal-overlay{display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px}
.modal-overlay.show{display:flex}
.modal-box{background:var(--bg-card);border:1px solid var(--border);border-radius:18px;width:100%;max-width:460px;box-shadow:0 25px 60px rgba(0,0,0,0.50);overflow:hidden;animation:modalPop 0.3s cubic-bezier(0.16,1,0.3,1) forwards}
@keyframes modalPop{from{opacity:0;transform:scale(0.92) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
</style>
</style>
</head>
<body>
<div id="app">
<header id="topbar">
    <div class="brand">&#127951; Cricket<span>Live</span></div>
    <div class="top-right">
        <button onclick="toggleTheme()" class="btn">&#127763;</button>
        <span style="color:var(--text-dim);font-size:11px"><?=htmlspecialchars($_SESSION['display_name']??$user['username'])?></span>
        <a href="<?=$dashboard?>">&#8592; Dashboard</a>
        <a href="api.php?action=logout" style="color:var(--red)" onclick="return confirmLogout()">Logout</a>
    </div>
</header>
<div id="content">
<div id="panel">
    <h1>&#128250; Broadcast Control</h1>
    <p class="sub">Select match &amp; choose output view for overlay</p>

    <div class="card anim-up" style="animation-delay:0s">
        <select id="matchSelect" onchange="onMatchChange()">
            <option value="">— Select Match —</option>
            <?php foreach($matches as $m):?>
            <option value="<?=$m['id']?>" data-a-id="<?=$m['team_a_id']?>" data-b-id="<?=$m['team_b_id']?>"><?=htmlspecialchars($m['match_title'])?> · <?=$m['status']?></option>
            <?php endforeach;?>
        </select>
    </div>

    <div id="conn"><span class="dot" id="connDot"></span><span id="connLabel">idle</span></div>

    <div id="cvBar"><span class="cvDot"></span><span class="cvLabel">Currently showing</span><span class="cvValue" id="cvValue">—</span></div>

    <div class="card anim-up" style="animation-delay:0.05s">
    <div class="grpHdr" style="margin-top:0">&#127951; Scorebug</div>
    <div id="tabs"><button data-view="scorebug" onclick="setView('scorebug')"><span class="icon">&#127951;</span><span class="label">Scorebug</span><span class="hint">Live scorebar</span></button></div>

    <div class="grpHdr">&#127942; 1st Innings</div>
    <div class="tabs">
        <button data-view="batting_1st" onclick="setView('batting_1st')"><span class="icon">&#127991;</span><span class="label">Batting</span><span class="hint">1st stats</span></button>
        <button data-view="bowling_1st" onclick="setView('bowling_1st')"><span class="icon">&#127952;</span><span class="label">Bowling</span><span class="hint">1st stats</span></button>
        <button data-view="xi_bat_1st" onclick="setView('xi_bat_1st')"><span class="icon">&#128101;</span><span class="label">XI (Bat)</span><span class="hint">Lineup</span></button>
        <button data-view="xi_bowl_1st" onclick="setView('xi_bowl_1st')"><span class="icon">&#128101;</span><span class="label">XI (Bowl)</span><span class="hint">Lineup</span></button>
    </div>
    <div class="grpHdr">&#127942; 2nd Innings</div>
    <div class="tabs">
        <button data-view="batting_2nd" onclick="setView('batting_2nd')"><span class="icon">&#127991;</span><span class="label">Batting</span><span class="hint">2nd stats</span></button>
        <button data-view="bowling_2nd" onclick="setView('bowling_2nd')"><span class="icon">&#127952;</span><span class="label">Bowling</span><span class="hint">2nd stats</span></button>
        <button data-view="xi_bat_2nd" onclick="setView('xi_bat_2nd')"><span class="icon">&#128101;</span><span class="label">XI (Bat)</span><span class="hint">Lineup</span></button>
        <button data-view="xi_bowl_2nd" onclick="setView('xi_bowl_2nd')"><span class="icon">&#128101;</span><span class="label">XI (Bowl)</span><span class="hint">Lineup</span></button>
    </div>
    <div class="grpHdr">&#9889; Super Over</div>
    <div class="tabs">
        <button data-view="batting_so" onclick="setView('batting_so')"><span class="icon">&#127991;</span><span class="label">Batting</span><span class="hint">SO stats</span></button>
        <button data-view="bowling_so" onclick="setView('bowling_so')"><span class="icon">&#127952;</span><span class="label">Bowling</span><span class="hint">SO stats</span></button>
        <button data-view="xi_bat_so" onclick="setView('xi_bat_so')"><span class="icon">&#128101;</span><span class="label">XI (Bat)</span><span class="hint">Lineup</span></button>
        <button data-view="xi_bowl_so" onclick="setView('xi_bowl_so')"><span class="icon">&#128101;</span><span class="label">XI (Bowl)</span><span class="hint">Lineup</span></button>
    </div>
    <div class="grpHdr">&#128202; Summary</div>
    <div class="tabs">
        <button data-view="summary_1st" onclick="setView('summary_1st')"><span class="icon">&#127942;</span><span class="label">1st Inns</span><span class="hint">Summary</span></button>
        <button data-view="summary_2nd" onclick="setView('summary_2nd')"><span class="icon">&#127942;</span><span class="label">2nd Inns</span><span class="hint">Summary</span></button>
        <button data-view="summary_so" onclick="setView('summary_so')"><span class="icon">&#9889;</span><span class="label">SO</span><span class="hint">Summary</span></button>
        <button data-view="summary" onclick="setView('summary')"><span class="icon">&#127942;</span><span class="label">Final</span><span class="hint">Result</span></button>
    </div>
    <div class="grpHdr">&#127936; Match</div>
    <div class="tabs">
        <button data-view="xi" onclick="setView('xi')"><span class="icon">&#128101;</span><span class="label">Both XI</span><span class="hint">Lineups</span></button>
        <button data-view="toss" onclick="setView('toss')"><span class="icon">&#127936;</span><span class="label">Toss</span><span class="hint">Coin toss</span></button>
        <button data-view="blank" onclick="setView('blank')"><span class="icon">&#11036;</span><span class="label">Blank</span><span class="hint">Clear</span></button>
    </div>

    <div class="grpHdr">&#128100; Player Profile</div>
    <div style="margin-bottom:8px">
        <select id="playerSelect" onchange="onPlayerChange()" style="margin-bottom:4px">
            <option value="">— Select Player —</option>
        </select>
        <button id="btnProfile" disabled onclick="showPlayerProfile()" style="width:100%;background:var(--bg-input);color:var(--text-dim);border:1px solid var(--border);border-radius:10px;padding:12px;font-size:12px;font-weight:700;cursor:pointer;transition:all 0.25s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px">
            <span>&#128100;</span> Show Player Profile
        </button>
    </div>
    </div>

    <a id="overlayLink" href="#" target="_blank">&#128250; Open Overlay: <span id="overlayUrl">—</span></a>
<?php include __DIR__.'/footer.php'; ?>
</div>
</div>
<div id="toast"></div>

<!-- Logout Modal -->
<div id="logoutModal" class="modal-overlay" onclick="if(event.target===this)closeModal('logoutModal')">
    <div class="modal-box" style="max-width:380px;text-align:center">
        <div style="padding:32px 24px 20px">
            <div style="font-size:48px;margin-bottom:12px">&#128682;</div>
            <h3 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Confirm Logout</h3>
            <p style="font-size:12px;color:var(--text-dim);margin-bottom:20px">Are you sure you want to sign out?</p>
            <div class="btn-row" style="justify-content:center">
                <button type="button" onclick="closeModal('logoutModal')" class="btn">Cancel</button>
                <a href="api.php?action=logout" class="btn" style="background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.2)">&#128682; Logout</a>
            </div>
        </div>
    </div>
</div>
</div>

<script>
var MATCH_ID=0,currentView='',sseCheck=null,cvPoll=null,TEAM_A_ID=0,TEAM_B_ID=0,ALL_PLAYERS=[],SEL_PLAYER_ID=0;
function $(id){return document.getElementById(id)}

function onMatchChange(){
    var v=$('matchSelect').value;
    if(!v){MATCH_ID=0;$('overlayUrl').textContent='—';$('cvBar').style.display='none';return}
    MATCH_ID=parseInt(v);
    var opt=$('matchSelect').selectedOptions[0];
    TEAM_A_ID=parseInt(opt.getAttribute('data-a-id')||0);
    TEAM_B_ID=parseInt(opt.getAttribute('data-b-id')||0);
    $('overlayUrl').textContent='overlay.php?match_id='+MATCH_ID;
    $('overlayLink').href='overlay.php?match_id='+MATCH_ID;
    updateUIForView('scorebug');$('cvValue').textContent='loading...';
    loadPlayers();fetchCurrentView();startSSECheck();startCVPoll();
}
function setView(view){
    if(!MATCH_ID)return toast('Select a match first','err');
    updateUIForView(view);currentView=view;
    fetch('api.php?action=set_output_view',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({match_id:MATCH_ID,view:view})})
    .then(function(r){return r.json()}).then(function(d){
        if(d.success)toast('Showing: '+view.toUpperCase().replace(/_/g,' '));
        else{toast(d.error||'Failed','err');fetchCurrentView()}
    }).catch(function(){toast('Connection error','err');fetchCurrentView()});
}
function highlightButton(view){
    document.querySelectorAll('#tabs button, .tabs button').forEach(function(b){b.classList.remove('active')});
    var btn=document.querySelector('#tabs button[data-view="'+view+'"], .tabs button[data-view="'+view+'"]');
    if(btn)btn.classList.add('active');
}
function checkConn(){
    if(!MATCH_ID)return;$('connDot').className='';$('connLabel').textContent='syncing...';
    fetch('api.php?action=get_overlay_data&match_id='+MATCH_ID).then(function(r){return r.json()}).then(function(d){
        if(d&&!d.error){$('connDot').className='';$('connLabel').textContent='live'}else{$('connDot').className='off';$('connLabel').textContent='no data'}
    }).catch(function(){$('connDot').className='off';$('connLabel').textContent='offline'});
}
function loadPlayers(){
    if(!MATCH_ID||!TEAM_A_ID){ $('playerSelect').innerHTML='<option value="">— Select Player —</option>';return }
    ALL_PLAYERS=[];var loaded=0;
    function fill(){
        loaded++;if(loaded<2)return;
        var html='<option value="">— Select Player —</option>';
        ALL_PLAYERS.sort(function(a,b){return a.team_short.localeCompare(b.team_short)||a.name.localeCompare(b.name)});
        ALL_PLAYERS.forEach(function(p){
            html+='<option value="'+p.id+'" data-team="'+p.team_short+'">'+p.name+' ['+p.team_short+'] ('+(p.role||'player')+')</option>';
        });
        $('playerSelect').innerHTML=html;SEL_PLAYER_ID=0;$('btnProfile').disabled=true;
        $('btnProfile').style.background='';$('btnProfile').style.color='';
    }
    fetch('api.php?action=get_players&team_id='+TEAM_A_ID+'&match_id='+MATCH_ID)
    .then(function(r){return r.json()}).then(function(d){
        if(d.players)d.players.forEach(function(p){ALL_PLAYERS.push(p)});
        fill();
    }).catch(function(){fill()});
    fetch('api.php?action=get_players&team_id='+TEAM_B_ID+'&match_id='+MATCH_ID)
    .then(function(r){return r.json()}).then(function(d){
        if(d.players)d.players.forEach(function(p){ALL_PLAYERS.push(p)});
        fill();
    }).catch(function(){fill()});
}
function onPlayerChange(){
    var v=$('playerSelect').value;SEL_PLAYER_ID=v?parseInt(v):0;
    $('btnProfile').disabled=!SEL_PLAYER_ID;
    $('btnProfile').style.background=SEL_PLAYER_ID?'linear-gradient(135deg,rgba(249,115,22,0.35),rgba(249,115,22,0.15))':'';
    $('btnProfile').style.color=SEL_PLAYER_ID?'#fff':'';
    $('btnProfile').style.borderColor=SEL_PLAYER_ID?'var(--accent)':'';
    $('btnProfile').style.boxShadow=SEL_PLAYER_ID?'0 0 28px rgba(249,115,22,0.35)':'';
}
function showPlayerProfile(){
    if(!MATCH_ID||!SEL_PLAYER_ID)return toast('Select a player first','err');
    fetch('api.php?action=set_output_view',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({match_id:MATCH_ID,view:'player_profile',player_id:SEL_PLAYER_ID})})
    .then(function(r){return r.json()}).then(function(d){
        if(d.success){updateUIForView('player_profile');currentView='player_profile';toast('Showing: '+$('playerSelect').selectedOptions[0].text.split(' [')[0])}
        else{toast(d.error||'Failed','err')}
    }).catch(function(){toast('Connection error','err')});
}
function fetchCurrentView(){
    if(!MATCH_ID)return;
    fetch('api.php?action=get_output_view&match_id='+MATCH_ID).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()}).then(function(d){
        currentView=(d&&d.view)?d.view:'scorebug';updateUIForView(currentView);
    }).catch(function(e){console.log('fetchCurrentView failed: '+e.message);});
}
function updateUIForView(view){if(!view)return;highlightButton(view);$('cvValue').textContent=view.toUpperCase().replace(/_/g,' ');$('cvBar').style.display='flex';}
function startCVPoll(){if(cvPoll)clearInterval(cvPoll);cvPoll=setInterval(fetchCurrentView,4000);}
function startSSECheck(){checkConn();if(sseCheck)clearInterval(sseCheck);sseCheck=setInterval(checkConn,5000);}
var toastTmr=null;function toast(msg,type){var t=$('toast');t.textContent=msg;t.className='toast'+(type==='err'?' err':'');t.classList.add('show');if(toastTmr)clearTimeout(toastTmr);toastTmr=setTimeout(function(){t.classList.remove('show')},2000);}
(function(){var s=localStorage.getItem('cricket-theme')||'dark';document.documentElement.setAttribute('data-theme',s);})();
function toggleTheme(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);localStorage.setItem('cricket-theme',n);}
function confirmLogout(){document.getElementById('logoutModal').classList.add('show');return false}
function closeModal(id){document.getElementById(id).classList.remove('show')}
(function(){var urlP=new URLSearchParams(window.location.search),mid=urlP.get('match_id');if(mid){$('matchSelect').value=mid;onMatchChange();return}var opts=$('matchSelect').options;if(opts.length===2){$('matchSelect').value=opts[1].value;onMatchChange()}})();
</script>
</body>
</html>
