<?php
/**
 * ============================================================================
 * scorer_panel.php — Redesigned Scorer Dashboard with Live Overlay Preview
 * ============================================================================
 * Touch-friendly scoring interface with:
 *   - Dual-pane layout: scoring controls + live broadcast overlay preview
 *   - SSE real-time sync (jittered exponential backoff)
 *   - Audio buzzer alerts on over-completion & wickets (per rules.md §1)
 *   - Ball tracker dots (current over) mirroring broadcast overlay
 *   - 48×48px tap targets, mobile-first
 *   - Atomic ball recording with undo stack
 * Requires 'scorer' or 'super_admin' role.
 * ============================================================================
 */

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}
$role = $_SESSION['role'] ?? '';
if ($role !== 'scorer' && $role !== 'super_admin' && $role !== 'company_admin') {
    header('Location: index.php?error=unauthorized');
    exit;
}
// License check for company-affiliated scorers
$scorerCompanyId = $_SESSION['company_id'] ?? null;
if ($scorerCompanyId && $role === 'scorer') {
    $licDb = getDB();
    $licStmt = $licDb->prepare("SELECT COUNT(*) FROM licenses WHERE company_id=? AND is_active=1 AND valid_from<=CURRENT_DATE AND valid_until>=CURRENT_DATE");
    $licStmt->execute([$scorerCompanyId]);
    if ((int)$licStmt->fetchColumn() === 0) {
        // Auto-revoke expired licenses
        $licDb->exec("UPDATE licenses SET is_active=0 WHERE company_id=$scorerCompanyId AND is_active=1 AND valid_until < CURRENT_DATE");
        header('Location: client_dashboard.php?error=license_expired');
        exit;
    }
}
$user = ['id' => (int)$_SESSION['user_id'], 'username' => $_SESSION['username'], 'role' => $role];

$db = getDB();

// Company-based filtering: company scorers see only their company's data
$scorerCid = $_SESSION['company_id'] ?? null;
$cidFilter = $scorerCid ? "AND m.company_id=$scorerCid" : '';
$matchesStmt = $db->query(
    'SELECT m.id, m.match_title, m.status, m.toss_won_by, m.toss_decision, m.batting_first,
            ta.short_name AS a_short, ta.id AS a_id, tb.short_name AS b_short, tb.id AS b_id
     FROM matches m JOIN teams ta ON m.team_a_id=ta.id JOIN teams tb ON m.team_b_id=tb.id
     WHERE 1=1 '.$cidFilter.'
     ORDER BY m.status DESC, m.created_at DESC LIMIT 30'
);
$allMatches = $matchesStmt->fetchAll();

