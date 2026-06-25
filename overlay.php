<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__.'/config.php';
$mid=(int)($_GET['match_id']??0);
if(!$mid){header('Content-Type:text/html;charset=utf-8');echo'<!DOCTYPE html><html><body style="background:transparent;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;"><div style="background:rgba(0,0,0,0.6);padding:32px;border-radius:16px;text-align:center;"><h1>CricketLive</h1><p>Add ?match_id=X</p></div></body></html>';exit;}
$db=getDB();
$s=$db->prepare("SELECT m.*,ta.name AS a_name,ta.short_name AS a_short,ta.logo_path AS a_logo,tb.name AS b_name,tb.short_name AS b_short,tb.logo_path AS b_logo FROM matches m JOIN teams ta ON m.team_a_id=ta.id JOIN teams tb ON m.team_b_id=tb.id WHERE m.id=?");
$s->execute([$mid]);$m=$s->fetch();
if(!$m){echo'Match not found';exit;}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>LIVE — <?php echo htmlspecialchars($m['match_title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Teko:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:100vw;height:100vh;overflow:hidden;font-family:'Inter',sans-serif;color:#fff;-webkit-font-smoothing:antialiased;background:transparent!important}

/* ── WRAPPER ── */
#wrap{position:fixed;bottom:clamp(6px,2vh,20px);left:0;right:0;z-index:9999;pointer-events:none;display:flex;justify-content:center;perspective:1200px}
#bar{pointer-events:auto;display:flex;flex-direction:row;width:98vw;max-width:1750px;min-width:0;height:clamp(70px,11vh,120px);border-radius:32px;overflow:visible;background:linear-gradient(180deg,rgba(255,255,255,0.08) 0%,rgba(0,0,0,0.55) 6%,rgba(0,0,0,0.55) 94%,rgba(0,0,0,0.70) 100%);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.18);box-shadow:0 8px 40px rgba(0,0,0,0.60),inset 0 1px 0 rgba(255,255,255,0.12),inset 0 -2px 6px rgba(0,0,0,0.25);transform:perspective(1000px) rotateX(0.8deg) translateZ(0);transform-style:preserve-3d}
.bgo{background:#FF9800}.bgb{background:#0288D1}.bgg{background:#E0E0E0}

/* ── 3D PANEL DEPTH ── */
#bar>div{position:relative;z-index:1;transform:rotateX(0.8deg);transform-origin:center bottom;backface-visibility:hidden}
#bar>div::after{content:'';position:absolute;bottom:0;left:0;right:0;height:50%;pointer-events:none;background:linear-gradient(to top,rgba(0,0,0,0.15) 0%,transparent 100%);z-index:-1}
#bar>div:first-child::after{border-radius:0 0 0 32px}
#bar{perspective:1200px;transform-style:preserve-3d}
#crr{box-shadow:inset 0 1px 0 rgba(255,255,255,0.15),inset 0 -1px 0 rgba(0,0,0,0.10)}

/* ── 1. TEAM (22%) ── */
#team{flex:0 0 24%;min-width:0;display:flex;align-items:center;padding:0 clamp(6px,1vw,10px);gap:clamp(4px,0.6vw,8px);border-right:1px solid rgba(255,255,255,0.18);position:relative;border-radius:32px 0 0 32px;overflow:hidden}
#tLogo{width:clamp(30px,5.5vh,58px);height:clamp(30px,5.5vh,58px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.15);border:2px solid rgba(255,215,64,0.45)}
#tLogo img{width:100%;height:100%;object-fit:contain}
#tInit{font-size:clamp(8px,1.4vh,14px);font-weight:800;color:rgba(255,255,255,0.55)}
#tInfo{flex:1;min-width:0;overflow:hidden}
#tName{font-size:clamp(10px,2.2vh,28px);font-weight:800;letter-spacing:0.04em;line-height:1.15;text-shadow:1px 1px 3px rgba(0,0,0,0.25);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#tVs{display:block;font-size:clamp(6px,1.2vh,12px);font-weight:500;opacity:0.90;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;width:100%}
.so-badge{display:inline-block;background:linear-gradient(135deg,#FF9800,#F44336);color:#fff;padding:1px 6px;border-radius:4px;font-size:clamp(7px,1.2vh,11px);font-weight:800;letter-spacing:0.08em;text-transform:uppercase;animation:soPulse 1.2s infinite}
@keyframes soPulse{0%,100%{box-shadow:0 0 4px rgba(255,152,0,0.4)}50%{box-shadow:0 0 12px rgba(255,152,0,0.8)}}

/* Chase bar auto-pulse every 15s */
#chBar.pulse{animation:chAutoPulse 15s ease-in-out infinite}
@keyframes chAutoPulse{0%,85%,100%{transform:scale(1);box-shadow:0 4px 24px rgba(0,0,0,0.45)}5%{transform:scale(1.04);box-shadow:0 4px 36px rgba(255,152,0,0.35)}10%{transform:scale(1);box-shadow:0 4px 24px rgba(0,0,0,0.45)}}
#tScoreWrap{display:flex;align-items:baseline;gap:1px;flex-shrink:0}
#tScore{font-size:clamp(14px,3.8vh,42px);font-weight:900;line-height:1;letter-spacing:-0.02em;text-shadow:0 2px 4px rgba(0,0,0,0.30),0 4px 12px rgba(0,0,0,0.15)}
#tSep{font-size:clamp(8px,1.8vh,20px);font-weight:300;opacity:0.35}
#tWkts{font-size:clamp(10px,2.4vh,24px);font-weight:300;opacity:0.50}
#tOvers{font-size:clamp(6px,1.1vh,11px);font-weight:600;opacity:0.85;white-space:nowrap;flex-shrink:0;margin-left:clamp(2px,0.3vw,4px)}

/* ── 2/3. BATSMEN (15% each) ── */
.pl{flex:0 0 14%;min-width:0;display:flex;align-items:flex-end;padding:0 0 0 4px;position:relative;border-right:1px solid rgba(255,255,255,0.16);overflow:visible;gap:clamp(2px,0.3vw,6px)}
.pl .pimg{height:clamp(72px,13vh,130px);width:auto;max-width:clamp(50px,6vw,120px);flex-shrink:0;position:relative;z-index:5;margin-bottom:0}
.pl .pimg img{height:100%;width:auto;max-width:none;object-fit:cover;object-position:top center}
.pl .pimg .pini{height:100%;min-width:clamp(40px,5vw,100px);display:flex;align-items:center;justify-content:center;font-size:clamp(14px,3vh,28px);font-weight:800;color:rgba(255,255,255,0.18);background:rgba(255,255,255,0.04)}
.pl .info{flex:1;min-width:0;z-index:10;margin-bottom:clamp(4px,1.2vh,14px);margin-left:clamp(4px,0.6vw,10px)}
.pl .nm{display:flex;flex-direction:column;line-height:1.15;text-shadow:1px 1px 2px rgba(0,0,0,0.22)}
.pl .nml{font-size:clamp(8px,1.6vh,18px);font-weight:700;letter-spacing:0.02em;white-space:nowrap}
.pl .wk-label{font-size:clamp(6px,1vh,11px);font-weight:800;color:#66bb6a;margin-left:2px;display:inline;text-shadow:0 0 8px rgba(102,187,106,0.30)}
.pl .pr{font-size:clamp(14px,3vh,30px);font-weight:800;line-height:1;margin-top:2px;text-shadow:1px 1px 2px rgba(0,0,0,0.22)}
.pl .pb{font-size:clamp(7px,1.4vh,14px);font-weight:500;opacity:0.82;margin-left:3px}
.pl+.pl{border-right:none}
.sdot{display:inline-block;flex-shrink:0;width:clamp(7px,1.3vh,13px);height:clamp(7px,1.3vh,13px);margin-left:2px;vertical-align:middle;animation:sPulse .75s infinite}
.sdot svg{width:100%;height:100%;fill:#FFD740;filter:drop-shadow(0 0 2px rgba(255,215,64,0.5))}
@keyframes sPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.55)}}
.pl .grad{position:absolute;bottom:0;left:0;width:100%;height:clamp(5px,1vh,12px);z-index:1}
.pl .grad.o{background:linear-gradient(to top,#FF9800,transparent)}.pl .grad.b{background:linear-gradient(to top,#0288D1,transparent)}

/* ── 4. CRR (10%) ── */
#crr{flex:0 0 8%;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#1a1a2e;overflow:hidden}
#crrL{font-size:clamp(7px,1.3vh,14px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em}
#crrV{font-size:clamp(18px,3.5vh,36px);font-weight:800;line-height:1}

/* ── 5. BOWLING (flex-1 = ~38%) ── */
#bWrap{flex:1;min-width:0;display:flex;align-items:stretch;padding:0 8px 0 0;position:relative;border-radius:0 32px 32px 0}
#bWrap>.pl{flex:0 0 45%;min-width:0;border-right:none;display:flex;align-items:flex-end;padding:0 0 0 6px}
.bdot{display:inline-block;flex-shrink:0;width:clamp(7px,1.4vh,14px);height:clamp(7px,1.4vh,14px);border-radius:50%;border:2px solid #fff;margin-left:3px;vertical-align:middle;animation:bPulse .7s infinite;box-shadow:0 0 5px rgba(255,255,255,0.45)}
@keyframes bPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.5)}}
#bSep{width:1px;background:rgba(255,255,255,0.18);align-self:center;height:55%;flex-shrink:0}

/* ── 6. TRACKER + OPP ── */
#tCol{flex:1;min-width:0;display:flex;align-items:center;justify-content:space-between;padding:0 clamp(2px,0.3vw,6px);gap:clamp(2px,0.4vw,6px)}
#dots{display:flex;gap:clamp(3px,0.5vw,6px);flex-shrink:0}
.dot{width:clamp(18px,2.8vh,32px);height:clamp(18px,2.8vh,32px);border-radius:8px;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.26);display:flex;align-items:center;justify-content:center;font-size:clamp(7px,1.2vh,13px);font-weight:700;color:#fff;flex-shrink:0}
.dot.e{opacity:0.16;border-style:dashed}.dot.w{background:rgba(220,38,38,0.50);border-color:rgba(255,80,80,0.40)}.dot.f{background:rgba(0,180,70,0.45);border-color:rgba(0,220,100,0.35)}.dot.s{background:rgba(140,40,160,0.45);border-color:rgba(180,70,200,0.35)}.dot.x{background:rgba(200,150,20,0.33);border-color:rgba(220,170,30,0.30)}
#opp{width:clamp(34px,6vh,62px);height:clamp(34px,6vh,62px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.30)}
#opp img{width:100%;height:100%;object-fit:contain}
#oppInit{font-size:clamp(8px,1.4vh,14px);font-weight:800;color:rgba(255,255,255,0.55)}

/* ── CONNECTION ── */
#cd,#recon{display:none!important}
.hidden{display:none!important}

/* ── CHASE BAR ── */
#chWrap{position:fixed;bottom:clamp(82px,14vh,152px);left:0;right:0;z-index:9998;pointer-events:none;display:none;justify-content:center}
#chWrap.on{display:flex}
#chBar{display:flex;align-items:center;gap:clamp(8px,1vw,14px);height:clamp(38px,5vh,52px);padding:0 clamp(16px,2vw,28px);border-radius:14px;background:rgba(28,34,48,0.82);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.10);box-shadow:0 4px 24px rgba(0,0,0,0.45)}
#chIcon{width:clamp(24px,3vh,34px);height:clamp(24px,3vh,34px);border-radius:50%;background:rgba(255,152,0,0.15);border:2px solid rgba(255,152,0,0.40);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:clamp(12px,1.6vh,18px)}
#chText{font-family:'Teko',sans-serif;font-size:clamp(16px,2.2vh,26px);font-weight:700;letter-spacing:0.04em;text-transform:uppercase;white-space:nowrap;text-shadow:1px 1px 2px rgba(0,0,0,0.25);color:#fff}
#chText .cht{color:#FF9800;font-weight:900}
#chText .chb{color:#64B5F6;font-weight:700}
#chText .chw{color:#4ade80;font-weight:900}
@keyframes chAutoPulse{0%,85%,100%{transform:scale(1);box-shadow:0 4px 24px rgba(0,0,0,0.45)}5%{transform:scale(1.04);box-shadow:0 4px 36px rgba(255,152,0,0.35)}10%{transform:scale(1);box-shadow:0 4px 24px rgba(0,0,0,0.45)}}

/* ── EVENT OVERLAY ── */
#evOvl{display:none;position:absolute;top:0;left:0;width:100%;height:100%;z-index:100;pointer-events:none;align-items:center;justify-content:center;border-radius:inherit}
#evOvl.show{display:flex}
#evOvl.four{display:flex;background:#00C853}#evOvl.six{display:flex;background:#FFB300}#evOvl.wicket{display:flex;background:#E53935}#evOvl.maiden{display:flex;background:#1a2332;border:3px solid #42a5f5}#evOvl.wide{display:flex;background:#FF8F00}#evOvl.noball{display:flex;background:#FF8F00}#evOvl.win{display:flex;background:linear-gradient(135deg,#FF9800,#F57C00)}#evOvl.toss{display:flex;background:linear-gradient(135deg,#1a2332,#263238);border:3px solid #FFB300}
#evTxt{font-weight:900;font-size:clamp(26px,5vh,54px);letter-spacing:0.03em;text-shadow:0 3px 12px rgba(0,0,0,0.40);text-transform:uppercase;color:#fff}
#evOvl.six #evTxt,#evOvl.wide #evTxt,#evOvl.noball #evTxt{color:#1a1a2e}
#evOvl.maiden #evTxt{color:#90caf9;font-size:clamp(18px,3vh,34px)}
#evOvl.win #evTxt{font-size:clamp(28px,5.5vh,60px);text-shadow:0 4px 20px rgba(255,152,0,0.50)}

.glass-panel{background:rgba(40,48,60,0.75);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1)}
.team-logo-panel{background:linear-gradient(135deg,rgba(255,152,0,0.9) 0%,rgba(230,138,0,0.9) 100%);box-shadow:0 10px 25px rgba(255,152,0,0.3)}
.font-display{font-family:'Teko',sans-serif}
.txt-pri{color:#FF9800}.txt-sec{color:#0288D1}
.bg-sec{background:#0288D1}.bg-sec-80{background:rgba(2,136,209,0.8)}.bg-sec-30{background:rgba(2,136,209,0.3)}
.bg-sec-25{background:rgba(2,136,209,0.25)}.bg-sec-20{background:rgba(2,136,209,0.2)}
.bdr-sec-50{border-color:rgba(2,136,209,0.5)}.bdr-sec-30{border-color:rgba(2,136,209,0.3)}
/* Tailwind-free color classes */
.c-white{color:#fff}.c-white-60{color:rgba(255,255,255,0.6)}.c-white-40{color:rgba(255,255,255,0.4)}
.c-gray-200{color:#e5e7eb}.c-gray-300{color:#d1d5db}.c-gray-400{color:#9ca3af}.c-gray-500{color:#6b7280}.c-gray-600{color:#4b5563}
.c-red-400{color:#f87171}.c-green-400{color:#4ade80}.c-yellow-400{color:#fbbf24}.c-blue-400{color:#60a5fa}.c-purple-400{color:#c084fc}
.bg-white-5{background:rgba(255,255,255,0.05)}.bg-white-10{background:rgba(255,255,255,0.1)}
.b-white-5{border-color:rgba(255,255,255,0.05)}.b-white-10{border-color:rgba(255,255,255,0.1)}.b-white-20{border-color:rgba(255,255,255,0.2)}
.op-40{opacity:0.4}.op-50{opacity:0.5}
.brd-tag-blue{background:rgba(30,64,175,0.5);color:#60a5fa;border:1px solid rgba(59,130,246,0.3)}
.brd-tag-purple{background:rgba(88,28,135,0.5);color:#c084fc;border:1px solid rgba(168,85,247,0.3)}
.brd-tag-green{background:rgba(20,83,45,0.5);color:#4ade80;border:1px solid rgba(74,222,128,0.3)}
.sdw{box-shadow:0 4px 12px rgba(0,0,0,0.3)}.sdw-lg{box-shadow:0 10px 15px rgba(0,0,0,0.4)}.sdw-xl{box-shadow:0 20px 25px rgba(0,0,0,0.5)}.sdw-2xl{box-shadow:0 25px 50px rgba(0,0,0,0.6)}
/* ── MODERN STAT CARDS ── */
.mc-hdr{display:flex;align-items:center;gap:clamp(14px,2vw,24px);margin-bottom:clamp(14px,2vh,22px);padding-bottom:clamp(10px,1.5vh,16px);border-bottom:1px solid rgba(255,255,255,0.08)}
.mc-logo{width:clamp(50px,8vh,80px);height:clamp(50px,8vh,80px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.04);border:2px solid rgba(255,255,255,0.15)}
.mc-logo img{width:100%;height:100%;object-fit:contain}
.mc-logo i{font-size:clamp(18px,2.5vh,30px);font-weight:800;color:rgba(255,255,255,0.25);font-style:normal}
.mc-title{font-size:clamp(16px,2.5vh,28px);font-weight:800;text-transform:uppercase;letter-spacing:0.05em;line-height:1.15}
.mc-sub{font-size:clamp(10px,1.4vh,14px);font-weight:500;opacity:0.55;margin-top:2px}
.mc-score{font-size:clamp(24px,3.8vh,44px);font-weight:900;letter-spacing:-0.02em;flex-shrink:0;margin-left:auto}
.mc-score small{font-size:50%;font-weight:300;opacity:0.35}
.mc-tbl{width:100%;border-collapse:collapse}
.mc-tbl th{text-align:left;font-size:clamp(8px,1vh,11px);font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.22);padding:clamp(5px,0.8vh,8px) clamp(6px,0.8vw,12px);border-bottom:1px solid rgba(255,255,255,0.05)}
.mc-tbl td{font-size:clamp(12px,1.8vh,18px);padding:clamp(7px,1vh,12px) clamp(6px,0.8vw,12px);border-bottom:1px solid rgba(255,255,255,0.025);color:#d1d5db}
.mc-tbl tr:last-child td{border-bottom:none}
.mc-tbl .hr td{background:rgba(2,136,209,0.12);color:#fff;font-weight:600}
.mc-tbl .dr td{color:#6b7280}
.mc-tbl .r{color:#f87171;font-weight:600}.mc-tbl .g{color:#4ade80;font-weight:600}
.mc-tbl .num{text-align:center;color:rgba(255,255,255,0.18);font-weight:700;width:clamp(22px,2.5vw,32px)}
.mc-tbl .big{font-weight:800;font-size:clamp(14px,2.2vh,24px);text-align:center}
.mc-tbl .st{font-size:clamp(9px,1.2vh,13px);font-weight:600;text-transform:uppercase}
.mc-foot{display:flex;justify-content:space-between;align-items:center;margin-top:clamp(10px,1.5vh,18px);padding-top:clamp(8px,1.2vh,14px);border-top:1px solid rgba(255,255,255,0.08);flex-wrap:wrap;gap:clamp(8px,1vw,16px);font-size:clamp(11px,1.5vh,15px);font-weight:600;color:rgba(255,255,255,0.5)}
.mc-foot span{color:#fff}
.mc-pill{background:rgba(2,136,209,0.2);border:1px solid rgba(2,136,209,0.3);padding:clamp(4px,0.6vh,8px) clamp(14px,2vw,24px);border-radius:8px;font-family:'Teko',sans-serif;font-size:clamp(24px,3.5vh,40px);font-weight:700;color:#fff}
.mc-pill small{font-size:45%;font-weight:400;opacity:0.5}
.mc-tag{display:inline-block;font-size:clamp(7px,0.9vh,10px);font-weight:600;text-transform:uppercase;padding:1px 5px;border-radius:3px;letter-spacing:0.05em;margin-left:4px}
.mc-tag.bat{background:rgba(74,222,128,0.12);color:#4ade80}.mc-tag.bwl{background:rgba(96,165,250,0.12);color:#60a5fa}
.mc-tag.all{background:rgba(192,132,252,0.12);color:#c084fc}.mc-tag.wk{background:rgba(251,191,36,0.12);color:#fbbf24}
.mc-photo{width:clamp(34px,4.5vh,48px);height:clamp(34px,4.5vh,48px);border-radius:50%;overflow:hidden;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);vertical-align:middle;margin-right:clamp(6px,0.8vw,10px)}
.mc-photo img{width:100%;height:100%;object-fit:cover}
.mc-photo i{font-size:clamp(11px,1.5vh,16px);font-weight:800;color:rgba(255,255,255,0.18);font-style:normal}
/* ── FULL VIEWS ── */
.fv{display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9990;align-items:center;justify-content:center;padding:clamp(16px,3vh,40px);perspective:1400px}
.fv.active{display:flex}
.fvc{width:100%;max-width:min(96vw,1640px);max-height:100%;overflow-y:auto;border-radius:18px;background:rgba(8,12,20,0.88);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.08);box-shadow:0 6px 40px rgba(0,0,0,0.50);padding:clamp(16px,2.5vh,32px);backface-visibility:hidden}
.fvc::-webkit-scrollbar{width:3px}.fvc::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.10);border-radius:2px}
.fvh{display:flex;align-items:center;justify-content:space-between;margin-bottom:clamp(10px,2vh,24px);padding-bottom:clamp(8px,1.2vh,14px);border-bottom:2px solid rgba(255,255,255,0.06)}
.fvh h2{font-size:clamp(16px,2.5vh,28px);font-weight:800;letter-spacing:.03em}.fvh .fvs{font-size:clamp(10px,1.6vh,16px);font-weight:600;opacity:.70}
.fvt{width:100%;border-collapse:collapse}
.fvt th{text-align:left;font-size:clamp(9px,1.2vh,13px);font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#8899aa;padding:clamp(6px,1vh,12px) clamp(6px,1vw,14px);border-bottom:2px solid rgba(255,255,255,0.08)}
.fvt td{font-size:clamp(12px,1.8vh,19px);font-weight:500;padding:clamp(8px,1.2vh,14px) clamp(6px,1vw,14px);border-bottom:1px solid rgba(255,255,255,0.04);color:#d1d5db}
.fvt tr:last-child td{border-bottom:none}.fvt .hr td{background:rgba(2,136,209,0.15);font-weight:700;color:#fff}.fvt .dr td{color:#6b7280}
.fvt .b{font-weight:800;color:#fff}.fvt .g{color:#4ade80;font-weight:700}.fvt .r{color:#f87171;font-weight:600}
.rt{display:inline-block;font-size:clamp(6px,0.8vh,9px);font-weight:600;padding:0 4px;border-radius:2px;letter-spacing:.03em;margin-left:3px}
.rt.bat{background:rgba(0,180,70,0.12);color:#4ade80}.rt.bwl{background:rgba(59,130,246,0.12);color:#60a5fa}.rt.all{background:rgba(168,85,247,0.12);color:#a78bfa}.rt.wk{background:rgba(251,191,36,0.12);color:#fbbf24}
.fvxi{display:grid;grid-template-columns:1fr 1fr;gap:clamp(20px,4vw,60px)}
.fvxi h3{font-size:clamp(14px,2.2vh,22px);font-weight:800;margin-bottom:clamp(10px,2vh,20px);letter-spacing:.08em;text-transform:uppercase;padding-bottom:clamp(6px,1vh,12px)}
.xr{display:flex;align-items:center;gap:clamp(10px,1.2vw,18px);padding:clamp(6px,1vh,12px) 0;border-bottom:1px solid rgba(255,255,255,0.03);opacity:0}.xr:last-child{border-bottom:none}
.xn{font-size:clamp(11px,1.6vh,18px);font-weight:700;color:rgba(255,255,255,0.25);width:clamp(22px,2.5vw,32px);text-align:center;flex-shrink:0}
.xpi{width:clamp(38px,5.5vh,56px);height:clamp(38px,5.5vh,56px);border-radius:50%;overflow:hidden;flex-shrink:0;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,0.15)}
.xpi img{width:100%;height:100%;object-fit:cover}
.xpi .xini{font-size:clamp(13px,2vh,22px);font-weight:800;color:rgba(255,255,255,0.28)}
.xd{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0}
.xp{font-size:clamp(12px,1.8vh,19px);font-weight:700;line-height:1.2}
.xrl{font-size:clamp(9px,1.3vh,13px);font-weight:500;opacity:0.70;display:flex;align-items:center;gap:4px}
.xst{font-size:clamp(8px,1.1vh,11px);font-weight:400;opacity:0.50;margin-top:1px}
.xic{font-size:clamp(14px,2vh,22px);flex-shrink:0;opacity:0.70}
.fvsm{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.fvsc{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);border-radius:12px;padding:16px}
.fvsc h3{font-size:12px;font-weight:700;margin-bottom:4px;letter-spacing:.02em}
.fvsc .big{font-size:clamp(30px,5vh,56px);font-weight:900;letter-spacing:-.02em}.fvsc .big small{font-size:clamp(14px,2vh,24px);font-weight:400;opacity:.40}
.fvsc .det{font-size:10px;color:#8899aa;margin-top:2px}
.fvres{background:linear-gradient(135deg,rgba(249,115,22,0.10),rgba(234,88,12,0.06));border:1px solid rgba(249,115,22,0.18);border-radius:12px;padding:16px;text-align:center}
.fvres .win{font-size:18px;font-weight:800;color:#f97316}.fvres .mar{font-size:12px;color:#d1d5db;margin-top:2px}

/* ── BROADCAST STAT CARDS (Batting / Bowling) ── */
.brd-hdr{display:flex;align-items:center;gap:clamp(14px,2vw,28px);margin-bottom:clamp(14px,2.2vh,24px);padding-bottom:clamp(10px,1.5vh,16px);border-bottom:2px solid rgba(255,255,255,0.06)}
.brd-hdr .brd-logo{width:clamp(58px,9.5vh,100px);height:clamp(58px,9.5vh,100px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.20);box-shadow:0 4px 24px rgba(0,0,0,0.40)}
.brd-hdr .brd-logo img{width:100%;height:100%;object-fit:contain}
.brd-hdr .brd-logo .brd-logoi{font-size:clamp(15px,2.4vh,28px);font-weight:800;color:rgba(255,255,255,0.35);letter-spacing:0.06em}
.brd-hdr .brd-info{flex:1;min-width:0}
.brd-hdr .brd-info .brd-title{font-size:clamp(18px,3vh,34px);font-weight:800;text-transform:uppercase;letter-spacing:0.06em;line-height:1.15;text-shadow:0 2px 12px rgba(0,0,0,0.50);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.brd-hdr .brd-info .brd-meta{font-size:clamp(11px,1.6vh,16px);font-weight:600;opacity:0.70;margin-top:3px;letter-spacing:0.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.brd-score-pill{display:flex;align-items:baseline;gap:2px;font-size:clamp(28px,4.5vh,52px);font-weight:900;letter-spacing:-0.03em;line-height:1;flex-shrink:0;text-shadow:0 2px 16px rgba(0,0,0,0.45)}
.brd-score-pill small{font-size:clamp(14px,2.2vh,22px);font-weight:300;opacity:0.30;margin:0 2px}
/* Col header bar */
.brd-col-bar{display:flex;align-items:center;gap:clamp(8px,1vw,14px);padding:clamp(3px,0.5vh,6px) clamp(8px,1vw,16px) clamp(4px,0.6vh,8px);margin-bottom:2px}
.brd-col-bar span{font-size:clamp(7px,1vh,10px);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.22)}
.brd-col-bar .bcb-num{width:clamp(20px,2vw,28px);text-align:center;flex-shrink:0}
.brd-col-bar .bcb-pic{width:clamp(52px,6.5vw,80px);flex-shrink:0}
.brd-col-bar .bcb-name{flex:1;text-align:left;min-width:0}
.brd-col-bar .bcb-s{min-width:clamp(30px,3.2vw,44px);text-align:center;flex-shrink:0}
.brd-col-bar .bcb-st{width:clamp(50px,5.5vw,72px);text-align:center;flex-shrink:0}
/* Player rows */
.brd-rows{display:flex;flex-direction:column;gap:clamp(2px,0.3vh,5px)}
.brd-row{display:flex;align-items:center;gap:clamp(8px,1vw,14px);padding:clamp(6px,0.9vh,11px) clamp(8px,1vw,16px);border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.03);opacity:0;transition:background .15s}
.brd-row:last-child{border-bottom:none}
.brd-row.dim{opacity:0.38;background:rgba(239,68,68,0.03);border-color:rgba(239,68,68,0.04)}
.brd-row .brd-num{width:clamp(20px,2vw,28px);text-align:center;font-size:clamp(9px,1.3vh,14px);font-weight:700;color:rgba(255,255,255,0.20);flex-shrink:0}
.brd-row .brd-pic{width:clamp(52px,6.5vw,80px);height:clamp(62px,8vh,96px);border-radius:8px;overflow:hidden;flex-shrink:0;position:relative;background:#1a1a2e;box-shadow:0 4px 14px rgba(0,0,0,0.35)}
.brd-row .brd-pic img{width:100%;height:100%;object-fit:cover;object-position:center 18%}
.brd-row .brd-pic .brd-pic-init{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:clamp(16px,2.5vh,28px);font-weight:800;color:rgba(255,255,255,0.10)}
.brd-row .brd-pic::after{content:'';position:absolute;bottom:0;left:0;right:0;height:28%;background:linear-gradient(to top,rgba(0,0,0,0.50),transparent);pointer-events:none}
.brd-row .brd-name-col{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.brd-row .brd-name-col .brd-pname{font-size:clamp(12px,1.9vh,20px);font-weight:700;text-transform:uppercase;letter-spacing:0.03em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:1px 1px 3px rgba(0,0,0,0.25);display:flex;align-items:center;gap:6px}
.brd-row .brd-name-col .brd-detail{font-size:clamp(7px,1vh,10px);font-weight:400;opacity:0.45;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.brd-row .brd-name-col .brd-dismissal{font-size:clamp(8px,1.2vh,12px);font-weight:600;color:#ef4444;text-transform:uppercase;letter-spacing:0.03em}
.brd-stat{font-size:clamp(12px,2vh,22px);font-weight:600;text-align:center;min-width:clamp(30px,3.2vw,44px);flex-shrink:0}
.brd-stat.big{font-weight:800;font-size:clamp(15px,2.6vh,30px);text-shadow:0 0 12px rgba(255,215,64,0.25)}
.brd-stat.wk{font-weight:800;font-size:clamp(15px,2.6vh,30px);color:#FF9800;text-shadow:0 0 14px rgba(255,152,0,0.30)}
.brd-stat.sr-lo{color:#ef4444}.brd-stat.sr-md{color:#fbbf24}.brd-stat.sr-hi{color:#4ade80}
.brd-stat.ec-lo{color:#4ade80}.brd-stat.ec-md{color:#fbbf24}.brd-stat.ec-hi{color:#ef4444}
.brd-st{font-size:clamp(8px,1.2vh,12px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;flex-shrink:0;min-width:clamp(50px,5.5vw,72px);text-align:center}
.brd-st.out{color:#ef4444}
.brd-st.no{color:#4ade80}
.brd-tag{display:inline-block;font-size:clamp(6px,0.8vh,9px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;padding:1px 4px;border-radius:3px;flex-shrink:0}
.brd-tag.bat{background:rgba(74,222,128,0.12);color:#4ade80;border:1px solid rgba(74,222,128,0.18)}
.brd-tag.bwl{background:rgba(96,165,250,0.12);color:#60a5fa;border:1px solid rgba(96,165,250,0.18)}
.brd-tag.all{background:rgba(192,132,252,0.12);color:#c084fc;border:1px solid rgba(192,132,252,0.18)}
.brd-tag.wk{background:rgba(251,191,36,0.12);color:#fbbf24;border:1px solid rgba(251,191,36,0.18)}
/* Extras / Total row */
.brd-row.brd-total{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);margin-top:clamp(3px,0.5vh,6px)}
.brd-row.brd-total .brd-pname{font-size:clamp(10px,1.5vh,15px);font-weight:600;opacity:0.6}
.brd-row.brd-extras{opacity:0.45}

/* ── GLASS PANEL BROADCAST CARDS (reference design) ── */
.gls-panel{background:rgba(28,34,48,0.82);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.10);border-radius:20px;overflow:hidden;backface-visibility:hidden}
.gls-inner{display:flex;height:100%}
.gls-left{width:clamp(200px,18vw,280px);flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:clamp(20px,3vh,36px) clamp(10px,1.5vw,20px);position:relative}
.gls-left.a{background:linear-gradient(180deg,rgba(255,152,0,0.70),rgba(200,100,0,0.70))}
.gls-left.b{background:linear-gradient(180deg,rgba(2,136,209,0.70),rgba(1,80,140,0.70))}
.gls-left .gls-logo-wrap{width:clamp(100px,14vh,160px);height:clamp(100px,14vh,160px);border-radius:50%;overflow:hidden;border:4px solid rgba(255,255,255,0.25);box-shadow:0 8px 30px rgba(0,0,0,0.40);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.12)}
.gls-left .gls-logo-wrap img{width:85%;height:85%;object-fit:contain}
.gls-left .gls-logo-wrap .gls-logoi{font-size:clamp(24px,3.5vh,42px);font-weight:800;color:rgba(255,255,255,0.45)}
.gls-left .gls-team-name{font-family:'Teko',sans-serif;font-size:clamp(18px,2.8vh,32px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:clamp(8px,1.2vh,14px);text-shadow:0 2px 8px rgba(0,0,0,0.30)}
.gls-right{flex:1;min-width:0;padding:clamp(16px,2.5vh,30px) clamp(16px,2vw,28px);display:flex;flex-direction:column}
.gls-right .gls-title{font-size:clamp(16px,2.5vh,28px);font-weight:800;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px}
.gls-right .gls-sub{font-size:clamp(9px,1.3vh,13px);font-weight:600;opacity:0.55;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:clamp(10px,1.8vh,18px)}
.gls-rows{flex:1;display:flex;flex-direction:column;gap:clamp(1px,0.2vh,3px)}
.gls-row{display:flex;align-items:center;gap:clamp(6px,0.8vw,10px);padding:clamp(5px,0.8vh,9px) clamp(6px,0.8vw,10px);border-bottom:1px solid rgba(255,255,255,0.04);border-radius:6px;transition:background .15s}
.gls-row:last-child{border-bottom:none}
.gls-row.dim{opacity:0.40}
.gls-row.active{background:rgba(2,136,209,0.25);border:1px solid rgba(2,136,209,0.35);border-radius:8px}
.gls-row .gls-name{width:clamp(140px,15vw,220px);font-size:clamp(12px,1.8vh,18px);font-weight:700;text-transform:uppercase;letter-spacing:0.03em;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:1px 1px 2px rgba(0,0,0,0.25)}
.gls-row .gls-detail{flex:1;min-width:0;font-size:clamp(9px,1.3vh,13px);font-weight:600;opacity:0.60;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gls-row .gls-detail .gls-dot{display:inline-block;margin-right:3px}
.gls-run-pill{width:clamp(52px,5.5vw,72px);height:clamp(34px,4.5vh,50px);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:'Teko',sans-serif;font-weight:600;font-size:clamp(22px,3.2vh,38px);line-height:1;flex-shrink:0}
.gls-run-pill.a{background:rgba(255,152,0,0.18);color:#FF9800;border:1px solid rgba(255,152,0,0.25)}
.gls-run-pill.b{background:rgba(2,136,209,0.22);color:#60a5fa;border:1px solid rgba(2,136,209,0.30)}
.gls-run-pill.empty{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05)}
.gls-balls{width:clamp(40px,4vw,56px);text-align:center;font-family:'Teko',sans-serif;font-size:clamp(20px,2.8vh,32px);font-weight:500;opacity:0.65;flex-shrink:0}
.gls-footer{display:flex;align-items:center;justify-content:space-between;margin-top:clamp(8px,1.5vh,16px);padding-top:clamp(8px,1.2vh,14px);border-top:2px solid rgba(255,255,255,0.08)}
.gls-footer .gls-total{display:flex;align-items:baseline;gap:clamp(4px,0.5vw,8px);font-size:clamp(13px,1.8vh,18px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;opacity:0.70}
.gls-footer .gls-total span{font-family:'Teko',sans-serif;font-size:clamp(22px,3.2vh,38px);font-weight:700;opacity:1;color:#fff}
.gls-footer .gls-score-pill{background:rgba(2,136,209,0.25);border:1px solid rgba(2,136,209,0.35);padding:clamp(4px,0.6vh,8px) clamp(16px,2vw,28px);border-radius:10px;font-family:'Teko',sans-serif;font-size:clamp(32px,4.5vh,56px);font-weight:700;line-height:1;letter-spacing:-0.02em;color:#fff;text-shadow:0 2px 10px rgba(0,0,0,0.30)}

/* ── MATCH SUMMARY UPGRADED ── */
.su-teams{display:flex;align-items:stretch;justify-content:center;gap:clamp(10px,1.5vw,20px);margin-bottom:clamp(16px,2.5vh,28px)}
.su-team-card{flex:1;max-width:480px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:clamp(16px,2.5vh,28px);text-align:center;opacity:0}
.su-team-card .su-logo{width:clamp(64px,11vh,100px);height:clamp(64px,11vh,100px);border-radius:50%;overflow:hidden;margin:0 auto clamp(10px,2vh,20px);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);border:2px solid rgba(255,255,255,0.18);box-shadow:0 4px 24px rgba(0,0,0,0.35)}
.su-team-card .su-logo img{width:100%;height:100%;object-fit:contain}
.su-team-card .su-logo .su-logo-init{font-size:clamp(18px,2.8vh,32px);font-weight:800;color:rgba(255,255,255,0.35)}
.su-team-card h3{font-size:clamp(14px,2.2vh,22px);font-weight:800;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px}
.su-team-card .su-score{font-size:clamp(34px,5.5vh,64px);font-weight:900;letter-spacing:-0.03em;line-height:1;text-shadow:0 2px 12px rgba(0,0,0,0.40)}
.su-team-card .su-score small{font-size:clamp(16px,2.5vh,28px);font-weight:400;opacity:0.35}
.su-team-card .su-meta{font-size:clamp(9px,1.3vh,13px);color:rgba(255,255,255,0.45);margin-top:6px}
.su-team-card .su-overs{display:inline-block;margin-top:8px;padding:4px 14px;border-radius:6px;font-size:clamp(10px,1.4vh,14px);font-weight:700;letter-spacing:0.04em}
.su-team-card .su-overs.bat-overs{background:rgba(249,115,22,0.12);color:#f97316;border:1px solid rgba(249,115,22,0.18)}
.su-vs-divider{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;flex-shrink:0;min-width:60px}
.su-vs-divider .su-vs-text{font-size:clamp(16px,2.5vh,28px);font-weight:200;opacity:0.35;letter-spacing:0.1em}
.su-vs-divider .su-vs-line{width:1px;flex:1;min-height:30px;background:linear-gradient(to bottom,transparent,rgba(255,255,255,0.10),rgba(255,255,255,0.10),transparent)}
.su-result{background:linear-gradient(135deg,rgba(249,115,22,0.12),rgba(234,88,12,0.06));border:2px solid rgba(249,115,22,0.22);border-radius:16px;padding:clamp(18px,2.8vh,30px);text-align:center;opacity:0}
.su-result .su-win{font-size:clamp(18px,3vh,34px);font-weight:800;color:#f97316;letter-spacing:0.02em;text-shadow:0 2px 12px rgba(249,115,22,0.25)}
.su-result .su-margin{font-size:clamp(12px,1.8vh,20px);font-weight:600;color:#d1d5db;margin-top:4px}
.su-target{background:linear-gradient(135deg,rgba(251,191,36,0.08),rgba(249,115,22,0.04));border:2px solid rgba(251,191,36,0.20);border-radius:16px;padding:clamp(18px,2.8vh,30px);text-align:center;opacity:0}
.su-target .su-tar-val{font-size:clamp(20px,3.2vh,38px);font-weight:800;color:#fbbf24;letter-spacing:0.02em}
.su-target .su-tar-lbl{font-size:clamp(10px,1.5vh,15px);font-weight:600;color:rgba(255,255,255,0.55);margin-top:4px;text-transform:uppercase;letter-spacing:0.08em}

/* ── TOSS UPGRADED ── */
.to-match-header{display:flex;align-items:center;gap:clamp(10px,1.2vw,18px);margin-bottom:clamp(20px,3vh,34px)}
.to-match-header .to-ml{width:clamp(56px,9vh,90px);height:clamp(56px,9vh,90px);border-radius:0;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:transparent}
.to-match-header .to-ml img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,0.50))}
.to-match-header .to-ml .to-no-logo{font-size:clamp(14px,2vh,22px);opacity:0.30}
.to-match-header h3{font-size:clamp(14px,2vh,22px);font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#FF9800}
.to-match-header .to-match-sub{font-size:clamp(8px,1.1vh,11px);opacity:0.55;text-transform:uppercase;letter-spacing:0.08em;margin-top:2px}
.to-teams{display:flex;align-items:center;justify-content:center;gap:clamp(24px,4vw,64px);padding:clamp(10px,2vh,20px) 0 clamp(20px,4vh,40px)}
.to-team{text-align:center;opacity:0}
.to-team .to-logo{width:clamp(76px,13vh,140px);height:clamp(76px,13vh,140px);border-radius:50%;overflow:hidden;margin:0 auto clamp(12px,2vh,20px);background:rgba(255,255,255,0.05);border:3px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;box-shadow:0 0 30px rgba(0,0,0,0.30);transition:box-shadow .5s ease}
.to-team .to-logo img{width:100%;height:100%;object-fit:contain}
.to-team .to-logo .to-logo-init{font-size:clamp(22px,3.5vh,42px);font-weight:800;color:rgba(255,255,255,0.30)}
.to-team .to-name{font-size:clamp(14px,2.2vh,24px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;text-shadow:0 2px 8px rgba(0,0,0,0.35)}
.to-team .to-name.winner{color:#FF9800;text-shadow:0 0 20px rgba(255,152,0,0.30)}
.to-vs{font-size:clamp(24px,4vh,48px);font-weight:200;opacity:0;letter-spacing:0.12em;flex-shrink:0}
.to-result{opacity:0;background:linear-gradient(135deg,rgba(255,152,0,0.12),rgba(2,136,209,0.06));border:2px solid rgba(255,152,0,0.22);border-radius:16px;padding:clamp(16px,2.5vh,28px);margin:0 clamp(12px,2vw,24px) clamp(16px,2.5vh,28px);text-align:center}
.to-result .to-res-label{font-size:clamp(10px,1.5vh,15px);font-weight:700;color:#FF9800;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.14em}
.to-result .to-res-winner{font-size:clamp(18px,3vh,34px);font-weight:800;color:#fff;letter-spacing:0.02em}
.to-result .to-res-dec{font-size:clamp(12px,1.8vh,22px);font-weight:600;color:#FF9800;margin-top:6px}

/* ── COMPACT XI STRIP (for Batting/Bowling card headers) ── */
.xi-strip{display:none;align-items:flex-end;justify-content:center;gap:clamp(4px,0.6vw,8px);padding:clamp(6px,1vh,12px) 0 clamp(10px,1.6vh,18px);border-bottom:1px solid rgba(255,255,255,0.05);margin-bottom:clamp(6px,1vh,12px);flex-wrap:wrap}
.xi-strip.on{display:flex}
.xi-strip .xi-spot{display:flex;flex-direction:column;align-items:center;gap:clamp(3px,0.5vh,6px);opacity:0;width:clamp(56px,6.5vw,82px)}
.xi-strip .xi-spot .xi-sp-img{width:clamp(40px,5vw,60px);height:clamp(50px,6.5vh,76px);border-radius:8px;overflow:hidden;position:relative;background:#1a1a2e;box-shadow:0 4px 14px rgba(0,0,0,0.35);border:1px solid rgba(255,255,255,0.06)}
.xi-strip .xi-spot .xi-sp-img img{width:100%;height:100%;object-fit:cover;object-position:center 20%}
.xi-strip .xi-spot .xi-sp-img .xi-sp-init{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:clamp(14px,2vh,22px);font-weight:800;color:rgba(255,255,255,0.12);background:rgba(255,255,255,0.03)}
.xi-strip .xi-spot .xi-sp-jersey{position:absolute;top:3px;right:3px;width:clamp(14px,1.8vh,20px);height:clamp(14px,1.8vh,20px);border-radius:50%;background:rgba(0,0,0,0.65);display:flex;align-items:center;justify-content:center;font-size:clamp(6px,0.8vh,9px);font-weight:700;color:#fff;z-index:2;border:1px solid rgba(255,255,255,0.20)}
.xi-strip .xi-spot .xi-sp-name{font-size:clamp(7px,0.9vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.03em;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis;text-align:center;text-shadow:0 1px 3px rgba(0,0,0,0.30)}
.xi-strip .xi-spot .xi-sp-role{font-size:clamp(5px,0.7vh,8px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;opacity:0.50}
.xi-strip .xi-spot.active .xi-sp-img{border-color:#FFD740;box-shadow:0 0 12px rgba(255,215,64,0.30),0 4px 14px rgba(0,0,0,0.35)}
.xi-strip .xi-spot.striker .xi-sp-img{border-color:#4ade80;box-shadow:0 0 16px rgba(74,222,128,0.40),0 4px 14px rgba(0,0,0,0.35)}

/* ── PLAYING XI CARD GRID ── */
.xi-section{width:100%;margin-bottom:clamp(18px,3vh,32px)}
.xi-section:last-child{margin-bottom:0}
.xi-header{display:flex;align-items:center;gap:clamp(12px,1.8vw,24px);margin-bottom:clamp(16px,2.5vh,28px)}
.xi-header .xi-logo{width:clamp(64px,11vh,100px);height:clamp(64px,11vh,100px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:none;background:transparent;box-shadow:none}
.xi-header .xi-logo img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,0.50))}
.xi-header .xi-logo .xi-logo-init{font-size:clamp(18px,2.8vh,32px);font-weight:800;color:rgba(255,255,255,0.35);letter-spacing:0.06em}
.xi-header .xi-info{flex:1;min-width:0}
.xi-header .xi-team-name{font-size:clamp(20px,3vh,36px);font-weight:800;letter-spacing:0.06em;text-transform:uppercase;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 2px 10px rgba(0,0,0,0.45)}
.xi-header .xi-subtitle{display:flex;align-items:center;gap:clamp(6px,1vw,12px);margin-top:4px}
.xi-header .xi-format-badge{font-size:clamp(8px,1.2vh,12px);font-weight:700;text-transform:uppercase;letter-spacing:0.12em;padding:2px 10px;border-radius:4px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.55)}
.xi-card-row{display:flex;justify-content:space-between;margin-bottom:clamp(6px,1.2vh,14px);gap:clamp(8px,1vw,14px);flex-wrap:wrap}
.xi-card-row.bottom{justify-content:center;gap:clamp(14px,2vw,28px);flex-wrap:wrap}
.xi-card{display:flex;flex-direction:column;align-items:center;width:clamp(120px,12.5vw,200px);flex-shrink:0;transition:transform .25s ease}
.xi-card .xi-img-wrap{width:clamp(92px,11.5vh,155px);height:clamp(115px,17vh,195px);overflow:hidden;display:flex;justify-content:center;align-items:center;position:relative;border-radius:12px 12px 0 0;box-shadow:0 8px 24px rgba(0,0,0,0.40);background:#1a1a2e}
.xi-card .xi-img-wrap::after{content:'';position:absolute;bottom:0;left:0;right:0;height:30%;background:linear-gradient(to top,rgba(0,0,0,0.50),transparent);z-index:1;pointer-events:none}
.xi-card .xi-img-wrap img{width:100%;height:100%;object-fit:cover;object-position:center 20%;position:relative;z-index:0}
.xi-card .xi-img-wrap .xi-img-init{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:clamp(26px,5vh,48px);font-weight:800;color:rgba(255,255,255,0.12);background:rgba(255,255,255,0.03)}
.xi-card .xi-jersey{position:absolute;top:clamp(5px,1vh,10px);right:clamp(5px,1vh,10px);width:clamp(20px,2.6vh,28px);height:clamp(20px,2.6vh,28px);border-radius:50%;background:rgba(0,0,0,0.60);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;font-size:clamp(8px,1.1vh,11px);font-weight:800;color:#fff;z-index:2;border:1px solid rgba(255,255,255,0.25)}
.xi-card .xi-badge{width:100%;text-align:center;padding:clamp(4px,0.7vh,8px) clamp(2px,0.4vw,6px);margin-top:clamp(-8px,-1.3vh,-16px);position:relative;z-index:10;border-radius:5px;display:flex;align-items:center;justify-content:center;gap:3px;transition:transform .15s ease}
.xi-card .xi-badge.a{background:linear-gradient(180deg,#FFD54F 0%,#FF9800 100%);color:#1a1a1a;box-shadow:0 4px 14px rgba(255,152,0,0.40)}
.xi-card .xi-badge.b{background:linear-gradient(180deg,#29b6f6 0%,#0277bd 100%);color:#fff;box-shadow:0 4px 14px rgba(2,136,209,0.45)}
.xi-card .xi-badge .xi-name{font-weight:700;font-size:clamp(9px,1.4vh,14px);text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.xi-card .xi-badge .xi-cap-star{font-size:clamp(10px,1.4vh,14px);flex-shrink:0;filter:drop-shadow(0 1px 2px rgba(0,0,0,0.35))}
.xi-card .xi-role-wrap{margin-top:clamp(4px,0.8vh,10px);text-align:center}
.xi-card .xi-role{font-size:clamp(7px,1.1vh,11px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;padding:2px 8px;border-radius:4px;display:inline-block}
.xi-card .xi-role.bat{background:rgba(74,222,128,0.15);color:#4ade80;border:1px solid rgba(74,222,128,0.20)}
.xi-card .xi-role.bwl{background:rgba(96,165,250,0.15);color:#60a5fa;border:1px solid rgba(96,165,250,0.20)}
.xi-card .xi-role.all{background:rgba(192,132,252,0.15);color:#c084fc;border:1px solid rgba(192,132,252,0.20)}
.xi-card .xi-role.wk{background:rgba(251,191,36,0.15);color:#fbbf24;border:1px solid rgba(251,191,36,0.20)}
.xi-card .xi-style{font-size:clamp(6px,0.9vh,9px);font-weight:400;opacity:0.50;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:clamp(90px,9vw,150px)}
.xi-separator{width:100%;height:2px;background:linear-gradient(90deg,transparent,rgba(255,152,0,0.50) 15%,rgba(255,152,0,0.15) 35%,rgba(2,136,209,0.15) 65%,rgba(2,136,209,0.50) 85%,transparent);margin:clamp(12px,2vh,24px) 0;border-radius:1px}
.xi-footer{width:100%;background:rgba(255,255,255,0.04);border-radius:14px;padding:clamp(10px,1.6vh,18px);text-align:center;border:1px solid rgba(255,255,255,0.10)}
.xi-footer span{font-size:clamp(12px,1.8vh,20px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.85}
.xi-footer .xi-toss-winner{color:#FF9800;font-weight:900}
.xi-top-header{display:flex;align-items:center;gap:clamp(10px,1.2vw,18px)}
.xi-top-header .xi-match-logo{width:clamp(64px,11vh,100px);height:clamp(64px,11vh,100px);border-radius:0;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:transparent;border:none;box-shadow:none}
.xi-top-header .xi-match-logo img{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 4px 16px rgba(0,0,0,0.50))}
.xi-top-header .xi-match-logo .xi-no-logo{font-size:clamp(16px,2.4vh,26px);opacity:0.30}

/* ── GLASS-PANEL BATTING CARD (Modern Broadcast) ── */
.bgp-wrap{width:100%;max-width:100%;display:flex;border-radius:20px;overflow:hidden;position:relative;margin:0 auto;background:#141e28;border:1px solid rgba(255,255,255,0.06);perspective:1200px;transform:rotateX(0.6deg);box-shadow:0 20px 60px rgba(0,0,0,0.50),0 8px 24px rgba(0,0,0,0.30),inset 0 1px 0 rgba(255,255,255,0.08)}
.bgp-wrap::after{content:'';position:absolute;inset:0;border-radius:20px;pointer-events:none;background:linear-gradient(180deg,rgba(255,255,255,0.04) 0%,transparent 30%,transparent 70%,rgba(0,0,0,0.20) 100%);z-index:1}
.bgp-wrap .bgp-left{width:clamp(240px,22vw,400px);flex-shrink:0;display:flex;align-items:center;justify-content:center;position:relative;z-index:20;padding:clamp(16px,2.5vh,30px)}
.bgp-wrap .bgp-left .bgp-logo-wrap{width:clamp(200px,22vh,340px);height:clamp(200px,22vh,340px);border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.12);border:4px solid rgba(255,255,255,0.25);box-shadow:0 12px 40px rgba(0,0,0,0.45)}
.bgp-wrap .bgp-left .bgp-logo-wrap img{width:90%;height:90%;object-fit:contain}
.bgp-wrap .bgp-left .bgp-logo-wrap .bgp-logo-init{font-size:clamp(38px,5vh,60px);font-weight:800;color:rgba(255,255,255,0.40)}
.bgp-wrap .bgp-main{flex:1;min-width:0;display:flex;flex-direction:column;padding:clamp(8px,1.2vh,12px) clamp(12px,1.5vw,18px) clamp(6px,1vh,10px)}
.bgp-wrap .bgp-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:clamp(10px,1.5vh,18px)}
.bgp-wrap .bgp-header .bgp-team-name{font-family:'Teko',sans-serif;font-size:clamp(26px,4vh,48px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;line-height:1.1;text-shadow:0 2px 10px rgba(0,0,0,0.35);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bgp-wrap .bgp-header .bgp-match-info{font-size:clamp(10px,1.5vh,15px);font-weight:600;opacity:0.55;text-transform:uppercase;letter-spacing:0.06em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.bgp-wrap .bgp-header .bgp-icon{width:clamp(50px,6vh,70px);height:clamp(50px,6vh,70px);border-radius:12px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:clamp(22px,3vh,32px);color:#FF9800}
.bgp-wrap .bgp-rows{flex:1;display:flex;flex-direction:column;gap:clamp(2px,0.3vh,4px);overflow-y:auto}
.bgp-wrap .bgp-rows::-webkit-scrollbar{width:2px}.bgp-wrap .bgp-rows::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.10);border-radius:2px}
.bgp-row{display:flex;align-items:center;gap:clamp(6px,0.8vw,10px);padding:clamp(4px,0.6vh,8px) clamp(6px,0.8vw,10px);border-bottom:1px solid rgba(255,255,255,0.06);border-radius:8px;transition:background .15s;opacity:0}
.bgp-row:last-child{border-bottom:none}
.bgp-row.dim{opacity:0.45;background:rgba(255,255,255,0.01)}
.bgp-row.active{background:rgba(2,136,209,0.20);border:1px solid rgba(2,136,209,0.35);border-radius:10px;padding:clamp(7px,1vh,12px) clamp(8px,1vw,14px)}
.bgp-row .bgp-name{width:clamp(160px,15vw,250px);font-size:clamp(11px,1.6vh,16px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 1px 3px rgba(0,0,0,0.25);color:#fff}
.bgp-row .bgp-detail{flex:1;min-width:0;font-size:clamp(8px,1.1vh,11px);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bgp-row .bgp-detail.dismissal{color:#ef4444;text-transform:uppercase;letter-spacing:0.03em}
.bgp-row .bgp-detail.no{color:#4ade80;text-transform:uppercase;letter-spacing:0.03em;font-weight:700}
.bgp-row .bgp-detail.dim-detail{opacity:0.35}
.bgp-run-pill{width:clamp(46px,5vw,64px);height:clamp(30px,4vh,44px);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:'Teko',sans-serif;font-weight:700;font-size:clamp(20px,2.8vh,32px);line-height:1;flex-shrink:0}
.bgp-run-pill.runs{background:rgba(2,136,209,0.25);border:1px solid rgba(2,136,209,0.35);color:#fff;box-shadow:0 0 12px rgba(2,136,209,0.20)}
.bgp-run-pill.empty{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05)}
.bgp-run-pill.out{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.18);color:rgba(255,255,255,0.55)}
.bgp-balls{width:clamp(36px,3.5vw,48px);text-align:center;font-family:'Teko',sans-serif;font-size:clamp(16px,2vh,24px);font-weight:500;opacity:0.60;flex-shrink:0}
.bgp-xs{width:clamp(26px,2.5vw,34px);text-align:center;font-family:'Teko',sans-serif;font-size:clamp(14px,1.8vh,20px);font-weight:500;opacity:0.50;flex-shrink:0}
/* Captain / WK inline tags for batting/bowling cards */
.bdg-c,.wk-tag{display:inline-block;font-weight:800;padding:0 3px;border-radius:3px;margin-left:3px;letter-spacing:0.04em;vertical-align:middle}
.bdg-c{background:#FF9800;color:#fff;font-size:clamp(5px,0.7vh,8px);padding:0 4px}
.wk-tag{background:#2e7d32;color:#fff;font-size:clamp(5px,0.7vh,8px)}
.su-tag{display:inline;font-size:clamp(5px,0.7vh,8px);font-weight:800;color:#66bb6a;margin-left:2px}
.bgp-footer{display:flex;align-items:center;justify-content:space-between;margin-top:clamp(10px,1.5vh,16px);padding-top:clamp(8px,1.2vh,14px);border-top:2px solid rgba(255,255,255,0.10)}
.bgp-footer .bgp-meta{display:flex;align-items:center;gap:clamp(12px,1.5vw,24px);font-size:clamp(11px,1.6vh,16px);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;opacity:0.55}
.bgp-footer .bgp-meta span{opacity:1;color:#fff}
.bgp-footer .bgp-total{font-family:'Teko',sans-serif;font-size:clamp(28px,4vh,52px);font-weight:700;line-height:1;color:#fff;text-shadow:0 2px 14px rgba(0,0,0,0.35);letter-spacing:-0.01em}
.bgp-footer .bgp-total small{font-size:clamp(14px,2vh,24px);font-weight:300;opacity:0.30;margin-left:2px}
/* Key Performer Panel (right side) */
.bgp-right{width:clamp(280px,22vw,450px);flex-shrink:0;position:relative;display:flex;flex-direction:column;align-items:center;overflow:hidden}
.bgp-right .bgp-perf-img-wrap{width:100%;flex:1;display:flex;align-items:flex-end;justify-content:center;z-index:5;padding:clamp(10px,2vh,20px);min-height:0}
.bgp-right .bgp-perf-img{max-width:85%;max-height:100%;object-fit:contain;object-position:center bottom;filter:drop-shadow(0 8px 30px rgba(0,0,0,0.45))}
.bgp-right .bgp-perf-img-placeholder{font-size:clamp(80px,12vh,160px);font-weight:900;color:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;width:100%;height:100%}
.bgp-right .bgp-perf-label-wrap{flex-shrink:0;width:100%;padding:0 clamp(10px,1.5vw,20px) clamp(10px,1.5vh,20px);z-index:20;display:flex;flex-direction:column;align-items:center}
.bgp-right .bgp-perf-badge{background:#FF9800;padding:clamp(5px,0.8vh,10px) clamp(14px,2vw,28px);border-radius:10px 10px 0 0;width:100%;text-align:center;border-bottom:2px solid rgba(255,255,255,0.30);box-shadow:0 4px 16px rgba(255,152,0,0.30);box-sizing:border-box}
.bgp-right .bgp-perf-badge span{font-family:'Teko',sans-serif;font-size:clamp(16px,2.2vh,26px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,0.30)}
.bgp-right .bgp-perf-status{background:rgba(255,152,0,0.12);padding:clamp(3px,0.5vh,6px) clamp(10px,1.5vw,20px);width:100%;text-align:center;font-size:clamp(10px,1.4vh,16px);font-weight:600;color:#FFCC80;letter-spacing:0.04em;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,0.06);box-sizing:border-box}
.bgp-right .bgp-perf-name{background:#fff;padding:clamp(6px,1vh,12px) clamp(14px,2vw,28px);border-radius:0 0 10px 10px;width:100%;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,0.35);box-sizing:border-box}
.bgp-right .bgp-perf-name span{font-family:'Teko',sans-serif;font-size:clamp(24px,3.5vh,42px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#1a1a2e}

/* ── BOWLING CARD STAT COLUMNS ── */
.bgp-stat{min-width:clamp(42px,4.5vw,58px);text-align:center;font-family:'Teko',sans-serif;font-size:clamp(16px,2.2vh,24px);font-weight:600;flex-shrink:0}
.bgp-stat.sm{font-size:clamp(14px,1.8vh,20px);font-weight:500;opacity:0.65}
.bgp-stat.wk{color:#FF9800;font-weight:700;text-shadow:0 0 10px rgba(255,152,0,0.25)}
.bgp-stat.e{font-size:clamp(14px,1.8vh,20px);font-weight:500}
.bgp-stat.e.lo{color:#4ade80}.bgp-stat.e.md{color:#fbbf24}.bgp-stat.e.hi{color:#ef4444}
.bgp-name-flex{flex:1;min-width:0;font-size:clamp(12px,1.9vh,19px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 1px 3px rgba(0,0,0,0.25);color:#fff}
.bgp-name-flex.dim{color:#fff}
/* Total row */
.bgp-row.bgp-total-row{border-top:2px solid rgba(255,255,255,0.12);margin-top:clamp(4px,0.6vh,8px);padding-top:clamp(8px,1.2vh,14px)}
/* Column header bar */
.bgp-col-bar{display:flex;align-items:center;gap:clamp(6px,0.8vw,10px);padding:clamp(2px,0.3vh,4px) clamp(6px,0.8vw,10px) clamp(4px,0.6vh,8px);margin-bottom:2px;border-bottom:1px solid rgba(255,255,255,0.05)}
.bgp-col-bar .bgp-col-hdr{font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)}
.bgp-col-bar .bgp-col-s{min-width:clamp(42px,4.5vw,58px);text-align:center;flex-shrink:0;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)}

/* ── BOWLING CARD: tighter side panels for wider name column ── */
#fvBwlB .bgp-left{width:clamp(220px,20vw,360px)}
#fvBwlB .bgp-left .bgp-logo-wrap{width:clamp(180px,19vh,280px);height:clamp(180px,19vh,280px)}

/* ── BATTING CARD: tighter side panels for wider columns ── */
#fvBatB .bgp-left{width:clamp(220px,20vw,360px)}
#fvBatB .bgp-right{width:clamp(260px,20vw,400px)}
#fvBatB .bgp-left .bgp-logo-wrap{width:clamp(180px,19vh,280px);height:clamp(180px,19vh,280px)}

/* ── PLAYING XI CARDS (Side-by-side, 16:9) ── */
.xi-wrap{display:flex;flex-direction:row;gap:clamp(10px,1.5vw,20px);align-items:flex-start}
.xi-col{flex:1;min-width:0;background:rgba(28,36,50,0.65);border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden;backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);position:relative}
.xi-col:first-child{border-top:3px solid #FF9800;box-shadow:0 4px 20px rgba(255,152,0,0.10),0 1px 4px rgba(0,0,0,0.25)}
.xi-col:not(:only-child):last-child{border-top:3px solid #0288D1;box-shadow:0 4px 20px rgba(2,136,209,0.10),0 1px 4px rgba(0,0,0,0.25)}
.xi-col:first-child .xi-body{background:linear-gradient(180deg,rgba(255,152,0,0.03),rgba(255,152,0,0.01) 40%,transparent)}
.xi-col:not(:only-child):last-child .xi-body{background:linear-gradient(180deg,rgba(2,136,209,0.03),rgba(2,136,209,0.01) 40%,transparent)}
.xi-tp{display:flex;align-items:center;gap:clamp(6px,0.8vw,10px);padding:clamp(6px,1vh,10px) clamp(8px,1vw,12px);position:relative;overflow:hidden}
.xi-col:first-child .xi-tp{background:linear-gradient(90deg,#FF9800,#e65100)}
.xi-col:not(:only-child):last-child .xi-tp{background:linear-gradient(90deg,#0288D1,#01579b)}
.xi-tp::after{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.5;pointer-events:none}
.xi-tp .xi-tp-logo{width:clamp(36px,4.5vh,50px);height:clamp(36px,4.5vh,50px);border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.15);border:2px solid rgba(255,255,255,0.25);box-shadow:0 0 12px rgba(0,0,0,0.18)}
.xi-tp .xi-tp-logo img{width:85%;height:85%;object-fit:contain}
.xi-tp .xi-tp-logo .xi-tp-ini{font-size:clamp(12px,1.6vh,18px);font-weight:800;color:rgba(255,255,255,0.35)}
.xi-tp .xi-tp-info{flex:1;min-width:0}
.xi-tp .xi-tp-name{font-family:'Teko',sans-serif;font-size:clamp(16px,2vh,24px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#fff;text-shadow:0 2px 4px rgba(0,0,0,0.25)}
.xi-tp .xi-tp-sub{font-size:clamp(6px,0.7vh,8px);font-weight:600;text-transform:uppercase;letter-spacing:0.10em;opacity:0.55;color:#fff}
.xi-body{padding:clamp(4px,0.6vh,8px) clamp(8px,1vw,12px)}
.xi-pgrid{display:flex;flex-direction:column;align-items:center;gap:clamp(4px,0.6vh,8px)}
.xi-prow{display:flex;justify-content:center;gap:clamp(6px,0.8vw,14px);flex-wrap:wrap}
.xi-prow.btm{justify-content:center}
.xi-play{display:flex;flex-direction:column;align-items:center;width:clamp(65px,8vw,170px);flex-shrink:0}
.xi-play .xi-play-card{width:100%;aspect-ratio:1/1;border-radius:50%;overflow:hidden;position:relative;background:linear-gradient(180deg,#1a1a2e 0%,#0f1119 100%);box-shadow:0 3px 12px rgba(0,0,0,0.40);border:2px solid rgba(255,255,255,0.06);margin:0 auto}
.xi-col:first-child .xi-play .xi-play-card{border-color:rgba(255,152,0,0.45);box-shadow:0 4px 16px rgba(0,0,0,0.45),0 0 20px rgba(255,152,0,0.08)}
.xi-col:not(:only-child):last-child .xi-play .xi-play-card{border-color:rgba(2,136,209,0.45);box-shadow:0 4px 16px rgba(0,0,0,0.45),0 0 20px rgba(2,136,209,0.08)}
.xi-play .xi-play-card img{width:100%;height:100%;object-fit:cover;object-position:center 25%}
.xi-play .xi-play-card .xi-play-init{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:clamp(24px,3.5vh,40px);font-weight:900;color:rgba(255,255,255,0.04)}
.xi-play .xi-play-card .xi-play-grad{position:absolute;bottom:0;left:0;right:0;height:40%;background:linear-gradient(to top,rgba(0,0,0,0.55),transparent);pointer-events:none;z-index:1;border-radius:0 0 50% 50%}
/* Captain (C) badge — top-right */
.xi-play .xi-play-card .xi-play-capt{position:absolute;top:clamp(3px,0.5vh,6px);right:clamp(3px,0.5vh,6px);width:clamp(18px,2.2vh,24px);height:clamp(18px,2.2vh,24px);border-radius:50%;background:#FF9800;display:flex;align-items:center;justify-content:center;font-size:clamp(8px,1vh,10px);font-weight:900;color:#fff;z-index:3;border:1.5px solid #FFD740;box-shadow:0 2px 8px rgba(0,0,0,0.40);letter-spacing:0.04em}
/* Wicket-keeper (WK) badge — top-left */
.xi-play .xi-play-card .xi-play-wk{position:absolute;top:clamp(3px,0.5vh,6px);left:clamp(3px,0.5vh,6px);width:clamp(18px,2.2vh,24px);height:clamp(18px,2.2vh,24px);border-radius:50%;background:#2e7d32;display:flex;align-items:center;justify-content:center;font-size:clamp(6px,0.8vh,8px);font-weight:900;color:#fff;z-index:3;border:1.5px solid #66bb6a;box-shadow:0 2px 8px rgba(0,0,0,0.40);letter-spacing:0.02em}
.xi-play .xi-play-name{font-family:'Teko',sans-serif;font-size:clamp(9px,1.3vh,14px);font-weight:600;text-transform:uppercase;letter-spacing:0.03em;text-align:center;margin-top:clamp(2px,0.4vh,5px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.xi-col:first-child .xi-play .xi-play-name{color:#FFCC80}
.xi-col:last-child .xi-play .xi-play-name{color:#90CAF9}
.xi-play .xi-play-cap{display:inline;color:#FFD740;font-size:clamp(7px,0.9vh,10px);margin:0 2px}
.xi-play .xi-play-cname{display:inline;color:#FFD740;font-weight:800;font-size:clamp(8px,1vh,11px);margin-left:3px;letter-spacing:0.04em}
.xi-play .xi-play-role{font-size:clamp(5px,0.7vh,8px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;padding:1px 5px;border-radius:4px;margin-top:clamp(1px,0.2vh,3px)}
.xi-play .xi-play-role.bat{background:rgba(74,222,128,0.15);color:#4ade80}
.xi-play .xi-play-role.bwl{background:rgba(96,165,250,0.15);color:#60a5fa}
.xi-play .xi-play-role.all{background:rgba(192,132,252,0.15);color:#c084fc}
.xi-play .xi-play-role.wk{background:rgba(251,191,36,0.15);color:#fbbf24}
/* Top bar */
.xi-top{display:flex;align-items:center;gap:clamp(8px,1vw,12px);margin-bottom:clamp(6px,1vh,10px);background:linear-gradient(90deg,#003d80,#0066cc);padding:clamp(6px,1vh,10px) clamp(10px,1.2vw,14px);border-radius:10px;border-bottom:2px solid rgba(255,255,255,0.10);position:relative;overflow:hidden}
.xi-top::after{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.3;pointer-events:none}
.xi-top .xi-top-logo{width:clamp(40px,5vh,52px);height:clamp(40px,5vh,52px);border-radius:12px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.15)}
.xi-top .xi-top-logo img{width:90%;height:90%;object-fit:contain}
.xi-top .xi-top-logo .xi-top-icn{font-size:clamp(16px,2vh,22px);color:#FFD740}
.xi-top .xi-top-title{font-family:'Teko',sans-serif;font-size:clamp(20px,2.5vh,30px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#fff;text-shadow:0 2px 6px rgba(0,0,0,0.20)}
.xi-top .xi-top-sub{font-size:clamp(8px,1vh,10px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;opacity:0.50;color:#fff;margin-top:1px}
/* Bottom toss */
.xi-bot{margin-top:clamp(6px,0.8vh,10px);text-align:center;padding:clamp(6px,0.9vh,10px);background:linear-gradient(90deg,#c62828,#d32f2f);border-radius:8px;border:2px solid rgba(255,255,255,0.12);position:relative;overflow:hidden}
.xi-bot::after{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.4;pointer-events:none}
.xi-bot span{position:relative;z-index:1;font-size:clamp(10px,1.3vh,14px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.25)}
.xi-bot .xi-bot-w{color:#FFD740;font-weight:900}
/* Single team */
.xi-a .xi-play-name{color:#FFCC80}.xi-b .xi-play-name{color:#90CAF9}
.xi-a .xi-play .xi-play-card{border-color:rgba(255,152,0,0.45);box-shadow:0 4px 16px rgba(0,0,0,0.45),0 0 20px rgba(255,152,0,0.08)}
.xi-b .xi-play .xi-play-card{border-color:rgba(2,136,209,0.45);box-shadow:0 4px 16px rgba(0,0,0,0.45),0 0 20px rgba(2,136,209,0.08)}
/* Single-team column color overrides */
.xi-a .xi-col:only-child{border-top-color:#FF9800}
.xi-b .xi-col:only-child{border-top-color:#0288D1}
.xi-a .xi-col:only-child .xi-tp{background:linear-gradient(90deg,#FF9800,#e65100)}
.xi-b .xi-col:only-child .xi-tp{background:linear-gradient(90deg,#0288D1,#01579b)}
.xi-a .xi-col:only-child .xi-body{background:linear-gradient(180deg,rgba(255,152,0,0.03),rgba(255,152,0,0.01) 40%,transparent)}
.xi-b .xi-col:only-child .xi-body{background:linear-gradient(180deg,rgba(2,136,209,0.03),rgba(2,136,209,0.01) 40%,transparent)}

/* ── SINGLE-TEAM XI (xi_bat / xi_bowl) — larger cards, more space ── */
.xi-single .xi-top{padding:clamp(10px,1.5vh,16px) clamp(14px,1.8vw,20px);gap:clamp(12px,1.4vw,18px)}
.xi-single .xi-top .xi-top-logo{width:clamp(60px,8vh,110px);height:clamp(60px,8vh,110px);border-radius:16px;border-width:2px}
.xi-single .xi-top .xi-top-logo .xi-top-icn{font-size:clamp(22px,3vh,36px)}
.xi-single .xi-top .xi-top-title{font-size:clamp(28px,3.5vh,44px)}
.xi-single .xi-top .xi-top-sub{font-size:clamp(10px,1.3vh,14px)}
.xi-single .xi-play{width:clamp(100px,15vw,260px)}
.xi-single .xi-prow{gap:clamp(10px,1.2vw,20px)}
.xi-single .xi-play .xi-play-name{font-size:clamp(13px,1.8vh,20px)}
.xi-single .xi-play .xi-play-role{font-size:clamp(7px,1vh,12px)}
.xi-single .xi-tp{padding:clamp(10px,1.4vh,16px) clamp(12px,1.4vw,16px);gap:clamp(12px,1.6vw,20px)}
.xi-single .xi-tp .xi-tp-logo{width:clamp(60px,7vh,90px);height:clamp(60px,7vh,90px);border-width:3px}
.xi-single .xi-tp .xi-tp-logo .xi-tp-ini{font-size:clamp(18px,2.4vh,30px)}
.xi-single .xi-tp .xi-tp-name{font-size:clamp(24px,3vh,38px)}
.xi-single .xi-tp .xi-tp-sub{font-size:clamp(8px,1vh,12px)}
.xi-single .xi-play .xi-play-card{border-width:3px}
.xi-single .xi-play .xi-play-card .xi-play-init{font-size:clamp(30px,4vh,52px)}
.xi-single .xi-play .xi-play-card .xi-play-capt{width:clamp(22px,2.8vh,30px);height:clamp(22px,2.8vh,30px);font-size:clamp(10px,1.2vh,13px)}
.xi-single .xi-play .xi-play-card .xi-play-wk{width:clamp(22px,2.8vh,30px);height:clamp(22px,2.8vh,30px);font-size:clamp(8px,1vh,11px)}
.xi-single .xi-body{padding:clamp(6px,0.8vh,12px) clamp(10px,1.2vw,16px)}
.xi-single .xi-bot{margin-top:clamp(8px,1vh,12px);padding:clamp(8px,1.1vh,14px)}
.xi-single .xi-bot span{font-size:clamp(12px,1.5vh,16px)}
/* Captains section between teams */
.xi-captains{display:flex;align-items:center;justify-content:center;gap:clamp(12px,1.5vw,24px);padding:0 clamp(8px,1vw,16px);flex-shrink:0;position:relative;z-index:10}
.xi-captain{display:flex;flex-direction:column;align-items:center;gap:clamp(4px,0.5vh,8px);width:clamp(70px,9vw,140px)}
.xi-captain .xi-cap-photo{width:clamp(48px,6.5vh,90px);height:clamp(48px,6.5vh,90px);border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:linear-gradient(180deg,#1a1a2e,#0f1119);border:3px solid #FFD740;box-shadow:0 4px 20px rgba(255,215,64,0.25)}
.xi-captain .xi-cap-photo img{width:100%;height:100%;object-fit:cover;object-position:center 25%}
.xi-captain .xi-cap-photo .xi-cap-init{font-size:clamp(20px,2.8vh,40px);font-weight:900;color:rgba(255,255,255,0.15)}
.xi-captain .xi-cap-photo.xi-cap-na{background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.15)}
.xi-captain .xi-cap-photo.xi-cap-na span{font-size:clamp(16px,2vh,28px);color:rgba(255,255,255,0.15)}
.xi-captain .xi-cap-name{font-family:'Teko',sans-serif;font-size:clamp(12px,1.6vh,18px);font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#FFD740;text-shadow:0 2px 8px rgba(0,0,0,0.30);text-align:center;white-space:nowrap}
.xi-captain .xi-cap-label{font-size:clamp(6px,0.7vh,9px);font-weight:700;text-transform:uppercase;letter-spacing:0.10em;padding:2px 10px;border-radius:4px;background:rgba(255,215,64,0.15);border:1px solid rgba(255,215,64,0.25);color:#FFD740}
/* Single-team responsive */
@media(max-width:1280px){.xi-single .xi-play{width:clamp(80px,14vw,180px)}.xi-single .xi-prow{gap:clamp(8px,1vw,14px)}.xi-single .xi-top .xi-top-title{font-size:clamp(22px,3vh,32px)}.xi-single .xi-tp .xi-tp-name{font-size:clamp(18px,2.4vh,28px)}.xi-single .xi-tp .xi-tp-logo{width:clamp(50px,5.5vh,70px);height:clamp(50px,5.5vh,70px)}.xi-single .xi-play .xi-play-name{font-size:clamp(11px,1.4vh,15px)}}
@media(max-width:1100px){.xi-single .xi-play{width:clamp(70px,12vw,140px)}.xi-single .xi-prow{gap:clamp(6px,0.8vw,12px)}.xi-single .xi-top .xi-top-logo{width:clamp(48px,6vh,70px);height:clamp(48px,6vh,70px)}}
@media(max-width:900px){.xi-single .xi-play{width:clamp(55px,10vw,110px)}.xi-single .xi-prow{gap:clamp(5px,0.7vw,10px)}.xi-single .xi-play .xi-play-name{font-size:clamp(9px,1.1vh,12px)}.xi-single .xi-top .xi-top-title{font-size:clamp(18px,2.2vh,24px)}}
@media(max-width:600px){.xi-single .xi-play{width:clamp(45px,11vw,85px)}.xi-single .xi-play .xi-play-name{font-size:clamp(8px,0.9vh,10px)}.xi-single .xi-top .xi-top-logo{width:clamp(36px,5vh,50px);height:clamp(36px,5vh,50px)}.xi-single .xi-top .xi-top-title{font-size:clamp(14px,1.8vh,18px)}}
/* ── PLAYING XI RESPONSIVE ── */
/* 4K+ broadcast — side-by-side, larger caps */
@media(min-width:2560px){.xi-play{width:clamp(110px,8vw,240px)}.xi-play .xi-play-name{font-size:clamp(13px,1vh,18px)}.xi-play .xi-play-role{font-size:clamp(7px,0.5vh,10px)}.xi-prow{gap:clamp(8px,0.6vw,18px)}.xi-wrap{gap:clamp(12px,1vw,22px)}.xi-top .xi-top-title{font-size:clamp(26px,1.6vh,38px)}.xi-tp .xi-tp-name{font-size:clamp(22px,1.6vh,32px)}.xi-play .xi-play-card{border-width:3px}}
/* QHD broadcast — side-by-side, medium caps */
@media(min-width:2000px) and (max-width:2559px){.xi-play{width:clamp(100px,8vw,220px)}.xi-play .xi-play-name{font-size:clamp(12px,1vh,16px)}.xi-prow{gap:clamp(7px,0.6vw,16px)}}
/* FHD — side-by-side, default styles handle 1280-1999px */
/* 1280px — tighter side-by-side cards */
@media(max-width:1280px){.xi-play{width:clamp(55px,7vw,120px)}.xi-prow{gap:clamp(4px,0.5vw,10px)}}
/* 1100px — smaller cards still side-by-side */
@media(max-width:1100px){.xi-play{width:clamp(50px,6vw,105px)}.xi-prow{gap:clamp(3px,0.5vw,8px)}}
/* ≤900px — switch to VERTICAL stack, single row of 6+5 */
@media(max-width:900px){.xi-wrap{flex-direction:column;gap:clamp(6px,1vh,12px)}.xi-prow{flex-wrap:nowrap}.xi-play{width:clamp(40px,6vw,80px)}.xi-prow{gap:clamp(3px,0.5vw,6px)}.xi-play .xi-play-name{font-size:clamp(7px,0.8vh,9px)}}
/* ≤600px — very compact vertical */
@media(max-width:600px){.xi-play{width:clamp(35px,8vw,70px)}.xi-play .xi-play-name{font-size:clamp(6px,0.7vh,8px)}.xi-top .xi-top-title{font-size:clamp(12px,1.4vh,16px)}}

/* ── SCOREBUG RESPONSIVE ── */
@media(max-width:1100px){#bar{border-radius:20px;height:clamp(56px,12vh,80px)}#team{flex:0 0 30%;border-radius:20px 0 0 20px}#bWrap{border-radius:0 20px 20px 0}.pl{flex:0 0 18%}#crr{flex:0 0 8%}#bWrap{flex:0 0 38%}}
@media(max-width:800px){#bar{width:100vw;border-radius:0}#team{border-radius:0}#bWrap{border-radius:0}}

/* ── BATTING/BOWLING CARD RESPONSIVE ── */
@media(max-width:1500px){#fvBatB .bgp-right,#fvBwlB .bgp-right{width:clamp(200px,18vw,320px)}.bgp-right .bgp-perf-badge span{font-size:clamp(13px,1.8vh,20px)}.bgp-right .bgp-perf-name span{font-size:clamp(18px,2.8vh,32px)}.bgp-right .bgp-perf-img-placeholder{font-size:clamp(50px,8vh,100px)}}
@media(max-width:1200px){#fvBatB .bgp-right,#fvBwlB .bgp-right{width:clamp(160px,16vw,240px)}.bgp-right .bgp-perf-badge span{font-size:clamp(11px,1.4vh,16px)}.bgp-right .bgp-perf-name span{font-size:clamp(14px,2.2vh,24px)}.bgp-right .bgp-perf-img{max-width:80%}.bgp-right .bgp-perf-img-wrap{padding:clamp(6px,1vh,12px)}.bgp-perf-label-wrap{padding:0 clamp(6px,1vw,12px) clamp(6px,1vh,12px)}}
@media(max-width:900px){.bgp-wrap{flex-wrap:wrap}.bgp-wrap .bgp-left{width:100%;padding:clamp(10px,1.5vh,16px);flex-direction:row;gap:clamp(10px,1.5vw,16px)}.bgp-wrap .bgp-left .bgp-logo-wrap{width:clamp(60px,8vh,100px);height:clamp(60px,8vh,100px);border-width:3px}#fvBatB .bgp-right,#fvBwlB .bgp-right{width:100%;flex-direction:row;align-items:center;padding:clamp(8px,1vh,12px)}.bgp-right .bgp-perf-img-wrap{flex:none;width:clamp(60px,10vh,100px);height:clamp(60px,10vh,100px);padding:clamp(4px,0.5vh,8px)}.bgp-right .bgp-perf-img{max-width:100%;max-height:100%;object-fit:cover;object-position:center 25%}.bgp-right .bgp-perf-label-wrap{padding:0 clamp(8px,1vw,12px)}.bgp-right .bgp-perf-badge{border-radius:8px}.bgp-right .bgp-perf-name{border-radius:0 0 8px 8px}}

/* ── BROADCAST MATCH SUMMARY ── */
.su-bcast{width:100%;max-width:100%}
.su-hdr{background:linear-gradient(90deg,#003d80,#0066cc);padding:clamp(14px,2vh,20px) clamp(20px,2.5vw,28px);display:flex;align-items:center;justify-content:space-between;border-radius:14px 14px 0 0;border-bottom:3px solid rgba(255,255,255,0.15)}
.su-hdr .su-title{font-family:'Teko',sans-serif;font-size:clamp(24px,3.5vh,42px);font-weight:800;text-transform:uppercase;letter-spacing:0.04em;color:#fff;line-height:1.1}
.su-hdr .su-sub{font-size:clamp(10px,1.3vh,14px);font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:rgba(255,255,255,0.7)}
.su-hdr .su-logos{display:flex;gap:clamp(6px,0.8vw,10px)}
.su-hdr .su-logos .su-hdr-logo{width:clamp(52px,7vh,70px);height:clamp(52px,7vh,70px);border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2)}
.su-hdr .su-logos .su-hdr-logo img{width:85%;height:85%;object-fit:contain}
.su-hdr .su-logos .su-hdr-ini{font-size:clamp(12px,1.8vh,18px);font-weight:800;color:rgba(255,255,255,0.4)}
.su-body-wrap{background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-radius:0 0 14px 14px;overflow:hidden;color:#1a1a2e}
.su-team{margin-bottom:clamp(4px,0.6vh,8px)}
.su-tbar{display:flex;align-items:stretch;height:clamp(42px,5.5vh,58px)}
.su-tbar .su-tname{flex:1;min-width:0;display:flex;align-items:center;padding:0 clamp(12px,1.5vw,18px);clip-path:polygon(0 0,100% 0,94% 100%,0 100%)}
.su-tbar .su-tname.a{background:linear-gradient(90deg,#FF9800,#e65100)}
.su-tbar .su-tname.b{background:linear-gradient(90deg,#0277bd,#01579b)}
.su-tbar .su-tname span{font-family:'Teko',sans-serif;font-size:clamp(18px,2.5vh,28px);font-weight:700;text-transform:uppercase;letter-spacing:0.03em;color:#fff}
.su-tbar .su-tov{display:flex;align-items:center;gap:clamp(3px,0.4vw,6px);padding:0 clamp(14px,1.5vw,22px);background:rgba(0,0,0,0.06)}
.su-tbar .su-tov .su-tov-lbl{font-size:clamp(8px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;opacity:0.5;color:#1a1a2e}
.su-tbar .su-tov .su-tov-val{font-family:'Teko',sans-serif;font-size:clamp(18px,2.5vh,26px);font-weight:700;color:#1a1a2e}
.su-tbar .su-tscore{width:clamp(220px,18vw,300px);display:flex;align-items:center;justify-content:center;clip-path:polygon(6% 0,100% 0,100% 100%,0 100%)}
.su-tbar .su-tscore.a{background:linear-gradient(90deg,#c62828,#d32f2f)}
.su-tbar .su-tscore.b{background:linear-gradient(90deg,#c62828,#b71c1c)}
.su-tbar .su-tscore span{font-family:'Teko',sans-serif;font-size:clamp(24px,3.5vh,40px);font-weight:700;color:#fff;text-shadow:0 2px 6px rgba(0,0,0,0.30)}
.su-stats{display:grid;grid-template-columns:1fr 1fr;border-top:2px solid rgba(0,0,0,0.06)}
.su-stats .su-col{padding:clamp(3px,0.4vh,6px) clamp(6px,0.8vw,10px)}
.su-stats .su-col:first-child{border-right:2px solid rgba(0,0,0,0.06)}
.su-sec-lbl{font-size:clamp(7px,0.8vh,9px);font-weight:700;text-transform:uppercase;letter-spacing:0.10em;color:rgba(0,0,0,0.22);margin-bottom:clamp(1px,0.2vh,3px);padding:clamp(1px,0.2vh,3px) clamp(4px,0.6vw,6px)}
.su-prow{display:flex;align-items:center;justify-content:space-between;padding:clamp(4px,0.6vh,7px) clamp(6px,0.8vw,10px);border-bottom:1px solid rgba(0,0,0,0.04);background:rgba(0,0,0,0.015)}
.su-prow:nth-child(even){background:rgba(0,0,0,0.03)}
.su-prow.dim{opacity:0.45}
.su-prow .su-pname{font-size:clamp(11px,1.4vh,16px);font-weight:700;text-transform:uppercase;letter-spacing:0.02em;color:#1a1a2e;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.su-prow .su-pstat{font-family:'Teko',sans-serif;font-size:clamp(18px,2.2vh,28px);font-weight:700;color:#1a1a2e;flex-shrink:0;margin-left:clamp(4px,0.6vw,8px)}
.su-prow .su-pstat sub{font-size:55%;font-weight:400;opacity:0.4}
.su-prow .su-pdet{font-size:clamp(8px,1vh,11px);font-weight:600;opacity:0.7;flex-shrink:0;margin-left:clamp(3px,0.4vw,6px);color:#4b5563}
.su-prow .su-pdet.star{color:#FF9800;font-weight:700}
.su-fbar{background:linear-gradient(90deg,#002244,#003d66,#002244);padding:clamp(10px,1.5vh,16px) clamp(20px,2.5vw,28px);text-align:center;position:relative;overflow:hidden;border-radius:10px;margin-top:clamp(10px,1.5vh,16px);border:2px solid rgba(255,255,255,0.1)}
.su-fbar .su-fbar-diag{position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.03) 0,rgba(255,255,255,0.03) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.3}
.su-fbar span{position:relative;z-index:1;font-family:'Teko',sans-serif;font-size:clamp(18px,2.5vh,30px);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.30)}

/* ── PLAYER PROFILE: Slanted Stat-Row Broadcast Overlay ── */
/* ── PLAYER PROFILE: 3D Slanted Broadcast Card ── */
#fvPlayerB{perspective:1400px;transform-style:preserve-3d}
.pp-overlay{display:flex;width:100%;height:100%;align-items:flex-end;justify-content:center;padding:0 clamp(16px,3vw,50px) clamp(24px,4vh,90px) clamp(16px,3vw,130px);gap:clamp(16px,2.5vw,44px);position:relative;transform-style:preserve-3d;transform:rotateY(-1.5deg) rotateX(2deg);transition:transform 0.5s cubic-bezier(0.23,1,0.32,1)}
/* 3D depth ring */
.pp-ring{position:absolute;inset:clamp(-30px,-4vh,-60px);border-radius:32px;pointer-events:none;z-index:0;opacity:0.25;transform:translateZ(-20px)}
.pp-ring::before{content:'';position:absolute;inset:0;border-radius:inherit;border:2px solid transparent;border-top-color:rgba(168,85,247,0.35);border-right-color:rgba(168,85,247,0.15);animation:ppRingSpin 22s linear infinite}
.pp-ring::after{content:'';position:absolute;inset:12px;border-radius:inherit;border:1px solid transparent;border-bottom-color:rgba(249,115,22,0.25);border-left-color:rgba(249,115,22,0.10);animation:ppRingSpin 16s linear infinite reverse}
@keyframes ppRingSpin{to{transform:rotate(360deg)}}
/* Corner ambient glows */
.pp-glow-tr{position:fixed;top:0;right:0;width:clamp(200px,25vw,500px);height:clamp(150px,20vh,350px);background:radial-gradient(ellipse at 100% 0%,rgba(168,85,247,0.15) 0%,transparent 70%);pointer-events:none;z-index:0;animation:ppGlowPulse 6s ease-in-out infinite}
.pp-glow-bl{position:fixed;bottom:0;left:0;width:clamp(250px,30vw,550px);height:clamp(180px,22vh,380px);background:radial-gradient(ellipse at 0% 100%,rgba(249,115,22,0.12) 0%,transparent 70%);pointer-events:none;z-index:0;animation:ppGlowPulse 6s ease-in-out infinite reverse}
@keyframes ppGlowPulse{0%,100%{opacity:0.4}50%{opacity:0.85}}
.pp-stats-section{position:relative;z-index:10;width:clamp(420px,42vw,780px);display:flex;flex-direction:column;gap:clamp(3px,0.4vh,6px);max-height:100%;overflow-y:auto}
.pp-stats-section::-webkit-scrollbar{width:3px}.pp-stats-section::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.10);border-radius:2px}
.pp-header{position:relative;width:100%;min-height:clamp(72px,11vh,115px);display:flex;margin-bottom:clamp(6px,1vh,14px);flex-shrink:0}
.pp-header .pp-bar{width:clamp(24px,3.5vh,42px);height:100%;background:linear-gradient(to bottom,#fbbf24,#f97316,#c2410c);clip-path:polygon(0 0,100% 0,88% 100%,0 100%);position:absolute;left:0;z-index:20}
.pp-header .pp-main{width:92%;height:100%;background:linear-gradient(105deg,#1e1045 0%,#2d1a6e 50%,#1a0f3c 100%);margin-left:clamp(18px,2.5vh,28px);clip-path:polygon(0 0,100% 0,92% 100%,0 100%);display:flex;flex-direction:column;justify-content:center;padding:0 clamp(36px,4.5vw,56px) 0 clamp(36px,4.5vw,56px);box-shadow:0 6px 28px rgba(0,0,0,0.45);position:relative;overflow:hidden;transform-style:preserve-3d;transition:box-shadow 0.8s ease;animation:ppHeaderGlow 3s ease-in-out infinite}
.pp-header .pp-main::after{content:'';position:absolute;top:0;right:0;bottom:0;width:30%;background:linear-gradient(90deg,transparent,rgba(123,97,255,0.08));pointer-events:none}
@keyframes ppHeaderGlow{0%,100%{box-shadow:0 6px 28px rgba(0,0,0,0.45),0 0 20px rgba(123,97,255,0.10)}50%{box-shadow:0 6px 38px rgba(0,0,0,0.55),0 0 40px rgba(123,97,255,0.18)}}
.pp-header .pp-name{font-size:clamp(26px,5.5vh,56px);font-weight:900;font-style:italic;letter-spacing:0.02em;color:#fff;text-shadow:2px 2px 6px rgba(0,0,0,0.50),0 0 30px rgba(168,85,247,0.30),0 0 60px rgba(168,85,247,0.12);text-transform:uppercase;line-height:1.06;margin:0;animation:ppNameGlow 3s ease-in-out infinite}
@keyframes ppNameGlow{0%,100%{text-shadow:2px 2px 6px rgba(0,0,0,0.50),0 0 30px rgba(168,85,247,0.25)}50%{text-shadow:2px 2px 6px rgba(0,0,0,0.50),0 0 50px rgba(168,85,247,0.45),0 0 80px rgba(168,85,247,0.15)}}
.pp-header .pp-role{font-size:clamp(13px,2.6vh,26px);font-weight:300;font-style:italic;color:#a5b4fc;margin-top:clamp(1px,0.3vh,3px);text-transform:uppercase;letter-spacing:0.1em}
.pp-stats-table{display:flex;flex-direction:column;gap:clamp(2px,0.35vh,5px);width:92%;margin-left:clamp(18px,2.5vh,28px)}
.pp-section-title{display:flex;height:clamp(32px,4.5vh,46px);width:100%;clip-path:polygon(0 0,100% 0,92% 100%,0 100%);overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.30);flex-shrink:0}
.pp-section-title .pp-st-bar{width:clamp(10px,1.2vh,14px);background:#7c3aed;flex-shrink:0;box-shadow:0 0 12px rgba(124,58,237,0.5);animation:ppBarPulse 2.5s ease-in-out infinite}
.pp-section-title .pp-st-text{flex:1;background:linear-gradient(90deg,#fff 0%,#f8fafc 100%);display:flex;align-items:center;padding:0 clamp(12px,1.5vw,22px)}
.pp-section-title .pp-st-text span{font-size:clamp(14px,2.5vh,26px);font-weight:700;font-style:italic;color:#1e1b4b;letter-spacing:0.05em}
.pp-stat-row{display:flex;min-height:clamp(30px,4.2vh,46px);clip-path:polygon(0 0,100% 0,92% 100%,0 100%);overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,0.22);opacity:0;flex-shrink:0;transform-style:preserve-3d;transition:transform 0.35s cubic-bezier(0.23,1,0.32,1),box-shadow 0.35s ease}
.pp-stat-row:hover{transform:translateX(4px) translateZ(12px) rotateX(-2deg);box-shadow:0 8px 24px rgba(0,0,0,0.35),0 0 16px rgba(168,85,247,0.12)}
.pp-stat-row .pp-sr-bar{width:clamp(10px,1.2vh,14px);background:#a855f7;flex-shrink:0}
.pp-stat-row .pp-sr-label{flex:0 0 42%;background:linear-gradient(90deg,rgba(255,255,255,0.94) 0%,rgba(255,255,255,0.85) 100%);display:flex;align-items:center;padding:0 clamp(10px,1.4vw,20px);border-bottom:1px solid #e2e8f0}
.pp-stat-row .pp-sr-label span{font-size:clamp(11px,1.9vh,20px);font-weight:600;font-style:italic;color:#1e293b;letter-spacing:0.04em;text-transform:uppercase;white-space:nowrap}
.pp-stat-row .pp-sr-value{flex:1;background:linear-gradient(105deg,#1e1045 0%,#2d1a6e 60%,#1a0f3c 100%);display:flex;align-items:center;justify-content:center;border-left:3px solid rgba(255,255,255,0.18);padding:clamp(4px,0.5vh,8px) clamp(8px,1vw,16px);min-width:0}
.pp-stat-row .pp-sr-value span{font-size:clamp(11px,2vh,20px);font-weight:700;font-style:italic;color:#fff;letter-spacing:0.03em;line-height:1.2;text-align:center;word-break:break-word}
/* Cascading widths */
.pp-stat-row:nth-child(2){width:98%}
.pp-stat-row:nth-child(3){width:96%}
.pp-stat-row:nth-child(4){width:94%}
.pp-stat-row:nth-child(5){width:92%}
.pp-stat-row:nth-child(6){width:90%}
.pp-stat-row:nth-child(7){width:88%}
.pp-stat-row:nth-child(8){width:86%}
.pp-stat-row:nth-child(9){width:84%}
.pp-stat-row:nth-child(10){width:82%}
/* Achievement row — taller for longer text */
.pp-stat-row.pp-row-achievement{min-height:clamp(40px,5.5vh,56px)}
.pp-stat-row.pp-row-achievement .pp-sr-value span{font-size:clamp(10px,1.8vh,18px);font-weight:500}
/* Player image section */
.pp-player-section{position:relative;z-index:0;height:clamp(420px,68vh,780px);width:clamp(340px,36vw,680px);display:flex;align-items:flex-end;justify-content:center;flex-shrink:0;transform-style:preserve-3d;transform:translateZ(-15px) rotateY(2deg)}
.pp-player-section::after{content:'';position:absolute;bottom:8%;left:50%;transform:translateX(-50%);width:clamp(140px,18vw,320px);height:clamp(140px,18vw,320px);border-radius:50%;background:radial-gradient(ellipse,rgba(168,85,247,0.18) 0%,rgba(249,115,22,0.08) 50%,transparent 70%);pointer-events:none;z-index:-1;animation:ppImgGlow 3.5s ease-in-out infinite}
@keyframes ppImgGlow{0%,100%{opacity:0.5;transform:translateX(-50%) scale(1)}50%{opacity:0.9;transform:translateX(-50%) scale(1.2)}}
.pp-player-section img{width:100%;height:auto;max-height:100%;object-fit:contain;object-position:center bottom;filter:drop-shadow(0 16px 36px rgba(0,0,0,0.50));animation:ppImgFloat 5s ease-in-out infinite;transform:translateZ(20px)}
.pp-player-section .pp-no-img{width:100%;height:100%;display:flex;align-items:flex-end;justify-content:center;font-size:clamp(100px,18vh,280px);font-weight:900;color:rgba(255,255,255,0.05);line-height:1;padding-bottom:5%}
@keyframes ppImgFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-6px) scale(1.01)}}
@keyframes ppBarPulse{0%,100%{box-shadow:0 0 8px rgba(124,58,237,0.4)}50%{box-shadow:0 0 20px rgba(124,58,237,0.7)}}
/* ── RESPONSIVE ── */
@media(max-width:1300px){.pp-overlay{padding-left:clamp(8px,2vw,24px)}.pp-stats-section{width:clamp(360px,48vw,580px)}.pp-player-section{width:clamp(280px,34vw,460px);height:clamp(360px,60vh,540px)}.pp-header .pp-name{font-size:clamp(22px,4.5vh,42px)}.pp-stat-row .pp-sr-label span{font-size:clamp(10px,1.6vh,16px)}.pp-stat-row .pp-sr-value span{font-size:clamp(10px,1.7vh,17px)}}
@media(max-width:1000px){.pp-overlay{padding:0 clamp(8px,2vw,14px) clamp(12px,3vh,20px) clamp(8px,2vw,14px);gap:clamp(8px,1.5vw,16px);align-items:center}.pp-stats-section{width:55%}.pp-header{min-height:clamp(56px,10vh,80px)}.pp-player-section{width:40%;height:clamp(260px,52vh,400px)}.pp-header .pp-name{font-size:clamp(17px,3.5vh,28px)}.pp-header .pp-role{font-size:clamp(10px,1.8vh,14px)}.pp-stat-row .pp-sr-label{flex:0 0 44%}.pp-stat-row .pp-sr-label span{font-size:clamp(8px,1.4vh,12px)}.pp-stat-row .pp-sr-value span{font-size:clamp(8px,1.4vh,13px)}.pp-section-title{height:clamp(26px,4vh,36px)}.pp-section-title .pp-st-text span{font-size:clamp(10px,1.8vh,16px)}.pp-stat-row{min-height:clamp(22px,3.5vh,32px)}.pp-stat-row.pp-row-achievement{min-height:clamp(30px,4.5vh,40px)}}
@media(max-width:700px){.pp-overlay{flex-direction:column-reverse;align-items:center;padding:clamp(4px,1.5vh,10px);gap:clamp(3px,0.8vh,6px)}.pp-stats-section{width:96%;max-height:none}.pp-header{min-height:clamp(44px,8vh,64px)}.pp-player-section{width:80%;height:auto;max-height:26vh;flex-shrink:0}.pp-player-section img{max-height:26vh}.pp-player-section .pp-no-img{font-size:clamp(60px,12vh,120px);padding-bottom:0}.pp-header .pp-name{font-size:clamp(15px,3vh,22px)}.pp-header .pp-role{font-size:clamp(9px,1.5vh,12px)}.pp-stat-row{min-height:clamp(20px,2.8vh,26px)}.pp-stat-row .pp-sr-label{flex:0 0 40%}.pp-stat-row .pp-sr-label span{font-size:clamp(7px,1.1vh,10px)}.pp-stat-row .pp-sr-value span{font-size:clamp(7px,1.1vh,11px)}.pp-section-title{height:clamp(20px,2.8vh,26px)}.pp-section-title .pp-st-text span{font-size:clamp(8px,1.3vh,12px)}.pp-stat-row.pp-row-achievement{min-height:clamp(26px,3.5vh,32px)}.pp-stat-row.pp-row-achievement .pp-sr-value span{font-size:clamp(7px,1vh,10px)}}
/* 4K+ scaling */
@media(min-width:2200px){.pp-stats-section{width:clamp(600px,40vw,900px)}.pp-player-section{width:clamp(500px,36vw,800px);height:clamp(600px,62vh,900px)}.pp-header .pp-name{font-size:clamp(40px,4vh,72px)}.pp-stat-row .pp-sr-label span{font-size:clamp(16px,1.6vh,26px)}.pp-stat-row .pp-sr-value span{font-size:clamp(16px,1.6vh,26px)}}
</style>
</head>
<body>
<div id="cd"></div><div id="recon">Reconnecting...</div>

<!-- Chase -->
<div id="chWrap"><div id="chBar"><div id="chIcon">&#127919;</div><div id="chText"></div></div></div>

<!-- Bar -->
<div id="wrap"><div id="bar">
 <!-- TEAM -->
 <div id="team" class="bgo">
  <div id="tLogo"><img id="tlImg" src="" class="hidden" onerror="this.classList.add('hidden');$('tInit').classList.remove('hidden')"><span id="tInit" class="hidden"></span></div>
  <div id="tInfo"><div id="tName"><?php echo htmlspecialchars($m['a_short']?:$m['a_name']) ?></div><div id="tVs">vs <?php echo htmlspecialchars($m['b_short']?:$m['b_name']) ?></div></div>
  <div id="tScoreWrap"><span id="tScore">0</span><span id="tSep">/</span><span id="tWkts">0</span></div><span id="tOvers">0.0 overs</span>
 </div>
 <!-- BATSMAN 1 -->
 <div id="p1" class="pl bgo">
  <div class="pimg"><img id="p1img" src="" class="hidden" onerror="this.classList.add('hidden');$('p1ini').classList.remove('hidden')"><span id="p1ini" class="pini hidden"></span></div>
  <div class="info"><div class="nm"><span class="nml" id="p1n1">--</span><span class="nml" id="p1n2"></span><span class="sdot"><svg viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg></span></div><div><span id="p1r" class="pr">0</span><span id="p1b" class="pb">(0)</span></div></div><div class="grad o"></div>
 </div>
 <!-- BATSMAN 2 -->
 <div id="p2" class="pl bgo">
  <div class="pimg"><img id="p2img" src="" class="hidden" onerror="this.classList.add('hidden');$('p2ini').classList.remove('hidden')"><span id="p2ini" class="pini hidden"></span></div>
  <div class="info"><div class="nm"><span class="nml" id="p2n1">--</span><span class="nml" id="p2n2"></span></div><div><span id="p2r" class="pr">0</span><span id="p2b" class="pb">(0)</span></div></div><div class="grad o"></div>
 </div>
 <!-- CRR -->
 <div id="crr" class="bgg"><span id="crrL">CRR</span><span id="crrV">0.00</span></div>
 <!-- BOWLING -->
 <div id="bWrap" class="bgb">
  <div class="pl bgb">
   <div class="pimg"><img id="p3img" src="" class="hidden" onerror="this.classList.add('hidden');$('p3ini').classList.remove('hidden')"><span id="p3ini" class="pini hidden"></span></div>
   <div class="info"><div class="nm"><span class="nml" id="p3n1">--</span><span class="nml" id="p3n2"></span><span class="bdot"></span></div><div class="flex justify-between"><span id="p3f" class="pr">0-0</span><span id="p3o" class="pb">(0.0)</span></div></div><div class="grad b"></div>
  </div>
  <div id="bSep"></div>
  <div id="tCol">
   <div id="dots"><div class="dot e">·</div><div class="dot e">·</div><div class="dot e">·</div><div class="dot e">·</div><div class="dot e">·</div><div class="dot e">·</div></div>
   <div id="opp"><img id="oppImg" src="" class="hidden" onerror="this.classList.add('hidden');$('oppInit').classList.remove('hidden')"><span id="oppInit" class="hidden"></span></div>
  </div>
 </div>
 <!-- EVENT OVERLAY -->
 <div id="evOvl"><span id="evTxt"></span></div>
</div></div>

<!-- Views -->
<div id="fvBat" class="fv"><div class="fvc" style="max-width:min(96vw,1800px);background:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important;padding:0;overflow:visible" id="fvBatB"></div></div>
<div id="fvBwl" class="fv"><div class="fvc" style="max-width:min(96vw,1800px);background:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important;padding:0;overflow:visible" id="fvBwlB"></div></div>
<div id="fvXI" class="fv"><div class="fvc gls-panel" style="max-width:min(96vw,1800px);max-height:96vh;overflow-y:auto;padding:clamp(14px,2vh,20px) clamp(16px,2vw,22px)" id="fvXIB"></div></div>
<div id="fvToss" class="fv"><div class="fvc gls-panel" style="max-width:min(96vw,1200px);padding:clamp(24px,3.5vh,40px) clamp(30px,4vw,44px)" id="fvTossB"></div></div>
<div id="fvXISingle" class="fv"><div class="fvc gls-panel" style="max-width:min(96vw,1800px);max-height:96vh;overflow-y:auto;padding:clamp(14px,2vh,20px) clamp(16px,2vw,22px)" id="fvXISB"></div></div>
<div id="fvSum" class="fv"><div class="fvc" style="max-width:min(96vw,1680px);max-height:94vh;overflow-y:auto;background:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important;padding:0;overflow:visible" id="fvSumB"></div></div>
<div id="fvPlayer" class="fv"><div class="fvc" style="max-width:min(96vw,1700px);max-height:94vh;overflow:visible;background:transparent!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;box-shadow:none!important;padding:0;overflow:visible;perspective:1400px" id="fvPlayerB"></div></div>

<script>
window.$=function(id){return document.getElementById(id)}
var MID=<?php echo $mid ?>,LE=0,at=0,tm=null,es=null,cv='blank',_js='',_winFired=!1;
$('bar').style.display='none';

$('tName').textContent=<?php echo json_encode(htmlspecialchars($m['a_short']?:$m['a_name'])) ?>.toUpperCase();
$('tVs').textContent='vs '+<?php echo json_encode(htmlspecialchars($m['b_short']?:$m['b_name'])) ?>.toUpperCase();
var AL=<?php echo json_encode($m['a_logo']) ?>,BL=<?php echo json_encode($m['b_logo']) ?>;
si('tlImg','tInit',AL,<?php echo json_encode($m['a_short']?:$m['a_name']) ?>);
si('oppImg','oppInit',BL,<?php echo json_encode($m['b_short']?:$m['b_name']) ?>);

function si(ii,ni,pa,na){var im=$(ii),el=$(ni);if(pa){im.src=pa;im.classList.remove('hidden');el.classList.add('hidden')}else if(na){im.classList.add('hidden');el.classList.remove('hidden');el.textContent=na.substring(0,2).toUpperCase()}}
function sn(pf,na){var p=(na||'--').toUpperCase().split(' ');$(pf+'n1').textContent=p[0]||'--';$(pf+'n2').textContent=p.slice(1).join(' ')||''}
function sp(ii,ni,pa,na){var im=$(ii),el=$(ni);if(pa){im.src=pa;im.classList.remove('hidden');el.classList.add('hidden')}else{im.classList.add('hidden');el.classList.remove('hidden');el.textContent=(na||'?').charAt(0).toUpperCase()}}

var pv={r:0,w:0,o:'0.0',crr:'0.00',on:-1,ob:0,oR:0,sq:0};

function up(d){
 if(!d)return;
 if(d.output_view&&d.output_view!==cv)sw(d.output_view);
 var ov=d.output_view||'';
 if((ov.indexOf('batting')===0)&&d.batting_card)rb(d);
 else if((ov.indexOf('bowling')===0)&&d.bowling_card)rbl(d);
  else if(ov==='xi'&&d.playing_xi)rxi(d);
  else if(ov.indexOf('xi_bat')===0&&d.playing_xi){
   var bs='a',m=d.match||{};
   if(ov==='xi_bat_1st')bs=m.batting_first==m.team_a_id?'a':'b';
   else if(ov==='xi_bat_2nd')bs=m.batting_first==m.team_a_id?'b':'a';
   else if(d.batting_team)bs=d.batting_team.id==m.team_a_id?'a':'b';
   rxis(d,bs,true);
  }
  else if(ov.indexOf('xi_bowl')===0&&d.playing_xi){
   var bs='b',m=d.match||{};
   if(ov==='xi_bowl_1st')bs=m.batting_first==m.team_a_id?'b':'a';
   else if(ov==='xi_bowl_2nd')bs=m.batting_first==m.team_a_id?'a':'b';
   else if(d.bowling_team)bs=d.bowling_team.id==m.team_a_id?'a':'b';
   rxis(d,bs,false);
  }
 else if((ov.indexOf('summary')===0)&&d.match_summary)rsu(d);
 else if(ov==='toss')rtoss(d);
 if(ov==='player_profile'&&d.player_profile)rpp(d.player_profile);
 if(d.match_not_started)return;
 var bt=d.batting_team,s=d.striker,ns=d.non_striker,bw=d.bowler;
 var isN=d.last_updated_epoch&&d.last_updated_epoch>LE;if(d.last_updated_epoch)LE=d.last_updated_epoch;
 var wk=bt&&bt.wickets>pv.w;
 if(bt){
  $('tName').textContent=(bt.short_name||bt.name||'--').toUpperCase();
  if(bt.logo)si('tlImg','tInit',bt.logo,(bt.short_name||bt.name||''));
  if(bt.runs!==pv.r)$('tScore').textContent=bt.runs;
  if(bt.wickets!==pv.w){$('tWkts').textContent=bt.wickets;if(wk)aw();}
   $('tOvers').textContent=(bt.overs||'0.0')+' overs';
 }
 var vs='';if(d.innings_number===99)vs='<span class="so-badge">&#9889; SUPER OVER</span>';else if(d.bowling_team){vs='vs '+(d.bowling_team.short_name||d.bowling_team.name||'--').toUpperCase();if(d.bowling_team.logo)si('oppImg','oppInit',d.bowling_team.logo,(d.bowling_team.short_name||d.bowling_team.name||''))}
 else vs='vs --';
 if(vs!==$('tVs').textContent){$('tVs').textContent=vs;if(gsap&&vs!=='vs --')gsap.fromTo($('tVs'),{scale:1.12},{scale:1,duration:.3,ease:'power2.out'})}
 if(cv==='scorebug'){if(d.innings_number===2&&d.target>0&&bt){var rn=d.target-bt.runs,mb=(d.max_overs||20)*6,bu=(d.overs_completed||0)*6+(d.current_ball_in_over||0),br=Math.max(0,mb-bu);$('chText').innerHTML=rn<=0?'<span class="chw">'+(bt.short_name||bt.name||'').toUpperCase()+' WIN!</span>':(bt.short_name||bt.name||'').toUpperCase()+' NEED <span class="cht">'+rn+'</span> RUNS <span class="chb">| '+br+' BALLS</span>';$('chIcon').innerHTML=rn<=0?'&#127942;':'&#127919;';if(!$('chWrap').classList.contains('on')){$('chWrap').classList.add('on');$('chBar').classList.add('pulse');if(gsap)gsap.fromTo('#chBar',{opacity:0,y:-12},{opacity:1,y:0,duration:.4,ease:'power3.out'})}if(rn<=0)$('chBar').classList.remove('pulse')}else{if($('chWrap').classList.contains('on')){if(gsap)gsap.to('#chBar',{opacity:0,y:-8,duration:.25,ease:'power2.in'});$('chWrap').classList.remove('on');$('chBar').classList.remove('pulse')}}}
  if(s){var wk1=(s.role||'').toLowerCase().includes('wk');sn('p1',s.name);if(wk1){var p=$('p1n1').parentNode;var c1=$('p1n1'),c2=$('p1n2');p.innerHTML='<span class="nml" id="p1n1">'+c1.textContent+'</span><span class="nml" id="p1n2">'+c2.textContent+'</span><span class="wk-label">(WK)</span>'}sp('p1img','p1ini',s.photo,s.name);$('p1r').textContent=s.runs||0;$('p1b').textContent='('+(s.balls||0)+')'}
  if(ns){var wk2=(ns.role||'').toLowerCase().includes('wk');sn('p2',ns.name);if(wk2){var p2=$('p2n1').parentNode;var d1=$('p2n1'),d2=$('p2n2');p2.innerHTML='<span class="nml" id="p2n1">'+d1.textContent+'</span><span class="nml" id="p2n2">'+d2.textContent+'</span><span class="wk-label">(WK)</span>'}sp('p2img','p2ini',ns.photo,ns.name);$('p2r').textContent=ns.runs||0;$('p2b').textContent='('+(ns.balls||0)+')'}
 var cr=parseFloat(d.current_run_rate||0).toFixed(2);if(cr!==pv.crr){$('crrV').textContent=cr;if(gsap)gsap.fromTo($('crrV'),{scale:1.2},{scale:1,duration:.3,ease:'power2.out'})}
  if(bw){var wk3=(bw.role||'').toLowerCase().includes('wk');sn('p3',bw.name);if(wk3){var p3=$('p3n1').parentNode;var e1=$('p3n1'),e2=$('p3n2');p3.innerHTML='<span class="nml" id="p3n1">'+e1.textContent+'</span><span class="nml" id="p3n2">'+e2.textContent+'</span><span class="wk-label">(WK)</span>'}sp('p3img','p3ini',bw.photo,bw.name);$('p3f').textContent=(bw.wickets_taken||0)+'-'+(bw.runs_conceded||0);$('p3o').textContent='('+(bw.overs_bowled||'0.0')+')'}
 var ls=d.last_5_balls||[],ob=d.this_over_balls||[],on=d.overs_completed!==undefined?d.overs_completed:pv.on,io=(on!==pv.on&&pv.on!==-1);
 if(io||JSON.stringify(ob)!==JSON.stringify(pv.ob)){if(cv==='scorebug')rd(ob,io||isN,io)}
 if(cv==='scorebug'&&d.sequence_id&&d.sequence_id>pv.sq&&ls.length>0){var lt=ls[ls.length-1];if(lt.wicket)fe('wicket');else if(lt.runs===6&&!lt.extra)fe('six');else if(lt.runs===4&&!lt.extra)fe('four');else if(lt.extra==='wd')fe('wide');else if(lt.extra==='nb')fe('noball')}
 if(cv==='scorebug'&&io&&pv.oR===0&&pv.ob&&pv.ob.length>0)fe('maiden');
 // Match win detection — fire once per match (survives reload via localStorage)
 if(d.match&&d.match.status==='completed'&&!_winFired){
  var wk='win_'+MID;if(!localStorage.getItem(wk)){
  var winName='';if(d.batting_team&&d.bowling_team){var btR=d.batting_team.runs||0,tgt=d.target||0;winName=tgt>0&&btR>=tgt?(d.batting_team.short_name||d.batting_team.name):(d.bowling_team.short_name||d.bowling_team.name)}
  fe('win',winName);_winFired=!0;localStorage.setItem(wk,'1');}
 }
 pv={r:bt?bt.runs:0,w:bt?bt.wickets:0,o:bt?bt.overs:'0.0',crr:cr,on:on,ob:ob,oR:d.this_over?d.this_over.runs:0,sq:d.sequence_id||pv.sq};
}

function sw(v){
 var old=document.querySelector('.fv.active');
 function showNew(){
  document.querySelectorAll('.fv').forEach(function(e){e.classList.remove('active')});
  $('bar').style.display='';if($('chWrap').classList.contains('on'))$('chWrap').classList.remove('on');
  if(v==='scorebug'){if(gsap){gsap.fromTo('#bar',{opacity:0,y:40,rotationX:-16,transformOrigin:'center bottom'},{opacity:1,y:0,rotationX:0.8,duration:.5,ease:'power3.out',onComplete:function(){gsap.to('#bar>div',{y:-1.5,rotationX:0.8,duration:3,ease:'sine.inOut',yoyo:true,repeat:-1})}})}}
  else if(v==='blank'){$('bar').style.display='none'}
  else{$('bar').style.display='none';var t=null;
   if(v.indexOf('batting')===0)t='fvBat';
   else if(v.indexOf('bowling')===0)t='fvBwl';
    else if(v==='xi')t='fvXI';
    else if(v.indexOf('xi_bat')===0||v.indexOf('xi_bowl')===0)t='fvXISingle';
   else if(v.indexOf('summary')===0)t='fvSum';
   else if(v==='toss')t='fvToss';
   else if(v==='player_profile')t='fvPlayer';
    if(t){var el=$(t);if(el){el.classList.add('active');if(gsap){gsap.from(el,{opacity:0,rotationX:12,y:28,transformOrigin:'center bottom -20px',duration:.5,ease:'power3.out',onComplete:function(){gsap.set(el,{rotationX:0.6,transformOrigin:'center bottom'});gsap.to(el,{y:-3,rotationX:0.6,duration:2.8,ease:'sine.inOut',yoyo:true,repeat:-1})}})}}}}
  cv=v;_js=v;
 }
  if(old&&gsap){gsap.to(old,{opacity:0,rotationX:-6,y:-16,duration:.18,ease:'power2.in',onComplete:showNew})}
 else showNew();
}

function rb(d){var bt=d.batting_team,c=d.batting_card||[],m=d.match||{},xi=d.playing_xi||{a:[],b:[]};
 var btLogo=bt?bt.logo:'',btName=bt?(bt.short_name||bt.name||'TEAM'):'TEAM',btShort=(bt?(bt.short_name||bt.name||'TEAM'):'TEAM').substring(0,2).toUpperCase();
 var matchLogo=m.match_logo||'',loc=m.location||'',inn=d.innings_number||0;
 var innLabel=inn===99?'SUPER OVER':(inn===2?'2ND INNINGS':'1ST INNINGS');
 var btId=bt?bt.id:0,sid=d.striker?d.striker.id:0,nsid=d.non_striker?d.non_striker.id:0;
 var oppName='';if(d.bowling_team)oppName=(d.bowling_team.short_name||d.bowling_team.name||'').toUpperCase();
 var teamNames={'a':(m.team_a_short||m.team_a_name||'TEAM A'),'b':(m.team_b_short||m.team_b_name||'TEAM B')};
 var xiKey='a';if(btId===m.team_b_id)xiKey='b';
 var allXI=xi[xiKey]||[];
 // Build XI lookup map for captain/WK badges
 var xiMap={};allXI.forEach(function(p){xiMap[p.id]=p});
 // Build batted player ID set
 var battedIds={};c.forEach(function(p){battedIds[p.id]=true});
 // Yet-to-bat players from XI
 var yetToBat=[];allXI.forEach(function(p){if(!battedIds[p.id]&&!p.dismissal)yetToBat.push(p)});
 // Key performer — ICC style: highest runs → best SR → not-out preferred → most boundaries
 var kp=null;
 c.forEach(function(p){
  if(!kp){kp=p;return}
  if(p.runs>kp.runs){kp=p;return}
  if(p.runs===kp.runs){
   var pSR=p.balls>0?p.runs/p.balls*100:0;
   var kpSR=kp.balls>0?kp.runs/kp.balls*100:0;
   if(pSR>kpSR){kp=p;return}
   if(pSR===kpSR){
    if(!p.dismissal&&kp.dismissal){kp=p;return}
    if(!p.dismissal===!kp.dismissal){
     if((p.fours||0)+(p.sixes||0)>(kp.fours||0)+(kp.sixes||0))kp=p;
    }
   }
  }
 });

 // Left panel accent color (batting team a=orange, b=blue)
 var leftGrad='';if(btId===m.team_a_id)leftGrad='background:linear-gradient(90deg,#FF9800,#e65100);box-shadow:0 10px 30px rgba(255,152,0,0.30)';
 else leftGrad='background:linear-gradient(90deg,#0288D1,#01579b);box-shadow:0 10px 30px rgba(2,136,209,0.30)';

 // ---- Build HTML ----
 var h='<div class="bgp-wrap" style="width:100%">';

 // LEFT: Team logo panel
 h+='<div class="bgp-left" style="'+leftGrad+'"><div class="bgp-logo-wrap">';
 if(btLogo)h+='<img src="'+e(btLogo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="bgp-logo-init" style="display:none">'+btShort+'</span>';
 else h+='<span class="bgp-logo-init">'+btShort+'</span>';
 h+='</div></div>';

 // MAIN: Header + rows + footer
 h+='<div class="bgp-main">';
 // Header
 h+='<div class="bgp-header" style="background:linear-gradient(90deg,#003d80,#0066cc);padding:clamp(12px,1.8vh,18px) clamp(16px,2vw,22px);border-radius:14px 14px 0 0;border-bottom:3px solid rgba(255,255,255,0.12);position:relative;overflow:hidden;margin:-8px -8px 10px -8px">';
 h+='<div style="position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.3;pointer-events:none"></div>';
 h+='<div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between"><div style="flex:1"><div class="bgp-team-name" style="color:#fff">'+e(btName)+'</div>';
 h+='<div class="bgp-match-info" style="color:rgba(255,255,255,0.70)">'+e(m.title||(oppName?'VS '+oppName:''))+(loc?' &middot; '+e(loc):'')+' &middot; BATTING &middot; '+innLabel+'</div></div>';
 h+='</div></div>';

 // Rows - batted players
 h+='<div class="bgp-rows">';
 // Column header bar
 h+='<div class="bgp-col-bar"><span style="width:clamp(160px,15vw,250px);flex-shrink:0;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">BATSMAN</span><span style="flex:1;min-width:0;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">STATUS</span><span style="width:clamp(46px,5vw,64px);flex-shrink:0;text-align:center;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">R</span><span style="width:clamp(36px,3.5vw,48px);flex-shrink:0;text-align:center;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">B</span><span style="width:clamp(26px,2.5vw,34px);flex-shrink:0;text-align:center;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">4s</span><span style="width:clamp(26px,2.5vw,34px);flex-shrink:0;text-align:center;font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">6s</span></div>';
 c.forEach(function(p,i){
  var out=!!p.dismissal,isStriker=!out&&p.id===sid&&p.id>0,isNonStriker=!out&&p.id===nsid&&p.id>0&&p.id!==sid;
  var isActive=isStriker||isNonStriker;
  var rowClass=out?'dim':(isActive?'active':'');
  var dt='';var dtClass='';
  if(out){dt=(p.dismissal||'').replace(/_/g,' ').toUpperCase();dtClass='dismissal'}
  else if(isStriker){dt='ON STRIKE';dtClass='no'}
  else if(isNonStriker){dt='NON STRIKE';dtClass='no'}
  else{dt='NOT OUT';dtClass='no'}
  var pillClass=out?'out':'runs';
  h+='<div class="bgp-row '+rowClass+'" style="opacity:1">';
   var pxi=xiMap[p.id];
   var capTag=pxi&&(pxi.captain||pxi.is_captain)?'<span class="bdg-c">(C)</span>':'';
   var wkTag=pxi&&(pxi.role||'').toLowerCase().includes('wk')?'<span class="wk-tag">(WK)</span>':'';
  h+='<div class="bgp-name">'+e(p.name)+capTag+wkTag+'</div>';
  h+='<div class="bgp-detail '+dtClass+'">'+dt+'</div>';
  h+='<div class="bgp-run-pill '+pillClass+'">'+p.runs+'</div>';
  h+='<div class="bgp-balls">'+p.balls+'</div>';
  h+='<div class="bgp-xs">'+(p.fours||0)+'</div>';
  h+='<div class="bgp-xs">'+(p.sixes||0)+'</div>';
  h+='</div>';
 });
  yetToBat.forEach(function(p){
   var capTag2=(p.captain||p.is_captain)?'<span class="bdg-c">(C)</span>':'';
   var wkTag2=(p.role||'').toLowerCase().includes('wk')?'<span class="wk-tag">(WK)</span>':'';
  h+='<div class="bgp-row dim" style="opacity:1"><div class="bgp-name">'+e(p.name)+capTag2+wkTag2+'</div><div class="bgp-detail dim-detail">YET TO BAT</div><div class="bgp-run-pill empty"></div><div class="bgp-balls"></div><div class="bgp-xs">-</div><div class="bgp-xs">-</div></div>';
 });
 h+='</div>'; // end bgp-rows

 // Footer
 h+='<div class="bgp-footer"><div class="bgp-meta"><div>EXTRAS <span>'+(bt?bt.extras||0:0)+'</span></div><div>OVERS <span>'+(bt?bt.overs||'0.0':'0.0')+'</span></div></div>';
 h+='<div class="bgp-total">'+(bt?bt.runs:'0')+'-'+(bt?bt.wickets:'0')+'</div></div>';
 h+='</div>'; // end bgp-main

 // RIGHT: Key Performer panel
 h+='<div class="bgp-right">';
 if(matchLogo)h+='<div style="text-align:center;padding:clamp(14px,2vh,20px) 0 clamp(10px,1.5vh,14px)"><div style="width:clamp(140px,18vh,260px);height:clamp(140px,18vh,260px);border-radius:18px;overflow:hidden;margin:0 auto;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.10);border:3px solid rgba(255,255,255,0.18);box-shadow:0 8px 30px rgba(0,0,0,0.40)"><img src="'+e(matchLogo)+'" style="width:90%;height:90%;object-fit:contain" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span style="display:none;font-size:clamp(36px,5vh,56px);color:rgba(255,255,255,0.30)">&#127951;</span></div></div>';
 if(kp){
  if(kp.photo)h+='<div class="bgp-perf-img-wrap"><img class="bgp-perf-img" src="'+e(kp.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="bgp-perf-img-placeholder" style="display:none">'+(kp.name||'?').charAt(0).toUpperCase()+'</span></div>';
  else h+='<div class="bgp-perf-img-wrap"><span class="bgp-perf-img-placeholder">'+(kp.name||'?').charAt(0).toUpperCase()+'</span></div>';
    h+='<div class="bgp-perf-label-wrap"><div class="bgp-perf-badge"><span>KEY PERFORMER</span></div><div class="bgp-perf-status">'+(kp.dismissal?(kp.dismissal||'').replace(/_/g,' ').toUpperCase():'NOT OUT')+' &middot; '+kp.runs+(kp.balls?' ('+kp.balls+')':'')+'</div><div class="bgp-perf-name"><span>'+e(kp.name)+((kp.role||'').toLowerCase().includes('wk')?' <span class="wk-tag">(WK)</span>':'')+'</span></div></div>';
  }else{
   h+='<div class="bgp-perf-img-wrap"><span class="bgp-perf-img-placeholder">&#9733;</span></div>';
  }
  h+='</div>'; // end bgp-right

  h+='</div>'; // end bgp-wrap

  $('fvBatB').innerHTML=h;
 // GSAP entrance animation
  if(gsap){gsap.set('#fvBatB .bgp-row',{opacity:0,rotationX:14,y:10,transformOrigin:'center top'});gsap.to('#fvBatB .bgp-row',{opacity:1,rotationX:0,y:0,duration:0.4,stagger:0.035,ease:'power3.out'});}
}
function rbl(d){var bt=d.batting_team,blt=d.bowling_team,c=d.bowling_card||[],m=d.match||{},bw=d.bowler,xi=d.playing_xi||{a:[],b:[]};
 var bltLogo=blt?blt.logo:'',bltName=blt?(blt.short_name||blt.name||'TEAM'):'TEAM',bltShort=(blt?(blt.short_name||blt.name||'TEAM'):'TEAM').substring(0,2).toUpperCase();
 var matchLogo=m.match_logo||'',loc=m.location||'',bltId=blt?blt.id:0,inn=d.innings_number||0;
 var innLabel=inn===99?'SUPER OVER':(inn===2?'2ND INNINGS':'1ST INNINGS');
 var bowlId=bw?bw.id:0;
 var oppName='';if(bt)oppName=(bt.short_name||bt.name||'').toUpperCase();
 // Key performer — ICC style: most wickets → best economy → best average → most maidens
 var kp=null;
 c.forEach(function(p){
  if(!kp){kp=p;return}
  if(p.wickets>kp.wickets){kp=p;return}
  if(p.wickets===kp.wickets){
   if(p.econ<kp.econ){kp=p;return}
   if(p.econ===kp.econ){
    var pAvg=p.wickets>0?p.runs/p.wickets:999;
    var kpAvg=kp.wickets>0?kp.runs/kp.wickets:999;
    if(pAvg<kpAvg){kp=p;return}
    if(pAvg===kpAvg){
     if((p.maidens||0)>(kp.maidens||0))kp=p;
    }
   }
  }
 });
 // Yet-to-bowl players from XI
 var xiKey='a';if(bltId===m.team_b_id)xiKey='b';
 var allXI=xi[xiKey]||[];
 var xiMap2={};allXI.forEach(function(p){xiMap2[p.id]=p});
 var bowledIds={};c.forEach(function(p){bowledIds[p.id]=true});
 var yetToBowl=[];allXI.forEach(function(p){if(!bowledIds[p.id])yetToBowl.push(p)});
 // Left panel accent
 var leftGrad='';if(bltId===m.team_a_id)leftGrad='background:linear-gradient(90deg,#FF9800,#e65100);box-shadow:0 10px 30px rgba(255,152,0,0.30)';
 else leftGrad='background:linear-gradient(90deg,#0288D1,#01579b);box-shadow:0 10px 30px rgba(2,136,209,0.30)';

 // ---- Build HTML ----
 var h='<div class="bgp-wrap" style="width:100%">';

 // LEFT: Team logo panel
 h+='<div class="bgp-left" style="'+leftGrad+'"><div class="bgp-logo-wrap">';
 if(bltLogo)h+='<img src="'+e(bltLogo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="bgp-logo-init" style="display:none">'+bltShort+'</span>';
 else h+='<span class="bgp-logo-init">'+bltShort+'</span>';
 h+='</div></div>';

 // MAIN: Header + column bar + rows + footer
 h+='<div class="bgp-main">';
 // Header
 h+='<div class="bgp-header" style="background:linear-gradient(90deg,#003d80,#0066cc);padding:clamp(12px,1.8vh,18px) clamp(16px,2vw,22px);border-radius:14px 14px 0 0;border-bottom:3px solid rgba(255,255,255,0.12);position:relative;overflow:hidden;margin:-8px -8px 10px -8px">';
 h+='<div style="position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.3;pointer-events:none"></div>';
 h+='<div style="position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between"><div style="flex:1"><div class="bgp-team-name" style="color:#fff">'+e(bltName)+'</div>';
 h+='<div class="bgp-match-info" style="color:rgba(255,255,255,0.70)">'+e(m.title||(oppName?'VS '+oppName:''))+(loc?' &middot; '+e(loc):'')+' &middot; BOWLING &middot; '+innLabel+'</div></div>';
 h+='</div></div>';

 // Column header bar (matches row column widths)
 h+='<div class="bgp-col-bar"><span class="bgp-name-flex" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">BOWLER</span><span class="bgp-stat" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">O</span><span class="bgp-stat sm" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">M</span><span class="bgp-stat" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">R</span><span class="bgp-stat wk" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,152,0,0.60)">W</span><span class="bgp-stat e" style="font-size:clamp(7px,1vh,10px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.30)">ECON</span></div>';

 // Rows
 h+='<div class="bgp-rows">';
 c.forEach(function(p,i){
  var isActive=p.id===bowlId&&bowlId>0;
  var rowClass=isActive?'active':'';
  var ec=p.econ||0,ecCls=ec<5?'lo':(ec>8?'hi':'md');
  h+='<div class="bgp-row '+rowClass+'" style="opacity:1">';
   var pxi2=xiMap2[p.id];
   var capTag3=pxi2&&(pxi2.captain||pxi2.is_captain)?'<span class="bdg-c">(C)</span>':'';
   var wkTag3=pxi2&&(pxi2.role||'').toLowerCase().includes('wk')?'<span class="wk-tag">(WK)</span>':'';
  h+='<div class="bgp-name-flex">'+e(p.name)+capTag3+wkTag3+(isActive?' <span style="color:#4ade80;font-size:70%">&#9679;</span>':'')+'</div>';
  h+='<div class="bgp-stat">'+p.overs+'</div>';
  h+='<div class="bgp-stat sm">'+(p.maidens||0)+'</div>';
  h+='<div class="bgp-stat">'+(p.runs||0)+'</div>';
  h+='<div class="bgp-stat wk">'+(p.wickets||0)+'</div>';
  h+='<div class="bgp-stat e '+ecCls+'">'+ec+'</div>';
  h+='</div>';
 });
 // Yet-to-bowl players
  yetToBowl.forEach(function(p){
   var capTag4=(p.captain||p.is_captain)?'<span class="bdg-c">(C)</span>':'';
   var wkTag4=(p.role||'').toLowerCase().includes('wk')?'<span class="wk-tag">(WK)</span>':'';
  h+='<div class="bgp-row dim" style="opacity:1"><div class="bgp-name-flex dim">'+e(p.name)+capTag4+wkTag4+'</div><div class="bgp-stat sm">-</div><div class="bgp-stat sm">-</div><div class="bgp-stat sm">-</div><div class="bgp-stat wk">-</div><div class="bgp-stat e">-</div></div>';
 });
 // Total row
 if(c.length>0){var tw=0,tr=0;c.forEach(function(p){tw+=p.wickets||0;tr+=p.runs||0});
  h+='<div class="bgp-row bgp-total-row" style="opacity:1"><div class="bgp-name-flex" style="font-weight:700;color:#fff">TOTAL</div><div class="bgp-stat">'+(blt&&blt.overs?blt.overs:'0.0')+'</div><div class="bgp-stat sm"></div><div class="bgp-stat" style="color:#fff;font-weight:700">'+tr+'</div><div class="bgp-stat wk">'+tw+'</div><div class="bgp-stat e"></div></div>';
 }
 h+='</div>'; // end bgp-rows

 // Footer
 h+='<div class="bgp-footer"><div class="bgp-meta"><div>EXTRAS <span>'+(bt?bt.extras||0:0)+'</span></div><div>OVERS <span>'+(bt?bt.overs||'0.0':'0.0')+'</span></div></div>';
 h+='<div class="bgp-total">'+(bt?bt.runs:'0')+'-'+(bt?bt.wickets:'0')+'</div></div>';
 h+='</div>'; // end bgp-main

 // RIGHT: Key Performer panel
 h+='<div class="bgp-right">';
 if(matchLogo)h+='<div style="text-align:center;padding:clamp(14px,2vh,20px) 0 clamp(10px,1.5vh,14px)"><div style="width:clamp(140px,18vh,260px);height:clamp(140px,18vh,260px);border-radius:18px;overflow:hidden;margin:0 auto;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.10);border:3px solid rgba(255,255,255,0.18);box-shadow:0 8px 30px rgba(0,0,0,0.40)"><img src="'+e(matchLogo)+'" style="width:90%;height:90%;object-fit:contain" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span style="display:none;font-size:clamp(36px,5vh,56px);color:rgba(255,255,255,0.30)">&#127951;</span></div></div>';
  if(kp){
   if(kp.photo)h+='<div class="bgp-perf-img-wrap"><img class="bgp-perf-img" src="'+e(kp.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="bgp-perf-img-placeholder" style="display:none">'+(kp.name||'?').charAt(0).toUpperCase()+'</span></div>';
   else h+='<div class="bgp-perf-img-wrap"><span class="bgp-perf-img-placeholder">'+(kp.name||'?').charAt(0).toUpperCase()+'</span></div>';
   h+='<div class="bgp-perf-label-wrap"><div class="bgp-perf-badge"><span>KEY PERFORMER &middot; '+kp.wickets+'/'+kp.runs+' ('+kp.overs+')</span></div><div class="bgp-perf-name"><span>'+e(kp.name)+((kp.role||'').toLowerCase().includes('wk')?' <span class="wk-tag">(WK)</span>':'')+'</span></div></div>';
  }else{
   h+='<div class="bgp-perf-img-wrap"><span class="bgp-perf-img-placeholder">&#9733;</span></div>';
  }
  h+='</div>'; // end bgp-right

  h+='</div>'; // end bgp-wrap

  $('fvBwlB').innerHTML=h;
  if(gsap){gsap.set('#fvBwlB .bgp-row',{opacity:0,rotationX:14,y:10,transformOrigin:'center top'});gsap.to('#fvBwlB .bgp-row',{opacity:1,rotationX:0,y:0,duration:0.4,stagger:0.035,ease:'power3.out'});}
}
function rxi(d){
 var xi=d.playing_xi||{a:[],b:[]},m=d.match||{};
 var aN=m.team_a_name||'Team A',bN=m.team_b_name||'Team B';
 var aS=m.team_a_short||aN,bS=m.team_b_short||bN;
 var inn=d.innings_number||0;
 var innLabel=inn===99?'SUPER OVER':(inn===2?'2ND INNINGS':'1ST INNINGS');
 var aL=m.team_a_logo||'',bL=m.team_b_logo||'';
 var ml=m.match_logo||'';
 var fm={t20i:'T20I',odi:'ODI',test:'TEST'},f=fm[m.format]||'T20';
 var toss='';
 if(m.toss_won_by){
  var wn=(m.toss_won_by===m.team_a_id?aS:bS).toUpperCase();
  toss='<span class="xi-bot-w">'+wn+' WON THE TOSS</span> & '+(m.toss_decision==='bat'?'CHOSE TO BAT':'CHOSE TO BOWL');
 }else{toss='TOSS NOT YET CONDUCTED'}
  var h='';
  h+='<div class="xi-top">';
  if(ml)h+='<div class="xi-top-logo"><img src="'+e(ml)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-top-icn" style="display:none">&#127951;</span></div>';
  else h+='<div class="xi-top-logo"><span class="xi-top-icn">&#127951;</span></div>';
  h+='<div style="flex:1"><div class="xi-top-title">PLAYING XI</div><div class="xi-top-sub">'+e(m.title||(aS+' VS '+bS))+' &middot; '+f+' &middot; '+innLabel+'</div></div></div>';
  // Find captains
  var cA=null,cB=null;
  (xi.a||[]).forEach(function(p){if(p.captain||p.is_captain)cA=p});
  (xi.b||[]).forEach(function(p){if(p.captain||p.is_captain)cB=p});
  h+='<div class="xi-wrap">';
  [{n:aN,s:aS,l:aL,x:xi.a,side:'a'},{n:bN,s:bS,l:bL,x:xi.b,side:'b'}].forEach(function(t,idx){
   var pl=t.x||[],top=pl.slice(0,6),btmRow=pl.slice(6,11);
     function bc(p,i,off){
     var rc=(p.role||'').toLowerCase(),rl='BAT',rcs='bat';
     if(rc.includes('bowl')&&rc.includes('bat')){rl='AR';rcs='all'}else if(rc.includes('wk')){rl='WK';rcs='wk'}else if(rc.includes('bowl')){rl='BWL';rcs='bwl'}
     var isCap=(p.captain||p.is_captain);
     var capName=isCap?' <span class="xi-play-cname">(C)</span>':'';
     var wkName=rcs==='wk'?' <span class="xi-play-cname">(WK)</span>':'';
     var capBadge=isCap?'<span class="xi-play-capt">C</span>':'';
     var wkBadge=rcs==='wk'?'<span class="xi-play-wk">WK</span>':'';
     var im='';
     if(p.photo)im='<div class="xi-play-card"><img src="'+e(p.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-play-init" style="display:none">'+(p.name||'?').charAt(0).toUpperCase()+'</span><div class="xi-play-grad"></div>'+capBadge+wkBadge+'</div>';
     else im='<div class="xi-play-card"><span class="xi-play-init">'+(p.name||'?').charAt(0).toUpperCase()+'</span><div class="xi-play-grad"></div>'+capBadge+wkBadge+'</div>';
     return '<div class="xi-play">'+im+'<div class="xi-play-name">'+e(p.name)+capName+wkName+'</div><div class="xi-play-role '+rcs+'">'+rl+'</div></div>';
   }
   h+='<div class="xi-col"><div class="xi-tp"><div class="xi-tp-logo">';
   if(t.l)h+='<img src="'+e(t.l)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-tp-ini" style="display:none">'+(t.s||'?').substring(0,2).toUpperCase()+'</span>';
   else h+='<span class="xi-tp-ini">'+(t.s||'?').substring(0,2).toUpperCase()+'</span>';
   h+='</div><div class="xi-tp-info"><div class="xi-tp-name">'+e(t.s||t.n)+'</div><div class="xi-tp-sub">PLAYING XI</div></div></div>';
   h+='<div class="xi-body">';
   if(!pl.length)h+='<div style="color:rgba(255,255,255,0.10);font-size:13px;padding:30px;text-align:center">No XI selected</div>';
    else{h+='<div class="xi-pgrid"><div class="xi-prow">';top.forEach(function(p,i){h+=bc(p,i,0)});h+='</div>';
     if(btmRow.length){h+='<div class="xi-prow btm">';btmRow.forEach(function(p,i){h+=bc(p,i,6)});h+='</div>'}}
      h+='</div>';
     h+='</div></div>';
    // Captains section between teams
    if(idx===0){
     h+='<div class="xi-captains">';
     var caps=[cA,cB];
     caps.forEach(function(cp){
      if(!cp){h+='<div class="xi-captain"><div class="xi-cap-photo xi-cap-na"><span>?</span></div><div class="xi-cap-name">--</div><div class="xi-cap-label">CAPTAIN</div></div>';return}
      var cpIm='';
      if(cp.photo)cpIm='<img src="'+e(cp.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-cap-init" style="display:none">'+(cp.name||'?').charAt(0).toUpperCase()+'</span>';
      else cpIm='<span class="xi-cap-init">'+(cp.name||'?').charAt(0).toUpperCase()+'</span>';
      h+='<div class="xi-captain"><div class="xi-cap-photo">'+cpIm+'</div><div class="xi-cap-name">'+e(cp.name)+'</div><div class="xi-cap-label">CAPTAIN</div></div>';
     });
     h+='</div>';
    }
   });
  h+='</div>';
  h+='<div class="xi-bot"><span>'+toss+'</span></div>';
  $('fvXIB').innerHTML=h;
  if(gsap&&(_js==='xi'||_js.indexOf('xi_bat')===0||_js.indexOf('xi_bowl')===0)){_js='';
   var cs=$('fvXIB').querySelectorAll('.xi-col,.xi-top,.xi-bot,.xi-captains');
   gsap.from(cs,{opacity:0,rotationX:12,y:20,transformOrigin:'center top',duration:.45,stagger:.06,ease:'power3.out'});
   var pcs=$('fvXIB').querySelectorAll('.xi-play');
   gsap.from(pcs,{opacity:0,scale:.82,rotationY:-12,y:14,duration:.35,delay:.14,stagger:.03,ease:'back.out(1.6)'});
   var bgs=$('fvXIB').querySelectorAll('.xi-play-capt,.xi-play-wk');
   gsap.from(bgs,{opacity:0,scale:0,duration:.25,delay:.45,stagger:.02,ease:'back.out(2)'});
  }
}
function rxis(d,sk,isBat){
 var xi=d.playing_xi||{a:[],b:[]},m=d.match||{};
 var aN=m.team_a_name||'Team A',bN=m.team_b_name||'Team B';
 var inn=d.innings_number||0;
 var innLabel=inn===99?'SUPER OVER':(inn===2?'2ND INNINGS':'1ST INNINGS');
 var aS=m.team_a_short||aN,bS=m.team_b_short||bN;
 var tN=sk==='a'?aN:bN,tS=sk==='a'?aS:bS;
 var tL=sk==='a'?m.team_a_logo||'':m.team_b_logo||'';
 var ml=m.match_logo||'';
 var fm={t20i:'T20I',odi:'ODI',test:'TEST'},f=fm[m.format]||'T20';
 var colorSide=isBat?'a':'b';
 var toss='';
 if(m.toss_won_by){
  var wn=(m.toss_won_by===m.team_a_id?aS:bS).toUpperCase();
  toss='<span class="xi-bot-w">'+wn+' WON THE TOSS</span> & '+(m.toss_decision==='bat'?'CHOSE TO BAT':'CHOSE TO BOWL');
 }else{toss='TOSS NOT YET CONDUCTED'}
 var pl=xi[sk]||[],top=pl.slice(0,6),btmRow=pl.slice(6,11);
  function bc(p,i,off){
    var rc=(p.role||'').toLowerCase(),rl='BAT',rcs='bat';
    if(rc.includes('bowl')&&rc.includes('bat')){rl='AR';rcs='all'}else if(rc.includes('wk')){rl='WK';rcs='wk'}else if(rc.includes('bowl')){rl='BWL';rcs='bwl'}
    var isCap=(p.captain||p.is_captain);
    var capName=isCap?' <span class="xi-play-cname">(C)</span>':'';
    var wkName=rcs==='wk'?' <span class="xi-play-cname">(WK)</span>':'';
    var capBadge=isCap?'<span class="xi-play-capt">C</span>':'';
    var wkBadge=rcs==='wk'?'<span class="xi-play-wk">WK</span>':'';
    var im='';
    if(p.photo)im='<div class="xi-play-card"><img src="'+e(p.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-play-init" style="display:none">'+(p.name||'?').charAt(0).toUpperCase()+'</span><div class="xi-play-grad"></div>'+capBadge+wkBadge+'</div>';
    else im='<div class="xi-play-card"><span class="xi-play-init">'+(p.name||'?').charAt(0).toUpperCase()+'</span><div class="xi-play-grad"></div>'+capBadge+wkBadge+'</div>';
    return '<div class="xi-play">'+im+'<div class="xi-play-name">'+e(p.name)+capName+wkName+'</div><div class="xi-play-role '+rcs+'">'+rl+'</div></div>';
  }
 var h='';
 h+='<div class="xi-top">';
 if(ml)h+='<div class="xi-top-logo"><img src="'+e(ml)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-top-icn" style="display:none">&#127951;</span></div>';
 else h+='<div class="xi-top-logo"><span class="xi-top-icn">&#127951;</span></div>';
 h+='<div style="flex:1"><div class="xi-top-title">PLAYING XI</div><div class="xi-top-sub">'+e(m.title||(aS+' VS '+bS))+' &middot; '+f+' &middot; '+tN.toUpperCase()+' &middot; '+innLabel+'</div></div></div>';
 h+='<div class="xi-wrap"><div class="xi-col"><div class="xi-tp"><div class="xi-tp-logo">';
 if(tL)h+='<img src="'+e(tL)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-tp-ini" style="display:none">'+(tS||'?').substring(0,2).toUpperCase()+'</span>';
 else h+='<span class="xi-tp-ini">'+(tS||'?').substring(0,2).toUpperCase()+'</span>';
 h+='</div><div class="xi-tp-info"><div class="xi-tp-name">'+e(tS||tN)+'</div><div class="xi-tp-sub">PLAYING XI</div></div></div><div class="xi-body">';
  if(!pl.length)h+='<div style="color:rgba(255,255,255,0.10);font-size:13px;padding:30px;text-align:center">No XI selected</div>';
  else{h+='<div class="xi-pgrid"><div class="xi-prow">';top.forEach(function(p,i){h+=bc(p,i,0)});h+='</div>';
   if(btmRow.length){h+='<div class="xi-prow btm">';btmRow.forEach(function(p,i){h+=bc(p,i,6)});h+='</div>'}}
   h+='</div>';
  h+='</div></div></div>';
 h+='<div class="xi-bot"><span>'+toss+'</span></div>';
 $('fvXISB').innerHTML='<div class="xi-'+colorSide+' xi-single">'+h+'</div>';
  if(gsap&&pl.length>0&&(_js==='xi_bat'||_js==='xi_bowl')){_js='';
   var cs=$('fvXISB').querySelectorAll('.xi-col,.xi-top,.xi-bot');
   gsap.from(cs,{opacity:0,rotationX:12,y:18,transformOrigin:'center top',duration:.45,stagger:.06,ease:'power3.out'});
   var pcs=$('fvXISB').querySelectorAll('.xi-play');
   gsap.from(pcs,{opacity:0,scale:.82,rotationY:-12,y:12,duration:.35,delay:.14,stagger:.03,ease:'back.out(1.6)'});
   var bgs=$('fvXISB').querySelectorAll('.xi-play-capt,.xi-play-wk');
   gsap.from(bgs,{opacity:0,scale:0,duration:.25,delay:.45,stagger:.02,ease:'back.out(2)'});
   }
}
function rpp(dp){var o='';
 o+='<div class="pp-ring"></div><div class="pp-glow-tr"></div><div class="pp-glow-bl"></div>';
 o+='<div class="pp-overlay">';
 o+='<div class="pp-stats-section">';
 o+='<div class="pp-header"><div class="pp-bar"></div><div class="pp-main"><h1 class="pp-name">'+e(dp.name)+'</h1><h2 class="pp-role">'+e((dp.role||'player').replace(/-/g,' ').toUpperCase())+'</h2></div></div>';
 o+='<div class="pp-stats-table">';
 o+='<div class="pp-section-title"><div class="pp-st-bar"></div><div class="pp-st-text"><span>PLAYER DETAILS</span></div></div>';
 var r=[],i;
 r.push(['ROLE',(dp.role||'player').replace(/-/g,' ').toUpperCase()]);
 r.push(['TEAM',dp.team_name||dp.team_short||'—']);
 if(dp.batting_style)r.push(['BATTING',dp.batting_style.toUpperCase()]);
 if(dp.bowling_style)r.push(['BOWLING',dp.bowling_style.toUpperCase()]);
 if(dp.age)r.push(['AGE',dp.age+' yrs']);
 if(dp.school)r.push(['SCHOOL',dp.school]);
 if(dp.achievements)r.push(['ACHIEVEMENTS',dp.achievements,'pp-row-achievement']);
 for(i=0;i<r.length;i++){
  var cls=r[i][2]?(' '+r[i][2]):'';
  o+='<div class="pp-stat-row'+cls+'"><div class="pp-sr-bar"></div><div class="pp-sr-label"><span>'+r[i][0]+'</span></div><div class="pp-sr-value"><span>'+e(r[i][1])+'</span></div></div>';
 }
 o+='</div></div>';
  o+='<div class="pp-player-section">';
  // Floating badges around player
  if(dp.team_name||dp.team_short)o+='<div class="pp-float-badge pp-fb-1"><span class="pp-fb-val">'+(dp.team_short||dp.team_name||'').toUpperCase()+'</span><span class="pp-fb-lbl">Team</span></div>';
  if(dp.age)o+='<div class="pp-float-badge pp-fb-2"><span class="pp-fb-val">'+dp.age+'</span><span class="pp-fb-lbl">Age</span></div>';
  if(dp.photo)o+='<img src="'+e(dp.photo)+'" alt="'+e(dp.name)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><div class="pp-no-img" style="display:none">'+(dp.name||'?').charAt(0).toUpperCase()+'</div>';
  else o+='<div class="pp-no-img">'+(dp.name||'?').charAt(0).toUpperCase()+'</div>';
 o+='</div></div>';
 $('fvPlayerB').innerHTML=o;
  if(gsap){
   var tl=gsap.timeline({defaults:{ease:'power3.out'}});
   tl.fromTo('.pp-overlay',{opacity:0,rotationX:16,rotationY:12,y:50,scale:0.88},{opacity:1,rotationX:2,rotationY:-1.5,y:0,scale:1,duration:1.1,ease:'back.out(1.4)'},0);
   tl.fromTo('.pp-ring',{opacity:0},{opacity:0.25,duration:0.9},0.35);
   tl.fromTo('.pp-glow-tr,.pp-glow-bl',{opacity:0},{opacity:1,duration:0.7},0.4);
   tl.fromTo('.pp-header',{opacity:0,x:-70,rotationY:8},{opacity:1,x:0,rotationY:0,duration:0.5},0.2);
   tl.fromTo('.pp-section-title',{opacity:0,x:-40,rotationY:5},{opacity:1,x:0,rotationY:0,duration:0.4},0.3);
   tl.fromTo('.pp-stat-row',{opacity:0,x:-40,rotationX:-8},{opacity:1,x:0,rotationX:0,duration:0.35,stagger:0.06},0.38);
   tl.fromTo('.pp-player-section',{opacity:0,scale:0.82,y:50,rotationY:-8},{opacity:1,scale:1,y:0,rotationY:2,duration:0.55},0);
   tl.fromTo('.pp-float-badge',{opacity:0,scale:0.5,rotationZ:-10},{opacity:1,scale:1,rotationZ:0,duration:0.4,stagger:0.08},0.6);
   // Continuous floating
   gsap.to('.pp-overlay',{y:-6,rotationX:1.5,rotationY:-2,duration:3.5,repeat:-1,yoyo:!0,ease:'sine.inOut',delay:1.3});
   // 3D Mouse parallax
   var fv=$('fvPlayerB'),over=document.querySelector('.pp-overlay'),ring=document.querySelector('.pp-ring'),ps=document.querySelector('.pp-player-section');
   if(fv&&over){fv.addEventListener('mousemove',function(e){
    var r=fv.getBoundingClientRect(),x=((e.clientX-r.left)/r.width-0.5)*2,y=((e.clientY-r.top)/r.height-0.5)*2;
    gsap.to(over,{rotationY:x*6,rotationX:y*-3.5+2,x:x*14,y:y*-9,duration:0.7,ease:'power2.out'});
    if(ring)gsap.to(ring,{x:x*22,y:y*-14,duration:0.7,ease:'power2.out'});
    if(ps)gsap.to(ps,{rotationY:x*4+2,rotationX:y*-2,x:x*-8,y:y*-5,duration:0.7,ease:'power2.out'});
   });fv.addEventListener('mouseleave',function(){
    gsap.to(over,{rotationY:-1.5,rotationX:2,x:0,y:0,duration:1,ease:'elastic.out(1,0.5)'});
    if(ring)gsap.to(ring,{x:0,y:0,duration:0.8,ease:'power2.out'});
    if(ps)gsap.to(ps,{rotationY:2,rotationX:0,x:0,y:0,duration:0.8,ease:'power2.out'});
   });}
  }
}
function rtoss(d){
 var m=d.match||{};
 var aS=(m.team_a_short||m.team_a_name||'Team A').toUpperCase();
 var bS=(m.team_b_short||m.team_b_name||'Team B').toUpperCase();
 var aLogo=m.team_a_logo||'',bLogo=m.team_b_logo||'';
 var ml=m.match_logo||'';
 var won=m.toss_won_by?(m.toss_won_by===m.team_a_id?aS:bS):'';
 var dec=m.toss_decision==='bat'?'Elected to BAT first':'Elected to BOWL first';

 var h='';

 // Header
 h+='<div style="background:linear-gradient(90deg,#003d80,#0066cc);border-radius:16px 16px 0 0;padding:clamp(16px,2.5vh,24px) clamp(24px,3vw,32px);text-align:center;border-bottom:3px solid rgba(255,255,255,0.12);position:relative;overflow:hidden">';
 h+='<div style="position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.3;pointer-events:none"></div>';
 h+='<div style="position:relative;z-index:1;font-family:\'Teko\',sans-serif;font-size:clamp(36px,5vh,60px);font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#fff;text-shadow:0 2px 10px rgba(0,0,0,0.30)">&#127936; COIN TOSS</div>';
 h+='<div style="position:relative;z-index:1;font-size:clamp(13px,1.6vh,18px);font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:#fff;margin-top:4px">'+e(m.title||'')+'</div>';
 h+='</div>';

 // Body
 h+='<div style="background:rgba(28,36,50,0.80);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border-radius:0 0 16px 16px;overflow:hidden">';

 // Teams
 h+='<div style="display:flex;align-items:center;justify-content:center;gap:clamp(40px,5vw,80px);padding:clamp(24px,4vh,44px) clamp(16px,2vw,24px)">';
  // Team A
  var isAW=won&&won.toUpperCase()===aS;
  h+='<div style="text-align:center">';
 h+='<div style="width:clamp(110px,15vh,170px);height:clamp(110px,15vh,170px);border-radius:50%;overflow:hidden;margin:0 auto clamp(12px,2vh,20px);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.12);border:3px solid '+(isAW?'#FF9800':'rgba(255,255,255,0.12)')+';box-shadow:'+(isAW?'0 0 40px rgba(255,152,0,0.35)':'0 0 20px rgba(0,0,0,0.25)')+'">';
 if(aLogo)h+='<img src="'+e(aLogo)+'" style="width:80%;height:80%;object-fit:contain" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span style="display:none;font-size:clamp(30px,5vh,50px);font-weight:800;color:rgba(255,255,255,0.35)">'+aS.substring(0,2)+'</span>';
 else h+='<span style="font-size:clamp(30px,5vh,50px);font-weight:800;color:rgba(255,255,255,0.35)">'+aS.substring(0,2)+'</span>';
 h+='</div>';
 h+='<div style="font-family:\'Teko\',sans-serif;font-size:clamp(22px,3vh,34px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:'+(isAW?'#FFB74D':'#fff')+'">'+aS+(isAW?' <span style="color:#FFD740;font-size:80%">&#9733;</span>':'')+'</div>';
  h+='</div>';

  // VS
  h+='<div style="font-family:\'Teko\',sans-serif;font-size:clamp(40px,6vh,72px);font-weight:200;opacity:0.45;letter-spacing:0.12em;color:#fff">VS</div>';

  // Team B
  var isBW=won&&won.toUpperCase()===bS;
  h+='<div style="text-align:center">';
 h+='<div style="width:clamp(110px,15vh,170px);height:clamp(110px,15vh,170px);border-radius:50%;overflow:hidden;margin:0 auto clamp(12px,2vh,20px);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.12);border:3px solid '+(isBW?'#0288D1':'rgba(255,255,255,0.12)')+';box-shadow:'+(isBW?'0 0 40px rgba(2,136,209,0.35)':'0 0 20px rgba(0,0,0,0.25)')+'">';
 if(bLogo)h+='<img src="'+e(bLogo)+'" style="width:80%;height:80%;object-fit:contain" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span style="display:none;font-size:clamp(30px,5vh,50px);font-weight:800;color:rgba(255,255,255,0.35)">'+bS.substring(0,2)+'</span>';
 else h+='<span style="font-size:clamp(30px,5vh,50px);font-weight:800;color:rgba(255,255,255,0.35)">'+bS.substring(0,2)+'</span>';
 h+='</div>';
 h+='<div style="font-family:\'Teko\',sans-serif;font-size:clamp(22px,3vh,34px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:'+(isBW?'#90CAF9':'#fff')+'">'+bS+(isBW?' <span style="color:#FFD740;font-size:80%">&#9733;</span>':'')+'</div>';
  h+='</div>';
 h+='</div>';

 // Result
 h+='<div style="background:linear-gradient(90deg,#c62828,#d32f2f);padding:clamp(16px,2.5vh,24px);margin:0 clamp(16px,2vw,24px) clamp(20px,3vh,28px);border-radius:14px;border:2px solid rgba(255,255,255,0.12);text-align:center;position:relative;overflow:hidden">';
 h+='<div style="position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,rgba(255,255,255,0.04) 0,rgba(255,255,255,0.04) 1px,transparent 0,transparent 50%);background-size:8px 8px;opacity:0.4;pointer-events:none"></div>';
 if(won){
  h+='<div style="position:relative;z-index:1;font-family:\'Teko\',sans-serif;font-size:clamp(26px,3.5vh,42px);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#FFD740;text-shadow:0 2px 8px rgba(0,0,0,0.30)">'+e(won)+' WON THE TOSS</div>';
  h+='<div style="position:relative;z-index:1;font-size:clamp(14px,1.8vh,20px);font-weight:600;color:#fff;margin-top:6px;text-shadow:0 1px 4px rgba(0,0,0,0.20)">'+e(dec)+'</div>';
 }else{
  h+='<div style="position:relative;z-index:1;font-family:\'Teko\',sans-serif;font-size:clamp(18px,2.5vh,28px);font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#fff">TOSS NOT YET CONDUCTED</div>';
 }
 h+='</div>';

 h+='</div>'; // body

 $('fvTossB').innerHTML=h;
  if(gsap&&_js==='toss'){_js='';
   gsap.from('#fvTossB > div',{opacity:0,rotationX:12,y:18,transformOrigin:'center top',duration:.48,stagger:.1,ease:'power3.out'});
  }
}
function rsu(d){var sm=d.match_summary||{},m=d.match||{},h='';
 var ml=m.match_logo||'',loc=m.location||'',inns=sm.innings||[],aLogo=m.team_a_logo||'',bLogo=m.team_b_logo||'';
 var inn=d.innings_number||0,ov=d.output_view||'';
 var innLabel=inn===99?'SUPER OVER':(inn===2?'2ND INNINGS':'1ST INNINGS');
 // Filter innings based on view type
 if(ov==='summary_1st')inns=inns.filter(function(x){return x.number===1});
 else if(ov==='summary_2nd')inns=inns.filter(function(x){return x.number===2});
 else if(ov==='summary_so')inns=inns.filter(function(x){return x.number===99});
 // summary (no suffix) = show all innings (final)
 var aS=(m.team_a_short||m.team_a_name||'TEAM A').toUpperCase();
 var bS=(m.team_b_short||m.team_b_name||'TEAM B').toUpperCase();

function topBat(bc){if(!bc||!bc.length)return null;var b=bc[0];bc.forEach(function(p){if(p.runs>b.runs)b=p});return b}
function topBowl(bwc){if(!bwc||!bwc.length)return null;var b=bwc[0];bwc.forEach(function(p){if(p.wickets>b.wickets||(p.wickets===b.wickets&&p.econ<b.econ))b=p});return b}

 // Header
 h+='<div class="su-bcast"><div class="su-hdr"><div><div class="su-title">'+e(sm.title||'MATCH SUMMARY')+'</div>';
 h+='<div class="su-sub">'+(loc?e(loc)+' &middot; ':'')+(ov.indexOf('summary_')===0?innLabel.toUpperCase():inns.length>=2?'FINAL MATCH RESULT':(innLabel.toUpperCase()))+'</div></div>';
 h+='<div class="su-logos">';
 if(ml)h+='<div class="su-hdr-logo"><img src="'+e(ml)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="su-hdr-ini" style="display:none">M</span></div>';
 h+='<div class="su-hdr-logo">';
 if(aLogo)h+='<img src="'+e(aLogo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="su-hdr-ini" style="display:none">'+aS.substring(0,2)+'</span>';
 else h+='<span class="su-hdr-ini">'+aS.substring(0,2)+'</span>';
 h+='</div><div class="su-hdr-logo">';
 if(bLogo)h+='<img src="'+e(bLogo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="su-hdr-ini" style="display:none">'+bS.substring(0,2)+'</span>';
 else h+='<span class="su-hdr-ini">'+bS.substring(0,2)+'</span>';
 h+='</div></div></div>';

 // Body
 h+='<div class="su-body-wrap">';
 if(inns.length>0){
  inns.forEach(function(inn,i){
   var cls=i===0?'a':'b',tb=topBat(inn.batting_card),tw=topBowl(inn.bowling_card);
   var ibc=inn.batting_card||[],ibwc=inn.bowling_card||[];

   // Team bar
   h+='<div class="su-team"><div class="su-tbar">';
   h+='<div class="su-tname '+cls+'"><span>'+e(inn.team_short||inn.team_name)+'</span></div>';
   h+='<div class="su-tov"><span class="su-tov-lbl">OVERS</span><span class="su-tov-val">'+inn.overs+'</span></div>';
   h+='<div class="su-tscore '+cls+'"><span>'+inn.runs+'-'+inn.wickets+'</span></div>';
   h+='</div>';

   // Stats grid
   h+='<div class="su-stats">';

   // Batters
   h+='<div class="su-col"><div class="su-sec-lbl">BATTING</div>';
   ibc.slice(0,5).forEach(function(p){
    var out=!!p.dismissal,star=(tb&&p.id===tb.id)?' star':'';
    h+='<div class="su-prow'+(out?' dim':'')+'"><div class="su-pname">'+e(p.name)+((p.role||'').toLowerCase().includes('wk')?' <span class="su-tag">(WK)</span>':'')+'</div><div class="su-pstat">'+p.runs+(out?'':'*')+'<sub>'+p.balls+'</sub></div><div class="su-pdet'+star+'">'+(out?(p.dismissal||'').replace(/_/g,' ').toUpperCase():'')+'</div></div>';
   });
   if(tb)h+='<div class="su-prow"><div class="su-pname" style="font-size:clamp(9px,1.2vh,12px);opacity:0.45">TOP SCORER</div><div class="su-pstat" style="color:#FF9800">'+tb.runs+'</div><div class="su-pdet" style="color:#FF9800">'+e(tb.name)+'</div></div>';
   h+='</div>';

   // Bowlers
   h+='<div class="su-col"><div class="su-sec-lbl">BOWLING</div>';
   ibwc.slice(0,5).forEach(function(p){
     h+='<div class="su-prow"><div class="su-pname">'+e(p.name)+((p.role||'').toLowerCase().includes('wk')?' <span class="su-tag">(WK)</span>':'')+'</div><div class="su-pstat">'+p.wickets+'-'+p.runs+'<sub>'+p.overs+'</sub></div><div class="su-pdet">Econ '+p.econ+'</div></div>';
   });
   if(tw)h+='<div class="su-prow"><div class="su-pname" style="font-size:clamp(9px,1.2vh,12px);opacity:0.45">BEST BOWLING</div><div class="su-pstat" style="color:#FF9800">'+tw.wickets+'/'+tw.runs+'</div><div class="su-pdet" style="color:#FF9800">'+e(tw.name)+'</div></div>';
   h+='</div>';

   h+='</div></div>'; // stats + team
  });
 }else{
  h+='<div style="text-align:center;padding:60px;color:rgba(0,0,0,0.25);font-size:16px">No innings data yet</div>';
 }
 h+='</div>'; // body-wrap

 // Footer result — check actual innings completion
 var res='';
 var i1=inns[0]||{},i2=inns[1]||{};
 var i1Done=i1.wickets>=10||(i1.overs&&parseFloat(i1.overs)>=(m.total_overs||20));
 var i2Done=i2.wickets>=10||(i2.overs&&parseFloat(i2.overs)>=(m.total_overs||20))||(sm.target&&i2.runs>=sm.target);
 if(sm.result&&sm.winner)res=e(sm.winner)+' WON BY '+e(sm.result).toUpperCase();
 else if(ov==='summary_1st')res=i1Done?'1ST INNINGS COMPLETE':'1ST INNINGS IN PROGRESS';
 else if(ov==='summary_2nd')res=i2Done?'2ND INNINGS COMPLETE':(sm.target?'TARGET: '+sm.target+' | NEED '+(sm.target-(i2.runs||0))+' RUNS':'2ND INNINGS IN PROGRESS');
 else if(ov==='summary_so')res='SUPER OVER';
 else if(sm.status==='completed')res='MATCH COMPLETE';
 else if(inns.length>=2&&i1Done&&i2Done)res='MATCH COMPLETE';
 else if(i1Done&&!i2Done)res='2ND INNINGS'+(sm.target?' | TARGET: '+sm.target:'');
 else if(!i1Done)res='1ST INNINGS IN PROGRESS';
 else res='MATCH IN PROGRESS';
 h+='<div class="su-fbar"><div class="su-fbar-diag"></div><span>'+res.toUpperCase()+'</span></div>';

 h+='</div>'; // su-bcast

 $('fvSumB').innerHTML=h;
  if(gsap&&_js==='summary'){_js='';
   var els=$('fvSumB').querySelectorAll('.su-tbar,.su-prow,.su-fbar');
   gsap.from(els,{opacity:0,rotationX:10,y:14,transformOrigin:'center top',duration:.38,stagger:.035,ease:'power3.out'});
  }
 // Always animate su-fbar on data updates for completed/result changes
 var fbar=$('fvSumB').querySelector('.su-fbar span');
 if(fbar&&gsap)gsap.from(fbar,{opacity:0,scale:.9,duration:.3,ease:'back.out(1.4)'});
}
function rxiStrip(tid,xi,m,tm,isBat,striker,ns){
 var el=$(tid);if(!el||!xi||!tm||!m)return;
 var tmId=tm?tm.id:0,key='b',ak=(xi.a&&xi.a.length)?'a':'b';
 // Determine which XI slot matches this team
 if(tmId===m.team_a_id)key='a';else if(tmId===m.team_b_id)key='b';else{el.classList.remove('on');return}
 var pl=xi[key]||[];
 if(!pl.length){el.classList.remove('on');return}
 var stripeId=isBat?striker?striker.id:0:(ns?ns.id:0);
 var nsId=isBat?(ns?ns.id:0):0;
 var h='';
 pl.forEach(function(p,i){
  var spotClass='xi-spot';
  if(p.id===stripeId)spotClass+=' striker';
  else if(p.id===nsId)spotClass+=' active';
  var imgHTML='';
  if(p.photo){
   imgHTML='<div class="xi-sp-img"><img src="'+e(p.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="xi-sp-init" style="display:none">'+(p.name||'?').charAt(0).toUpperCase()+'</span><span class="xi-sp-jersey">'+(i+1)+'</span></div>';
  }else{
   imgHTML='<div class="xi-sp-img"><span class="xi-sp-init">'+(p.name||'?').charAt(0).toUpperCase()+'</span><span class="xi-sp-jersey">'+(i+1)+'</span></div>';
  }
  var rc=(p.role||'').toLowerCase(),rl='BAT';
  if(rc.includes('bowl')&&rc.includes('bat'))rl='AR';
  else if(rc.includes('wk'))rl='WK';
  else if(rc.includes('bowl'))rl='BWL';
  h+='<div class="'+spotClass+'">'+imgHTML+'<div class="xi-sp-name">'+e(p.name)+((rc.includes('wk'))?' <span class="wk-tag">(WK)</span>':'')+'</div><div class="xi-sp-role">'+rl+'</div></div>';
 });
 el.innerHTML=h;
 el.classList.add('on');
 if(gsap&&_js){var spots=el.querySelectorAll('.xi-spot');spots.forEach(function(s,i){gsap.to(s,{opacity:1,duration:0.35,delay:i*0.04,ease:'power2.out'})})}
}
function e(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML}

function aw(){if(!gsap)return;gsap.fromTo($('tWkts'),{scale:2.2,rotationY:180,color:'#ff1744'},{scale:1,rotationY:0,color:'#fff',duration:.7,ease:'elastic.out(1,0.4)'})}
function rd(bl,isN,isO){var h='',n=bl.length;for(var i=0;i<6;i++){if(i<n){var b=bl[i],c='dot';if(b.wicket)c+=' w';else if(b.runs===4&&!b.extra)c+=' f';else if(b.runs===6&&!b.extra)c+=' s';else if(b.extra&&(b.extra==='wd'||b.extra==='nb'))c+=' x';h+='<div class="'+c+'">'+b.display+'</div>'}else h+='<div class="dot e">·</div>'}$('dots').innerHTML=h;if(isO&&gsap){var ds=$('dots').querySelectorAll('.dot');for(var j=0;j<ds.length;j++){(function(idx){gsap.from(ds[idx],{scale:0,rotationY:180,opacity:0,duration:.35,delay:idx*.05,ease:'back.out(1.7)'})})(j)}}else if(isN&&gsap){var ds=$('dots').querySelectorAll('.dot'),idx=Math.min(n-1,5);if(idx>=0&&ds[idx])gsap.from(ds[idx],{scale:0,rotationY:90,opacity:0,duration:.35,ease:'back.out(2)'})}}

function fe(tp,wn){
 if(!gsap)return;
 var ov=$('evOvl'),tx=$('evTxt'),bar=$('bar'),sc=$('tScore'),wk=$('tWkts');
 var bk=[];for(var i=0;i<bar.children.length;i++){if(bar.children[i]!==ov)bk.push(bar.children[i])}
 // Kill tweens on event overlay elements only
 gsap.killTweensOf([ov,bar,sc,wk].concat(bk));
 // Reset inline styles on key elements
 gsap.set([ov,bar,sc,wk],{clearProps:'all'});
 gsap.set(bk,{clearProps:'all'});
 ov.className='';ov.style.opacity='';ov.style.transform='';

 // Phase 1: Hide bar content + full bar takeover
 gsap.to(bk,{opacity:0,duration:.08,ease:'power2.in'});

 // Phase 2: Show overlay with bounce
 ov.className='show '+tp;
 if(tp==='four')tx.textContent='FOUR';
 else if(tp==='six')tx.textContent='SIX!';
 else if(tp==='wicket')tx.textContent='WICKET';
 else if(tp==='maiden')tx.textContent='MAIDEN OVER';
 else if(tp==='wide')tx.textContent='WIDE';
 else if(tp==='noball')tx.textContent='NO BALL';
 else if(tp==='win')tx.textContent=(wn?wn.toUpperCase()+' WIN!':'VICTORY!')+' \u{1F3C6}';
 else if(tp==='toss')tx.textContent='TOSS';
  gsap.fromTo(ov,{scale:.5,rotationY:-20,opacity:0},{scale:1,rotationY:0,opacity:1,duration:.5,ease:'back.out(3)'});

 // Phase 3: Score/wicket + bar effects (broadcast level)
 if(tp==='four'){
   gsap.fromTo(sc,{scale:2,rotationY:90,color:'#00E676'},{scale:1,rotationY:0,color:'#fff',duration:.7,delay:.05,ease:'elastic.out(1,0.4)'});
  }else if(tp==='six'){
   gsap.fromTo(sc,{scale:2.6,rotationY:180,color:'#FFD740'},{scale:1,rotationY:0,color:'#fff',duration:.8,delay:.05,ease:'elastic.out(1,0.3)'});
   gsap.fromTo(bar,{scale:1},{scale:1.06,duration:.16,delay:.05,onComplete:function(){gsap.to(bar,{scale:1,duration:.55,ease:'elastic.out(1,0.3)'})}});
  }else if(tp==='wicket'){
   gsap.fromTo(wk,{scale:4,rotationY:360,color:'#ff1744'},{scale:1,rotationY:0,color:'#fff',duration:.8,delay:.05,ease:'elastic.out(1,0.4)'});
   gsap.to(bar,{keyframes:[{x:-12,duration:.07},{x:12,duration:.07},{x:-8,duration:.06},{x:8,duration:.06},{x:-4,duration:.05},{x:4,duration:.05},{x:0,duration:.35,ease:'elastic.out(1,0.3)'}],delay:.05});
  }else if(tp==='maiden'){
  gsap.fromTo(bar,{scale:1},{scale:1.08,duration:.32,ease:'power2.out',onComplete:function(){gsap.to(bar,{scale:1,duration:.6,ease:'elastic.out(1,0.3)'})}});
 }else if(tp==='wide'||tp==='noball'){
  gsap.fromTo(bar,{scale:1},{scale:1.04,duration:.2,ease:'power2.out',onComplete:function(){gsap.to(bar,{scale:1,duration:.4,ease:'elastic.out(1,0.3)'})}});
  }else if(tp==='win'){
   // Victory celebration — stay on scorebug after (don't force blank)
   gsap.fromTo(bar,{scale:1},{scale:1.08,duration:.4,ease:'power2.out',onComplete:function(){gsap.to(bar,{scale:1,duration:.8,ease:'elastic.out(1,0.3)'})}});
   gsap.fromTo(sc,{scale:2.5,rotationY:360,color:'#FF9800'},{scale:1,rotationY:0,color:'#fff',duration:1,delay:.1,ease:'elastic.out(1,0.4)'});
  }else if(tp==='toss'){
   gsap.fromTo(bar,{scale:1},{scale:1.05,duration:.35,ease:'power2.out',onComplete:function(){gsap.to(bar,{scale:1,duration:.5,ease:'elastic.out(1,0.3)'})}});
  }

 // Phase 4: Restore
 var delay=tp==='win'?4.5:tp==='maiden'?1.8:1.5;
 gsap.to(ov,{scale:1.15,opacity:0,duration:.3,delay:delay,ease:'power2.in',onComplete:function(){
  ov.className='';ov.style.opacity='';ov.style.transform='';
  gsap.set([bar,sc,wk,wrap],{clearProps:'all'});
  gsap.set(bk,{clearProps:'all'});
  if(tp==='maiden'){setTimeout(function(){sw('scorebug')},300)}
 }});
 gsap.to(bk,{opacity:1,duration:.3,delay:delay+.05,ease:'power2.out'});
}

function bo(){at++;return Math.min(1500*Math.pow(2,at)*(.8+Math.random()*.4),30000)}
function cn(){if(es){es.close();es=null}if(tm){clearTimeout(tm);tm=null}es=new EventSource('api.php?action=sse_stream&match_id='+MID+'&last_epoch='+LE);es.addEventListener('update',function(e){try{var d=JSON.parse(e.data);if(d&&!d.error)up(d)}catch(er){console.error('SSE:',er)}});es.addEventListener('reconnect',function(e){try{var d=JSON.parse(e.data),dl=d.retry||bo();es.close();tm=setTimeout(cn,dl)}catch(er){tm=setTimeout(cn,bo())}});es.onerror=function(){es.close();es=null;tm=setTimeout(cn,bo())};es.onopen=function(){at=0}}
// Polling fallback — ensures sync even if SSE misses updates
var _pt=null;function _pf(){_pt=setInterval(function(){fetch('api.php?action=get_overlay_data&match_id='+MID+'&_='+Date.now()).then(function(r){return r.json()}).then(function(d){if(d&&!d.error&&d.last_updated_epoch&&d.last_updated_epoch>LE){LE=d.last_updated_epoch;up(d)}}).catch(function(){})},6000)}

fetch('api.php?action=get_overlay_data&match_id='+MID).then(function(r){return r.json()}).then(function(d){if(d&&!d.error){if(d.last_updated_epoch)LE=d.last_updated_epoch;up(d)}}).catch(function(e){console.error(e)}).finally(function(){cn();_pf()});
</script>
</body>
</html>