$teamCidFilter = $scorerCid ? "WHERE company_id=$scorerCid" : '';
$teamsStmt = $db->query('SELECT id, name, short_name FROM teams '.$teamCidFilter.' ORDER BY name');
$allTeams = $teamsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Scorer Panel — Cricket Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* ================================================================== */
        /*  THEME VARIABLES — dark / light                                      */
        /* ================================================================== */
        :root, [data-theme="dark"] {
            --bg:        #030712;
            --bg2:       #0b1020;
            --bg-card:   rgba(17, 24, 39, 0.80);
            --bg-card2:  #111827;
            --bg-input:  #1e293b;
            --bg-hover:  rgba(30, 41, 59, 0.60);
            --border:    rgba(255, 255, 255, 0.08);
            --border2:   #1f2937;
            --text:      #f3f4f6;
            --text-dim:  #9ca3af;
            --text-mute: #6b7280;
            --accent:    #f97316;
            --accent2:   #fb923c;
            --scrollbar-track: #0f172a;
            --scrollbar-thumb: #334155;
        }
        [data-theme="light"] {
            --bg:        #e4e8ee;
            --bg2:       #e2e6ec;
            --bg-card:   #ffffff;
            --bg-card2:  #f8fafc;
            --bg-input:  #f1f5f9;
            --bg-hover:  #e9eef3;
            --border:    #c8cdd5;
            --border2:   #dde1e7;
            --text:      #0f172a;
            --text-dim:  #334155;
            --text-mute: #64748b;
            --accent:    #d9531e;
            --accent2:   #e8652a;
            --scrollbar-track: #e4e8ee;
            --scrollbar-thumb: #b0b8c4;
        }

        /* ================================================================== */
        /*  BASE                                                              */
        /* ================================================================== */
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        /* Override Tailwind's bg-gray-xxx classes with theme vars */
        .bg-gray-950, .bg-gray-900\/80, .bg-gray-900\/90 {
            background: var(--bg-card) !important;
        }
        .bg-gray-900 { background: var(--bg-card2) !important; }
        .bg-gray-800 { background: var(--bg-input) !important; }
        .bg-gray-800\/50 { background: var(--bg-hover) !important; }
        .border-gray-800, .border-gray-800\/50 { border-color: var(--border2) !important; }
        .border-gray-700, .border-gray-700\/50 { border-color: var(--border) !important; }
        .text-gray-100 { color: var(--text) !important; }
        .text-gray-200 { color: #e5e7eb !important; }
        .text-gray-300 { color: var(--text-dim) !important; }
        .text-gray-400 { color: var(--text-dim) !important; }
        .text-gray-500 { color: var(--text-mute) !important; }
        .text-gray-600 { color: var(--text-mute) !important; }
        .text-white   { color: var(--text) !important; }
        .hover\:bg-gray-700:hover { background: var(--bg-hover) !important; }
        .hover\:bg-gray-800\/50:hover { background: var(--bg-hover) !important; }

        /* Light-mode overrides */
        [data-theme="light"] .bg-gray-900\/80,
        [data-theme="light"] .bg-gray-900\/90 { background: var(--bg-card) !important; }
        [data-theme="light"] .bg-gray-900 { background: var(--bg-card2) !important; }
        [data-theme="light"] .bg-gray-800 { background: var(--bg-input) !important; }
        [data-theme="light"] .text-white { color: var(--text) !important; }

        /* --- Light mode: make ALL text readable on white backgrounds --- */
        [data-theme="light"] .text-gray-200,
        [data-theme="light"] .text-gray-300,
        [data-theme="light"] .text-gray-400,
        [data-theme="light"] .text-gray-500,
        [data-theme="light"] .text-gray-600 { color: var(--text-dim) !important; }
        [data-theme="light"] .text-orange-200,
        [data-theme="light"] .text-orange-300,
        [data-theme="light"] .text-orange-400,
        [data-theme="light"] .text-orange-500 { color: #c2410c !important; }
        [data-theme="light"] .text-blue-200,
        [data-theme="light"] .text-blue-300,
        [data-theme="light"] .text-blue-400,
        [data-theme="light"] .text-blue-500 { color: #1d4ed8 !important; }
        [data-theme="light"] .text-red-200,
        [data-theme="light"] .text-red-300,
        [data-theme="light"] .text-red-400,
        [data-theme="light"] .text-red-500 { color: #dc2626 !important; }
        [data-theme="light"] .text-green-200,
        [data-theme="light"] .text-green-300,
        [data-theme="light"] .text-green-400,
        [data-theme="light"] .text-green-500 { color: #16a34a !important; }
        [data-theme="light"] .text-emerald-200,
        [data-theme="light"] .text-emerald-300,
        [data-theme="light"] .text-emerald-400 { color: #059669 !important; }
        [data-theme="light"] .text-purple-200,
        [data-theme="light"] .text-purple-300,
        [data-theme="light"] .text-purple-400 { color: #7c3aed !important; }
        [data-theme="light"] .text-amber-200,
        [data-theme="light"] .text-amber-300,
        [data-theme="light"] .text-amber-400 { color: #92400e !important; }
        [data-theme="light"] .text-yellow-200,
        [data-theme="light"] .text-yellow-300,
        [data-theme="light"] .text-yellow-400 { color: #a16207 !important; }
        [data-theme="light"] .text-indigo-200,
        [data-theme="light"] .text-indigo-300,
        [data-theme="light"] .text-indigo-400 { color: #4338ca !important; }
        /* Hover text states */
        [data-theme="light"] .hover\:text-gray-300:hover { color: var(--text) !important; }
        [data-theme="light"] .hover\:text-red-400:hover { color: #dc2626 !important; }
        [data-theme="light"] .hover\:text-red-300:hover { color: #b91c1c !important; }
        [data-theme="light"] .hover\:text-blue-300:hover { color: #1d4ed8 !important; }
        [data-theme="light"] .hover\:text-blue-400:hover { color: #2563eb !important; }
        [data-theme="light"] .hover\:text-orange-300:hover,
        [data-theme="light"] .hover\:text-orange-400:hover { color: #c2410c !important; }

        /* Backgrounds */
        [data-theme="light"] .bg-amber-900\/20,
        [data-theme="light"] .bg-amber-900\/30 { background: #fef3c7 !important; }
        [data-theme="light"] .border-amber-700\/30,
        [data-theme="light"] .border-amber-700\/40 { border-color: #f59e0b !important; }
        [data-theme="light"] .bg-blue-900\/30,
        [data-theme="light"] .bg-blue-900\/40 { background: #dbeafe !important; }
        [data-theme="light"] .border-blue-700\/40,
        [data-theme="light"] .border-blue-700\/50 { border-color: #3b82f6 !important; }
        [data-theme="light"] .border-blue-800 { border-color: #bfdbfe !important; }
        [data-theme="light"] .bg-blue-900\/20 { background: #eff6ff !important; }
        [data-theme="light"] .bg-red-700\/50,
        [data-theme="light"] .bg-red-700\/60 { background: #fecaca !important; }
        [data-theme="light"] .border-red-600\/40,
        [data-theme="light"] .border-red-600\/50 { border-color: #ef4444 !important; }
        [data-theme="light"] .bg-green-900\/50 { background: #bbf7d0 !important; }
        [data-theme="light"] .border-green-600\/40 { border-color: #22c55e !important; }
        [data-theme="light"] .bg-indigo-900\/30 { background: #e0e7ff !important; }
        [data-theme="light"] .border-indigo-700\/40 { border-color: #6366f1 !important; }
        [data-theme="light"] .bg-emerald-900\/30,
        [data-theme="light"] .bg-emerald-900\/40 { background: #d1fae5 !important; }
        [data-theme="light"] .border-emerald-700\/30,
        [data-theme="light"] .border-emerald-700\/40 { border-color: #10b981 !important; }
        [data-theme="light"] .bg-purple-900\/30,
        [data-theme="light"] .bg-purple-900\/40 { background: #ede9fe !important; }
        [data-theme="light"] .border-purple-700\/30,
        [data-theme="light"] .border-purple-700\/40 { border-color: #8b5cf6 !important; }
        [data-theme="light"] .bg-amber-600\/60 { background: #f59e0b !important; }
        [data-theme="light"] .hover\:bg-amber-500\/70:hover { background: #d97706 !important; }

        /* Input placeholder */
        [data-theme="light"] .placeholder-gray-600::placeholder { color: #94a3b8 !important; }
        [data-theme="light"] ::placeholder { color: #94a3b8 !important; }
        [data-theme="light"] input::placeholder { color: #94a3b8 !important; }
        [data-theme="light"] select { color: var(--text) !important; }
        [data-theme="light"] input { color: var(--text) !important; }

        /* Live score card — keep white text on orange */
        [data-theme="light"] #liveScoreCard {
            background: linear-gradient(135deg, #f97316, #ea580c) !important;
        }
        [data-theme="light"] #liveScoreCard .text-white,
        [data-theme="light"] #liveScoreCard span { color: #ffffff !important; }
        [data-theme="light"] #liveScoreCard .text-orange-200,
        [data-theme="light"] #liveScoreCard .text-orange-200 span { color: #fed7aa !important; }

        /* Overlay preview — always dark */
        [data-theme="light"] #overlayPreview {
            background: rgba(15, 23, 42, 0.88) !important;
            border-color: rgba(255,255,255,0.15) !important;
        }
        [data-theme="light"] #overlayPreview .text-white,
        [data-theme="light"] #overlayPreview .text-white\/80,
        [data-theme="light"] #overlayPreview .text-white\/70,
        [data-theme="light"] #overlayPreview .text-white\/60,
        [data-theme="light"] #overlayPreview .text-white\/50,
        [data-theme="light"] #overlayPreview .text-white\/40,
        [data-theme="light"] #overlayPreview .text-white\/30 { color: #fff !important; }
        [data-theme="light"] #overlayPreview .text-blue-200,
        [data-theme="light"] #overlayPreview .text-blue-300 { color: #93c5fd !important; }
        [data-theme="light"] #overlayPreview .text-blue-400 { color: #60a5fa !important; }

        /* ================================================================== */
        .score-btn {
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            -webkit-user-select: none;
            cursor: pointer;
        }
        .score-btn:active {
            transform: scale(0.94);
            transition: transform 0.08s ease-out;
        }
        .score-btn:disabled {
            opacity: 0.35;
            pointer-events: none;
        }

        /* ================================================================== */
        /*  TOASTS                                                             */
        /* ================================================================== */
        @keyframes slideUp {
            from { transform: translateY(16px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        @keyframes slideDown {
            from { transform: translateY(0);    opacity: 1; }
            to   { transform: translateY(16px); opacity: 0; }
        }
        .toast-in  { animation: slideUp 0.22s ease-out forwards; }
        .toast-out { animation: slideDown 0.18s ease-in forwards; }

        /* ================================================================== */
        /*  PULSE DOT                                                          */
        /* ================================================================== */
        @keyframes pulseDot {
            0%,100% { opacity: 1; transform: scale(1); }
            50%     { opacity: 0.25; transform: scale(0.6); }
        }
        .live-dot { animation: pulseDot 1.4s ease-in-out infinite; }

        /* ================================================================== */
        /*  OVERLAY PREVIEW — mirror broadcast scorebug                        */
        /* ================================================================== */
        #overlayPreview {
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 14px;
            overflow: hidden;
        }
        #overlayPreview .ov-score-num {
            font-size: 40px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        #overlayPreview .ov-bat-name {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        #overlayPreview .ov-bat-runs {
            font-size: 22px;
            font-weight: 700;
        }
        #overlayPreview .ov-bowler-name {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        @keyframes strikerDotPulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50%     { opacity: 0.3; transform: scale(0.6); }
        }
        .striker-dot {
            width: 7px; height: 7px;
            background: #ff1744;
            border-radius: 50%;
            animation: strikerDotPulse 1.1s ease-in-out infinite;
            box-shadow: 0 0 6px rgba(255,23,68,0.7);
            display: inline-block;
            flex-shrink: 0;
        }
        .track-dot {
            width: 36px; height: 36px;
            border-radius: 8px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .track-dot.empty { opacity: 0.20; font-weight: 400; }
        .track-dot.wkt   { background: rgba(220,38,38,0.55);   border-color: rgba(255,80,80,0.45); }
        .track-dot.four  { background: rgba(0,180,70,0.50);    border-color: rgba(0,220,100,0.45); }
        .track-dot.six   { background: rgba(140,40,160,0.50);  border-color: rgba(180,70,200,0.45); }
        .track-dot.xtra  { background: rgba(200,150,20,0.40);  border-color: rgba(220,170,30,0.45); }

        /* ================================================================== */
        /*  SCROLLBAR                                                          */
        /* ================================================================== */
        ::-webkit-scrollbar { width: 3px; }
        ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 2px; }

        /* ================================================================== */
        /*  OVER COMPLETE ALERT PULSE                                           */
        /* ================================================================== */
        @keyframes alertPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.5); }
            50%     { box-shadow: 0 0 0 12px rgba(59,130,246,0); }
        }
        .over-alert-pulse {
            animation: alertPulse 2s ease-in-out infinite;
        }

        /* ================================================================== */
        /*  RESPONSIVE — tablet+ gets side-by-side                               */
        /* ================================================================== */
        @media (min-width: 768px) {
            #mainGrid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                align-items: start;
            }
        }
        /* ================================================================== */
        /*  ICC-LEVEL SCORECARD                                                */
        /* ================================================================== */
        .sc-card{background:linear-gradient(180deg,rgba(15,20,30,0.95),rgba(8,12,22,0.97));border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden}
        .sc-card-head{display:flex;align-items:center;gap:14px;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,0.05)}
        .sc-card-head .sc-logo{width:44px;height:44px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.15)}
        .sc-card-head .sc-logo img{width:100%;height:100%;object-fit:contain}
        .sc-card-head .sc-logo-init{font-size:15px;font-weight:800;color:rgba(255,255,255,0.3)}
        .sc-card-head .sc-inn-name{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em}
        .sc-card-head .sc-inn-meta{font-size:10px;color:rgba(255,255,255,0.4);margin-top:2px}
        .sc-row-new{display:flex;align-items:center;gap:8px;padding:8px 16px;border-bottom:1px solid rgba(255,255,255,0.025);transition:background .15s}
        .sc-row-new:last-child{border-bottom:none}
        .sc-row-new:hover{background:rgba(255,255,255,0.015)}
        .sc-row-new.dim{opacity:0.40}
        .sc-row-new .sc-num{width:24px;text-align:center;font-size:11px;font-weight:700;color:rgba(255,255,255,0.20);flex-shrink:0}
        .sc-row-new .sc-photo{width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08)}
        .sc-row-new .sc-photo img{width:100%;height:100%;object-fit:cover}
        .sc-row-new .sc-photo-init{font-size:13px;font-weight:800;color:rgba(255,255,255,0.2)}
        .sc-row-new .sc-player{flex:1;min-width:0}
        .sc-row-new .sc-player .sc-pname{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.02em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .sc-row-new .sc-player .sc-pstyle{font-size:8px;color:rgba(255,255,255,0.30);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px}
        .sc-stat{font-size:12px;font-weight:600;text-align:center;min-width:28px;flex-shrink:0}
        .sc-stat.hl{font-weight:700;font-size:14px}
        .sc-stat.dim-stat{font-size:11px;color:rgba(255,255,255,0.35)}
        .sc-tag{display:inline-block;font-size:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;padding:1px 5px;border-radius:3px;flex-shrink:0}
        .sc-tag.bat{background:rgba(74,222,128,0.12);color:#4ade80;border:1px solid rgba(74,222,128,0.18)}
        .sc-tag.bwl{background:rgba(96,165,250,0.12);color:#60a5fa;border:1px solid rgba(96,165,250,0.18)}
        .sc-tag.all{background:rgba(192,132,252,0.12);color:#c084fc;border:1px solid rgba(192,132,252,0.18)}
        .sc-tag.wk{background:rgba(251,191,36,0.12);color:#fbbf24;border:1px solid rgba(251,191,36,0.18)}
        .sc-st{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;padding:2px 8px;border-radius:4px;flex-shrink:0}
        .sc-st.out{background:rgba(239,68,68,0.12);color:#ef4444}
        .sc-st.no{background:rgba(74,222,128,0.10);color:#4ade80}
        .sc-sr-lo{color:#ef4444}.sc-sr-md{color:#fbbf24}.sc-sr-hi{color:#4ade80}
        .sc-ec-lo{color:#4ade80}.sc-ec-md{color:#fbbf24}.sc-ec-hi{color:#ef4444}
        .sc-result-banner{padding:14px 18px;text-align:center;border-radius:12px;margin-bottom:10px}
        .sc-result-banner .sc-res-lbl{font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:0.12em;margin-bottom:4px}
        .sc-result-banner .sc-res-txt{font-size:16px;font-weight:800;letter-spacing:0.02em}
        .sc-inning-summary{display:flex;align-items:center;justify-content:center;gap:10px;padding:4px 0 10px}
        .sc-inning-summary .sc-is-card{text-align:center;padding:8px 16px}
        .sc-inning-summary .sc-is-card .sc-is-score{font-size:22px;font-weight:800}
        .sc-inning-summary .sc-is-card .sc-is-score small{font-size:12px;opacity:0.35}
        .sc-inning-summary .sc-is-card .sc-is-meta{font-size:9px;color:rgba(255,255,255,0.35);margin-top:2px}
        .sc-inning-summary .sc-is-vs{font-size:18px;font-weight:200;opacity:0.20}
        @keyframes modalPop{from{opacity:0;transform:scale(0.92) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}
    </style>
</head>
<body class="h-full bg-gray-950 text-gray-100 antialiased overflow-x-hidden">

<!-- ======================================================================== -->
<!-- TOAST CONTAINER                                                           -->
<!-- ======================================================================== -->
<div id="toastContainer" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex flex-col gap-2 items-center pointer-events-none" style="max-width:92vw;"></div>

<!-- ======================================================================== -->
<!-- MAIN APP                                                                  -->
<!-- ======================================================================== -->
<div id="app" class="max-w-5xl mx-auto px-2 sm:px-3 py-3 pb-8 space-y-3">

    <!-- ================================================================ -->
    <!-- HEADER                                                            -->
    <!-- ================================================================ -->
    <div class="flex items-center justify-between px-2">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-orange-400">&#127951; Scorer Panel</h1>
            <p class="text-[11px] text-gray-500"><?= htmlspecialchars($_SESSION['display_name'] ?? $user['username']) ?> &middot; <a href="<?= $role==='company_admin'?'client_dashboard.php':'super_admin.php' ?>" class="text-blue-400 hover:text-blue-300">Dashboard</a></p>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-1.5">
                <span id="connDot" class="w-2 h-2 rounded-full bg-gray-600"></span>
                <span id="connLabel" class="text-[10px] text-gray-500 hidden sm:inline">idle</span>
            </div>
            <button id="btnTheme" onclick="toggleTheme()" class="w-7 h-7 rounded-lg border border-gray-700 flex items-center justify-center text-sm hover:bg-gray-800 transition shrink-0" title="Toggle theme">&#127763;</button>
            <a href="live_output.php" class="text-[11px] text-gray-500 hover:text-blue-400 transition hidden sm:inline">&#128250; Control</a>
            <a href="api.php?action=logout" class="text-[11px] text-gray-500 hover:text-red-400 transition" onclick="return confirmLogout()">Logout</a>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- MATCH SELECTION (compact)                                         -->
    <!-- ================================================================ -->
    <div class="bg-gray-900/80 border border-gray-800 rounded-xl px-3 py-3 flex flex-wrap items-center gap-2">
        <select id="matchSelect" class="flex-1 min-w-[160px] bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-orange-500 focus:outline-none">
            <option value="">-- Select Match --</option>
            <?php foreach ($allMatches as $m): ?>
            <option value="<?= $m['id'] ?>" data-status="<?= $m['status'] ?>"
             data-ta-id="<?= $m['a_id'] ?>" data-tb-id="<?= $m['b_id'] ?>"
             data-toss-won="<?= $m['toss_won_by'] ?>" data-toss-dec="<?= $m['toss_decision'] ?>"
             data-bat-first="<?= $m['batting_first'] ?>">
                <?= htmlspecialchars($m['match_title']) ?> (<?= $m['a_short'] ?> vs <?= $m['b_short'] ?>) — <?= $m['status'] ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button id="btnLoad" class="bg-orange-600 hover:bg-orange-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition shrink-0">Load</button>
        <button id="btnToggleSetup" class="bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-2 rounded-lg text-xs font-medium transition shrink-0 hidden" title="Setup">&#9881;</button>
        <button id="btnToggleOverlay" class="bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-2 rounded-lg text-xs font-medium transition shrink-0 sm:hidden" title="Preview">&#128250;</button>
    </div>

    <!-- ================================================================ -->
    <!-- PLAYING XI SELECTOR (collapsible, one per team)                   -->
    <!-- ================================================================ -->
    <div id="xiSection" class="hidden bg-gray-900/80 border border-gray-800 rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-green-400">&#127951; Playing XI Selection</h3>
            <button id="btnToggleXI" class="bg-gray-800 hover:bg-gray-700 text-gray-300 px-3 py-2 rounded-lg text-xs font-medium transition shrink-0" onclick="XI.toggleSection()">&#9650; Collapse</button>
        </div>
        <p class="text-[10px] text-gray-500">Select up to 11 players per team. Changes take effect immediately in player dropdowns.</p>

        <div id="xiPanels" class="grid grid-cols-2 gap-3">
            <!-- Team A -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[11px] font-semibold text-blue-300" id="xiLabelA">Team A</label>
                    <span class="text-[10px] text-gray-500" id="xiCountA">0/11</span>
                </div>
                <div id="xiListA" class="max-h-52 overflow-y-auto space-y-0.5 bg-gray-800/50 border border-gray-700/50 rounded-lg p-2">
                    <p class="text-[10px] text-gray-600 text-center py-4">Select a match first</p>
                </div>
                <button id="btnSaveXIA" class="w-full mt-1.5 bg-blue-600/50 hover:bg-blue-500/70 text-blue-200 font-medium py-1.5 rounded-lg text-xs transition disabled:opacity-30 hidden" disabled onclick="XI.save('A')">
                    Save XI — Team A
                </button>
            </div>
            <!-- Team B -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[11px] font-semibold text-red-300" id="xiLabelB">Team B</label>
                    <span class="text-[10px] text-gray-500" id="xiCountB">0/11</span>
                </div>
                <div id="xiListB" class="max-h-52 overflow-y-auto space-y-0.5 bg-gray-800/50 border border-gray-700/50 rounded-lg p-2">
                    <p class="text-[10px] text-gray-600 text-center py-4">Select a match first</p>
                </div>
                <button id="btnSaveXIB" class="w-full mt-1.5 bg-red-600/50 hover:bg-red-500/70 text-red-200 font-medium py-1.5 rounded-lg text-xs transition disabled:opacity-30 hidden" disabled onclick="XI.save('B')">
                    Save XI — Team B
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================================ -->
    <!-- INNINGS SETUP (collapsible)                                       -->
    <!-- ================================================================ -->
    <div id="setupSection" class="hidden bg-gray-900/80 border border-gray-800 rounded-xl p-4 space-y-3">
        <h3 class="text-sm font-semibold text-orange-400">&#9881;&#65039; Innings Setup</h3>

        <!-- TOSS -->
        <div class="bg-amber-900/20 border border-amber-700/30 rounded-lg p-3 space-y-2">
            <p class="text-[11px] font-semibold text-amber-400">&#127936; Toss</p>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-[10px] text-gray-400">Won By</label>
                    <select id="sTossWinner" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-amber-500 focus:outline-none">
                        <option value="">-- Select --</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] text-gray-400">Decision</label>
                    <select id="sTossDecision" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-amber-500 focus:outline-none" disabled>
                        <option value="">-- Select --</option>
                        <option value="bat">Bat First</option>
                        <option value="bowl">Bowl First</option>
                    </select>
                </div>
            </div>
            <button id="btnSaveToss" class="w-full bg-amber-600/60 hover:bg-amber-500/70 text-amber-200 font-medium py-1.5 rounded-lg text-xs transition disabled:opacity-30" disabled onclick="B.saveToss()">
                Save Toss
            </button>
            <p id="tossSummary" class="text-[10px] text-amber-300/70 text-center hidden"></p>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-[11px] text-gray-400">Batting Team</label>
                <select id="sBatTeam" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-orange-500 focus:outline-none">
                    <option value="">-- Select --</option>
                    <?php foreach ($allTeams as $t): ?>
                    <option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-[9px] text-gray-500 mt-0.5 block">Players filtered by Playing XI below</span>
            </div>
            <div>
                <label class="text-[11px] text-gray-400">Bowling Team</label>
                <select id="sBowlTeam" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-orange-500 focus:outline-none">
                    <option value="">-- Select --</option>
                    <?php foreach ($allTeams as $t): ?>
                    <option value="<?=$t['id']?>"><?=htmlspecialchars($t['name'])?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-[9px] text-gray-500 mt-0.5 block">Players filtered by Playing XI below</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-[11px] text-gray-400">Striker</label>
                <select id="sStriker" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-orange-500 focus:outline-none" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
            <div>
                <label class="text-[11px] text-gray-400">Non-Striker</label>
                <select id="sNonStriker" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-orange-500 focus:outline-none" disabled>
                    <option value="">-- Select --</option>
                </select>
            </div>
        </div>
        <div>
            <label class="text-[11px] text-gray-400">Opening Bowler</label>
            <select id="sBowler" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-orange-500 focus:outline-none" disabled>
                <option value="">-- Select --</option>
            </select>
        </div>
        <button id="btnStart" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-2.5 rounded-lg text-sm transition disabled:opacity-40" disabled>
            &#9654; Start Innings
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- TARGET / INNINGS BANNER                                            -->
    <!-- ================================================================ -->
    <div id="targetBanner" class="hidden bg-amber-900/40 border border-amber-600/40 rounded-xl px-4 py-2.5 text-center text-amber-300 text-sm font-medium"></div>

    <!-- ======================================================================== -->
    <!-- MAIN GRID: Scoring Controls + Overlay Preview                             -->
    <!-- ======================================================================== -->
    <div id="mainGrid" class="space-y-3">

        <!-- ============================================================ -->
        <!-- LEFT PANE: SCORING CONTROLS                                   -->
        <!-- ============================================================ -->
        <div id="scoringPane" class="space-y-3">

            <!-- LIVE SCORE CARD -->
            <div id="liveScoreCard" class="bg-gradient-to-r from-orange-700 to-orange-600 rounded-xl p-4 text-white shadow-lg hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <p id="scBatTeam" class="text-[11px] font-medium text-orange-200">--</p>
                        <p class="text-3xl sm:text-4xl font-bold tracking-tight leading-none">
                            <span id="scRuns">0</span>/<span id="scWkts">0</span>
                        </p>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-[11px] text-orange-200">Ov: <span id="scOvers" class="font-mono">0.0</span></span>
                            <span class="text-[11px] text-orange-200">CRR: <span id="scCrr">0.00</span></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="flex items-center gap-1 justify-end">
                            <span class="live-dot w-2 h-2 bg-white rounded-full inline-block"></span>
                            <span class="text-[10px] font-bold uppercase tracking-wider">LIVE</span>
                        </div>
                        <p class="text-[11px] text-orange-200 mt-1">Extras: <span id="scExtras">0</span></p>
                        <p id="scThisOver" class="text-[11px] text-orange-200 mt-0.5 hidden">This ov: <span id="scOverRuns">0</span></p>
                    </div>
                </div>
            </div>

            <!-- CURRENT PLAYERS -->
            <div id="playerCards" class="grid grid-cols-3 gap-2 hidden">
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-2.5 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full bg-gray-700 overflow-hidden border border-gray-600/40 mb-1">
                        <img id="pStrikerPhoto" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                    </div>
                    <div class="flex items-center justify-center gap-1 mb-0.5">
                        <span class="striker-dot"></span>
                        <span id="pStrikerName" class="text-xs font-semibold text-white truncate">--</span>
                    </div>
                    <span id="pStrikerStats" class="text-[11px] text-gray-400">--</span>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-lg p-2.5 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full bg-gray-700 overflow-hidden border border-gray-600/40 mb-1">
                        <img id="pNonStrikerPhoto" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                    </div>
                    <span id="pNonStrikerName" class="text-xs font-semibold text-white truncate block">--</span>
                    <span id="pNonStrikerStats" class="text-[11px] text-gray-400">--</span>
                </div>
                <div class="bg-gray-900 border border-blue-900/40 rounded-lg p-2.5 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full bg-gray-700 overflow-hidden border border-blue-700/30 mb-1">
                        <img id="pBowlerPhoto" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                    </div>
                    <span id="pBowlerName" class="text-xs font-semibold text-blue-300 truncate block">--</span>
                    <span id="pBowlerStats" class="text-[11px] text-gray-400">--</span>
                </div>
            </div>

            <!-- RUN BUTTONS -->
            <div id="btnGroup" class="hidden">
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3">
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">
                        &#127922; Runs Off Bat
                        <span class="text-gray-600 font-normal normal-case ml-2">Keys: 0-6</span>
                    </p>
                    <div class="grid grid-cols-4 gap-2">
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-xl font-bold text-white transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(0)"><span>0</span><kbd class="text-[9px] font-normal text-gray-500 leading-none">0</kbd></button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-xl font-bold text-white transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(1)"><span>1</span><kbd class="text-[9px] font-normal text-gray-500 leading-none">1</kbd></button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-xl font-bold text-white transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(2)"><span>2</span><kbd class="text-[9px] font-normal text-gray-500 leading-none">2</kbd></button>
                        <button class="score-btn bg-blue-900/30 hover:bg-blue-800/50 border border-blue-700/40 rounded-xl py-3 text-xl font-bold text-blue-300 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(3)"><span>3</span><kbd class="text-[9px] font-normal text-blue-400/60 leading-none">3</kbd></button>
                        <button class="score-btn bg-emerald-900/30 hover:bg-emerald-800/50 border border-emerald-700/40 rounded-xl py-3 text-xl font-bold text-emerald-300 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(4)"><span>4</span><kbd class="text-[9px] font-normal text-emerald-400/60 leading-none">4</kbd></button>
                        <button class="score-btn bg-yellow-900/30 hover:bg-yellow-800/50 border border-yellow-700/40 rounded-xl py-3 text-xl font-bold text-yellow-300 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(5)"><span>5</span><kbd class="text-[9px] font-normal text-yellow-400/60 leading-none">5</kbd></button>
                        <button class="score-btn bg-purple-900/30 hover:bg-purple-800/50 border border-purple-700/40 rounded-xl py-3 text-xl font-bold text-purple-300 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.record(6)"><span>6</span><kbd class="text-[9px] font-normal text-purple-400/60 leading-none">6</kbd></button>
                    </div>
                </div>

                <!-- EXTRAS -->
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3 mt-2">
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">
                        &#9888; Extras
                        <span class="text-gray-600 font-normal normal-case ml-2">Keys: W N L Y</span>
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <button class="score-btn bg-amber-900/30 hover:bg-amber-800/50 border border-amber-700/40 rounded-xl py-3 text-sm font-bold text-amber-300 transition min-h-[48px]" onclick="B.extra('wd')">
                            <span class="block text-base">WD</span><span class="text-[10px]">+1</span>
                        </button>
                        <button class="score-btn bg-amber-900/30 hover:bg-amber-800/50 border border-amber-700/40 rounded-xl py-3 text-sm font-bold text-amber-300 transition min-h-[48px]" onclick="B.promptExtra('nb')">
                            <span class="block text-base">NB</span><span class="text-[10px]">No Ball</span>
                        </button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-sm font-bold text-gray-300 transition min-h-[48px]" onclick="B.promptExtra('lb')">
                            <span class="block text-base">LB</span><span class="text-[10px]">Leg Bye</span>
                        </button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-sm font-bold text-gray-300 transition min-h-[48px]" onclick="B.promptExtra('by')">
                            <span class="block text-base">BY</span><span class="text-[10px]">Bye</span>
                        </button>
                    </div>
                    <div id="extraRunsPanel" class="mt-3 pt-3 border-t border-gray-800 hidden">
                        <p class="text-xs text-yellow-400 mb-2" id="extraRunsLabel">Select runs:</p>
                        <div class="grid grid-cols-4 gap-2" id="extraRunsGrid">
                            <button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(1)">1</button>
                            <button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(2)">2</button>
                            <button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(3)">3</button>
                            <button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(4)">4</button>
                        </div>
                        <button class="text-xs text-gray-500 hover:text-gray-300 mt-2 py-1 w-full text-center" onclick="B.cancelExtra()">Cancel</button>
                    </div>
                </div>

                <!-- WICKET + UNDO -->
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3 mt-2">
                    <div class="grid grid-cols-2 gap-2 mb-0">
                        <button id="btnWicket" class="score-btn bg-red-700/50 hover:bg-red-600/60 border border-red-600/40 rounded-xl py-3 text-base font-bold text-red-200 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.toggleWicket()">
                            <span>&#128308; WICKET</span><kbd class="text-[9px] font-normal text-red-400/60 leading-none">X</kbd>
                        </button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl py-3 text-base font-bold text-gray-300 transition min-h-[52px] flex flex-col items-center justify-center gap-0.5" onclick="B.undo()">
                            <span>&#8630; UNDO</span><kbd class="text-[9px] font-normal text-gray-500 leading-none">Z</kbd>
                        </button>
                    </div>
                    <div id="wicketPanel" class="hidden pt-3 border-t border-gray-800 space-y-3">
                        <p class="text-xs font-semibold text-red-400">Dismissal Type:</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('bowled')">Bowled</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('caught')">Caught</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('lbw')">LBW</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('run_out')">Run Out</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('stumped')">Stumped</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('hit_wicket')">Hit Wicket</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('timed_out')">Timed Out</button>
                            <button class="score-btn bg-gray-800 hover:bg-red-900/30 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.recordWicket('obstructing')">Obst. Field</button>
                        </div>
                        <p class="text-xs font-semibold text-amber-400">&#9888; Retired Hurt (not a wicket):</p>
                        <button class="score-btn w-full bg-amber-900/30 hover:bg-amber-800/50 border border-amber-700/40 rounded-lg py-2.5 text-xs font-medium text-amber-300 transition min-h-[44px]" onclick="B.retiredHurt()">
                            Retired Hurt — Replace Striker (no wicket added)
                        </button>
                        <div>
                            <label class="text-[11px] text-gray-400">New Batsman:</label>
                            <select id="wNewBatsman" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white mt-1 focus:border-red-500 focus:outline-none">
                                <option value="">-- Select --</option>
                            </select>
                        </div>
                        <button class="text-xs text-gray-500 hover:text-gray-300 py-1 w-full text-center" onclick="B.toggleWicket()">&#9650; Collapse</button>
                    </div>
                </div>

                <!-- MORE ACTIONS -->
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl p-3 mt-2">
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">&#128295; More Actions</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.showBowlerChange()">
                            &#128260; Change Bowler
                        </button>
                        <button class="score-btn bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg py-2.5 text-xs font-medium text-white transition min-h-[44px]" onclick="B.swapStriker()">
                            &#8646; Swap Strike
                        </button>
                        <button class="score-btn bg-indigo-900/30 hover:bg-indigo-800/50 border border-indigo-700/40 rounded-lg py-2.5 text-xs font-bold text-indigo-300 transition min-h-[44px]" onclick="B.penaltyRuns()">
                            +5 Penalty
                        </button>
                        <button id="btnFreeHit" class="score-btn bg-gray-800 hover:bg-green-900/40 border border-gray-700 rounded-lg py-2.5 text-xs font-bold text-gray-300 transition min-h-[44px]" onclick="B.toggleFreeHit()">
                            &#9889; Free Hit
                        </button>
                    </div>
                    <!-- Bowler Change Panel (inline) -->
                    <div id="bowlerChangePanel" class="hidden mt-3 pt-3 border-t border-gray-800 space-y-2">
                        <p class="text-xs text-blue-400">Select new bowler:</p>
                        <select id="midBowlerSelect" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-orange-500 focus:outline-none">
                            <option value="">-- Choose --</option>
                        </select>
                        <div class="flex gap-2">
                            <button class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-medium py-2 rounded-lg text-sm transition" onclick="B.confirmMidBowler()">Confirm</button>
                            <button class="text-xs text-gray-500 hover:text-gray-300 py-2" onclick="B.hideBowlerChange()">Cancel</button>
                        </div>
                    </div>
                    <!-- Free Hit indicator -->
                    <div id="freeHitIndicator" class="hidden mt-2 text-center">
                        <span class="inline-flex items-center gap-1 bg-green-900/50 border border-green-600/40 rounded-full px-3 py-1 text-xs font-bold text-green-400">
                            &#9889; FREE HIT
                        </span>
                    </div>
                </div>
            </div>

            <!-- OVER COMPLETE ALERT -->
            <div id="overCompleteAlert" class="hidden bg-blue-900/30 border border-blue-700/50 rounded-xl p-4 space-y-2 over-alert-pulse">
                <p class="text-sm font-semibold text-blue-300">&#128260; Over Complete! Pick new bowler:</p>
                <select id="newBowlerSelect" class="w-full bg-gray-800 border border-blue-700 rounded-lg px-3 py-2 text-sm text-white focus:border-orange-500 focus:outline-none">
                    <option value="">-- Choose --</option>
                </select>
                <div class="flex gap-2">
                    <button id="btnConfirmBowler" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-medium py-2 rounded-lg text-sm transition disabled:opacity-40" disabled onclick="B.confirmBowler()">Confirm</button>
                    <button class="text-xs text-gray-500 hover:text-gray-300 py-2" onclick="B.dismissOver()">Dismiss</button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- RIGHT PANE: LIVE OVERLAY PREVIEW                             -->
        <!-- ============================================================ -->
        <div id="overlayPane" class="space-y-3 hidden">

            <!-- Broadcast Overlay Preview -->
            <div id="overlayPreview" class="p-3 sm:p-4">
                <!-- Top row: Team Score + CRR -->
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0 overflow-hidden">
                            <img id="ovTeamLogo" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                            <span class="text-xs font-bold text-white/60" id="ovTeamInit">--</span>
                        </div>
                        <div>
                            <span class="text-[11px] font-semibold text-white/80 uppercase" id="ovTeamName">--</span>
                            <span class="text-[10px] text-white/50 ml-1" id="ovVs">vs --</span>
                        </div>
                    </div>
                    <div class="flex items-baseline gap-0.5">
                        <span class="ov-score-num text-white" id="ovRuns">0</span>
                        <span class="text-lg font-light text-white/30">/</span>
                        <span class="ov-score-num text-white" id="ovWkts">0</span>
                    </div>
                </div>

                <!-- Batsmen Row -->
                <div class="grid grid-cols-2 gap-3 mb-2">
                    <div class="bg-white/5 rounded-lg p-2">
                        <div class="flex items-center gap-2 mb-0.5">
                            <div class="w-7 h-7 rounded-full bg-white/10 overflow-hidden flex-shrink-0">
                                <img id="ovB1Photo" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                            </div>
                            <div>
                                <div class="flex items-center gap-1">
                                    <span class="striker-dot" style="width:5px;height:5px;"></span>
                                    <span class="ov-bat-name text-white" id="ovB1Name">--</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-1 ml-9">
                            <span class="ov-bat-runs text-white" id="ovB1Runs">0</span>
                            <span class="text-[10px] text-white/40">(<span id="ovB1Balls">0</span>b)</span>
                        </div>
                    </div>
                    <div class="bg-white/5 rounded-lg p-2 text-right">
                        <div class="flex items-center gap-2 justify-end mb-0.5">
                            <div>
                                <span class="ov-bat-name text-white/70 block" id="ovB2Name">--</span>
                            </div>
                            <div class="w-7 h-7 rounded-full bg-white/10 overflow-hidden flex-shrink-0">
                                <img id="ovB2Photo" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                            </div>
                        </div>
                        <div class="flex items-baseline gap-1 justify-end mr-9">
                            <span class="ov-bat-runs text-white/70" id="ovB2Runs">0</span>
                            <span class="text-[10px] text-white/40">(<span id="ovB2Balls">0</span>b)</span>
                        </div>
                    </div>
                </div>

                <!-- Bowler + Overs Row -->
                <div class="flex items-center justify-between bg-blue-500/15 rounded-lg p-2 mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-white/10 overflow-hidden flex-shrink-0">
                            <img id="ovBowlerPhoto" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                        </div>
                        <span class="ov-bowler-name text-blue-200" id="ovBowlerName">--</span>
                    </div>
                    <div class="flex items-center gap-3 text-right">
                        <span class="text-xs text-white/60"><span id="ovBowlerFig">0-0</span></span>
                        <span class="text-xs text-white/60">(<span id="ovBowlerOv">0.0</span>)</span>
                    </div>
                </div>

                <!-- CRR + Overs -->
                <div class="flex items-center justify-between text-[10px] text-white/50 mb-2 px-1">
                    <span>CRR: <span class="text-white/80 font-semibold" id="ovCrr">0.00</span></span>
                    <span>Overs: <span class="text-white/80 font-mono font-semibold" id="ovOvers">0.0</span></span>
                    <span>Extras: <span class="text-white/80 font-semibold" id="ovExtras">0</span></span>
                </div>

                <!-- Ball Tracker Dots (this over) -->
                <div class="flex items-center justify-between gap-1.5 px-1">
                    <div class="flex gap-1.5" id="ovBallDots">
                        <div class="track-dot empty">·</div>
                        <div class="track-dot empty">·</div>
                        <div class="track-dot empty">·</div>
                        <div class="track-dot empty">·</div>
                        <div class="track-dot empty">·</div>
                        <div class="track-dot empty">·</div>
                    </div>
                    <span class="text-[10px] text-white/40 whitespace-nowrap" id="ovOverRunsLbl">Ov: 0</span>
                </div>
            </div>

            <!-- Ball-by-Ball Timeline -->
            <div class="bg-gray-900/80 border border-gray-800 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2.5 border-b border-gray-800">
                    <h3 class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">&#128337; Timeline</h3>
                    <span id="logCount" class="text-[10px] text-gray-600">0 balls</span>
                </div>
                <div id="ballLog" class="max-h-36 overflow-y-auto p-2">
                    <p class="text-[11px] text-gray-600 text-center py-4">No balls recorded yet.</p>
                </div>
            </div>

            <!-- Completed Match Scorecard (shown for completed matches) -->
            <div id="completedCard" class="hidden space-y-3">
                <div class="bg-gradient-to-r from-gray-700 to-gray-600 rounded-xl p-4 text-white">
                    <p id="compTitle" class="text-[11px] font-medium text-gray-300">--</p>
                    <p class="text-3xl font-bold"><span id="compScore">0/0</span></p>
                    <p class="text-[11px] text-gray-400">Ov: <span id="compOvers">0.0</span> | CRR: <span id="compCrr">0.00</span></p>
                </div>
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-800"><span class="text-[11px] font-semibold text-gray-400 uppercase">&#127951; Batting</span></div>
                    <div class="overflow-x-auto"><table class="w-full text-[11px]" id="compBatTbl"></table></div>
                </div>
                <div class="bg-gray-900/80 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-800"><span class="text-[11px] font-semibold text-gray-400 uppercase">&#127919; Bowling</span></div>
                    <div class="overflow-x-auto"><table class="w-full text-[11px]" id="compBowlTbl"></table></div>
                </div>
                <button onclick="B.loadCompleted()" class="w-full bg-gray-800 hover:bg-gray-700 text-gray-300 py-2.5 rounded-lg text-sm transition">&#8635; Refresh</button>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================================================
// BUZZER — Web Audio API (per rules.md §1: audible alerts)
// ============================================================================
const Buzzer = {
    ctx: null,
    init() {
        if (!this.ctx) {
            try { this.ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) {}
        }
    },
    beep(freq, dur, type, vol) {
        type = type || 'square'; vol = vol || 0.08;
        this.init();
        if (!this.ctx) return;
        var o = this.ctx.createOscillator();
        var g = this.ctx.createGain();
        o.type = type; o.frequency.value = freq;
        g.gain.setValueAtTime(vol, this.ctx.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + dur);
        o.connect(g); g.connect(this.ctx.destination);
        o.start(); o.stop(this.ctx.currentTime + dur);
    },
    overComplete: function() {
        this.init(); if (!this.ctx) return;
        this.beep(880, 0.15, 'square', 0.07);
        var self = this;
        setTimeout(function(){ self.beep(1100, 0.2, 'square', 0.07); }, 180);
    },
    wicket: function() {
        this.init(); if (!this.ctx) return;
        this.beep(600, 0.12, 'sawtooth', 0.06);
        var self = this;
        setTimeout(function(){ self.beep(350, 0.25, 'sawtooth', 0.06); }, 140);
    },
    boundary: function() {
        this.init(); if (!this.ctx) return;
        this.beep(1000, 0.08, 'sine', 0.05);
        var self = this;
        setTimeout(function(){ self.beep(1400, 0.1, 'sine', 0.05); }, 100);
    }
};

// ============================================================================
// TOAST SYSTEM
// ============================================================================
function toast(msg, type='success') {
    const c = document.getElementById('toastContainer');
    const t = document.createElement('div');
    const bg = type==='error'?'bg-red-600':type==='warning'?'bg-amber-600':type==='info'?'bg-blue-600':'bg-emerald-600';
    t.className = bg+' text-white px-4 py-2.5 rounded-lg shadow-xl text-sm font-medium toast-in pointer-events-auto max-w-xs text-center';
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => { t.classList.replace('toast-in','toast-out'); setTimeout(() => t.remove(), 200); }, 2200);
}

// ============================================================================
// API HELPERS
// ============================================================================
async function api(action, data={}, method='POST') {
    try {
        const url = method==='GET' ? `api.php?action=${action}&${new URLSearchParams(data)}` : `api.php?action=${action}`;
        let opts = { method, headers:{'Content-Type':'application/json'}, credentials:'same-origin' };
        if (method==='POST') opts.body = JSON.stringify(data);
        const r = await fetch(url, opts);
        const j = await r.json();
        if (!r.ok || j.error) throw new Error(j.error || `HTTP ${r.status}`);
        return j;
    } catch(e) { throw e; }  // caller decides whether to toast
}

// ============================================================================
// GLOBAL STATE
// ============================================================================
const $ = id => document.getElementById(id);
let matchId = null, state = null, pendingExtra = null;
let battingPlayers = [], bowlingPlayers = [];
let sseConn = null, lastEpoch = 0, sseAttempt = 0, sseTimer = null;
let overLocked = false, audioReady = false;
let sseEverOpened = false;          // track whether SSE has ever successfully opened
let sseConsecutiveErrors = 0;       // count consecutive errors for status escalation
let freeHitActive = false;           // free hit tracking for ICC wicket restrictions

// ============================================================================
// PLAYING XI MANAGER
// ============================================================================
const XI = {
    // Per-team selected IDs: { A: Set, B: Set }
    selected: { A: new Set(), B: new Set() },
    captainId: { A: 0, B: 0 },
    allPlayers: { A: [], B: [] },
    teamId: { A: 0, B: 0 },
    teamName: { A: '', B: '' },
    xiLoaded: false,

    reset() {
        XI.selected = { A: new Set(), B: new Set() };
        XI.captainId = { A: 0, B: 0 };
        XI.allPlayers = { A: [], B: [] };
        XI.teamId = { A: 0, B: 0 };
        XI.teamName = { A: '', B: '' };
        XI.xiLoaded = false;
        $('xiSection').classList.add('hidden');
        $('xiListA').innerHTML = '<p class="text-[10px] text-gray-600 text-center py-4">Select a match first</p>';
        $('xiListB').innerHTML = '<p class="text-[10px] text-gray-600 text-center py-4">Select a match first</p>';
        $('btnSaveXIA').classList.add('hidden');
        $('btnSaveXIB').classList.add('hidden');
        $('xiCountA').textContent = '0/11';
        $('xiCountB').textContent = '0/11';
    },

    async load(mid, taId, tbId, taName, tbName) {
        XI.reset();
        if (!mid) return;
        XI.teamId.A = taId; XI.teamId.B = tbId;
        XI.teamName.A = taName || 'Team A';
        XI.teamName.B = tbName || 'Team B';
        $('xiLabelA').textContent = XI.teamName.A;
        $('xiLabelB').textContent = XI.teamName.B;
        $('xiSection').classList.remove('hidden');

        // Fetch existing XI selections from server
        try {
            const d = await api('get_playing_xi', {match_id: mid}, 'GET');
            if (d && d.success && d.teams) {
                if (d.teams[taId]) {
                    d.teams[taId].forEach(p => {
                        XI.selected.A.add(parseInt(p.id));
                        if (p.is_captain) XI.captainId.A = parseInt(p.id);
                    });
                }
                if (d.teams[tbId]) {
                    d.teams[tbId].forEach(p => {
                        XI.selected.B.add(parseInt(p.id));
                        if (p.is_captain) XI.captainId.B = parseInt(p.id);
                    });
                }
            }
        } catch(e) { /* XI may not exist yet */ }

        // Fetch all players for both teams
        await Promise.all([
            XI._fetchTeam('A', taId),
            XI._fetchTeam('B', tbId),
        ]);
        XI.xiLoaded = true;
    },

    async _fetchTeam(side, teamId) {
        if (!teamId) return;
        try {
            const d = await api('get_players', {team_id: teamId, match_id: matchId}, 'GET');
            XI.allPlayers[side] = (d.players || []).map(p => {
                p.id = parseInt(p.id);
                return p;
            });
        } catch(e) { XI.allPlayers[side] = []; }
        XI._render(side);
    },

    _render(side) {
        const list = side === 'A' ? $('xiListA') : $('xiListB');
        const btn = side === 'A' ? $('btnSaveXIA') : $('btnSaveXIB');
        const countEl = side === 'A' ? $('xiCountA') : $('xiCountB');
        const players = XI.allPlayers[side];
        const selected = XI.selected[side];

        if (players.length === 0) {
            list.innerHTML = '<p class="text-[10px] text-gray-600 text-center py-4">No players in team</p>';
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        btn.disabled = false;
        XI._updateCount(side);

        let html = '';
        players.forEach(p => {
            const checked = selected.has(p.id);
            const isCap = XI.captainId[side] === p.id;
            html += `<label class="flex items-center gap-2 cursor-pointer px-2 py-1.5 rounded hover:bg-gray-800/60 transition">
                <input type="checkbox" class="xi-cb w-3.5 h-3.5 rounded border-gray-600 bg-gray-700 text-green-500 focus:ring-green-500/30" data-side="${side}" data-pid="${p.id}" ${checked ? 'checked' : ''} onchange="XI.toggle('${side}', ${p.id}, this)">
                <span class="text-xs text-gray-300 truncate flex-1">${p.name}</span>
                <span class="text-[9px] text-gray-500">${p.role}</span>
                <input type="radio" name="captain_${side}" class="xi-cap w-3 h-3 border-gray-600 bg-gray-700 text-orange-500 focus:ring-orange-500/30" ${isCap ? 'checked' : ''} ${!checked ? 'disabled' : ''} onclick="XI.setCaptain('${side}', ${p.id})" title="Captain">
            </label>`;
        });
        list.innerHTML = html;
    },

    toggle(side, playerId, checkbox) {
        const checked = checkbox.checked;
        if (checked) {
            if (XI.selected[side].size >= 11) {
                toast('Maximum 11 players per team.', 'warning');
                checkbox.checked = false;
                return;
            }
            XI.selected[side].add(playerId);
        } else {
            XI.selected[side].delete(playerId);
            if (XI.captainId[side] === playerId) XI.captainId[side] = 0;
        }
        XI._render(side);
    },

    _updateCount(side) {
        const countEl = side === 'A' ? $('xiCountA') : $('xiCountB');
        const count = XI.selected[side].size;
        countEl.textContent = count + '/11';
        countEl.className = 'text-[10px] ' + (count === 11 ? 'text-green-400 font-bold' : count > 11 ? 'text-red-400' : 'text-gray-500');
    },

    setCaptain(side, playerId) {
        XI.captainId[side] = playerId;
    },

    async save(side) {
        const tid = XI.teamId[side];
        const playerIds = Array.from(XI.selected[side]);
        if (!matchId || !tid) { toast('No match/team selected.', 'error'); return; }

        try {
            await api('set_playing_xi', {
                match_id: matchId,
                team_id: tid,
                player_ids: playerIds,
                captain_id: XI.captainId[side] || 0,
            });
            toast(`${XI.teamName[side]} XI saved (${playerIds.length} players)`, 'success');

            // Refresh player dropdowns in setup if currently showing
            if (!$('setupSection').classList.contains('hidden')) {
                loadSetupPlayers();
            }
            // Also refresh bowler dropdown cache
            if (state && state.bowling_team_id) {
                if ((side === 'A' && XI.teamId.A === state.bowling_team_id) ||
                    (side === 'B' && XI.teamId.B === state.bowling_team_id)) {
                    bowlingPlayers = playerIds.map(id => {
                        const p = XI.allPlayers[side].find(pl => pl.id === id);
                        return p || {id: id, name: 'Player ' + id, role: ''};
                    });
                }
            }
            if (state && state.batting_team_id) {
                if ((side === 'A' && XI.teamId.A === state.batting_team_id) ||
                    (side === 'B' && XI.teamId.B === state.batting_team_id)) {
                    battingPlayers = playerIds.map(id => {
                        const p = XI.allPlayers[side].find(pl => pl.id === id);
                        return p || {id: id, name: 'Player ' + id, role: ''};
                    });
                }
            }
        } catch(e) { toast(e.message, 'error'); }
    },

    toggleSection() {
        $('xiPanels').classList.toggle('hidden');
        const btn = $('btnToggleXI');
        if ($('xiPanels').classList.contains('hidden')) {
            btn.innerHTML = '&#9660; Expand';
        } else {
            btn.innerHTML = '&#9650; Collapse';
        }
    },

    /** Return the playing XI player IDs for a team, or null if no XI set */
    getSelectedIds(teamId) {
        if (teamId === XI.teamId.A) return Array.from(XI.selected.A);
        if (teamId === XI.teamId.B) return Array.from(XI.selected.B);
        return null;
    },

    /** Filter a player list to only those in the playing XI */
    filterXI(players, teamId) {
        if (!players || !Array.isArray(players)) return [];
        const xiIds = XI.getSelectedIds(teamId);
        if (!xiIds || xiIds.length === 0) return players; // No XI set — show all
        const xiSet = new Set(xiIds);
        return players.filter(p => xiSet.has(parseInt(p.id)));
    },
};

// ============================================================================
// SSE BACKOFF — Jittered exponential (per rules.md §7 / features.md §1)
// ============================================================================
function sseBackoff() {
    const base = 1200, max = 30000, attempt = sseAttempt++;
    return Math.min(base * Math.pow(2, attempt) * (0.8 + Math.random() * 0.4), max);
}

function startSSE() {
    stopSSE();
    if (!matchId) return;
    sseAttempt = 0;
    sseEverOpened = false;
    sseConsecutiveErrors = 0;
    setConn('connecting');          // show yellow while establishing
    connectSSE();
}

function stopSSE() {
    if (sseConn) {
        // Remove listeners before closing to avoid triggering onerror handler
        sseConn.onopen = null;
        sseConn.onerror = null;
        sseConn.close();
        sseConn = null;
    }
    if (sseTimer) { clearTimeout(sseTimer); sseTimer = null; }
}

function connectSSE() {
    stopSSE();
    if (!matchId) return;
    setConn('connecting');

    const url = `api.php?action=sse_stream&match_id=${matchId}&last_epoch=${lastEpoch}`;
    sseConn = new EventSource(url);

    sseConn.addEventListener('update', e => {
        try {
            const d = JSON.parse(e.data);
            if (d && !d.error && d.last_updated_epoch) {
                lastEpoch = d.last_updated_epoch;
                B.updateFromOverlay(d);
                if (d.last_5_balls) B.renderBalls(d.last_5_balls, d.sequence_id);
                if (d.this_over_balls) B.renderOverBalls(d.this_over_balls);
                setConn('green');
                sseConsecutiveErrors = 0;
                if (!audioReady) { Buzzer.init(); audioReady = true; }
            }
        } catch(err) { console.error('SSE parse:', err); }
    });

    sseConn.addEventListener('reconnect', e => {
        // Server asked us to reconnect (e.g., timeout after 5 min)
        try {
            const d = JSON.parse(e.data);
            sseConn.close();
            sseTimer = setTimeout(connectSSE, d.retry || sseBackoff());
        } catch(err) { sseTimer = setTimeout(connectSSE, sseBackoff()); }
    });

    sseConn.onopen = () => {
        sseAttempt = 0;
        sseEverOpened = true;
        sseConsecutiveErrors = 0;
        setConn('green');
    };

    sseConn.onerror = () => {
        // EventSource fires onerror for transient network blips too.
        // Only escalate to red after multiple consecutive failures.
        sseConsecutiveErrors++;

        if (sseConn) {
            sseConn.close();
            sseConn = null;
        }

        if (sseEverOpened) {
            // Connection was previously healthy — show yellow and retry
            setConn('connecting');
        } else if (sseConsecutiveErrors >= 3) {
            // Never successfully connected after multiple attempts — show red
            setConn('red');
        } else {
            // Still trying to establish first connection — keep showing yellow
            setConn('connecting');
        }

        sseTimer = setTimeout(connectSSE, sseBackoff());
    };
}

function setConn(color) {
    var dot = $('connDot'), lbl = $('connLabel');
    if (color === 'green') {
        dot.className = 'w-2 h-2 rounded-full bg-green-500 live-dot';
        lbl.textContent = 'live';
        lbl.className = 'text-[10px] text-green-400 hidden sm:inline';
    } else if (color === 'red') {
        dot.className = 'w-2 h-2 rounded-full bg-red-500';
        lbl.textContent = 'offline';
        lbl.className = 'text-[10px] text-red-400 hidden sm:inline';
    } else if (color === 'connecting') {
        dot.className = 'w-2 h-2 rounded-full bg-yellow-500 live-dot';
        lbl.textContent = 'connecting';
        lbl.className = 'text-[10px] text-yellow-400 hidden sm:inline';
    } else {
        // idle / no match loaded
        dot.className = 'w-2 h-2 rounded-full bg-gray-600';
        lbl.textContent = 'idle';
        lbl.className = 'text-[10px] text-gray-500 hidden sm:inline';
    }
}

// ============================================================================
// PLAYER LOADING FOR SETUP
// ============================================================================
$('sBatTeam').addEventListener('change', loadSetupPlayers);
$('sBowlTeam').addEventListener('change', loadSetupPlayers);
$('sStriker').addEventListener('change', validateStartButton);
$('sNonStriker').addEventListener('change', validateStartButton);
$('sBowler').addEventListener('change', validateStartButton);

function validateStartButton() {
    const s1 = $('sStriker'), s2 = $('sNonStriker'), s3 = $('sBowler');
    const sid = parseInt(s1.value) || 0;
    const nid = parseInt(s2.value) || 0;

    // Cross-disable: striker selected → disabled in non-striker, and vice versa
    [...s1.options].forEach(o => {
        if (o.value && parseInt(o.value) === nid) o.disabled = true;
        else o.disabled = false;
    });
    [...s2.options].forEach(o => {
        if (o.value && parseInt(o.value) === sid) o.disabled = true;
        else o.disabled = false;
    });

    // Button enabled only when ALL 5 fields are valid
    const btn = $('btnStart');
    const allFilled = $('sBatTeam').value && $('sBowlTeam').value
                    && s1.value && s2.value && s3.value;
    const notDuplicate = s1.value !== s2.value;
    btn.disabled = !(allFilled && notDuplicate);
}

let setupLoading = false, setupPending = false;

async function loadSetupPlayers() {
    // Skip if already loading; mark as pending for fresh reload after current finishes
    if (setupLoading) { setupPending = true; return; }
    setupLoading = true;
    setupPending = false;

    // Small delay to coalesce rapid duplicate calls
    await new Promise(r => setTimeout(r, 30));
    // Check if another call was queued during the delay
    if (setupPending) { setupLoading = false; return loadSetupPlayers(); }

    const batId = $('sBatTeam').value, bowlId = $('sBowlTeam').value;
    const s1=$('sStriker'), s2=$('sNonStriker'), s3=$('sBowler'), btn=$('btnStart');
    [s1,s2,s3].forEach(s=>s.innerHTML='<option value="">-- Select --</option>');
    s1.disabled = !batId; s2.disabled = !batId; s3.disabled = !bowlId;

    try {
        if (batId) {
            const d = await api('get_players', {team_id: batId, match_id: matchId}, 'GET');
            battingPlayers = d.players || [];
            // Filter to playing XI if set
            const batFiltered = XI.filterXI(battingPlayers, parseInt(batId));
            batFiltered.forEach(p => {
                s1.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name} (${p.role})</option>`);
                s2.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name} (${p.role})</option>`);
            });
        }
        if (bowlId) {
            const d = await api('get_players', {team_id: bowlId, match_id: matchId}, 'GET');
            bowlingPlayers = d.players || [];
            // Filter to playing XI if set
            const bowlFiltered = XI.filterXI(bowlingPlayers, parseInt(bowlId));
            bowlFiltered.forEach(p => {
                s3.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name} (${p.role})</option>`);
            });
        }
        btn.disabled = true; // Will be validated by validateStartButton on player select
    } catch(e) {
        toast('Failed to load players.', 'error');
        btn.disabled = true;
    }
    setupLoading = false;
    // If another call was queued while we were loading, run it now
    if (setupPending) { setupPending = false; loadSetupPlayers(); }
    // Re-validate button state after all dropdowns populated
    validateStartButton();
}

// ============================================================================
// MATCH LOADING
// ============================================================================
$('btnLoad').addEventListener('click', async () => {
    const sel = $('matchSelect');
    matchId = parseInt(sel.value);
    const status = sel.options[sel.selectedIndex]?.dataset?.status;
    if (!matchId) { toast('Select a match.', 'warning'); return; }
    overLocked = false;
    $('overCompleteAlert').classList.add('hidden');

    // Hide all dynamic panels initially
    $('liveScoreCard').classList.add('hidden');
    $('playerCards').classList.add('hidden');
    $('btnGroup').classList.add('hidden');
    $('overlayPane').classList.add('hidden');
    $('completedCard').classList.add('hidden');
    $('btnToggleSetup').classList.add('hidden');
    $('btnToggleOverlay').classList.add('hidden');

    stopSSE();
    setConn('idle');

    // Load playing XI for this match (team IDs from dataset)
    const taId = parseInt(sel.options[sel.selectedIndex]?.dataset?.taId) || 0;
    const tbId = parseInt(sel.options[sel.selectedIndex]?.dataset?.tbId) || 0;
    const taName = sel.options[sel.selectedIndex]?.textContent?.match(/\(([^)]+)\s+vs/)?.[1] || 'Team A';
    const tbName = sel.options[sel.selectedIndex]?.textContent?.match(/vs\s+([^)]+)/)?.[1] || 'Team B';
    if (taId && tbId) {
        XI.load(matchId, taId, tbId, taName, tbName);
    }

    // Try live state — expected to fail for non-live matches, so catch silently
    let isLive = false;
    try {
        const st = await api('get_live_state', {match_id: matchId}, 'GET');
        if (st && !st.error) {
            state = st;

            // Detect if innings needs setup (no batsmen/bowler configured)
            const needsSetup = (!st.striker && !st.bowler && st.innings_number >= 1);

            if (needsSetup) {
                // Innings not yet configured (e.g. after all-out transition)
                B.showLive(false);
                $('setupSection').classList.remove('hidden');
                $('btnToggleSetup').classList.remove('hidden');
                $('overlayPane').classList.remove('hidden');
                // Auto-populate team dropdowns if we have team info
                if (st.batting_team_id) {
                    $('sBatTeam').value = st.batting_team_id;
                }
                if (st.bowling_team_id) {
                    $('sBowlTeam').value = st.bowling_team_id;
                }
                if (st.batting_team_id || st.bowling_team_id) {
                    loadSetupPlayers();
                }
                // Show target for 2nd innings
                if (st.innings_number === 2 && st.target) {
                    $('targetBanner').classList.remove('hidden');
                    $('targetBanner').innerHTML = '\uD83C\uDFAF Chasing <b>' + st.target + '</b> to win';
                    var setupHdr = document.querySelector('#setupSection h3');
                    if (setupHdr) setupHdr.textContent = '\u2699\uFE0F 2nd Innings Setup \u2014 Target: ' + st.target;
                    var tossBox = document.querySelector('#setupSection .bg-amber-900\\/20');
                    if (tossBox) tossBox.classList.add('hidden');
                }
                toast('Select batsmen and bowler to start innings.', 'info');
                isLive = true; // Mark as live so we don't fall through to upcoming/completed
            } else {
                B.updateScoreDisplay(st);
                B.showLive(true);
                startSSE();
                B.fetchBalls();
                $('btnToggleSetup').classList.remove('hidden');
                $('btnToggleOverlay').classList.remove('hidden');
                $('overlayPane').classList.remove('hidden');
                $('scoringPane').style.display = '';
                isLive = true;
            }
        }
    } catch(e) {
        // Expected: upcoming/completed matches have no live_state
        // Only show error if the match status is 'live' (inconsistent state)
        if (status === 'live') {
            toast('Match marked live but no state found. Try re-starting.', 'warning');
        }
    }

    if (isLive) return;

    B.showLive(false);

    if (status === 'upcoming') {
        B.populateToss(sel.options[sel.selectedIndex]);
        $('setupSection').classList.remove('hidden');
        $('btnToggleSetup').classList.remove('hidden');
        $('overlayPane').classList.remove('hidden');
        toast('Match loaded. Configure innings to start.', 'success');
    } else if (status === 'completed') {
        $('overlayPane').classList.remove('hidden');
        B.loadCompleted();
    } else if (status === 'live') {
        // Already warned above
    }
});

$('btnToggleSetup').addEventListener('click', () => {
    $('setupSection').classList.toggle('hidden');
});

$('btnToggleOverlay').addEventListener('click', () => {
    $('overlayPane').classList.toggle('hidden');
});

// ============================================================================
// START MATCH
// ============================================================================
$('btnStart').addEventListener('click', async () => {
    const batTeam = parseInt($('sBatTeam').value);
    const bowlTeam = parseInt($('sBowlTeam').value);
    const sid = parseInt($('sStriker').value);
    const nid = parseInt($('sNonStriker').value);
    const bid = parseInt($('sBowler').value);
    if (!matchId || !batTeam || !bowlTeam || !sid || !nid || !bid) {
        toast('Fill all fields.', 'warning'); return;
    }
    if (batTeam === bowlTeam) { toast('Teams must differ.', 'error'); return; }
    if (sid === nid) { toast('Striker and non-striker must be different players.', 'error'); return; }
    if (sid === bid || nid === bid) { toast('Bowler must be from bowling team only.', 'error'); return; }

    try {
        await api('start_match', {
            match_id: matchId, batting_team_id: batTeam, bowling_team_id: bowlTeam,
            striker_id: sid, non_striker_id: nid, bowler_id: bid
        });

        $('setupSection').classList.add('hidden');

        const batName = $('sBatTeam').options[$('sBatTeam').selectedIndex]?.text || 'Batting';

        // Set initial display
        $('scBatTeam').textContent = batName;
        $('scRuns').textContent = '0'; $('scWkts').textContent = '0';
        $('scOvers').textContent = '0.0'; $('scCrr').textContent = '0.00'; $('scExtras').textContent = '0';

        const st = await api('get_live_state', {match_id: matchId}, 'GET');
        if (st && !st.error) {
            st.batting_team_name = batName;
            state = st;
            B.updateScoreDisplay(st);

            // Show 2nd innings context
            if (st.innings_number === 2 && st.target) {
                $('targetBanner').classList.remove('hidden');
                $('targetBanner').innerHTML = '\uD83C\uDFAF Chasing <b>' + st.target + '</b> to win';
                $('scWkts').textContent = st.target + ' target | ' + st.total_wickets + ' wkts';
                toast('2nd Innings started! Target: ' + st.target, 'success');
            } else {
                $('targetBanner').classList.add('hidden');
                toast('Match started!', 'success');
            }
        }

        // Reset toss section (show again for potential restart)
        var tossBox = document.querySelector('#setupSection .bg-amber-900\\/20');
        if (tossBox) tossBox.classList.remove('hidden');
        var setupHdr = document.querySelector('#setupSection h3');
        if (setupHdr) setupHdr.textContent = '\u2699\uFE0F Innings Setup';

        B.showLive(true);
        startSSE();
        $('btnToggleSetup').classList.remove('hidden');
        $('btnToggleOverlay').classList.remove('hidden');
        $('overlayPane').classList.remove('hidden');
        $('btnStart').disabled = true;
    } catch(e) { toast(e.message, 'error'); }
});

// ============================================================================
// BRIDGE OBJECT — All scoring operations
// ============================================================================
const B = {

    // ---- Toss Setup ----
    populateToss(optEl) {
        if (!optEl) return;
        const taName = optEl.textContent.match(/\(([^)]+)\s+vs/)?.[1] || 'Team A';
        const tbName = optEl.textContent.match(/vs\s+([^)]+)/)?.[1] || 'Team B';
        const taId = parseInt(optEl.dataset.taId) || 0;
        const tbId = parseInt(optEl.dataset.tbId) || 0;
        const savedWinner = parseInt(optEl.dataset.tossWon) || 0;
        const savedDec = optEl.dataset.tossDec || '';
        const savedBatFirst = parseInt(optEl.dataset.batFirst) || 0;

        const sel = $('sTossWinner');
        sel.innerHTML = '<option value="">-- Select --</option>';
        if (taId) sel.insertAdjacentHTML('beforeend', `<option value="${taId}">${taName}</option>`);
        if (tbId) sel.insertAdjacentHTML('beforeend', `<option value="${tbId}">${tbName}</option>`);

        // Restore saved toss if exists
        if (savedWinner) {
            sel.value = savedWinner;
            $('sTossDecision').disabled = false;
            if (savedDec) $('sTossDecision').value = savedDec;
            $('btnSaveToss').disabled = false;
            B.showTossSummary(taId, tbId, savedWinner, savedDec);

            // Auto-set batting/bowling teams from toss
            if (savedBatFirst) {
                $('sBatTeam').value = savedBatFirst;
                $('sBowlTeam').value = savedBatFirst === taId ? tbId : taId;
                loadSetupPlayers();
            }
        }

        sel.onchange = function() {
            $('sTossDecision').disabled = !this.value;
            $('btnSaveToss').disabled = !this.value;
            // Reset batting/bowling teams
            if (!this.value) {
                $('sTossDecision').value = '';
            }
        };

        $('sTossDecision').onchange = function() {
            $('btnSaveToss').disabled = !this.value || !$('sTossWinner').value;
        };
    },

    showTossSummary(taId, tbId, winnerId, dec) {
        const label = $('tossSummary');
        if (!winnerId || !dec) { label.classList.add('hidden'); return; }
        const winnerName = winnerId === taId ?
            ($('sTossWinner').options[1]?.text || 'Team A') :
            ($('sTossWinner').options[2]?.text || 'Team B');
        const decText = dec === 'bat' ? 'elected to bat' : 'elected to bowl';
        label.textContent = `${winnerName} won toss & ${decText}`;
        label.classList.remove('hidden');
    },

    async saveToss() {
        if (!matchId) return;
        const winner = parseInt($('sTossWinner').value);
        const dec = $('sTossDecision').value;
        if (!winner || !dec) { toast('Select toss winner and decision.', 'warning'); return; }

        try {
            await api('set_toss', {match_id: matchId, toss_won_by: winner, toss_decision: dec});
            const taId = parseInt($('sTossWinner').options[1]?.value) || 0;
            const tbId = parseInt($('sTossWinner').options[2]?.value) || 0;
            const batFirst = dec === 'bat' ? winner : (winner === taId ? tbId : taId);

            // Auto-set teams based on toss
            $('sBatTeam').value = batFirst;
            $('sBowlTeam').value = batFirst === taId ? tbId : taId;
            loadSetupPlayers();
            B.showTossSummary(taId, tbId, winner, dec);
            $('btnSaveToss').disabled = true;
            toast('Toss saved. Teams auto-set.', 'success');
        } catch(e) { toast(e.message, 'error'); }
    },

    // ---- Score Display Update ----
    updateScoreDisplay(st) {
        if (!st) return;
        state = st;
        $('liveScoreCard').classList.remove('hidden');
        $('playerCards').classList.remove('hidden');
        $('btnGroup').classList.remove('hidden');

        const name = st.batting_team_name || 'Batting';
        const innNum = st.innings_number || 1;
        const target = st.target || 0;
        const isSecond = (innNum === 2 && target > 0);

        $('scBatTeam').textContent = name + (isSecond ? ' (2nd inn)' : '');
        $('scRuns').textContent = st.total_runs;
        $('scWkts').textContent = isSecond
            ? st.total_wickets + ' (need ' + Math.max(0, target - st.total_runs) + ')'
            : st.total_wickets;
        $('scOvers').textContent = st.over_display || '0.0';
        $('scCrr').textContent = parseFloat(st.current_run_rate || 0).toFixed(2);
        $('scExtras').textContent = st.total_extras || 0;

        // Update target banner
        if (isSecond) {
            $('targetBanner').classList.remove('hidden');
            const need = Math.max(0, target - st.total_runs);
            $('targetBanner').innerHTML = '\uD83C\uDFAF Chasing <b>' + target + '</b> \u2014 need <b>' + need + '</b> more to win';
        } else {
            $('targetBanner').classList.add('hidden');
        }

        if (st.striker) {
            $('pStrikerName').innerHTML = B.esc(st.striker.name) + ' <span class="text-red-400">*</span>';
            $('pStrikerStats').textContent = `${st.striker.runs}(${st.striker.balls}) SR:${st.striker.strike_rate}`;
            B.setPreviewPhoto('pStrikerPhoto', st.striker.photo, st.striker.name);
        }
        if (st.non_striker) {
            $('pNonStrikerName').textContent = st.non_striker.name;
            $('pNonStrikerStats').textContent = `${st.non_striker.runs}(${st.non_striker.balls}) SR:${st.non_striker.strike_rate}`;
            B.setPreviewPhoto('pNonStrikerPhoto', st.non_striker.photo, st.non_striker.name);
        }
        if (st.bowler) {
            $('pBowlerName').textContent = st.bowler.name;
            $('pBowlerStats').textContent = `${st.bowler.overs_bowled}-${st.bowler.maidens}-${st.bowler.runs_conceded}-${st.bowler.wickets_taken}`;
            B.setPreviewPhoto('pBowlerPhoto', st.bowler.photo, st.bowler.name);
        }

        B.updateOverlayPreview(st);

        // Cache player lists lazily
        if (st.bowling_team_id && bowlingPlayers.length === 0) {
            api('get_players', {team_id: st.bowling_team_id, match_id: matchId}, 'GET').then(d => bowlingPlayers = d.players||[]).catch(()=>{});
        }
        if (st.batting_team_id && battingPlayers.length === 0) {
            api('get_players', {team_id: st.batting_team_id, match_id: matchId}, 'GET').then(d => battingPlayers = d.players||[]).catch(()=>{});
        }
    },

    // ---- Overlay Preview Update ----
    updateOverlayPreview(st) {
        if (!st) return;
        const name = st.batting_team_name || 'Batting';
        $('ovTeamName').textContent = name.toUpperCase();
        $('ovTeamInit').textContent = name.substring(0,2).toUpperCase();
        $('ovVs').textContent = 'vs ' + (st.bowling_team_name || '--');
        // Team logo
        if (st.batting_team_logo) {
            var tl = $('ovTeamLogo');
            tl.src = st.batting_team_logo;
            tl.style.display = '';
            tl.onerror = function() { this.style.display = 'none'; $('ovTeamInit').style.display = ''; };
            $('ovTeamInit').style.display = 'none';
        } else {
            $('ovTeamLogo').style.display = 'none';
            $('ovTeamInit').style.display = '';
        }
        $('ovRuns').textContent = st.total_runs;
        $('ovWkts').textContent = st.total_wickets;
        $('ovOvers').textContent = st.over_display || '0.0';
        $('ovCrr').textContent = parseFloat(st.current_run_rate || 0).toFixed(2);
        $('ovExtras').textContent = st.total_extras || 0;

        if (st.striker) {
            $('ovB1Name').textContent = st.striker.name.toUpperCase();
            $('ovB1Runs').textContent = st.striker.runs;
            $('ovB1Balls').textContent = st.striker.balls;
            B.setPreviewPhoto('ovB1Photo', st.striker.photo, st.striker.name);
        }
        if (st.non_striker) {
            $('ovB2Name').textContent = st.non_striker.name.toUpperCase();
            $('ovB2Runs').textContent = st.non_striker.runs;
            $('ovB2Balls').textContent = st.non_striker.balls;
            B.setPreviewPhoto('ovB2Photo', st.non_striker.photo, st.non_striker.name);
        }
        if (st.bowler) {
            $('ovBowlerName').textContent = st.bowler.name.toUpperCase();
            $('ovBowlerFig').textContent = `${st.bowler.wickets_taken}-${st.bowler.runs_conceded}`;
            $('ovBowlerOv').textContent = st.bowler.overs_bowled || '0.0';
            B.setPreviewPhoto('ovBowlerPhoto', st.bowler.photo, st.bowler.name);
        }
    },

    setPreviewPhoto(imgId, photoPath, name) {
        var img = document.getElementById(imgId);
        if (!img) return;
        if (photoPath) {
            img.src = photoPath;
            img.style.display = '';
            img.onerror = function() { this.style.display = 'none'; };
        } else {
            img.style.display = 'none';
        }
    },

    esc(str) { var d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; },

    // ---- Update from SSE overlay data ----
    updateFromOverlay(d) {
        if (!d || d.match_not_started) return;
        const st = {
            batting_team_name: d.batting_team?.short_name || d.batting_team?.name || 'Batting',
            batting_team_logo: d.batting_team?.logo || null,
            bowling_team_name: d.bowling_team?.short_name || d.bowling_team?.name || '--',
            total_runs: d.batting_team?.runs || 0,
            total_wickets: d.batting_team?.wickets || 0,
            total_extras: d.batting_team?.extras || 0,
            over_display: d.batting_team?.overs || '0.0',
            current_run_rate: d.current_run_rate || 0,
            innings_number: d.innings_number || 1,
            target: d.target || 0,
            striker: d.striker ? {id:d.striker.id,name:d.striker.name,photo:d.striker.photo,runs:d.striker.runs,balls:d.striker.balls,strike_rate:d.striker.strike_rate} : null,
            non_striker: d.non_striker ? {id:d.non_striker.id,name:d.non_striker.name,photo:d.non_striker.photo,runs:d.non_striker.runs,balls:d.non_striker.balls,strike_rate:d.non_striker.strike_rate} : null,
            bowler: d.bowler ? {id:d.bowler.id,name:d.bowler.name,photo:d.bowler.photo,overs_bowled:d.bowler.overs_bowled,maidens:d.bowler.maidens,runs_conceded:d.bowler.runs_conceded,wickets_taken:d.bowler.wickets_taken} : null,
            batting_team_id: d.batting_team?.id,
            bowling_team_id: d.bowling_team?.id,
        };
        B.updateScoreDisplay(st);
    },

    // ---- Show/Hide Live Indicator ----
    showLive(vis) {
        if (vis) {
            $('liveScoreCard').classList.remove('hidden');
            $('playerCards').classList.remove('hidden');
            $('btnGroup').classList.remove('hidden');
            $('scThisOver').classList.remove('hidden');
        } else {
            $('liveScoreCard').classList.add('hidden');
            $('playerCards').classList.add('hidden');
            $('btnGroup').classList.add('hidden');
            $('scThisOver').classList.add('hidden');
            $('overCompleteAlert').classList.add('hidden');
            $('bowlerChangePanel').classList.add('hidden');
            $('wicketPanel').classList.add('hidden');
            $('extraRunsPanel').classList.add('hidden');
            overLocked = false;
        }
    },

    // ---- Record Ball ----
    async record(runs) {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        B.lock(true);
        try {
            const r = await api('record_ball', {match_id: matchId, runs_scored: runs, extra_type: null, is_wicket: false});
            B.handleResult(r);
            if (runs >= 4) Buzzer.boundary();
            toast(`+${runs} run${runs!==1?'s':''}`, 'success');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    async extra(type) {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        B.lock(true);
        try {
            const r = await api('record_ball', {match_id: matchId, runs_scored: 0, extra_type: type, is_wicket: false});
            B.handleResult(r);
            toast(`${type==='wd'?'Wide':'No Ball'} +1`, 'success');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    promptExtra(type) {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        pendingExtra = type;
        $('extraRunsPanel').classList.remove('hidden');
        const grid = $('extraRunsGrid');
        if (type === 'nb') {
            $('extraRunsLabel').textContent = 'No Ball — runs off bat (+1 penalty auto):';
            // NB: runs 0-6 off the bat (plus automatic +1 penalty)
            grid.innerHTML = '';
            [0,1,2,3,4,6].forEach(r => {
                grid.insertAdjacentHTML('beforeend',
                    `<button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(${r})">${r}</button>`);
            });
        } else if (type === 'lb') {
            $('extraRunsLabel').textContent = 'Leg Bye runs:';
            grid.innerHTML = '';
            [1,2,3,4].forEach(r => {
                grid.insertAdjacentHTML('beforeend',
                    `<button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(${r})">${r}</button>`);
            });
        } else if (type === 'by') {
            $('extraRunsLabel').textContent = 'Bye runs:';
            grid.innerHTML = '';
            [1,2,3,4].forEach(r => {
                grid.insertAdjacentHTML('beforeend',
                    `<button class="score-btn bg-gray-800 hover:bg-gray-700 rounded-lg py-2 text-sm font-bold text-white min-h-[44px]" onclick="B.confirmExtra(${r})">${r}</button>`);
            });
        }
    },

    async confirmExtra(runs) {
        $('extraRunsPanel').classList.add('hidden');
        const type = pendingExtra; pendingExtra = null;
        if (!type) return;
        B.lock(true);
        try {
            const r = await api('record_ball', {match_id: matchId, runs_scored: runs, extra_type: type, is_wicket: false});
            B.handleResult(r);
            if (type === 'nb') {
                const total = 1 + runs;
                toast(`No Ball +${total} (1 penalty + ${runs} off bat)`, 'success');
            } else if (type === 'lb') {
                toast(`Leg Bye ${runs}`, 'success');
            } else {
                toast(`Bye ${runs}`, 'success');
            }
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    cancelExtra() {
        $('extraRunsPanel').classList.add('hidden');
        pendingExtra = null;
    },

    // ---- Wicket ----
    toggleWicket() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        const panel = $('wicketPanel');
        if (panel.classList.contains('hidden')) {
            const sel = $('wNewBatsman');
            sel.innerHTML = '<option value="">-- Select --</option>';

            // Fetch batting players with batted-status from API
            const batTeamId = state.batting_team_id;
            if (batTeamId) {
                sel.innerHTML = '<option value="">-- Loading... --</option>';
                api('get_players', {team_id: batTeamId, match_id: matchId}, 'GET')
                    .then(d => {
                        const players = d.players || [];
                        sel.innerHTML = '<option value="">-- Select --</option>';
                        const excludeIds = new Set();
                        if (state.striker?.id) excludeIds.add(state.striker.id);
                        if (state.non_striker?.id) excludeIds.add(state.non_striker.id);

                        // Filter to playing XI first
                        const xiFiltered = XI.filterXI(players, batTeamId);
                        let hasAvailable = false;
                        xiFiltered.forEach(p => {
                            if (excludeIds.has(p.id)) return;
                            if (p.has_batted) return; // Already batted in this innings
                            hasAvailable = true;
                            sel.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name} (${p.role})</option>`);
                        });

                        // If no replacement available (all-out scenario), show hint
                        if (!hasAvailable) {
                            sel.innerHTML = '<option value="">-- Team all out! --</option>';
                            sel.disabled = true;
                            toast('No more batsmen available. Team will be all out on this wicket.', 'warning');
                        }
                    })
                    .catch(() => {
                        sel.innerHTML = '<option value="">-- Error loading --</option>';
                    });
            } else {
                sel.innerHTML = '<option value="">-- Unknown batting team --</option>';
            }

            panel.classList.remove('hidden');
            $('btnWicket').classList.add('bg-red-800/70');
        } else {
            panel.classList.add('hidden');
            $('btnWicket').classList.remove('bg-red-800/70');
        }
    },

    async recordWicket(type) {
        const newB = parseInt($('wNewBatsman').value) || null;
        $('wicketPanel').classList.add('hidden');
        $('btnWicket').classList.remove('bg-red-800/70');
        B.lock(true);
        try {
            const r = await api('record_ball', {
                match_id: matchId, runs_scored: 0, extra_type: null,
                is_wicket: true, wicket_type: type, new_batsman_id: newB,
                is_free_hit: freeHitActive
            });
            B.handleResult(r);
            if (r.is_free_hit && r.is_wicket) {
                toast(`Free Hit: ${type.replace(/_/g,' ')} dismissal valid`, 'warning');
            }
            if (r.is_free_hit && !r.is_wicket) {
                toast(`Wicket NOT valid on Free Hit — only Run Out allowed`, 'error');
            }
            Buzzer.wicket();
            toast(`WICKET! (${type.replace('_',' ')})`, 'warning');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Retired Hurt (replace batsman without wicket) ----
    async retiredHurt() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        const newB = parseInt($('wNewBatsman').value) || null;
        if (!newB) { toast('Select a replacement batsman.', 'warning'); return; }
        $('wicketPanel').classList.add('hidden');
        $('btnWicket').classList.remove('bg-red-800/70');
        B.lock(true);
        try {
            await api('retire_batsman', {match_id: matchId, new_batsman_id: newB});
            await B.refresh();
            toast('Batsman retired hurt. Replacement in.', 'warning');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Penalty Runs (+5) ----
    async penaltyRuns() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        if (!confirm('Award 5 penalty runs to batting team?')) return;
        B.lock(true);
        try {
            const r = await api('record_ball', {
                match_id: matchId, runs_scored: 5, extra_type: 'penalty', is_wicket: false
            });
            B.handleResult(r);
            toast('+5 penalty runs awarded', 'success');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Manual Striker Swap ----
    async swapStriker() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        B.lock(true);
        try {
            await api('swap_striker', {match_id: matchId});
            await B.refresh();
            toast('Strike rotated', 'success');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Free Hit Toggle ----
    toggleFreeHit() {
        const indicator = $('freeHitIndicator');
        const btn = $('btnFreeHit');
        if (indicator.classList.contains('hidden')) {
            indicator.classList.remove('hidden');
            btn.classList.add('bg-green-900/50', 'border-green-600/40', 'text-green-300');
            btn.classList.remove('bg-gray-800', 'border-gray-700', 'text-gray-300');
            freeHitActive = true;
            toast('Free Hit activated — wicket restricted to run out', 'warning');
        } else {
            indicator.classList.add('hidden');
            btn.classList.remove('bg-green-900/50', 'border-green-600/40', 'text-green-300');
            btn.classList.add('bg-gray-800', 'border-gray-700', 'text-gray-300');
            freeHitActive = false;
            toast('Free Hit deactivated', 'warning');
        }
    },

    // ---- Mid-Over Bowler Change ----
    showBowlerChange() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        const sel = $('midBowlerSelect');
        sel.innerHTML = '<option value="">-- Choose --</option>';
        if (bowlingPlayers.length === 0 && state && state.bowling_team_id) {
            api('get_players', {team_id: state.bowling_team_id, match_id: matchId}, 'GET')
                .then(d => {
                    bowlingPlayers = d.players || [];
                    B.populateBowlerDropdown(sel);
                })
                .catch(() => { sel.innerHTML = '<option value="">-- Failed to load --</option>'; });
        } else {
            B.populateBowlerDropdown(sel);
        }
        $('bowlerChangePanel').classList.remove('hidden');
    },

    hideBowlerChange() {
        $('bowlerChangePanel').classList.add('hidden');
    },

    async confirmMidBowler() {
        const bid = parseInt($('midBowlerSelect').value);
        if (!bid) { toast('Select a bowler.', 'warning'); return; }
        B.lock(true);
        try {
            await api('set_bowler', {match_id: matchId, bowler_id: bid});
            toast('Bowler changed mid-over.', 'success');
            $('bowlerChangePanel').classList.add('hidden');
            B.refresh();
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Undo ----
    async undo() {
        if (!matchId || !state) { toast('Load/start match first.', 'warning'); return; }
        B.lock(true);
        try {
            await api('undo', {match_id: matchId});
            await B.refresh();
            toast('Last ball undone.', 'warning');
        } catch(e) { toast(e.message, 'error'); } finally { B.lock(false); }
    },

    // ---- Over Complete ----
    handleResult(r) {
        // Update UI instantly from API response (no extra network call)
        if (r.total_runs !== undefined) {
            $('scRuns').textContent = r.total_runs;
            $('scWkts').textContent = r.total_wickets;
            $('scOvers').textContent = r.overs || '0.0';
            $('scCrr').textContent = parseFloat(r.crr || 0).toFixed(2);
            $('scExtras').textContent = r.total_extras || 0;
        }
        if (r.striker) {
            $('pStrikerName').innerHTML = B.esc(r.striker.name) + ' <span class="text-red-400">*</span>';
            $('pStrikerStats').textContent = `${r.striker.runs}(${r.striker.balls}) SR:${r.striker.strike_rate}`;
            B.setPreviewPhoto('pStrikerPhoto', r.striker.photo, r.striker.name);
        }
        if (r.non_striker) {
            $('pNonStrikerName').textContent = r.non_striker.name;
            $('pNonStrikerStats').textContent = `${r.non_striker.runs}(${r.non_striker.balls}) SR:${r.non_striker.strike_rate}`;
            B.setPreviewPhoto('pNonStrikerPhoto', r.non_striker.photo, r.non_striker.name);
        }
        if (r.bowler) {
            $('pBowlerName').textContent = r.bowler.name;
            $('pBowlerStats').textContent = `${r.bowler.overs_bowled}-${r.bowler.maidens}-${r.bowler.runs_conceded}-${r.bowler.wickets_taken}`;
            B.setPreviewPhoto('pBowlerPhoto', r.bowler.photo, r.bowler.name);
        }

        if (r.over_complete) {
            B.showOverAlert();
            Buzzer.overComplete();
        }
        if (r.innings_over) {
            B.handleInningsOver(r);
            return;
        }
        // Lightweight refresh for ball tracker / timeline (player stats already updated above)
        B.fetchBalls();
    },

    handleInningsOver(r) {
        overLocked = false;
        B.unlockAll();
        if (r.innings_ended === 1) {
            toast('1st Innings ended! Target: ' + r.target + '. Start 2nd innings.', 'warning');
            Buzzer.overComplete();

            // Show setup for 2nd innings
            $('setupSection').classList.remove('hidden');
            $('btnToggleSetup').classList.remove('hidden');

            // Update setup header for 2nd innings
            const setupHdr = document.querySelector('#setupSection h3');
            if (setupHdr) setupHdr.textContent = '\u2699\uFE0F 2nd Innings Setup \u2014 Target: ' + r.target;

            // Auto-swap teams for 2nd innings
            if (r.new_batting_team_id && r.new_bowling_team_id) {
                $('sBatTeam').value = r.new_batting_team_id;
                $('sBowlTeam').value = r.new_bowling_team_id;
                loadSetupPlayers(); // enables btnStart after players load
            }

            // Hide toss section (already done for 1st innings)
            var tossBox = document.querySelector('#setupSection .bg-amber-900\\/20');
            if (tossBox) tossBox.classList.add('hidden');

            // Show target prominently
            $('targetBanner').classList.remove('hidden');
            $('targetBanner').innerHTML = '\uD83C\uDFAF Chasing <b>' + r.target + '</b> to win';

            $('liveScoreCard').classList.add('hidden');
            $('playerCards').classList.add('hidden');
            $('btnGroup').classList.add('hidden');
            stopSSE();
            setConn('idle');
            B.showLive(false);
        } else if (r.innings_ended === 2) {
            overLocked = false;
            B.unlockAll();
            stopSSE();
            B.showLive(false);
            setConn('idle');
            if (r.is_tie && r.needs_super_over) {
                toast('Match Tied! Super Over required.', 'warning');
                if (confirm('Start Super Over? (1 over, 2 wickets)')) {
                    B.startSuperOver();
                }
            } else {
                // Match completed — show result with team names
                const winner = r.winner_name || (r.result === 'win' ? (r.batting_team_name || 'Batting') : (r.bowling_team_name || 'Bowling'));
                const batName = r.batting_team_name || 'Batting';
                const bowlName = r.bowling_team_name || 'Bowling';
                const inn1Runs = r.first_innings_runs || (r.target ? r.target - 1 : 0);
                const inn2Runs = r.second_innings_runs || r.total_runs;
                const wktsRemaining = r.wickets_remaining !== undefined ? r.wickets_remaining : (10 - r.total_wickets);

                let resultMsg = '';
                if (r.is_tie) {
                    resultMsg = '\uD83C\uDFC6 Match Tied! ' + batName + ' ' + inn1Runs + ' vs ' + bowlName + ' ' + inn2Runs;
                } else if (r.result === 'win') {
                    resultMsg = '\uD83C\uDFC6 ' + winner + ' won by ' + wktsRemaining + ' wicket' + (wktsRemaining !== 1 ? 's' : '') + '!';
                } else {
                    const margin = inn1Runs - inn2Runs;
                    resultMsg = '\uD83C\uDFC6 ' + winner + ' won by ' + margin + ' run' + (margin !== 1 ? 's' : '') + '!';
                }

                toast(resultMsg, 'success');
                Buzzer.overComplete();

                // Show completed scorecard
                B.loadCompleted();
            }
        } else if (r.innings_ended === 99) {
            toast('Super Over ended!', 'warning');
            stopSSE();
        }
    },

    async startSuperOver() {
        if (!matchId) return;
        try {
            await api('start_super_over', {match_id: matchId});
            toast('Super Over started! Select batsmen and bowler.', 'success');
            $('setupSection').classList.remove('hidden');
            $('btnToggleSetup').classList.remove('hidden');
            $('liveScoreCard').classList.add('hidden');
            $('playerCards').classList.add('hidden');
            $('btnGroup').classList.add('hidden');
            B.showLive(false);
        } catch(e) { toast(e.message, 'error'); }
    },

    showOverAlert() {
        overLocked = true;
        B.lock(true);

        const sel = $('newBowlerSelect');
        sel.innerHTML = '<option value="">-- Choose --</option>';

        // Ensure bowling players are loaded — load eagerly if cache is empty
        if (bowlingPlayers.length === 0 && state && state.bowling_team_id) {
            sel.innerHTML = '<option value="">-- Loading bowlers... --</option>';
            api('get_players', {team_id: state.bowling_team_id, match_id: matchId}, 'GET')
                .then(d => {
                    bowlingPlayers = d.players || [];
                    sel.innerHTML = '<option value="">-- Choose --</option>';
                    B.populateBowlerDropdown(sel);
                })
                .catch(() => {
                    sel.innerHTML = '<option value="">-- Failed to load --</option>';
                });
        } else {
            B.populateBowlerDropdown(sel);
        }

        $('overCompleteAlert').classList.remove('hidden');
        $('btnConfirmBowler').disabled = true;
        sel.onchange = () => { $('btnConfirmBowler').disabled = !sel.value; };
    },

    populateBowlerDropdown(sel) {
        var added = 0;
        const filtered = XI.filterXI(bowlingPlayers, state ? state.bowling_team_id : 0);
        filtered.forEach(p => {
            if (state && p.id === state.bowler?.id) return;
            sel.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name} (${p.role})</option>`);
            added++;
        });
        if (added === 0) {
            sel.insertAdjacentHTML('beforeend', '<option value="" disabled>-- All bowlers used --</option>');
        }
    },

    async confirmBowler() {
        const bid = parseInt($('newBowlerSelect').value);
        if (!bid) return;
        try {
            await api('set_bowler', {match_id: matchId, bowler_id: bid});
            toast('New bowler assigned.', 'success');
        } catch(e) {
            toast(e.message, 'error');
        }
        // Always unlock — regardless of API success/failure
        B.unlockAll();
        B.refresh();
    },

    dismissOver() {
        B.unlockAll();
    },

    // Force-unlock: hide alert, reset lock, re-enable all buttons
    unlockAll() {
        $('overCompleteAlert').classList.add('hidden');
        overLocked = false;
        // Enable ALL .score-btn buttons unconditionally
        document.querySelectorAll('.score-btn').forEach(b => {
            b.disabled = b.dataset.wasDisabled === 'true';
            b.style.opacity = '';
            delete b.dataset.wasDisabled;
        });
        // Also enable the wicket button and other special buttons
        var btnW = $('btnWicket');
        if (btnW) { btnW.disabled = false; btnW.style.opacity = ''; delete btnW.dataset.wasDisabled; }
    },

    // ---- Refresh ----
    async refresh() {
        if (!matchId) return;
        try {
            const d = await api('get_overlay_data', {match_id: matchId}, 'GET');
            if (d && !d.error && !d.match_not_started) {
                if (d.last_updated_epoch) lastEpoch = d.last_updated_epoch;
                B.updateFromOverlay(d);
                if (d.last_5_balls) B.renderBalls(d.last_5_balls, d.sequence_id);
                if (d.this_over_balls) B.renderOverBalls(d.this_over_balls);
            }
        } catch(e) {
            console.error('Refresh failed:', e);
        }
    },

    async fetchBalls() { await B.refresh(); },

    // ---- Ball Log Render ----
    renderBalls(balls, seq) {
        $('logCount').textContent = `${seq || 0} balls`;
        const c = $('ballLog');
        if (!balls || balls.length === 0) {
            c.innerHTML = '<p class="text-[11px] text-gray-600 text-center py-4">No balls yet.</p>';
            return;
        }
        let h = '';
        balls.forEach(b => {
            let cls = 'bg-gray-800';
            if (b.wicket) cls = 'bg-red-900/50 border-red-700/30';
            else if (b.extra==='wd' || b.extra==='nb') cls = 'bg-amber-900/30 border-amber-700/30';
            else if (b.runs===4) cls = 'bg-emerald-900/30 border-emerald-700/30';
            else if (b.runs===6) cls = 'bg-purple-900/30 border-purple-700/30';
            h += `<span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold ${cls} border border-gray-700 text-white">${b.display}</span>`;
        });
        c.innerHTML = `<div class="flex flex-wrap gap-1.5 p-1 justify-center">${h}</div>`;
    },

    // ---- Over Ball Tracker Dots ----
    renderOverBalls(overBalls) {
        const balls = overBalls || [];
        let h = '';
        for (let i = 0; i < 6; i++) {
            if (i < balls.length) {
                const b = balls[i];
                let cls = 'track-dot';
                if (b.wicket) cls += ' wkt';
                else if (b.runs === 4 && !b.extra) cls += ' four';
                else if (b.runs === 6 && !b.extra) cls += ' six';
                else if (b.extra && (b.extra==='wd'||b.extra==='nb')) cls += ' xtra';
                h += `<div class="${cls}">${b.display}</div>`;
            } else {
                h += '<div class="track-dot empty">·</div>';
            }
        }
        $('ovBallDots').innerHTML = h;

        // This-over runs
        const overRuns = balls.reduce((s,b) => s + (b.runs||0), 0);
        $('ovOverRunsLbl').textContent = `Ov: ${overRuns}`;
        $('scOverRuns').textContent = overRuns;
    },

    // ---- Button Lock ----
    lock(disabled) {
        document.querySelectorAll('.score-btn').forEach(b => {
            if (disabled) {
                // Only save original state the first time (prevents double-lock overwrite)
                if (b.dataset.wasDisabled === undefined) {
                    b.dataset.wasDisabled = b.disabled;
                }
                b.disabled = true;
                b.style.opacity = '0.4';
            } else if (!overLocked) {
                b.disabled = (b.dataset.wasDisabled === 'true');
                b.style.opacity = '';
                delete b.dataset.wasDisabled;
            }
        });
    },

    // ---- Completed Match Scorecard ----
    async loadCompleted() {
        if (!matchId) return;
        stopSSE();
        B.showLive(false);
        try {
            const d = await api('get_match_scorecard', {match_id: matchId}, 'GET');
            if (d && d.success) B.renderCompleted(d);
        } catch(e) { toast('Failed to load scorecard.', 'error'); }
    },

    renderCompleted(d) {
        $('liveScoreCard').classList.add('hidden');
        $('playerCards').classList.add('hidden');
        $('btnGroup').classList.add('hidden');
        $('targetBanner').classList.add('hidden');
        $('completedCard').classList.remove('hidden');

        let html = '';

        // Match result header
        if (d.match && d.match.result_msg) {
            html += `<div class="sc-result-banner" style="background:linear-gradient(135deg,rgba(249,115,22,0.15),rgba(234,88,12,0.05));border:1px solid rgba(249,115,22,0.22)">
                <div class="sc-res-lbl" style="color:#f97316">Match Result</div>
                <div class="sc-res-txt" style="color:#fff">${d.match.result_msg}</div>
                ${d.match.winner_name ? `<div style="font-size:11px;color:#f97316;margin-top:3px;font-weight:600">${d.match.winner_name}</div>` : ''}
            </div>`;
        } else if (d.match && d.match.status === 'live') {
            html += `<div class="sc-result-banner" style="background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(99,102,241,0.04));border:1px solid rgba(59,130,246,0.18)">
                <div class="sc-res-lbl" style="color:#60a5fa">Match In Progress</div>
                <div class="sc-res-txt" style="color:#fff">Live</div>
            </div>`;
        }

        const innings = d.innings || [];
        if (innings.length === 0 && d.batting_team && d.batting_team.runs !== undefined) {
            innings.push({
                number: 1, batting_team: d.batting_team, bowling_team: d.bowling_team,
                batting_card: d.batting_card || [], bowling_card: d.bowling_card || [],
            });
        }

        innings.forEach((inn, iidx) => {
            const bt = inn.batting_team || {};
            const bot = inn.bowling_team || {};
            const innLabel = innings.length > 1 ? (inn.number === 1 ? '1st Innings' : '2nd Innings') : 'Innings';
            const teamColor = iidx === 0 ? '#f97316' : '#60a5fa';

            // Innings summary card
            html += `<div class="sc-card mb-3">
                <div class="sc-card-head">
                    <div class="sc-logo">${bt.logo ? `<img src="${B.esc(bt.logo)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span class="sc-logo-init" style="display:none">${(bt.short_name||bt.name||'T').substring(0,2).toUpperCase()}</span>` : `<span class="sc-logo-init">${(bt.short_name||bt.name||'T').substring(0,2).toUpperCase()}</span>`}</div>
                    <div>
                        <div class="sc-inn-name" style="color:${teamColor}">${bt.short_name || bt.name || 'Batting'} — ${innLabel}</div>
                        <div class="sc-inn-meta">${bt.runs}/${bt.wickets} · ${bt.overs || '0.0'} ov · Extras: ${bt.extras || 0}</div>
                    </div>
                    <div class="sc-inning-summary" style="margin-left:auto">
                        <div class="sc-is-card"><div class="sc-is-score" style="color:${teamColor}">${bt.runs}<small>/${bt.wickets}</small></div><div class="sc-is-meta">${bt.overs||'0.0'} overs</div></div>
                    </div>
                </div>`;

            // Batting card
            html += `<div style="padding:4px 16px 2px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.25)">Batting</span></div>`;
            if (inn.batting_card && inn.batting_card.length) {
                inn.batting_card.forEach(p => {
                    const rc = (p.role||'').toLowerCase();
                    let roleLabel='BAT',roleClass='bat';
                    if(rc.includes('bowl')&&rc.includes('bat')){roleLabel='AR';roleClass='all'}
                    else if(rc.includes('wk')){roleLabel='WK';roleClass='wk'}
                    else if(rc.includes('bowl')){roleLabel='BWL';roleClass='bwl'}
                    const sr = p.sr || 0, srClass = sr<80?'sc-sr-lo':(sr>120?'sc-sr-hi':'sc-sr-md');
                    const dismissed = !!p.dismissal_type;
                    const st = p.dismissal_type ? p.dismissal_type.replace(/_/g,' ') : 'not out';
                    const stClass = dismissed ? 'sc-st out' : 'sc-st no';
                    let styleHTML = '';
                    if(p.batting_style) styleHTML += B.esc(p.batting_style);
                    if(p.bowling_style) styleHTML += (styleHTML?' · ':'') + B.esc(p.bowling_style);
                    html += `<div class="sc-row-new ${dismissed?'dim':''}">
                        <div class="sc-num">${p.batting_position||'•'}</div>
                        <div class="sc-photo">${p.photo_path ? `<img src="${B.esc(p.photo_path)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span class="sc-photo-init" style="display:none">${(p.name||'?').charAt(0).toUpperCase()}</span>` : `<span class="sc-photo-init">${(p.name||'?').charAt(0).toUpperCase()}</span>`}</div>
                        <div class="sc-player"><div class="sc-pname">${B.esc(p.name)} <span class="sc-tag ${roleClass}">${roleLabel}</span></div>${styleHTML?`<div class="sc-pstyle">${styleHTML}</div>`:''}</div>
                        <div class="sc-stat hl">${p.runs_scored||0}</div>
                        <div class="sc-stat dim-stat">${p.balls_faced||0}</div>
                        <div class="sc-stat dim-stat">${p.fours||0}</div>
                        <div class="sc-stat dim-stat">${p.sixes||0}</div>
                        <div class="sc-stat ${srClass}">${sr}</div>
                        <div class="${stClass}">${st}</div>
                    </div>`;
                });
                if (bt.extras > 0) html += `<div class="sc-row-new" style="opacity:0.5"><div class="sc-num"></div><div class="sc-photo"></div><div class="sc-player"><div class="sc-pname" style="font-style:italic;font-size:11px">Extras</div></div><div class="sc-stat hl">${bt.extras}</div><div class="sc-stat"></div><div class="sc-stat"></div><div class="sc-stat"></div><div class="sc-stat"></div><div></div></div>`;
                html += `<div class="sc-row-new" style="border-top:1px solid rgba(255,255,255,0.06)"><div class="sc-num"></div><div class="sc-photo"></div><div class="sc-player"><div class="sc-pname" style="font-weight:700">Total</div></div><div class="sc-stat hl">${bt.runs}/${bt.wickets}</div><div class="sc-stat dim-stat">${bt.overs||'0.0'} ov</div><div class="sc-stat"></div><div class="sc-stat"></div><div class="sc-stat"></div><div></div></div>`;
            } else {
                html += '<div style="padding:16px;text-align:center;color:rgba(255,255,255,0.2);font-size:11px">No batting data</div>';
            }

            // Bowling card
            html += `<div style="padding:8px 16px 2px;border-top:1px solid rgba(255,255,255,0.04);margin-top:4px"><span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.25)">Bowling</span></div>`;
            if (inn.bowling_card && inn.bowling_card.length) {
                inn.bowling_card.forEach(p => {
                    const rc = (p.role||'').toLowerCase();
                    let roleLabel='BWL',roleClass='bwl';
                    if(rc.includes('bowl')&&rc.includes('bat')){roleLabel='AR';roleClass='all'}
                    else if(rc.includes('wk')){roleLabel='WK';roleClass='wk'}
                    else if(rc.includes('bat')){roleLabel='BAT';roleClass='bat'}
                    const econ = p.econ || 0, ecClass = econ<5?'sc-ec-lo':(econ>8?'sc-ec-hi':'sc-ec-md');
                    let styleHTML = '';
                    if(p.bowling_style) styleHTML += B.esc(p.bowling_style);
                    if(p.batting_style) styleHTML += (styleHTML?' · ':'') + B.esc(p.batting_style);
                    html += `<div class="sc-row-new">
                        <div class="sc-num"></div>
                        <div class="sc-photo">${p.photo_path ? `<img src="${B.esc(p.photo_path)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><span class="sc-photo-init" style="display:none">${(p.name||'?').charAt(0).toUpperCase()}</span>` : `<span class="sc-photo-init">${(p.name||'?').charAt(0).toUpperCase()}</span>`}</div>
                        <div class="sc-player"><div class="sc-pname">${B.esc(p.name)} <span class="sc-tag ${roleClass}">${roleLabel}</span></div>${styleHTML?`<div class="sc-pstyle">${styleHTML}</div>`:''}</div>
                        <div class="sc-stat">${typeof p.overs_bowled==='number'?p.overs_bowled.toFixed(1):(p.overs_bowled||'0.0')}</div>
                        <div class="sc-stat dim-stat">${p.maidens||0}</div>
                        <div class="sc-stat">${p.runs_conceded||0}</div>
                        <div class="sc-stat hl" style="color:#f97316">${p.wickets_taken||0}</div>
                        <div class="sc-stat ${ecClass}">${econ}</div>
                    </div>`;
                });
            } else {
                html += '<div style="padding:16px;text-align:center;color:rgba(255,255,255,0.2);font-size:11px">No bowling data</div>';
            }

            html += '</div>';
        });

        // Toss info if available
        if (d.match && (d.match.toss_won_by || d.match.toss_decision)) {
            const taName = d.match.team_a_short || d.match.team_a_name || 'Team A';
            const tbName = d.match.team_b_short || d.match.team_b_name || 'Team B';
            const wonName = d.match.toss_won_by === d.match.team_a_id ? taName : (d.match.toss_won_by === d.match.team_b_id ? tbName : '');
            const dec = d.match.toss_decision === 'bat' ? 'Elected to BAT' : (d.match.toss_decision === 'bowl' ? 'Elected to BOWL' : '');
            if (wonName) {
                html += `<div class="sc-card" style="margin-top:3px;padding:12px 16px;text-align:center;background:linear-gradient(135deg,rgba(255,152,0,0.06),rgba(255,152,0,0.01));border:1px solid rgba(255,152,0,0.15)">
                    <span style="font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:0.10em;color:#f97316">&#127936; Toss</span>
                    <span style="font-size:12px;font-weight:700;color:#fff;margin-left:8px">${wonName.toUpperCase()} WON THE TOSS</span>
                    <span style="font-size:10px;font-weight:600;color:#f97316;margin-left:4px">&mdash; ${dec}</span>
                </div>`;
            }
        }

        html += '<button onclick="B.loadCompleted()" class="w-full bg-gray-800/60 hover:bg-gray-700/60 text-gray-400 py-2.5 rounded-xl text-xs transition mt-3 border border-gray-700/40">Refresh Scorecard</button>';

        $('completedCard').innerHTML = html;
        B.lock(true);
        $('btnStart').disabled = true;
    }
};

// ============================================================================
// INIT
// ============================================================================
// Auto-load if match_id in URL
const qp = new URLSearchParams(window.location.search);
if (qp.get('match_id')) {
    $('matchSelect').value = qp.get('match_id');
    setTimeout(() => $('btnLoad').click(), 200);
}

window.addEventListener('beforeunload', stopSSE);

// ============================================================================
// KEYBOARD SHORTCUTS — quick scoring without touching the mouse
// ============================================================================
document.addEventListener('keydown', e => {
    // Ignore when typing in inputs, selects, or textareas
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'select' || tag === 'textarea') return;
    // Ignore if over complete alert is showing (user must resolve first)
    if (overLocked) return;
    // Ignore if no match is loaded or not live
    if (!matchId || !state) return;
    // Ignore modifier keys
    if (e.ctrlKey || e.altKey || e.metaKey) return;

    const key = e.key.toLowerCase();

    // Runs Off Bat: 0-6
    if (key === '0') { e.preventDefault(); B.record(0); return; }
    if (key === '1') { e.preventDefault(); B.record(1); return; }
    if (key === '2') { e.preventDefault(); B.record(2); return; }
    if (key === '3') { e.preventDefault(); B.record(3); return; }
    if (key === '4') { e.preventDefault(); B.record(4); return; }
    if (key === '5') { e.preventDefault(); B.record(5); return; }
    if (key === '6') { e.preventDefault(); B.record(6); return; }

    // Extras
    if (key === 'w') { e.preventDefault(); B.extra('wd'); return; }
    if (key === 'n') { e.preventDefault(); B.promptExtra('nb'); return; }
    if (key === 'l') { e.preventDefault(); B.promptExtra('lb'); return; }
    if (key === 'y') { e.preventDefault(); B.promptExtra('by'); return; } // 'y' for bYe

    // Undo
    if (key === 'z' && !e.shiftKey) { e.preventDefault(); B.undo(); return; }

    // Wicket toggle
    if (key === 'x') { e.preventDefault(); B.toggleWicket(); return; }

    // Close panels / dismiss over alert
    if (key === 'escape') {
        e.preventDefault();
        if (!$('overCompleteAlert').classList.contains('hidden')) B.dismissOver();
        else if (!$('wicketPanel').classList.contains('hidden')) B.toggleWicket();
        else if (!$('bowlerChangePanel').classList.contains('hidden')) B.hideBowlerChange();
        else if (!$('extraRunsPanel').classList.contains('hidden')) B.cancelExtra();
        return;
    }

    // Swap strike
    if (key === 's') { e.preventDefault(); B.swapStriker(); return; }
});

// ============================================================================
// THEME TOGGLE — dark / light, persisted in localStorage
// ============================================================================
(function(){
    var saved = localStorage.getItem('cricket-theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    var btn = document.getElementById('btnTheme');
    if (btn) btn.textContent = saved === 'dark' ? '\uD83C\uDF19' : '\u2600\uFE0F';
})();

function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme');
    var next = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('cricket-theme', next);
    var btn = document.getElementById('btnTheme');
    if (btn) btn.textContent = next === 'dark' ? '\uD83C\uDF19' : '\u2600\uFE0F';
}
function confirmLogout(){var m=document.getElementById('logoutModal');if(m)m.style.display='flex';return false}
</script>
<?php include __DIR__.'/footer.php'; ?>
<!-- Logout Modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:16px" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:18px;width:100%;max-width:380px;text-align:center;box-shadow:0 25px 60px rgba(0,0,0,0.50);animation:modalPop 0.3s cubic-bezier(0.16,1,0.3,1) forwards;overflow:hidden">
        <div style="padding:32px 24px 20px">
            <div style="font-size:48px;margin-bottom:12px">&#128682;</div>
            <h3 style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px">Confirm Logout</h3>
            <p style="font-size:12px;color:var(--text-dim);margin-bottom:20px">Are you sure you want to sign out?</p>
            <div style="display:flex;gap:8px;justify-content:center">
                <button onclick="document.getElementById('logoutModal').style.display='none'" style="padding:9px 18px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--bg-hover);color:var(--text);font-family:inherit">Cancel</button>
                <a href="api.php?action=logout" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;background:rgba(239,68,68,0.12);color:var(--red);border:1px solid rgba(239,68,68,0.2);font-family:inherit">&#128682; Logout</a>
            </div>
        </div>
    </div>
</div>
<script>document.getElementById('logoutModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});</script>
</body>
</html>
