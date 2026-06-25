<?php
/**
 * ============================================================================
 * api.php — Unified REST API for Live Cricket Scoring Engine
 * ============================================================================
 * Handles authentication, ball-by-ball scoring, strike rotation, undo stack,
 * live state queries, and overlay data serving. All endpoints are role-gated.
 *
 * Supported Actions (via ?action= or POST action field):
 *   GET  me                  — Current session user info
 *   POST login               — Authenticate user
 *   POST logout              — Destroy session
 *   POST create_match        — [super_admin] Create a new match
 *   POST start_match         — [scorer] Initialise live_state for a match
 *   POST record_ball         — [scorer] Record a single delivery
 *   POST undo                — [scorer] Rollback last delivery
 *   POST set_bowler          — [scorer] Assign/replace bowler mid-over
 *   GET  get_live_state      — [scorer]  Current match live state (polling)
 *   GET  get_overlay_data    — [public]  Full overlay JSON (no auth needed)
 *   GET  sse_stream          — [public]  Server-Sent Events real-time stream
 *   GET  get_teams           — [scorer]  List teams with players
 *   POST set_playing_xi       — [scorer]  Select playing XI for a team in a match
 *   GET  get_playing_xi       — [scorer]  Get current playing XI selections
 * ============================================================================
 */

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Route dispatching
// ---------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? null;
$input  = []; // Parsed JSON body for POST/PUT

// Parse JSON body for POST requests
if ($method === 'POST') {
    $rawBody = file_get_contents('php://input');
    if ($rawBody) {
        $input = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'Invalid JSON body.'], 400);
        }
    }
    // Allow form-encoded fallback
    if (empty($input)) {
        $input = $_POST;
    }
}

// Merge query params into input (GET requests)
if ($method === 'GET') {
    $input = $_GET;
}

// ---------------------------------------------------------------------------
// Action Router
// ---------------------------------------------------------------------------
switch ($action) {

    // ========================================================================
    // AUTHENTICATION
    // ========================================================================
    case 'login':
        handleLogin($input);
        break;

    case 'logout':
        handleLogout();
        break;

    case 'me':
        handleMe();
        break;

    // ========================================================================
    // MATCH MANAGEMENT (super_admin)
    // ========================================================================
    case 'create_match':
        requireAuth('super_admin');
        handleCreateMatch($input);
        break;

    // ========================================================================
    // SCORING ENGINE (scorer / super_admin)
    // ========================================================================
    case 'start_match':
        requireAuth('scorer');
        handleStartMatch($input);
        break;

    case 'record_ball':
        requireAuth('scorer');
        handleRecordBall($input);
        break;

    case 'undo':
        requireAuth('scorer');
        handleUndo($input);
        break;

    case 'set_bowler':
        requireAuth('scorer');
        handleSetBowler($input);
        break;

    case 'retire_batsman':
        requireAuth('scorer');
        handleRetireBatsman($input);
        break;

    case 'swap_striker':
        requireAuth('scorer');
        handleSwapStriker($input);
        break;

    case 'set_toss':
        requireAuth('scorer');
        handleSetToss($input);
        break;

    case 'start_super_over':
        requireAuth('scorer');
        handleStartSuperOver($input);
        break;

    // ========================================================================
    // DATA QUERIES
    // ========================================================================
    case 'get_live_state':
        requireAuth('scorer');
        handleGetLiveState($input);
        break;

    case 'get_overlay_data':
        // Public endpoint — no auth required (read-only)
        handleGetOverlayData($input);
        break;

    case 'sse_stream':
        // Public SSE endpoint — no auth required (read-only stream)
        handleSSEStream($input);
        break;

    case 'get_teams':
        requireAuth('scorer');
        handleGetTeams();
        break;

    case 'get_players':
        requireAuth('scorer');
        handleGetPlayers($input);
        break;

    case 'get_matches':
        requireAuth('scorer');
        handleGetMatches();
        break;

    case 'get_match_scorecard':
        requireAuth('scorer');
        handleGetMatchScorecard($input);
        break;

    case 'set_output_view':
        requireAuth('scorer');
        handleSetOutputView($input);
        break;

    case 'get_output_view':
        requireAuth('scorer');
        handleGetOutputView($input);
        break;

    case 'set_playing_xi':
        requireAuth('scorer');
        handleSetPlayingXI($input);
        break;

    case 'get_playing_xi':
        requireAuth('scorer');
        handleGetPlayingXI($input);
        break;

    default:
        jsonResponse(['error' => 'Unknown action. Valid: login, logout, me, create_match, start_match, record_ball, undo, set_bowler, retire_batsman, swap_striker, get_live_state, get_overlay_data, sse_stream, get_teams, get_players, get_matches, get_match_scorecard'], 400);
}

// ============================================================================
// AUTH HANDLERS
// ============================================================================

function handleLogin(array $input): void
{
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'Username and password are required.'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, role, display_name, company_id FROM users WHERE username = ? AND is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Constant-time-ish delay to mitigate timing attacks
        usleep(random_int(50000, 150000));
        jsonResponse(['error' => 'Invalid credentials.'], 401);
    }

    // Regenerate session ID on login (prevents session fixation)
    session_regenerate_id(true);

    $_SESSION['user_id']         = (int) $user['id'];
    $_SESSION['username']        = $user['username'];
    $_SESSION['role']            = $user['role'];
    $_SESSION['display_name']    = $user['display_name'] ?? $user['username'];
    $_SESSION['company_id']      = $user['company_id'] ?? null;
    $_SESSION['_regenerated_at'] = time();

    jsonResponse([
        'success'      => true,
        'user'         => [
            'id'           => (int) $user['id'],
            'username'     => $user['username'],
            'role'         => $user['role'],
            'display_name' => $user['display_name'],
        ],
    ]);
}

function handleLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();

    // Browser navigation (GET) → redirect; AJAX/fetch (POST) → JSON
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: index.php?msg=logged_out');
        exit;
    }
    jsonResponse(['success' => true, 'message' => 'Logged out.']);
}

function handleMe(): void
{
    $user = requireAuth(); // Any role
    jsonResponse([
        'user' => [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'role'         => $user['role'],
            'display_name' => $_SESSION['display_name'] ?? $user['username'],
        ],
    ]);
}

// ============================================================================
// MATCH MANAGEMENT
// ============================================================================

function handleCreateMatch(array $input): void
{
    $teamA   = (int) ($input['team_a_id'] ?? 0);
    $teamB   = (int) ($input['team_b_id'] ?? 0);
    $title   = trim($input['match_title'] ?? '');
    $location = trim($input['location'] ?? '');
    $tossWon = $input['toss_won_by'] ?? null;
    $batFirst = $input['batting_first'] ?? null;
    $format  = $input['match_format'] ?? 't20i';
    if (!in_array($format, ['t20i','odi','test'])) $format = 't20i';
    $maxOvers = ['t20i' => 20, 'odi' => 50, 'test' => 450][$format];

    if ($teamA <= 0 || $teamB <= 0) {
        jsonResponse(['error' => 'Both team_a_id and team_b_id are required.'], 400);
    }
    if ($teamA === $teamB) {
        jsonResponse(['error' => 'A team cannot play against itself.'], 400);
    }

    // Validate teams exist
    $db   = getDB();
    $stmt = $db->prepare('SELECT COUNT(*) FROM teams WHERE id IN (?, ?)');
    $stmt->execute([$teamA, $teamB]);
    if ((int) $stmt->fetchColumn() !== 2) {
        jsonResponse(['error' => 'One or both teams not found.'], 404);
    }

    if (empty($title)) {
        // Auto-generate title
        $stmt = $db->prepare('SELECT short_name FROM teams WHERE id IN (?, ?)');
        $stmt->execute([$teamA, $teamB]);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $title = ($names[0] ?? 'Team A') . ' vs ' . ($names[1] ?? 'Team B');
    }

    $stmt = $db->prepare(
        'INSERT INTO matches (team_a_id, team_b_id, match_title, location, match_format, total_overs, toss_won_by, batting_first)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$teamA, $teamB, $title, $location ?: null, $format, $maxOvers, $tossWon, $batFirst]);

    jsonResponse([
        'success'  => true,
        'match_id' => (int) $db->lastInsertId(defined('DB_ENGINE') && DB_ENGINE === 'pgsql' ? 'matches_id_seq' : null),
        'message'  => 'Match created.',
        'format'   => $format,
        'max_overs'=> $maxOvers,
    ], 201);
}

// ============================================================================
// SCORING ENGINE
// ============================================================================

function handleStartMatch(array $input): void
{
    $matchId         = (int) ($input['match_id'] ?? 0);
    $battingTeamId   = (int) ($input['batting_team_id'] ?? 0);
    $bowlingTeamId   = (int) ($input['bowling_team_id'] ?? 0);
    $strikerId       = (int) ($input['striker_id'] ?? 0);
    $nonStrikerId    = (int) ($input['non_striker_id'] ?? 0);
    $bowlerId        = (int) ($input['bowler_id'] ?? 0);
    $inningsNumber   = (int) ($input['innings_number'] ?? 1);

    if (!$matchId || !$battingTeamId || !$bowlingTeamId || !$strikerId || !$nonStrikerId || !$bowlerId) {
        jsonResponse(['error' => 'match_id, batting_team_id, bowling_team_id, striker_id, non_striker_id, and bowler_id are required.'], 400);
    }

    $db = getDB();

    // Validate match exists
    $stmt = $db->prepare('SELECT id, status, team_a_id, team_b_id, match_format, total_overs FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        jsonResponse(['error' => 'Match not found.'], 404);
    }
    if ($match['status'] !== 'upcoming' && $match['status'] !== 'live') {
        jsonResponse(['error' => 'Match has already been completed.'], 409);
    }
    // If match is already 'live', allow re-start only for a new innings (e.g. 2nd innings after all-out)
    $isRestart = ($match['status'] === 'live');

    $maxOvers = (int) ($match['total_overs'] ?? 20);

    // Validate teams belong to match
    $matchTeamIds = [(int) $match['team_a_id'], (int) $match['team_b_id']];
    if (!in_array($battingTeamId, $matchTeamIds) || !in_array($bowlingTeamId, $matchTeamIds)) {
        jsonResponse(['error' => 'Teams must be part of this match.'], 400);
    }

    // Validate players belong to correct teams
    if (!playerInTeam($db, $strikerId, $battingTeamId) ||
        !playerInTeam($db, $nonStrikerId, $battingTeamId)) {
        jsonResponse(['error' => 'Batsmen must belong to the batting team.'], 400);
    }
    if (!playerInTeam($db, $bowlerId, $bowlingTeamId)) {
        jsonResponse(['error' => 'Bowler must belong to the bowling team.'], 400);
    }

    // Striker and non-striker must be different players
    if ($strikerId === $nonStrikerId) {
        jsonResponse(['error' => 'Striker and non-striker must be different players.'], 400);
    }

    $nowEpoch = (int)(microtime(true) * 1000); // milliseconds

    $db->beginTransaction();
    try {
        // Check if live_state already exists (e.g. 2nd innings auto-created by all-out)
        $stmtCheck = $db->prepare('SELECT match_id, innings_number FROM live_state WHERE match_id = ?');
        $stmtCheck->execute([$matchId]);
        $existingState = $stmtCheck->fetch();

        // Update match status (only set batting_first on initial start, not 2nd innings)
        if ($existingState) {
            // 2nd innings — keep existing batting_first, just ensure status is live
            $stmt = $db->prepare("UPDATE matches SET status = 'live' WHERE id = ?");
            $stmt->execute([$matchId]);
            // If input didn't specify innings_number, use the one already set
            if (empty($input['innings_number'])) {
                $inningsNumber = (int) $existingState['innings_number'];
            }
        } else {
            $stmt = $db->prepare("UPDATE matches SET status = 'live', batting_first = ? WHERE id = ?");
            $stmt->execute([$battingTeamId, $matchId]);
        }

        if ($existingState) {
            // Update existing row (2nd innings after all-out transition or restart)
            $stmt = $db->prepare(
                'UPDATE live_state SET
                 batting_team_id = ?, bowling_team_id = ?, innings_number = ?, max_overs = ?,
                 striker_id = ?, non_striker_id = ?, bowler_id = ?, last_updated_epoch = ?
                 WHERE match_id = ?'
            );
            $stmt->execute([$battingTeamId, $bowlingTeamId, $inningsNumber, $maxOvers,
                            $strikerId, $nonStrikerId, $bowlerId, $nowEpoch, $matchId]);
        } else {
            // Create live_state row
            $stmt = $db->prepare(
                'INSERT INTO live_state
                 (match_id, batting_team_id, bowling_team_id, innings_number, max_overs,
                  striker_id, non_striker_id, bowler_id, last_updated_epoch)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$matchId, $battingTeamId, $bowlingTeamId, $inningsNumber, $maxOvers,
                            $strikerId, $nonStrikerId, $bowlerId, $nowEpoch]);
        }

        // Create batsman innings entries
        $stmt = $db->prepare(
            'INSERT INTO batsman_innings (match_id, innings_number, batsman_id, batting_position)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$matchId, $inningsNumber, $strikerId, 1]);
        $stmt->execute([$matchId, $inningsNumber, $nonStrikerId, 2]);

        // Create bowler spell entry
        $stmt = $db->prepare(
            'INSERT INTO bowler_spells (match_id, innings_number, bowler_id)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$matchId, $inningsNumber, $bowlerId]);

        $db->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Match started. Live scoring active.',
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] start_match failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to start match.'], 500);
    }
}

/**
 * Record a single ball/delivery — the heart of the scoring engine.
 *
 * Expected input:
 *   match_id        int    Required
 *   runs_scored     int    Runs off the bat (0-6). For extras, see below.
 *   extra_type      string|null  'wd','nb','lb','by' or null
 *   is_wicket       bool   Did a wicket fall?
 *   wicket_type     string|null  'bowled','caught','lbw','run_out','stumped','hit_wicket'
 *   new_batsman_id  int|null     Required if is_wicket=true
 *   new_bowler_id   int|null     Required if over is completed
 */
function handleRecordBall(array $input): void
{
    $matchId       = (int) ($input['match_id'] ?? 0);
    $runsScored    = (int) ($input['runs_scored'] ?? 0);
    $extraType     = $input['extra_type'] ?? null;
    $isWicket      = !empty($input['is_wicket']);
    $wicketType    = $input['wicket_type'] ?? null;
    $newBatsmanId  = $input['new_batsman_id'] ?? null;
    $newBowlerId   = $input['new_bowler_id'] ?? null;

        // Normalise extras (accept 'penalty' as internal type too)
        $validExtras = ['wd', 'nb', 'lb', 'by', 'penalty'];
        if ($extraType !== null && !in_array($extraType, $validExtras, true)) {
            $extraType = null;
        }

    // For wd/nb, ensure at least 1 run
    if (($extraType === 'wd' || $extraType === 'nb') && $runsScored < 0) {
        $runsScored = 0;
    }

    if ($matchId <= 0) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();

    // Fetch current live state (with row lock for atomicity)
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $state = $stmt->fetch();

        if (!$state) {
            $db->rollBack();
            jsonResponse(['error' => 'Match is not live. Start the match first.'], 409);
        }

        // Fetch match format info
        $stmtFmt = $db->prepare('SELECT match_format, total_overs FROM matches WHERE id = ?');
        $stmtFmt->execute([$matchId]);
        $matchInfo = $stmtFmt->fetch();
        $matchFormat = is_array($matchInfo) ? ($matchInfo['match_format'] ?? 't20i') : 't20i';
        $matchStatus = is_array($matchInfo) ? ($matchInfo['status'] ?? '') : '';
        if ($matchStatus === 'completed') {
            $db->rollBack();
            jsonResponse(['error' => 'Match is already completed. Scoring is locked.'], 409);
        }

        // Determine ball legality
        $isLegalBall = ($extraType !== 'wd' && $extraType !== 'nb' && $extraType !== 'penalty');

        // ---- Calculate runs attribution ----
        // extra_runs: runs that count as extras (not credited to batsman)
        // batsman_runs: runs credited to the batsman
        // bowler_runs: runs conceded by the bowler
        // total_runs_added: added to team total

        $extraRuns    = 0;
        $batsmanRuns  = 0;
        $bowlerRuns   = 0;
        $totalRunsAdded = 0;

        switch ($extraType) {
            case 'wd':
                // Wide: 1 run always, no additional off-bat runs
                $extraRuns      = 1;
                $bowlerRuns     = 1;
                $totalRunsAdded = 1;
                break;

            case 'nb':
                // No-ball: 1 run penalty + any runs off the bat
                $extraRuns      = 1;
                $batsmanRuns    = $runsScored; // runs off the bat (e.g., 4 for a no-ball boundary)
                $bowlerRuns     = 1 + $runsScored;
                $totalRunsAdded = 1 + $runsScored;
                break;

            case 'lb':
                // Leg-bye: runs NOT credited to batsman, BUT count against bowler (ICC rule)
                $extraRuns      = $runsScored;
                $bowlerRuns     = $runsScored;
                $totalRunsAdded = $runsScored;
                break;

            case 'by':
                // Bye: runs NOT credited to batsman or bowler
                $extraRuns      = $runsScored;
                $totalRunsAdded = $runsScored;
                break;

            case 'penalty':
                // Penalty runs: added to team total only, NOT batsman/bowler
                $totalRunsAdded = $runsScored;
                break;

            default:
                // Normal delivery
                $batsmanRuns    = $runsScored;
                $bowlerRuns     = $runsScored;
                $totalRunsAdded = $runsScored;
                break;
        }

        // ---- No-ball wicket restrictions (ICC Law 21.18) ----
        if ($isWicket && $extraType === 'nb') {
            $notAllowedOnNB = ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket', 'timed_out', 'obstructing'];
            if (in_array($wicketType, $notAllowedOnNB, true)) {
                // Dismissals from no-ball are invalid — treat as a legal ball with runs but no wicket
                $isWicket = false;
                $wicketBatsmanId = null;
                // Re-evaluate newBatsmanId as not needed
                $newBatsmanId = null;
            }
        }

        // ---- Free Hit wicket restrictions (only run_out, obstructing, hitting ball twice) ----
        $isFreeHit = !empty($input['is_free_hit']);
        if ($isWicket && $isFreeHit && $extraType !== 'nb') {
            // On a free hit (not already a no-ball), only specific dismissals allowed
            $notAllowedOnFreeHit = ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket', 'timed_out'];
            if (in_array($wicketType, $notAllowedOnFreeHit)) {
                $isWicket = false;
                $wicketBatsmanId = null;
                $newBatsmanId = null;
            }
        }

        // ---- Over / Ball Counter Logic ----
        $oversCompleted      = (int) $state['overs_completed'];
        $currentBallInOver   = (int) $state['current_ball_in_over'];
        $totalRuns           = (int) $state['total_runs'];
        $totalWickets        = (int) $state['total_wickets'];
        $totalExtras         = (int) $state['total_extras'];
        $strikerId           = (int) $state['striker_id'];
        $nonStrikerId        = (int) $state['non_striker_id'];
        $bowlerId            = (int) $state['bowler_id'];
        $currentSequence     = (int) $state['last_ball_sequence'];

        // Defensive: if striker/bowler are 0 (invalid FK), innings needs to be set up
        if ($strikerId <= 0 || $bowlerId <= 0) {
            $db->rollBack();
            jsonResponse(['error' => 'Innings not set up. Select batsmen and bowler via Setup then click Start.'], 409);
        }
        $inningsNumber       = (int) $state['innings_number'];

        // Increment ball counter only for legal deliveries
        $overComplete = false;
        if ($isLegalBall) {
            $currentBallInOver++;
            if ($currentBallInOver >= 6) {
                $overComplete = true;
            }
        }

        // Over display string (e.g., "12.4")
        $overDisplay = $oversCompleted . '.' . $currentBallInOver;

        // ---- Build new sequence ID ----
        $newSequence = $currentSequence + 1;
        $nowEpoch    = time() * 1000;

        // ---- Handle Wicket ----
        $wicketBatsmanId = null;
        if ($isWicket) {
            // Wicket of the striker
            $wicketBatsmanId = $strikerId;
            $totalWickets++;

            // Mark batsman as dismissed
            $stmtUp = $db->prepare(
                'UPDATE batsman_innings
                 SET dismissal_type = ?, dismissed_by = ?
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmtUp->execute([$wicketType, $bowlerId, $matchId, $inningsNumber, $strikerId]);

            // Determine if this is the final wicket (team all-out)
            $isSuperOverCheck = (int) ($state['is_super_over'] ?? 0);
            $willBeAllOut = ($totalWickets >= 10 && !$isSuperOverCheck);
            $superOverAllOut = ($isSuperOverCheck && $totalWickets >= 2);

            // New batsman takes striker's place
            if ($newBatsmanId) {
                // Validate new batsman belongs to batting team
                $battingTeamId = (int) ($state['batting_team_id'] ?? 0);
                if (!playerInTeam($db, (int) $newBatsmanId, $battingTeamId)) {
                    $db->rollBack();
                    jsonResponse(['error' => 'Replacement batsman must belong to the batting team.'], 400);
                }

                // Check batsman hasn't already batted in this innings
                $stmtCheckBat = $db->prepare(
                    'SELECT id FROM batsman_innings WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
                );
                $stmtCheckBat->execute([$matchId, $inningsNumber, $newBatsmanId]);
                if ($stmtCheckBat->fetch()) {
                    $db->rollBack();
                    jsonResponse(['error' => 'This batsman has already batted in this innings.'], 400);
                }

                // Create innings entry for new batsman
                $maxPos = $db->prepare(
                    'SELECT COALESCE(MAX(batting_position), 0) + 1 FROM batsman_innings
                     WHERE match_id = ? AND innings_number = ?'
                );
                $maxPos->execute([$matchId, $inningsNumber]);
                $nextPos = (int) $maxPos->fetchColumn();

                $stmtIns = $db->prepare(
                    'INSERT INTO batsman_innings (match_id, innings_number, batsman_id, batting_position)
                     VALUES (?, ?, ?, ?)'
                );
                $stmtIns->execute([$matchId, $inningsNumber, $newBatsmanId, $nextPos]);

                $strikerId = (int) $newBatsmanId;
            } elseif ($willBeAllOut || $superOverAllOut) {
                // Team all out — no replacement needed, innings will end.
                // Keep striker as dismissed player (valid FK); innings transition resets to NULL.
            } else {
                // Wicket but no replacement provided
                $db->rollBack();
                jsonResponse(['error' => 'Please select a replacement batsman.'], 400);
            }

            // Bowler gets credit for wicket (except run-outs)
            if ($wicketType !== 'run_out') {
                $stmtBow = $db->prepare(
                    'UPDATE bowler_spells SET wickets_taken = wickets_taken + 1
                     WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
                );
                $stmtBow->execute([$matchId, $inningsNumber, $bowlerId]);
            }
        }

        // ---- Update totals ----
        $totalRuns   += $totalRunsAdded;
        $totalExtras += $extraRuns;

        // ---- Update batsman innings (striker) ----
        if ($batsmanRuns > 0 && !$isWicket) {
            // ICC rule: boundaries off no-balls count as runs but NOT as fours/sixes
            $fours = ($batsmanRuns === 4 && $extraType === null) ? 1 : 0;
            $sixes = ($batsmanRuns === 6 && $extraType === null) ? 1 : 0;
            $stmtBat = $db->prepare(
                'UPDATE batsman_innings
                 SET runs_scored = runs_scored + ?, balls_faced = balls_faced + ?,
                     fours = fours + ?, sixes = sixes + ?
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmtBat->execute([$batsmanRuns, ($isLegalBall ? 1 : 0), $fours, $sixes,
                               $matchId, $inningsNumber, $strikerId]);
        } elseif ($isLegalBall && !$isWicket) {
            // Dot ball — increment balls faced only
            $stmtBat = $db->prepare(
                'UPDATE batsman_innings SET balls_faced = balls_faced + 1
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmtBat->execute([$matchId, $inningsNumber, $strikerId]);
        }

        // ---- Update bowler spell ----
        if ($bowlerRuns > 0 || $isLegalBall) {
            $stmtBow = $db->prepare(
                'UPDATE bowler_spells
                 SET runs_conceded = runs_conceded + ?,
                     overs_bowled = overs_bowled + ?
                 WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
            );
            $ballFraction = $isLegalBall ? 0.1 : 0.0; // Only legal balls count in overs
            $stmtBow->execute([$bowlerRuns, $ballFraction, $matchId, $inningsNumber, $bowlerId]);
        }

        // ---- Handle Over Completion ----
        if ($overComplete) {
            // Check for maiden over (0 runs in the just-completed over, INCLUDING this ball)
            $completedOverNum = $oversCompleted; // old value before increment
            $stmtMaiden = $db->prepare(
                'SELECT COALESCE(SUM(runs_scored + extra_runs), 0) AS over_runs
                 FROM ball_timeline
                 WHERE match_id = ? AND innings_number = ?
                 AND over_display LIKE ?'
            );
            $stmtMaiden->execute([$matchId, $inningsNumber, $completedOverNum . '.%']);
            // Previous balls + current ball's runs = total over runs
            $overRuns = (int) $stmtMaiden->fetchColumn() + $totalRunsAdded;

            $oversCompleted++;
            $currentBallInOver = 0;

            // Auto-swap striker/non-striker at over end
            $temp       = $strikerId;
            $strikerId    = $nonStrikerId;
            $nonStrikerId = $temp;

            // Increment maiden count if 0 runs in the over
            if ($overRuns === 0) {
                $stmtM = $db->prepare(
                    'UPDATE bowler_spells SET maidens = maidens + 1
                     WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
                );
                $stmtM->execute([$matchId, $inningsNumber, $bowlerId]);
            }

            // New bowler assignment
            if ($newBowlerId) {
                // Validate new bowler belongs to bowling team
                $bowlingTeamId = (int) $state['bowling_team_id'];
                if (!playerInTeam($db, (int) $newBowlerId, $bowlingTeamId)) {
                    $db->rollBack();
                    jsonResponse(['error' => 'New bowler must belong to the bowling team.'], 400);
                }
                // Create bowler spell if not exists
                $stmtCheck = $db->prepare(
                    'SELECT id FROM bowler_spells WHERE match_id=? AND innings_number=? AND bowler_id=?'
                );
                $stmtCheck->execute([$matchId, $inningsNumber, $newBowlerId]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns = $db->prepare(
                        'INSERT INTO bowler_spells (match_id, innings_number, bowler_id) VALUES (?, ?, ?)'
                    );
                    $stmtIns->execute([$matchId, $inningsNumber, $newBowlerId]);
                }
                $bowlerId = (int) $newBowlerId;
            }
        } else {
            // ---- Striker Rotation on Odd Runs (ICC Rule) ----
            // Swap on 1, 3, 5 (odd runs) for legal deliveries, byes, and leg-byes.
            // Wicket and over-completion swaps are handled separately.
            $isLegalSwapExtra = ($extraType === null || $extraType === 'lb' || $extraType === 'by');
            if (!$isWicket && ($totalRunsAdded % 2 === 1) && $isLegalSwapExtra) {
                $temp          = $strikerId;
                $strikerId     = $nonStrikerId;
                $nonStrikerId  = $temp;
            }
        }

        // ---- Update over display ----
        // pre-over-completion display stored in timeline (e.g. "0.6")
        $timelineOverDisplay = $overDisplay;
        $overDisplayNew = $oversCompleted . '.' . $currentBallInOver;

        // ---- Calculate Current Run Rate (CRR) ----
        $totalOversFloat = $oversCompleted + ($currentBallInOver / 6.0);
        $crr = $totalOversFloat > 0 ? round($totalRuns / $totalOversFloat, 2) : 0.00;

        // ---- Persist Ball to Timeline ----
        $stmtTimeline = $db->prepare(
            'INSERT INTO ball_timeline
             (match_id, sequence_id, innings_number, timestamp_epoch, over_display,
               runs_scored, extra_type, extra_runs, is_wicket, wicket_type,
               striker_id, non_striker_id, bowler_id, batsman_runs, bowler_runs, is_legal_ball)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmtTimeline->execute([
            $matchId, $newSequence, $inningsNumber, $nowEpoch, $timelineOverDisplay,
            $runsScored, $extraType, $extraRuns, $isWicket ? 1 : 0, $wicketType,
            $state['striker_id'], $state['non_striker_id'], $state['bowler_id'],
            $batsmanRuns, $bowlerRuns, $isLegalBall ? 1 : 0,
        ]);

        // ---- Update live_state Atomically ----
        $stmtUpdate = $db->prepare(
            'UPDATE live_state SET
             total_runs = ?, total_wickets = ?, total_extras = ?,
             overs_completed = ?, current_ball_in_over = ?,
             current_over_display = ?,
             striker_id = ?, non_striker_id = ?, bowler_id = ?,
             last_ball_sequence = ?, current_run_rate = ?,
             last_updated_epoch = ?
             WHERE match_id = ?'
        );
        $stmtUpdate->execute([
            $totalRuns, $totalWickets, $totalExtras,
            $oversCompleted, $currentBallInOver,
            $overDisplayNew,
            $strikerId, $nonStrikerId, $bowlerId,
            $newSequence, $crr, $nowEpoch,
            $matchId,
        ]);

        // ---- Innings Transition Check ----
        $inningsOver = false;
        $target      = (int) ($state['target'] ?? 0);
        $maxOvers    = (int) ($state['max_overs'] ?? 20);
        $isSuperOver = (int) ($state['is_super_over'] ?? 0);
        $matchFormat = $matchInfo['match_format'] ?? 't20i';
        $currentInnings = (int) $inningsNumber;
        $result      = null;

        // Check if innings should end
        $allOut = ($totalWickets >= 10 && !$isSuperOver);
        $oversMaxed = (!$isSuperOver && $matchFormat !== 'test' && $oversCompleted >= $maxOvers);
        $superOverDone = ($isSuperOver && (($totalWickets >= 2) || ($oversCompleted >= 1)));
        // 2nd innings: target reached → match ends immediately (international rule)
        $targetReached = ($currentInnings === 2 && $target > 0 && $totalRuns >= $target && !$isSuperOver);

        if ($allOut || $oversMaxed || $superOverDone || $targetReached) {
            $inningsOver = true;

            if ($currentInnings === 1 && !$isSuperOver) {
                // 1st innings ended → auto-start 2nd innings
                $target = $totalRuns + 1;
                $battingTeamId = (int) $state['batting_team_id'];
                $bowlingTeamId = (int) $state['bowling_team_id'];

                // Update match target
                $stmtT = $db->prepare('UPDATE matches SET target = ?, innings_count = 2 WHERE id = ?');
                $stmtT->execute([$target, $matchId]);

                // Create 2nd innings live_state (swap teams, reset stats)
                $stmtNew = $db->prepare(
                    'UPDATE live_state SET
                     batting_team_id = ?, bowling_team_id = ?,
                     innings_number = 2, target = ?,
                     total_runs = 0, total_wickets = 0, total_extras = 0,
                     overs_completed = 0, current_ball_in_over = 0,
                     current_over_display = ?, striker_id = NULL, non_striker_id = NULL, bowler_id = NULL,
                     last_ball_sequence = 0, current_run_rate = 0,
                     last_updated_epoch = ?
                     WHERE match_id = ?'
                );
                $stmtNew->execute([$bowlingTeamId, $battingTeamId, $target, '0.0', $nowEpoch, $matchId]);
            } elseif ($currentInnings === 2 && !$isSuperOver) {
                // 2nd innings ended → determine result
                $firstInningsRuns = $target - 1;
                $winnerId = null;
                $battingTeamId2 = (int) $state['batting_team_id'];
                $bowlingTeamId2 = (int) $state['bowling_team_id'];
                $wicketsRemaining = 10 - $totalWickets;

                $result = '';
                if ($totalRuns >= $target) {
                    $result = 'win'; // batting team (2nd innings) wins
                    $winnerId = $battingTeamId2;
                } elseif ($totalRuns === $firstInningsRuns) {
                    $result = 'tie';
                } else {
                    $result = 'loss'; // bowling team wins (defended target)
                    $winnerId = $bowlingTeamId2;
                }

                // Fetch team names BEFORE updating (so we always have them)
                $stmtTeams = $db->prepare('SELECT id, name, short_name FROM teams WHERE id IN (?, ?)');
                $stmtTeams->execute([$battingTeamId2, $bowlingTeamId2]);
                $teamNames = [];
                foreach ($stmtTeams->fetchAll() as $t) {
                    $teamNames[$t['id']] = $t['short_name'] ?: $t['name'];
                }

                // Update match as completed with winner
                try {
                    if ($winnerId) {
                        $stmtR = $db->prepare("UPDATE matches SET status = 'completed', winner_id = ? WHERE id = ?");
                        $stmtR->execute([$winnerId, $matchId]);
                    } else {
                        $stmtR = $db->prepare("UPDATE matches SET status = 'completed' WHERE id = ?");
                        $stmtR->execute([$matchId]);
                    }
                } catch (Exception $e) {
                    // Fallback: winner_id column may not exist yet
                    $stmtR = $db->prepare("UPDATE matches SET status = 'completed' WHERE id = ?");
                    $stmtR->execute([$matchId]);
                }

                $resp['batting_team_name'] = $teamNames[$battingTeamId2] ?? 'Batting';
                $resp['bowling_team_name'] = $teamNames[$bowlingTeamId2] ?? 'Bowling';
                $resp['winner_name'] = $winnerId ? ($teamNames[$winnerId] ?? 'Unknown') : null;
                $resp['first_innings_runs'] = $firstInningsRuns;
                $resp['second_innings_runs'] = $totalRuns;
                $resp['wickets_remaining'] = ($result === 'win') ? $wicketsRemaining : 0;
            } elseif ($isSuperOver) {
                // Super Over ended
                $stmtR = $db->prepare("UPDATE matches SET status = 'completed' WHERE id = ?");
                $stmtR->execute([$matchId]);
                $result = 'super_over_done';
            }
        }

        $db->commit();

        // ---- Return Enriched State ----
        $resp = [
            'success'         => true,
            'sequence_id'     => $newSequence,
            'over_complete'   => $overComplete,
            'total_runs'      => $totalRuns,
            'total_wickets'   => $totalWickets,
            'total_extras'    => $totalExtras,
            'overs'           => $overDisplayNew,
            'overs_completed' => $oversCompleted,
            'ball_in_over'    => $currentBallInOver,
            'crr'             => $crr,
            'striker_id'      => $strikerId,
            'non_striker_id'  => $nonStrikerId,
            'bowler_id'       => $bowlerId,
            'is_legal_ball'   => $isLegalBall,
            'extra_type'      => $extraType,
            'is_wicket'       => $isWicket,
            'is_free_hit'     => $isFreeHit,
        ];

        // Include full player summaries for instant UI update (no extra network call needed)
        if (!$inningsOver) {
            $resp['striker']     = playerSummary($db, $strikerId, $matchId, $inningsNumber);
            $resp['non_striker'] = playerSummary($db, $nonStrikerId, $matchId, $inningsNumber);
            $resp['bowler']      = bowlerSummary($db, $bowlerId, $matchId, $inningsNumber);
        }

        if ($inningsOver) {
            $resp['innings_over']  = true;
            $resp['innings_ended'] = $currentInnings;
            $resp['target']        = $target;
            $resp['result']        = $result ?? null;
            $resp['is_tie']        = ($result ?? '') === 'tie';
            $resp['needs_super_over'] = (($result ?? '') === 'tie' && $matchFormat !== 'test');

            // For 1st innings end, provide team IDs for 2nd innings auto-setup
            if ($currentInnings === 1 && !$isSuperOver) {
                // Teams have been swapped for 2nd innings
                $resp['new_batting_team_id'] = $bowlingTeamId;
                $resp['new_bowling_team_id'] = $battingTeamId;
            }
        }

        jsonResponse($resp);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] record_ball failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Scoring engine error. The operation was rolled back.'], 500);
    }
}

/**
 * Transactional undo: pops the last ball from ball_timeline and
 * reverses its effect on live_state, batsman innings, and bowler spells.
 */
function handleUndo(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    if ($matchId <= 0) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        // Lock live_state
        $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $state = $stmt->fetch();

        if (!$state) {
            $db->rollBack();
            jsonResponse(['error' => 'Match is not live.'], 409);
        }

        $inningsNumber = (int) $state['innings_number'];
        $currentSeq    = (int) $state['last_ball_sequence'];

        if ($currentSeq <= 0) {
            $db->rollBack();
            jsonResponse(['error' => 'Nothing to undo. No balls recorded.'], 400);
        }

        // Fetch the last ball
        $stmt = $db->prepare(
            'SELECT * FROM ball_timeline WHERE match_id = ? AND sequence_id = ?'
        );
        $stmt->execute([$matchId, $currentSeq]);
        $lastBall = $stmt->fetch();

        if (!$lastBall) {
            $db->rollBack();
            jsonResponse(['error' => 'Timeline inconsistency detected.'], 500);
        }

        // ---- Reverse the ball effect ----

        // 1. Reverse team totals
        $totalRunsToRemove = (int) $lastBall['runs_scored'] + (int) $lastBall['extra_runs'];
        // More accurate: totalRunsToRemove = what was added
        $extraRunsToRemove = (int) $lastBall['extra_runs'];
        $isLegalBall       = (int) $lastBall['is_legal_ball'];
        $isWicket          = (int) $lastBall['is_wicket'];
        $batsmanRuns       = (int) $lastBall['batsman_runs'];
        $bowlerRuns        = (int) $lastBall['bowler_runs'];

        $newTotalRuns   = (int) $state['total_runs'] - $totalRunsToRemove;
        $newTotalExtras = (int) $state['total_extras'] - $extraRunsToRemove;
        $newTotalWickets = (int) $state['total_wickets'] - ($isWicket ? 1 : 0);

        // 2. Reverse ball counter
        $newOversCompleted    = (int) $state['overs_completed'];
        $newCurrentBallInOver = (int) $state['current_ball_in_over'];
        $wasOverCompletion    = false;
        $undoneOverNum        = 0;

        if ($isLegalBall) {
            if ($newCurrentBallInOver === 0) {
                // We were at the start of an over — the last ball must have triggered over completion
                $wasOverCompletion = true;
                $undoneOverNum     = $newOversCompleted - 1; // the over that was just completed
                $newOversCompleted--;
                $newCurrentBallInOver = 5; // 0-indexed: ball 6 was just completed
            } else {
                $newCurrentBallInOver--;
            }
        }

        // Reverse maiden if the undone over-completion was a maiden
        if ($wasOverCompletion) {
            // Check if the undone over was a maiden (0 runs in that over AFTER removing this ball)
            $stmtMCheck = $db->prepare(
                'SELECT COALESCE(SUM(runs_scored + extra_runs), 0) AS over_runs
                 FROM ball_timeline
                 WHERE match_id = ? AND innings_number = ?
                 AND over_display LIKE ? AND sequence_id != ?'
            );
            $stmtMCheck->execute([$matchId, $inningsNumber, $undoneOverNum . '.%', $currentSeq]);
            $remainingRuns = (int) $stmtMCheck->fetchColumn();
            if ($remainingRuns === 0 && $totalRunsToRemove === 0) {
                $stmtM = $db->prepare(
                    'UPDATE bowler_spells SET maidens = GREATEST(0, maidens - 1)
                     WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
                );
                $stmtM->execute([$matchId, $inningsNumber, (int)$lastBall['bowler_id']]);
            }
        }

        // 3. Reverse batsman innings
        $originalStrikerId = (int) $lastBall['striker_id'];
        if ($isWicket) {
            // Undo dismissal: mark batsman as not-out again
            $stmt = $db->prepare(
                'UPDATE batsman_innings SET dismissal_type = NULL, dismissed_by = NULL
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmt->execute([$matchId, $inningsNumber, $originalStrikerId]);

            // Remove the replacement batsman's innings if it exists and has 0 balls faced
            $currentStrikerId = (int) $state['striker_id'];
            if ($currentStrikerId !== $originalStrikerId) {
                $stmt = $db->prepare(
                    'DELETE FROM batsman_innings
                     WHERE match_id = ? AND innings_number = ? AND batsman_id = ?
                     AND balls_faced = 0 AND runs_scored = 0 AND dismissal_type IS NULL'
                );
                $stmt->execute([$matchId, $inningsNumber, $currentStrikerId]);
            }
        } elseif ($batsmanRuns > 0) {
            // Reverse runs
            $fours = ($batsmanRuns === 4) ? 1 : 0;
            $sixes = ($batsmanRuns === 6) ? 1 : 0;
            $stmt = $db->prepare(
                'UPDATE batsman_innings
                 SET runs_scored = GREATEST(0, runs_scored - ?),
                     balls_faced = GREATEST(0, balls_faced - ?),
                     fours = GREATEST(0, fours - ?),
                     sixes = GREATEST(0, sixes - ?)
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmt->execute([$batsmanRuns, ($isLegalBall ? 1 : 0), $fours, $sixes,
                           $matchId, $inningsNumber, $originalStrikerId]);
        } elseif ($isLegalBall) {
            // Dot ball — just reduce balls faced
            $stmt = $db->prepare(
                'UPDATE batsman_innings
                 SET balls_faced = GREATEST(0, balls_faced - 1)
                 WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
            );
            $stmt->execute([$matchId, $inningsNumber, $originalStrikerId]);
        }

        // 4. Reverse bowler spell
        $originalBowlerId = (int) $lastBall['bowler_id'];
        if ($bowlerRuns > 0 || $isLegalBall) {
            $stmt = $db->prepare(
                'UPDATE bowler_spells
                 SET runs_conceded = GREATEST(0, runs_conceded - ?),
                     overs_bowled = GREATEST(0.0, overs_bowled - ?)
                 WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
            );
            $ballFraction = $isLegalBall ? 0.1 : 0.0;
            $stmt->execute([$bowlerRuns, $ballFraction, $matchId, $inningsNumber, $originalBowlerId]);
        }

        // Reverse bowler wicket credit (except run-outs)
        $wicketType = $lastBall['wicket_type'];
        if ($isWicket && $wicketType !== 'run_out') {
            $stmt = $db->prepare(
                'UPDATE bowler_spells
                 SET wickets_taken = GREATEST(0, wickets_taken - 1)
                 WHERE match_id = ? AND innings_number = ? AND bowler_id = ?'
            );
            $stmt->execute([$matchId, $inningsNumber, $originalBowlerId]);
        }

        // 5. Restore striker/non-striker/bowler to pre-ball state
        $newStrikerId    = (int) $lastBall['striker_id'];
        $newNonStrikerId = (int) $lastBall['non_striker_id'];
        $newBowlerId     = (int) $lastBall['bowler_id'];

        // 6. Calculate new CRR
        $totalOversFloat = $newOversCompleted + ($newCurrentBallInOver / 6.0);
        $newCrr = $totalOversFloat > 0 ? round($newTotalRuns / $totalOversFloat, 2) : 0.00;

        $newOverDisplay = $newOversCompleted . '.' . $newCurrentBallInOver;
        $nowEpoch = time() * 1000;

        // 7. Update live_state
        $stmt = $db->prepare(
            'UPDATE live_state SET
             total_runs = ?, total_wickets = ?, total_extras = ?,
             overs_completed = ?, current_ball_in_over = ?,
             current_over_display = ?,
             striker_id = ?, non_striker_id = ?, bowler_id = ?,
             last_ball_sequence = ?, current_run_rate = ?,
             last_updated_epoch = ?
             WHERE match_id = ?'
        );
        $stmt->execute([
            $newTotalRuns, $newTotalWickets, $newTotalExtras,
            $newOversCompleted, $newCurrentBallInOver,
            $newOverDisplay,
            $newStrikerId, $newNonStrikerId, $newBowlerId,
            $currentSeq - 1, $newCrr, $nowEpoch,
            $matchId,
        ]);

        // 8. Delete the ball from timeline
        $stmt = $db->prepare('DELETE FROM ball_timeline WHERE match_id = ? AND sequence_id = ?');
        $stmt->execute([$matchId, $currentSeq]);

        $db->commit();

        jsonResponse([
            'success'          => true,
            'message'          => 'Last ball undone.',
            'new_sequence_id'  => $currentSeq - 1,
            'total_runs'       => $newTotalRuns,
            'total_wickets'    => $newTotalWickets,
            'overs'            => $newOverDisplay,
            'crr'              => $newCrr,
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] undo failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Undo failed. The operation was rolled back.'], 500);
    }
}

/**
 * Assign or replace the current bowler (e.g., mid-over change or after over completion).
 */
function handleSetBowler(array $input): void
{
    $matchId  = (int) ($input['match_id'] ?? 0);
    $bowlerId = (int) ($input['bowler_id'] ?? 0);

    if (!$matchId || !$bowlerId) {
        jsonResponse(['error' => 'match_id and bowler_id are required.'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ? FOR UPDATE');
    $stmt->execute([$matchId]);
    $state = $stmt->fetch();

    if (!$state) {
        jsonResponse(['error' => 'Match is not live.'], 409);
    }

    $bowlingTeamId = (int) $state['bowling_team_id'];
    if (!playerInTeam($db, $bowlerId, $bowlingTeamId)) {
        jsonResponse(['error' => 'Bowler must belong to the bowling team.'], 400);
    }

    $db->beginTransaction();
    try {
        // Create bowler spell if not exists
        $inningsNumber = (int) $state['innings_number'];
        $stmtCheck = $db->prepare(
            'SELECT id FROM bowler_spells WHERE match_id=? AND innings_number=? AND bowler_id=?'
        );
        $stmtCheck->execute([$matchId, $inningsNumber, $bowlerId]);
        if (!$stmtCheck->fetch()) {
            $stmtIns = $db->prepare(
                'INSERT INTO bowler_spells (match_id, innings_number, bowler_id) VALUES (?, ?, ?)'
            );
            $stmtIns->execute([$matchId, $inningsNumber, $bowlerId]);
        }

        $stmtUp = $db->prepare('UPDATE live_state SET bowler_id = ?, last_updated_epoch = ? WHERE match_id = ?');
        $stmtUp->execute([$bowlerId, (int)(microtime(true)*1000), $matchId]);

        $db->commit();
        jsonResponse(['success' => true, 'bowler_id' => $bowlerId, 'message' => 'Bowler assigned.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to assign bowler.'], 500);
    }
}

/**
 * Retire a batsman hurt — replace striker WITHOUT incrementing wicket count.
 * Expected input: match_id, new_batsman_id
 */
function handleRetireBatsman(array $input): void
{
    $matchId     = (int) ($input['match_id'] ?? 0);
    $newBatsman  = (int) ($input['new_batsman_id'] ?? 0);

    if (!$matchId || !$newBatsman) {
        jsonResponse(['error' => 'match_id and new_batsman_id are required.'], 400);
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $state = $stmt->fetch();
        if (!$state) {
            $db->rollBack();
            jsonResponse(['error' => 'Match is not live.'], 409);
        }

        $inningsNumber = (int) $state['innings_number'];
        $oldStrikerId  = (int) $state['striker_id'];
        $battingTeamId = (int) $state['batting_team_id'];

        if (!playerInTeam($db, $newBatsman, $battingTeamId)) {
            $db->rollBack();
            jsonResponse(['error' => 'New batsman must belong to the batting team.'], 400);
        }

        $stmt = $db->prepare(
            'UPDATE batsman_innings SET dismissal_type = ? WHERE match_id = ? AND innings_number = ? AND batsman_id = ?'
        );
        $stmt->execute(['retired_hurt', $matchId, $inningsNumber, $oldStrikerId]);

        $maxPos = $db->prepare(
            'SELECT COALESCE(MAX(batting_position), 0) + 1 FROM batsman_innings WHERE match_id = ? AND innings_number = ?'
        );
        $maxPos->execute([$matchId, $inningsNumber]);
        $nextPos = (int) $maxPos->fetchColumn();

        $stmtIns = $db->prepare(
            'INSERT INTO batsman_innings (match_id, innings_number, batsman_id, batting_position) VALUES (?, ?, ?, ?)'
        );
        $stmtIns->execute([$matchId, $inningsNumber, $newBatsman, $nextPos]);

        $nowEpoch = time() * 1000;
        $stmtUp = $db->prepare(
            'UPDATE live_state SET striker_id = ?, last_updated_epoch = ? WHERE match_id = ?'
        );
        $stmtUp->execute([$newBatsman, $nowEpoch, $matchId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Batsman retired hurt. Replacement in.']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] retire_batsman failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Operation failed.'], 500);
    }
}

/**
 * Manually swap striker and non-striker positions.
 */
function handleSwapStriker(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$matchId) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT striker_id, non_striker_id FROM live_state WHERE match_id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $state = $stmt->fetch();
        if (!$state) {
            $db->rollBack();
            jsonResponse(['error' => 'Match is not live.'], 409);
        }

        $nowEpoch = time() * 1000;
        $stmtUp = $db->prepare(
            'UPDATE live_state SET striker_id = ?, non_striker_id = ?, last_updated_epoch = ? WHERE match_id = ?'
        );
        $stmtUp->execute([(int)$state['non_striker_id'], (int)$state['striker_id'], $nowEpoch, $matchId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Striker swapped.']);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] swap_striker failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Operation failed.'], 500);
    }
}

/**
 * Update match toss info. Expected: match_id, toss_won_by, toss_decision
 */
function handleSetToss(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    $tossWon = (int) ($input['toss_won_by'] ?? 0);
    $tossDec = $input['toss_decision'] ?? '';

    if (!$matchId || !$tossWon || !in_array($tossDec, ['bat', 'bowl'])) {
        jsonResponse(['error' => 'match_id, toss_won_by, and toss_decision (bat/bowl) required.'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT id, team_a_id, team_b_id FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        jsonResponse(['error' => 'Match not found.'], 404);
    }

    $teamAId = (int) $match['team_a_id'];
    $teamBId = (int) $match['team_b_id'];
    if ($tossWon !== $teamAId && $tossWon !== $teamBId) {
        jsonResponse(['error' => 'Toss winner must be one of the match teams.'], 400);
    }

    $batFirst = ($tossDec === 'bat') ? $tossWon : ($tossWon === $teamAId ? $teamBId : $teamAId);

    $stmt = $db->prepare(
        'UPDATE matches SET toss_won_by = ?, toss_decision = ?, batting_first = ? WHERE id = ?'
    );
    $stmt->execute([$tossWon, $tossDec, $batFirst, $matchId]);

    jsonResponse([
        'success'     => true,
        'message'     => 'Toss saved.',
        'batting_first' => $batFirst,
    ]);
}

/**
 * Start a Super Over — mini innings (1 over, 2 wickets max).
 */
function handleStartSuperOver(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$matchId) jsonResponse(['error' => 'match_id required.'], 400);

    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ? FOR UPDATE');
        $stmt->execute([$matchId]);
        $state = $stmt->fetch();
        if (!$state) { $db->rollBack(); jsonResponse(['error' => 'No live state.'], 409); }

        $batId  = (int) ($input['batting_team_id'] ?? $state['batting_team_id']);
        $bowlId = (int) ($input['bowling_team_id'] ?? $state['bowling_team_id']);
        $now = (int)(microtime(true) * 1000);

        $stmt = $db->prepare(
            'UPDATE live_state SET
             batting_team_id = ?, bowling_team_id = ?,
             innings_number = 99, target = 0, is_super_over = 1,
             total_runs = 0, total_wickets = 0, total_extras = 0,
             overs_completed = 0, current_ball_in_over = 0,
             current_over_display = ?, max_overs = 1,
             striker_id = NULL, non_striker_id = NULL, bowler_id = NULL,
             last_ball_sequence = 0, current_run_rate = 0,
             last_updated_epoch = ?
             WHERE match_id = ?'
        );
        $stmt->execute(['0.0', $now, $matchId]);

        $stmtM = $db->prepare("UPDATE matches SET status = 'live', innings_count = 99 WHERE id = ?");
        $stmtM->execute([$matchId]);

        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Super Over started. Select batsmen and bowler.']);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Failed to start Super Over.'], 500);
    }
}

// ============================================================================
// DATA QUERY HANDLERS
// ============================================================================

function handleGetLiveState(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$matchId) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db    = getDB();
    $stmt  = $db->prepare('SELECT * FROM live_state WHERE match_id = ?');
    $stmt->execute([$matchId]);
    $state = $stmt->fetch();

    if (!$state) {
        jsonResponse(['error' => 'No live state found for this match.'], 404);
    }

    // Fetch batsman names
    $striker    = playerSummary($db, (int) $state['striker_id'], $matchId, (int) $state['innings_number']);
    $nonStriker = playerSummary($db, (int) $state['non_striker_id'], $matchId, (int) $state['innings_number']);
    $bowler     = bowlerSummary($db, (int) $state['bowler_id'], $matchId, (int) $state['innings_number']);

    // Fetch team names
    $stmt = $db->prepare('SELECT id, name, short_name, logo_path FROM teams WHERE id IN (?, ?)');
    $stmt->execute([(int) $state['batting_team_id'], (int) $state['bowling_team_id']]);
    $teams = $stmt->fetchAll();
    $battingTeamName = '';
    $bowlingTeamName = '';
    $battingTeamLogo = null;
    $bowlingTeamLogo = null;
    foreach ($teams as $t) {
        if ((int) $t['id'] === (int) $state['batting_team_id']) {
            $battingTeamName = $t['short_name'] ?: $t['name'];
            $battingTeamLogo = $t['logo_path'];
        }
        if ((int) $t['id'] === (int) $state['bowling_team_id']) {
            $bowlingTeamName = $t['short_name'] ?: $t['name'];
            $bowlingTeamLogo = $t['logo_path'];
        }
    }

    jsonResponse([
        'match_id'            => (int) $state['match_id'],
        'batting_team_id'     => (int) $state['batting_team_id'],
        'batting_team_name'   => $battingTeamName,
        'batting_team_logo'   => $battingTeamLogo,
        'bowling_team_id'     => (int) $state['bowling_team_id'],
        'bowling_team_name'   => $bowlingTeamName,
        'bowling_team_logo'   => $bowlingTeamLogo,
        'total_runs'          => (int) $state['total_runs'],
        'total_wickets'       => (int) $state['total_wickets'],
        'total_extras'        => (int) $state['total_extras'],
        'overs_completed'     => (int) $state['overs_completed'],
        'current_ball_in_over'=> (int) $state['current_ball_in_over'],
        'over_display'        => $state['current_over_display'],
        'current_run_rate'    => (float) $state['current_run_rate'],
        'innings_number'      => (int) $state['innings_number'],
        'target'              => (int) ($state['target'] ?? 0),
        'max_overs'           => (int) ($state['max_overs'] ?? 20),
        'sequence_id'         => (int) $state['last_ball_sequence'],
        'striker'             => $striker,
        'non_striker'         => $nonStriker,
        'bowler'              => $bowler,
    ]);
}

/**
 * Full overlay data endpoint — public, no auth required.
 * Returns everything the broadcast overlay needs in one JSON payload.
 */
function handleGetOverlayData(array $input): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$matchId) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();
    $data = buildOverlayData($db, $matchId);

    if ($data === null) {
        jsonResponse(['error' => 'Match not found.'], 404);
    }

    jsonResponse($data);
}

function handleGetTeams(): void
{
    $db   = getDB();
    $stmt = $db->query('SELECT id, name, short_name, logo_path FROM teams ORDER BY name');
    $teams = $stmt->fetchAll();

    // Attach player counts
    foreach ($teams as &$team) {
        $stmt2 = $db->prepare('SELECT COUNT(*) FROM players WHERE team_id = ? AND is_active = 1');
        $stmt2->execute([$team['id']]);
        $team['player_count'] = (int) $stmt2->fetchColumn();
    }

    jsonResponse(['teams' => $teams]);
}

function handleGetPlayers(array $input): void
{
    $teamId  = (int) ($input['team_id'] ?? 0);
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$teamId) {
        jsonResponse(['error' => 'team_id is required.'], 400);
    }

    $db = getDB();
    $stmt = $db->prepare(
        'SELECT p.id, p.team_id, p.name, p.role, p.photo_path, p.age, p.school_entry_year, p.achievements,
                t.short_name AS team_short, t.name AS team_name
         FROM players p JOIN teams t ON p.team_id = t.id
         WHERE p.team_id = ? AND p.is_active = 1 ORDER BY p.name'
    );
    $stmt->execute([$teamId]);
    $players = $stmt->fetchAll();

    // If match_id provided, mark which players are in playing XI and have already batted
    if ($matchId) {
        // Playing XI check
        $xiSet = [];
        $stmtXI = $db->prepare(
            'SELECT player_id FROM match_playing_xi WHERE match_id = ? AND team_id = ?'
        );
        $stmtXI->execute([$matchId, $teamId]);
        $xiIds = $stmtXI->fetchAll(PDO::FETCH_COLUMN);
        $xiSet = array_flip($xiIds);

        // Already batted check
        $stmtState = $db->prepare('SELECT innings_number FROM live_state WHERE match_id = ?');
        $stmtState->execute([$matchId]);
        $state = $stmtState->fetch();
        $inningsNumber = $state ? (int) $state['innings_number'] : 1;

        $stmtBatted = $db->prepare(
            'SELECT batsman_id FROM batsman_innings WHERE match_id = ? AND innings_number = ?'
        );
        $stmtBatted->execute([$matchId, $inningsNumber]);
        $battedIds = $stmtBatted->fetchAll(PDO::FETCH_COLUMN);
        $battedSet = array_flip($battedIds);

        foreach ($players as &$p) {
            $p['is_in_xi']   = isset($xiSet[$p['id']]);
            $p['has_batted'] = isset($battedSet[$p['id']]);
        }
    }

    jsonResponse(['players' => $players]);
}

// ============================================================================
// MATCH SCORECARD — For completed matches (no live_state)
// ============================================================================

function handleGetMatchScorecard(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    if (!$matchId) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();

    // Fetch match info with teams
    $stmt = $db->prepare(
        'SELECT m.*, ta.name AS team_a_name, ta.short_name AS team_a_short, ta.logo_path AS team_a_logo,
                tb.name AS team_b_name, tb.short_name AS team_b_short, tb.logo_path AS team_b_logo
         FROM matches m
         JOIN teams ta ON m.team_a_id = ta.id
         JOIN teams tb ON m.team_b_id = tb.id
         WHERE m.id = ?'
    );
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) {
        jsonResponse(['error' => 'Match not found.'], 404);
    }

    $teamAId = (int) $match['team_a_id'];
    $teamBId = (int) $match['team_b_id'];
    $teamNames = [
        $teamAId => ['name' => $match['team_a_name'], 'short_name' => $match['team_a_short'], 'logo' => $match['team_a_logo']],
        $teamBId => ['name' => $match['team_b_name'], 'short_name' => $match['team_b_short'], 'logo' => $match['team_b_logo']],
    ];

    // Determine which team batted first (from matches table or live_state or ball_timeline)
    $battingFirst = (int) ($match['batting_first'] ?? 0);
    $target = (int) ($match['target'] ?? 0);
    $winnerId = (int) ($match['winner_id'] ?? 0);

    // Build innings data by querying live_state or ball_timeline for each innings
    $inningsList = [];
    for ($inn = 1; $inn <= 2; $inn++) {
        // Get aggregate from ball_timeline for this innings
        $stmt = $db->prepare(
            'SELECT SUM(runs_scored + extra_runs) AS total_runs,
                    SUM(CASE WHEN is_wicket = 1 THEN 1 ELSE 0 END) AS total_wickets,
                    SUM(extra_runs) AS total_extras,
                    COUNT(CASE WHEN is_legal_ball = 1 THEN 1 END) AS legal_balls
             FROM ball_timeline WHERE match_id = ? AND innings_number = ?'
        );
        $stmt->execute([$matchId, $inn]);
        $agg = $stmt->fetch();

        if (!$agg || $agg['total_runs'] === null) continue; // No data for this innings

        $legalBalls = (int) ($agg['legal_balls'] ?? 0);
        $oversCompleted = intdiv($legalBalls, 6);
        $ballsInOver = $legalBalls % 6;
        $overDisplay = $oversCompleted . '.' . $ballsInOver;

        // Determine batting/bowling teams for this innings
        $battingTeamId = 0;
        $bowlingTeamId = 0;
        // Try live_state
        $stmtLS = $db->prepare('SELECT batting_team_id, bowling_team_id FROM live_state WHERE match_id = ?');
        $stmtLS->execute([$matchId]);
        $ls = $stmtLS->fetch();
        if ($ls) {
            // Current live_state shows the last/current innings teams
            if ($inn === (int) ($ls['innings_number'] ?? 1)) {
                $battingTeamId = (int) $ls['batting_team_id'];
                $bowlingTeamId = (int) $ls['bowling_team_id'];
            } else {
                // Previous innings: teams were swapped
                $battingTeamId = (int) $ls['bowling_team_id'];
                $bowlingTeamId = (int) $ls['batting_team_id'];
            }
        }
        // Fallback: use batting_first
        if (!$battingTeamId && $battingFirst) {
            $battingTeamId = ($inn === 1) ? $battingFirst : ($battingFirst === $teamAId ? $teamBId : $teamAId);
            $bowlingTeamId = ($battingTeamId === $teamAId) ? $teamBId : $teamAId;
        }

        $bt = $teamNames[$battingTeamId] ?? ['name' => 'Team ' . $inn, 'short_name' => 'T' . $inn, 'logo' => null];
        $bot = $teamNames[$bowlingTeamId] ?? ['name' => 'Opposition', 'short_name' => 'OPP', 'logo' => null];

        // Batting card
        $stmt = $db->prepare(
            'SELECT p.id, p.name, p.role, p.photo_path, p.batting_style, p.bowling_style,
                    bi.runs_scored, bi.balls_faced, bi.fours, bi.sixes,
                    bi.dismissal_type, bi.batting_position
             FROM batsman_innings bi
             JOIN players p ON p.id = bi.batsman_id
             WHERE bi.match_id = ? AND bi.innings_number = ?
             ORDER BY bi.batting_position ASC'
        );
        $stmt->execute([$matchId, $inn]);
        $battingCard = $stmt->fetchAll();
        foreach ($battingCard as &$br) {
            $br['sr'] = $br['balls_faced'] > 0 ? round(($br['runs_scored'] / $br['balls_faced']) * 100, 1) : 0;
        }
        unset($br);

        // Bowling card
        $stmt = $db->prepare(
            'SELECT p.id, p.name, p.role, p.photo_path, p.batting_style, p.bowling_style,
                    bs.overs_bowled, bs.maidens, bs.runs_conceded, bs.wickets_taken
             FROM bowler_spells bs
             JOIN players p ON p.id = bs.bowler_id
             WHERE bs.match_id = ? AND bs.innings_number = ?
             ORDER BY bs.id ASC'
        );
        $stmt->execute([$matchId, $inn]);
        $bowlingCard = $stmt->fetchAll();
        foreach ($bowlingCard as &$bwr) {
            $ov = (float)($bwr['overs_bowled'] ?? 0);
            $bwr['econ'] = oversToFloat($ov) > 0 ? round(($bwr['runs_conceded'] ?? 0) / oversToFloat($ov), 1) : 0;
        }
        unset($bwr);

        $inningsList[] = [
            'number'          => $inn,
            'batting_team'    => array_merge(['id' => $battingTeamId], $bt, [
                'runs'    => (int) ($agg['total_runs'] ?? 0),
                'wickets' => (int) ($agg['total_wickets'] ?? 0),
                'overs'   => $overDisplay,
                'extras'  => (int) ($agg['total_extras'] ?? 0),
            ]),
            'bowling_team'    => array_merge(['id' => $bowlingTeamId], $bot),
            'batting_card'    => $battingCard,
            'bowling_card'    => $bowlingCard,
        ];
    }

    // Determine result
    $resultMsg = '';
    $winnerName = '';
    $margin = '';
    if ($match['status'] === 'completed') {
        $winnerName = $winnerId ? ($teamNames[$winnerId]['short_name'] ?? $teamNames[$winnerId]['name'] ?? 'Unknown') : '';
        if ($target > 0 && count($inningsList) >= 2) {
            $inn1Runs = $inningsList[0]['batting_team']['runs'];
            $inn2 = $inningsList[1]['batting_team'];
            $inn2Runs = $inn2['runs'];
            $inn2Wkts = $inn2['wickets'];

            if ($inn2Runs >= $target) {
                // Chasing team won
                $margin = (10 - $inn2Wkts) . ' wicket' . ($inn2Wkts !== 9 ? 's' : '');
                $resultMsg = $winnerName . ' won by ' . $margin;
            } elseif ($inn2Runs === ($target - 1)) {
                $resultMsg = 'Match Tied';
                $margin = '';
            } else {
                $margin = (($target - 1) - $inn2Runs) . ' run' . (($target - 1 - $inn2Runs) !== 1 ? 's' : '');
                $resultMsg = $winnerName . ' won by ' . $margin;
            }
        }
    }

    // Last 10 balls for the log (from all innings)
    $stmt = $db->prepare(
        'SELECT runs_scored, extra_type, extra_runs, is_wicket, wicket_type, over_display, sequence_id
         FROM ball_timeline WHERE match_id = ?
         ORDER BY sequence_id DESC LIMIT 10'
    );
    $stmt->execute([$matchId]);
    $lastBallsRaw = $stmt->fetchAll();
    $lastBalls = [];
    foreach ($lastBallsRaw as $ball) {
        $display = '';
        if ($ball['is_wicket']) {
            $display = 'W';
        } elseif ($ball['extra_type'] === 'wd') {
            $display = 'WD';
        } elseif ($ball['extra_type'] === 'nb') {
            $display = 'NB' . ($ball['runs_scored'] > 0 ? '+' . $ball['runs_scored'] : '');
        } elseif ($ball['extra_type'] === 'lb') {
            $display = 'LB' . $ball['extra_runs'];
        } elseif ($ball['extra_type'] === 'by') {
            $display = 'B' . $ball['extra_runs'];
        } else {
            $display = (string) $ball['runs_scored'];
        }
        $lastBalls[] = [
            'runs'    => (int) $ball['runs_scored'],
            'extra'   => $ball['extra_type'],
            'wicket'  => (bool) $ball['is_wicket'],
            'display' => $display,
        ];
    }
    $lastBalls = array_reverse($lastBalls);

    // Legacy compatibility fields (use first innings)
    $inn1 = $inningsList[0] ?? null;
    jsonResponse([
        'success'      => true,
        'is_completed' => $match['status'] === 'completed',
        'match'        => [
            'id'           => (int) $match['id'],
            'title'        => $match['match_title'],
            'status'       => $match['status'],
            'target'       => $target,
            'result_msg'   => $resultMsg,
            'winner_name'  => $winnerName,
            'margin'       => $margin,
            'toss_won_by'  => (int)($match['toss_won_by'] ?? 0),
            'toss_decision'=> $match['toss_decision'] ?? '',
            'batting_first'=> (int)($match['batting_first'] ?? 0),
            'team_a_id'    => $teamAId,
            'team_b_id'    => $teamBId,
            'team_a_name'  => $match['team_a_name'] ?? '',
            'team_b_name'  => $match['team_b_name'] ?? '',
            'team_a_short' => $match['team_a_short'] ?? '',
            'team_b_short' => $match['team_b_short'] ?? '',
            'team_a_logo'  => $match['team_a_logo'] ?? '',
            'team_b_logo'  => $match['team_b_logo'] ?? '',
        ],
        'innings'      => $inningsList,
        // Legacy fields for backward compat
        'batting_team'  => $inn1 ? $inn1['batting_team'] : [],
        'bowling_team'  => $inn1 ? $inn1['bowling_team'] : [],
        'batting_card'  => $inn1 ? $inn1['batting_card'] : [],
        'bowling_card'  => $inn1 ? $inn1['bowling_card'] : [],
        'current_run_rate' => 0,
        'sequence_id'   => 0,
        'last_balls'    => $lastBalls,
    ]);
}

function handleGetMatches(): void
{
    $db = getDB();
    $stmt = $db->query(
        'SELECT m.*, ta.name AS team_a_name, ta.short_name AS team_a_short,
                tb.name AS team_b_name, tb.short_name AS team_b_short
         FROM matches m
         JOIN teams ta ON m.team_a_id = ta.id
         JOIN teams tb ON m.team_b_id = tb.id
         ORDER BY m.created_at DESC'
    );
    jsonResponse(['matches' => $stmt->fetchAll()]);
}

// ============================================================================
// OUTPUT VIEW STATE (Broadcast Control Panel → Overlay sync)
// ============================================================================

function getOutputStatePath(int $matchId): string {
    return __DIR__ . '/data/output_state_' . $matchId . '.json';
}

function readOutputState(int $matchId): array {
    $path = getOutputStatePath($matchId);
    if (!file_exists($path)) return ['view' => 'scorebug'];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : ['view' => 'scorebug'];
}

function writeOutputState(int $matchId, array $state): void {
    $path = getOutputStatePath($matchId);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($state), LOCK_EX);
}

function handleSetOutputView(array $input): void {
    // Release session lock immediately (prevents blocking concurrent requests)
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $matchId = (int) ($input['match_id'] ?? 0);
    $view    = $input['view'] ?? 'scorebug';
    $validViews = ['scorebug','batting','bowling','xi','xi_bat','xi_bowl','summary','blank','toss',
        'batting_1st','batting_2nd','batting_so','bowling_1st','bowling_2nd','bowling_so',
        'xi_bat_1st','xi_bat_2nd','xi_bowl_1st','xi_bowl_2nd',
        'summary_1st','summary_2nd','summary_so','player_profile'];
    if (!in_array($view, $validViews)) {
        jsonResponse(['error' => 'Invalid view. Valid: ' . implode(', ', $validViews)], 400);
    }
    if ($matchId <= 0) jsonResponse(['error' => 'match_id required'], 400);

    $state = readOutputState($matchId);
    $state['view'] = $view;
    $state['updated_at'] = time();
    // Store player_id for player_profile view
    if ($view === 'player_profile') {
        $state['player_id'] = (int) ($input['player_id'] ?? 0);
    }
    writeOutputState($matchId, $state);

    // Bump/ensure live_state row exists so SSE pushes the new output_view
    $db = getDB();
    $now = (int)(microtime(true) * 1000);
    $stmt = $db->prepare('UPDATE live_state SET last_updated_epoch = ? WHERE match_id = ?');
    $stmt->execute([$now, $matchId]);
    // If no live_state yet (match not started), insert a minimal row for SSE tracking
    if ($stmt->rowCount() === 0) {
        $isPg = (defined('DB_ENGINE') && DB_ENGINE === 'pgsql');
        $sqlIns = $isPg
            ? "INSERT INTO live_state (match_id, batting_team_id, bowling_team_id, innings_number, max_overs, striker_id, non_striker_id, bowler_id, last_updated_epoch, current_run_rate, last_ball_sequence, total_runs, total_wickets, total_extras, overs_completed, current_ball_in_over, current_over_display) VALUES (?, 0, 0, 1, 20, 0, 0, 0, ?, 0, 0, 0, 0, 0, 0, 0, '0.0') ON CONFLICT (match_id) DO NOTHING"
            : "INSERT IGNORE INTO live_state (match_id, batting_team_id, bowling_team_id, innings_number, max_overs, striker_id, non_striker_id, bowler_id, last_updated_epoch, current_run_rate, last_ball_sequence, total_runs, total_wickets, total_extras, overs_completed, current_ball_in_over, current_over_display) VALUES (?, 0, 0, 1, 20, 0, 0, 0, ?, 0, 0, 0, 0, 0, 0, 0, '0.0')";
        $stmtIns = $db->prepare($sqlIns);
        $stmtIns->execute([$matchId, $now]);
    }

    jsonResponse(['success' => true, 'view' => $view]);
}

function handleGetOutputView(array $input): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $matchId = (int) ($input['match_id'] ?? 0);
    if ($matchId <= 0) jsonResponse(['error' => 'match_id required'], 400);
    $state = readOutputState($matchId);
    jsonResponse(['view' => $state['view'] ?? 'scorebug', 'player_id' => $state['player_id'] ?? 0]);
}

// ============================================================================
// PLAYING XI MANAGEMENT
// ============================================================================

/**
 * Set playing XI for a team in a match.
 * Expected: match_id, team_id, player_ids (array of up to 11 player IDs)
 */
function handleSetPlayingXI(array $input): void
{
    $matchId   = (int) ($input['match_id'] ?? 0);
    $teamId    = (int) ($input['team_id'] ?? 0);
    $playerIds = $input['player_ids'] ?? [];

    if (!$matchId || !$teamId) {
        jsonResponse(['error' => 'match_id and team_id are required.'], 400);
    }
    if (!is_array($playerIds) || empty($playerIds)) {
        jsonResponse(['error' => 'player_ids array is required (max 11).'], 400);
    }
    if (count($playerIds) > 12) {
        jsonResponse(['error' => 'Maximum 12 players allowed (11 + 1 substitute).'], 400);
    }

    // Cast all to int and remove duplicates
    $playerIds = array_unique(array_map('intval', $playerIds));
    $playerIds = array_values(array_filter($playerIds, fn($id) => $id > 0));

    if (count($playerIds) < 1 || count($playerIds) > 12) {
        jsonResponse(['error' => 'Playing XI must have 1-12 players.'], 400);
    }

    $db = getDB();

    // Validate match and team exist
    $stmt = $db->prepare('SELECT id, team_a_id, team_b_id FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        jsonResponse(['error' => 'Match not found.'], 404);
    }
    $matchTeamIds = [(int)$match['team_a_id'], (int)$match['team_b_id']];
    if (!in_array($teamId, $matchTeamIds)) {
        jsonResponse(['error' => 'Team is not part of this match.'], 400);
    }

    // Validate all players belong to the team
    $inPlaceholders = implode(',', array_fill(0, count($playerIds), '?'));
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM players WHERE id IN ($inPlaceholders) AND team_id = ? AND is_active = 1"
    );
    $params = array_merge($playerIds, [$teamId]);
    $stmt->execute($params);
    $validCount = (int) $stmt->fetchColumn();
    if ($validCount !== count($playerIds)) {
        jsonResponse(['error' => 'One or more players do not belong to this team or are inactive.'], 400);
    }

    $db->beginTransaction();
    try {
        // Clear existing XI for this match/team
        $stmt = $db->prepare('DELETE FROM match_playing_xi WHERE match_id = ? AND team_id = ?');
        $stmt->execute([$matchId, $teamId]);

        // Insert new XI
        $captainId = (int) ($input['captain_id'] ?? 0);
        $stmt = $db->prepare(
            'INSERT INTO match_playing_xi (match_id, team_id, player_id, is_captain) VALUES (?, ?, ?, ?)'
        );
        foreach ($playerIds as $pid) {
            $stmt->execute([$matchId, $teamId, $pid, $pid === $captainId ? 1 : 0]);
        }

        $db->commit();
        jsonResponse([
            'success'    => true,
            'message'    => 'Playing XI saved.',
            'match_id'   => $matchId,
            'team_id'    => $teamId,
            'player_ids' => $playerIds,
            'count'      => count($playerIds),
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        error_log('[API] set_playing_xi failed: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to save playing XI.'], 500);
    }
}

/**
 * Get playing XI selections for a match (optionally filtered by team).
 * Expected: match_id, team_id (optional)
 */
function handleGetPlayingXI(array $input): void
{
    $matchId = (int) ($input['match_id'] ?? 0);
    $teamId  = (int) ($input['team_id'] ?? 0);

    if (!$matchId) {
        jsonResponse(['error' => 'match_id is required.'], 400);
    }

    $db = getDB();

    if ($teamId) {
        $stmt = $db->prepare(
            'SELECT p.id, p.name, p.role, p.photo_path, mpx.is_captain
             FROM match_playing_xi mpx
             JOIN players p ON p.id = mpx.player_id
             WHERE mpx.match_id = ? AND mpx.team_id = ?
             ORDER BY p.name'
        );
        $stmt->execute([$matchId, $teamId]);
        $players = $stmt->fetchAll();
        jsonResponse(['success' => true, 'players' => $players, 'count' => count($players)]);
    } else {
        // Return XI for both teams
        $stmt = $db->prepare('SELECT team_a_id, team_b_id FROM matches WHERE id = ?');
        $stmt->execute([$matchId]);
        $match = $stmt->fetch();
        if (!$match) jsonResponse(['error' => 'Match not found.'], 404);

        $result = ['success' => true, 'teams' => []];
        foreach ([(int)$match['team_a_id'], (int)$match['team_b_id']] as $tid) {
            $stmt2 = $db->prepare(
                'SELECT p.id, p.name, p.role, p.photo_path
                 FROM match_playing_xi mpx
                 JOIN players p ON p.id = mpx.player_id
                 WHERE mpx.match_id = ? AND mpx.team_id = ?
                 ORDER BY p.name'
            );
            $stmt2->execute([$matchId, $tid]);
            $result['teams'][$tid] = $stmt2->fetchAll();
        }
        jsonResponse($result);
    }
}

// ============================================================================
// SERVER-SENT EVENTS — Real-Time Streaming
// ============================================================================

/**
 * SSE (Server-Sent Events) endpoint for real-time live state streaming.
 * Public — no auth required (read-only).
 *
 * The client connects with ?match_id=X&last_epoch=Y.
 * The server loops, checking live_state.last_updated_epoch every 1 second.
 * When it changes, it pushes the full overlay_data JSON as an SSE event.
 * The client updates its UI and reconnects with the new epoch.
 *
 * Query params:
 *   match_id   int     Required
 *   last_epoch int     Client's last known timestamp (ms). Default 0.
 */
function handleSSEStream(array $input): void
{
    $matchId   = (int) ($input['match_id'] ?? 0);
    $lastEpoch = (int) ($input['last_epoch'] ?? 0);

    // Release session lock immediately (critical: prevents blocking other requests)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    if ($matchId <= 0) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        echo "event: error\ndata: {\"error\":\"match_id is required\"}\n\n";
        flush();
        return;
    }

    // SSE headers
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    header('Access-Control-Allow-Origin: *');

    // Disable output buffering for real-time streaming
    while (ob_get_level()) {
        ob_end_clean();
    }
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', 'Off');
    @ini_set('implicit_flush', 'On');
    @ini_set('max_execution_time', '0'); // Prevent timeout on long-running SSE

    $db = getDB();

    // Send initial keepalive comment to establish connection
    echo ": connected\n\n";
    @ob_flush();
    flush();

    $maxIterations = 300; // Max ~5 minutes at 1s intervals
    $iterations    = 0;
    $startTime     = time();

    while ($iterations < $maxIterations) {
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }

        // Check if we've been running too long
        if (time() - $startTime > 300) {
            echo "event: reconnect\ndata: {\"reason\":\"timeout\"}\n\n";
            @ob_flush(); flush();
            break;
        }

        try {
            // Query live_state for this match
            $stmt = $db->prepare(
                'SELECT last_updated_epoch, total_runs, total_wickets, total_extras,
                        overs_completed, current_ball_in_over, current_over_display,
                        current_run_rate, last_ball_sequence
                 FROM live_state WHERE match_id = ?'
            );
            $stmt->execute([$matchId]);
            $state = $stmt->fetch();

            if ($state) {
                $currentEpoch = (int) $state['last_updated_epoch'];

                if ($currentEpoch > $lastEpoch) {
                    // Data has changed — push full overlay data
                    $overlayData = buildOverlayData($db, $matchId);
                    if ($overlayData) {
                        echo "id: {$currentEpoch}\n";
                        echo "event: update\n";
                        echo 'data: ' . json_encode($overlayData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                        @ob_flush(); flush();
                        $lastEpoch = $currentEpoch;
                    }
                }
            }
        } catch (\Exception $ex) {
            error_log('[SSE] Loop error: ' . $ex->getMessage());
            // Continue loop — don't kill the stream for transient DB errors
        }

        // Send heartbeat comment every cycle to keep connection alive
        echo ": heartbeat\n\n";
        @ob_flush();
        flush();

        sleep(1);
        $iterations++;
    }

    // Tell client to reconnect
    echo "event: reconnect\ndata: {\"retry\":1000}\n\n";
    @ob_flush(); flush();
}

/**
 * Build the full overlay data payload (same as get_overlay_data).
 * Used by both the REST endpoint and the SSE stream.
 */
function buildOverlayData(PDO $db, int $matchId): ?array
{
    // Fetch match info
    $stmt = $db->prepare(
        'SELECT m.*, ta.name AS team_a_name, ta.short_name AS team_a_short, ta.logo_path AS team_a_logo,
                tb.name AS team_b_name, tb.short_name AS team_b_short, tb.logo_path AS team_b_logo
         FROM matches m
         JOIN teams ta ON m.team_a_id = ta.id
         JOIN teams tb ON m.team_b_id = tb.id
         WHERE m.id = ?'
    );
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();

    if (!$match) return null;

    // Fetch live state
    $stmt = $db->prepare('SELECT * FROM live_state WHERE match_id = ?');
    $stmt->execute([$matchId]);
    $state = $stmt->fetch();

    if (!$state || ((int)$state['batting_team_id']===0 && (int)$state['bowling_team_id']===0)) {
        // Match not started or dummy live_state — return minimal data for view switching
        $ov = 'scorebug';
        $outputState = [];
        try { $outputState = readOutputState($matchId); $ov = $outputState['view'] ?? 'scorebug'; } catch (\Exception $e) {}
        $xi = ['a' => [], 'b' => []];
        try { $xi = getPlayingXIData($db, $matchId); } catch (\Exception $e) {}
        // Fetch player profile if view is player_profile
        $profileData = null;
        if ($ov === 'player_profile') {
            $ppId = (int)($outputState['player_id'] ?? 0);
            if ($ppId > 0) {
                try { $profileData = getPlayerProfileData($db, $matchId, $ppId, $match); } catch (\Exception $e) {}
                if ($profileData) $profileData['match_title'] = $match['match_title'] ?? '';
            }
        }
        return [
            'match' => [
                'id'     => (int) $match['id'],
                'title'  => $match['match_title'],
                'status' => $match['status'],
                'format' => $match['match_format'] ?? 't20i',
                'toss_won_by' => (int)($match['toss_won_by'] ?? 0),
                'toss_decision' => $match['toss_decision'] ?? '',
                'batting_first' => (int)($match['batting_first'] ?? 0),
                'team_a_id' => (int)$match['team_a_id'],
                'team_b_id' => (int)$match['team_b_id'],
                'team_a_name' => $match['team_a_name'] ?? '',
                'team_b_name' => $match['team_b_name'] ?? '',
                'team_a_short' => $match['team_a_short'] ?? '',
                'team_b_short' => $match['team_b_short'] ?? '',
                'team_a_logo' => $match['team_a_logo'] ?? '',
                'team_b_logo' => $match['team_b_logo'] ?? '',
                'match_logo' => $match['match_logo'] ?? '',
                'location'   => $match['location'] ?? '',
            ],
            'batting_team' => null,
            'bowling_team' => null,
            'match_not_started' => true,
            'output_view' => $ov,
            'batting_card' => [],
            'bowling_card' => [],
            'playing_xi' => $xi,
            'match_summary' => [],
            'player_profile' => $profileData,
        ];
    }

    $battingTeamId = (int) $state['batting_team_id'];
    $bowlingTeamId = (int) $state['bowling_team_id'];

    $battingTeam = ($battingTeamId === (int) $match['team_a_id'])
        ? ['id' => (int) $match['team_a_id'], 'name' => $match['team_a_name'],
           'short_name' => $match['team_a_short'], 'logo' => $match['team_a_logo']]
        : ['id' => (int) $match['team_b_id'], 'name' => $match['team_b_name'],
           'short_name' => $match['team_b_short'], 'logo' => $match['team_b_logo']];

    $bowlingTeam = ($bowlingTeamId === (int) $match['team_a_id'])
        ? ['id' => (int) $match['team_a_id'], 'name' => $match['team_a_name'],
           'short_name' => $match['team_a_short'], 'logo' => $match['team_a_logo']]
        : ['id' => (int) $match['team_b_id'], 'name' => $match['team_b_name'],
           'short_name' => $match['team_b_short'], 'logo' => $match['team_b_logo']];

    $battingTeam['runs']    = (int) $state['total_runs'];
    $battingTeam['wickets'] = (int) $state['total_wickets'];
    $battingTeam['overs']   = $state['current_over_display'];
    $battingTeam['extras']  = (int) $state['total_extras'];

    $inningsNumber = (int) $state['innings_number'];
    $striker    = playerSummary($db, (int) $state['striker_id'], $matchId, $inningsNumber);
    $nonStriker = playerSummary($db, (int) $state['non_striker_id'], $matchId, $inningsNumber);
    $bowler     = bowlerSummary($db, (int) $state['bowler_id'], $matchId, $inningsNumber);

    // Last 5 balls
    $stmtBalls = $db->prepare(
        'SELECT runs_scored, extra_type, is_wicket, wicket_type, over_display, extra_runs
         FROM ball_timeline
         WHERE match_id = ? AND innings_number = ?
         ORDER BY sequence_id DESC
         LIMIT 5'
    );
    $stmtBalls->execute([$matchId, $inningsNumber]);
    $lastBallsRaw = $stmtBalls->fetchAll();
    $lastBalls = [];
    foreach ($lastBallsRaw as $ball) {
        $display = '';
        if ($ball['is_wicket']) {
            $display = 'W';
        } elseif ($ball['extra_type'] === 'wd') {
            $display = 'WD';
        } elseif ($ball['extra_type'] === 'nb') {
            $display = 'NB' . ($ball['runs_scored'] > 0 ? '+' . $ball['runs_scored'] : '');
        } elseif ($ball['extra_type'] === 'lb') {
            $display = 'LB' . $ball['extra_runs'];
        } elseif ($ball['extra_type'] === 'by') {
            $display = 'B' . $ball['extra_runs'];
        } else {
            $display = (string) $ball['runs_scored'];
        }
        $lastBalls[] = [
            'runs'    => (int) $ball['runs_scored'],
            'extra'   => $ball['extra_type'],
            'wicket'  => (bool) $ball['is_wicket'],
            'display' => $display,
        ];
    }
    $lastBalls = array_reverse($lastBalls);

    // This-over summary
    $currentOverNum = (int) $state['overs_completed'];
    // When current_ball_in_over=0, the over just completed — use previous over number
    // otherwise the 6th ball's over_display won't match the incremented overs_completed
    if ((int) $state['current_ball_in_over'] === 0 && $currentOverNum > 0) {
        $currentOverNum--;
    }
    $stmtOver = $db->prepare(
        'SELECT SUM(runs_scored + extra_runs) AS over_runs, COUNT(*) AS balls_bowled
         FROM ball_timeline
         WHERE match_id = ? AND innings_number = ?
         AND over_display LIKE ?'
    );
    $stmtOver->execute([$matchId, $inningsNumber, $currentOverNum . '.%']);
    $thisOver = $stmtOver->fetch();

    // This-over individual balls (for 6-dot tracker display)
    $stmtOverBalls = $db->prepare(
        'SELECT runs_scored, extra_type, is_wicket, extra_runs
         FROM ball_timeline
         WHERE match_id = ? AND innings_number = ?
         AND over_display LIKE ?
         ORDER BY sequence_id ASC'
    );
    $stmtOverBalls->execute([$matchId, $inningsNumber, $currentOverNum . '.%']);
    $thisOverBallsRaw = $stmtOverBalls->fetchAll();
    $thisOverBalls = [];
    foreach ($thisOverBallsRaw as $ball) {
        $display = '';
        if ($ball['is_wicket']) {
            $display = 'W';
        } elseif ($ball['extra_type'] === 'wd') {
            $display = 'WD';
        } elseif ($ball['extra_type'] === 'nb') {
            $display = 'NB' . ($ball['runs_scored'] > 0 ? '+' . $ball['runs_scored'] : '');
        } elseif ($ball['extra_type'] === 'lb') {
            $display = 'LB' . $ball['extra_runs'];
        } elseif ($ball['extra_type'] === 'by') {
            $display = 'B' . $ball['extra_runs'];
        } else {
            $display = (string) $ball['runs_scored'];
        }
        $thisOverBalls[] = [
            'runs'    => (int) $ball['runs_scored'],
            'extra'   => $ball['extra_type'],
            'wicket'  => (bool) $ball['is_wicket'],
            'display' => $display,
        ];
    }

    // Read output view state safely
    $outputView = 'scorebug';
    try { $outputView = readOutputState($matchId)['view'] ?? 'scorebug'; } catch (\Exception $e) { $outputView = 'scorebug'; }

    $data = [
        'match' => [
            'id'     => (int) $match['id'],
            'title'  => $match['match_title'],
            'status' => $match['status'],
            'format' => $match['match_format'] ?? 't20i',
            'toss_won_by' => (int)($match['toss_won_by'] ?? 0),
            'toss_decision' => $match['toss_decision'] ?? '',
            'batting_first' => (int)($match['batting_first'] ?? 0),
            'team_a_id' => (int)$match['team_a_id'],
            'team_b_id' => (int)$match['team_b_id'],
            'team_a_name' => $match['team_a_name'] ?? '',
            'team_b_name' => $match['team_b_name'] ?? '',
            'team_a_short' => $match['team_a_short'] ?? '',
            'team_b_short' => $match['team_b_short'] ?? '',
            'team_a_logo' => $match['team_a_logo'] ?? '',
            'team_b_logo' => $match['team_b_logo'] ?? '',
            'match_logo' => $match['match_logo'] ?? '',
            'location'   => $match['location'] ?? '',
        ],
        'batting_team'      => $battingTeam,
        'bowling_team'      => $bowlingTeam,
        'striker'           => $striker,
        'non_striker'       => $nonStriker,
        'bowler'            => $bowler,
        'current_run_rate'  => (float) $state['current_run_rate'],
        'innings_number'    => (int) $state['innings_number'],
        'target'              => (int) ($state['target'] ?? 0),
        'max_overs'           => (int) ($state['max_overs'] ?? 20),
        'is_super_over'       => (int) ($state['is_super_over'] ?? 0),
        'overs_completed'     => (int) $state['overs_completed'],
        'current_ball_in_over'=> (int) $state['current_ball_in_over'],
        'last_5_balls'        => $lastBalls,
        'this_over'           => [
            'runs'  => (int) ($thisOver['over_runs'] ?? 0),
            'balls' => (int) ($thisOver['balls_bowled'] ?? 0),
        ],
        'this_over_balls'     => $thisOverBalls,
        'sequence_id'       => (int) $state['last_ball_sequence'],
        'last_updated_epoch'=> (int) $state['last_updated_epoch'],
        'output_view'       => $outputView,
    ];

    // Card/summary data — only fetch when output view needs it
    $cardViews = ['batting','bowling','xi','xi_bat','xi_bowl','summary',
        'batting_1st','batting_2nd','batting_so','bowling_1st','bowling_2nd','bowling_so',
        'xi_bat_1st','xi_bat_2nd','xi_bowl_1st','xi_bowl_2nd',
        'summary_1st','summary_2nd','summary_so'];
    if (in_array($outputView, $cardViews)) {
        // Determine which innings to fetch based on view suffix
        $viewInnings = $inningsNumber;
        $isSpecificView = false;
        if (str_ends_with($outputView, '_1st')) { $viewInnings = 1; $isSpecificView = true; }
        elseif (str_ends_with($outputView, '_2nd')) { $viewInnings = 2; $isSpecificView = true; }
        elseif (str_ends_with($outputView, '_so')) { $viewInnings = 99; $isSpecificView = true; }

        if ($isSpecificView) {
            // Recalculate batting/bowling teams for the requested innings
            $viewBattingTeamId = (int)$match['batting_first'];
            if ($viewInnings === 2) {
                $viewBattingTeamId = ($viewBattingTeamId === (int)$match['team_a_id']) ? (int)$match['team_b_id'] : (int)$match['team_a_id'];
            }
            $viewBowlingTeamId = ($viewBattingTeamId === (int)$match['team_a_id']) ? (int)$match['team_b_id'] : (int)$match['team_a_id'];

            $btView = ($viewBattingTeamId === (int)$match['team_a_id'])
                ? ['id' => (int)$match['team_a_id'], 'name' => $match['team_a_name'], 'short_name' => $match['team_a_short'], 'logo' => $match['team_a_logo']]
                : ['id' => (int)$match['team_b_id'], 'name' => $match['team_b_name'], 'short_name' => $match['team_b_short'], 'logo' => $match['team_b_logo']];
            $bltView = ($viewBowlingTeamId === (int)$match['team_a_id'])
                ? ['id' => (int)$match['team_a_id'], 'name' => $match['team_a_name'], 'short_name' => $match['team_a_short'], 'logo' => $match['team_a_logo']]
                : ['id' => (int)$match['team_b_id'], 'name' => $match['team_b_name'], 'short_name' => $match['team_b_short'], 'logo' => $match['team_b_logo']];

            $stmtAgg = $db->prepare('SELECT SUM(runs_scored + extra_runs) AS runs, SUM(CASE WHEN is_wicket=1 THEN 1 ELSE 0 END) AS wkts, SUM(extra_runs) AS extras, COUNT(CASE WHEN is_legal_ball=1 THEN 1 END) AS lb FROM ball_timeline WHERE match_id=? AND innings_number=?');
            $stmtAgg->execute([$matchId, $viewInnings]);
            $agg = $stmtAgg->fetch();
            $lb = (int)($agg['lb'] ?? 0);
            $btView['runs'] = (int)($agg['runs'] ?? 0);
            $btView['wickets'] = (int)($agg['wkts'] ?? 0);
            $btView['extras'] = (int)($agg['extras'] ?? 0);
            $btView['overs'] = intdiv($lb, 6) . '.' . ($lb % 6);

            $data['batting_team'] = $btView;
            $data['bowling_team'] = $bltView;
            $data['innings_number'] = $viewInnings;
            // Clear striker/non-striker/bowler for historical innings views
            $data['striker'] = null;
            $data['non_striker'] = null;
            $data['bowler'] = null;
        }

        try { $data['batting_card']  = getBattingCard($db, $matchId, $viewInnings); } catch (\Exception $e) { $data['batting_card'] = []; }
        try { $data['bowling_card']  = getBowlingCard($db, $matchId, $viewInnings); } catch (\Exception $e) { $data['bowling_card'] = []; }
        try { $data['playing_xi']    = getPlayingXIData($db, $matchId); } catch (\Exception $e) { $data['playing_xi'] = ['a'=>[],'b'=>[]]; }
    } else {
        $data['batting_card'] = [];
        $data['bowling_card'] = [];
        $data['playing_xi'] = ['a' => [], 'b' => []];
    }
        try { $data['match_summary'] = getMatchSummaryData($db, $matchId); } catch (\Exception $e) { $data['match_summary'] = []; }

    // Player profile data — fetched when output_view is player_profile
    if ($outputView === 'player_profile') {
        $profilePlayerId = 0;
        try { $profilePlayerId = (int)(readOutputState($matchId)['player_id'] ?? 0); } catch (\Exception $e) {}
        if ($profilePlayerId > 0) {
            $data['player_profile'] = getPlayerProfileData($db, $matchId, $profilePlayerId, $match);
        } else {
            $data['player_profile'] = null;
        }
        // Also include match title
        if ($data['player_profile']) {
            $data['player_profile']['match_title'] = $match['match_title'] ?? '';
        }
    }

    return $data;
}

/**
 * Get batting card for current innings (compact, for overlay views).
 */
function getBattingCard(PDO $db, int $matchId, int $inningsNumber): array
{
    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.role, p.photo_path, p.batting_style, p.bowling_style,
                bi.runs_scored, bi.balls_faced, bi.fours, bi.sixes,
                bi.dismissal_type, bi.batting_position
         FROM batsman_innings bi
         JOIN players p ON p.id = bi.batsman_id
         WHERE bi.match_id = ? AND bi.innings_number = ?
         ORDER BY bi.batting_position ASC'
    );
    $stmt->execute([$matchId, $inningsNumber]);
    $rows = $stmt->fetchAll();
    $card = [];
    foreach ($rows as $r) {
        $card[] = [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'role'          => $r['role'],
            'photo'         => $r['photo_path'],
            'batting_style' => $r['batting_style'] ?? '',
            'bowling_style' => $r['bowling_style'] ?? '',
            'runs'          => (int) $r['runs_scored'],
            'balls'         => (int) $r['balls_faced'],
            'fours'         => (int) $r['fours'],
            'sixes'         => (int) $r['sixes'],
            'sr'            => $r['balls_faced'] > 0 ? round(($r['runs_scored'] / $r['balls_faced']) * 100, 1) : 0,
            'dismissal'     => $r['dismissal_type'],
            'pos'           => (int) $r['batting_position'],
        ];
    }
    return $card;
}

/**
 * Get bowling card for current innings (compact, for overlay views).
 */
function getBowlingCard(PDO $db, int $matchId, int $inningsNumber): array
{
    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.photo_path, p.batting_style, p.bowling_style, p.role,
                bs.overs_bowled, bs.maidens, bs.runs_conceded, bs.wickets_taken
         FROM bowler_spells bs
         JOIN players p ON p.id = bs.bowler_id
         WHERE bs.match_id = ? AND bs.innings_number = ?
         ORDER BY bs.id ASC'
    );
    $stmt->execute([$matchId, $inningsNumber]);
    $rows = $stmt->fetchAll();
    $card = [];
    foreach ($rows as $r) {
        $card[] = [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'photo'         => $r['photo_path'],
            'role'          => $r['role'] ?? '',
            'batting_style' => $r['batting_style'] ?? '',
            'bowling_style' => $r['bowling_style'] ?? '',
            'overs'         => formatOvers($r['overs_bowled'] ?? 0),
            'maidens'       => (int) ($r['maidens'] ?? 0),
            'runs'          => (int) ($r['runs_conceded'] ?? 0),
            'wickets'       => (int) ($r['wickets_taken'] ?? 0),
            'econ'          => oversToFloat($r['overs_bowled'] ?? 0) > 0
                ? round(($r['runs_conceded'] ?? 0) / oversToFloat($r['overs_bowled'] ?? 0), 1)
                : 0,
        ];
    }
    return $card;
}

/**
 * Get playing XI data for both teams.
 */
function getPlayingXIData(PDO $db, int $matchId): array
{
    $stmt = $db->prepare('SELECT team_a_id, team_b_id FROM matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) return ['a' => [], 'b' => []];

    $result = ['a' => [], 'b' => []];
    foreach (['a' => (int)$match['team_a_id'], 'b' => (int)$match['team_b_id']] as $key => $teamId) {
            $stmt = $db->prepare(
                'SELECT p.id, p.name, p.role, p.photo_path, p.batting_style, p.bowling_style, p.age, p.school_entry_year, p.achievements, mxi.is_captain
                 FROM match_playing_xi mxi
                 JOIN players p ON p.id = mxi.player_id
                 WHERE mxi.match_id = ? AND mxi.team_id = ?
                 ORDER BY mxi.id ASC'
        );
        $stmt->execute([$matchId, $teamId]);
        $players = $stmt->fetchAll();
        foreach ($players as $i => $p) {
            $result[$key][] = [
                'id'    => (int) $p['id'],
                'name'  => $p['name'],
                'role'  => $p['role'],
                'photo' => $p['photo_path'],
                'age'   => $p['age'] ? (int) $p['age'] : null,
                'school_entry_year' => $p['school_entry_year'] ?? '',
                'achievements'      => $p['achievements'] ?? '',
                'batting_style' => $p['batting_style'] ?? '',
                'bowling_style' => $p['bowling_style'] ?? '',
                'pos'   => $i + 1,
                'is_captain' => (bool) ($p['is_captain'] ?? false),
            ];
        }
    }
    return $result;
}

/**
 * Get match summary (both innings totals + result).
 */
function getMatchSummaryData(PDO $db, int $matchId): array
{
    $stmt = $db->prepare('SELECT m.*, ta.short_name AS team_a_short, ta.name AS team_a_name, ta.logo_path AS team_a_logo,
            tb.short_name AS team_b_short, tb.name AS team_b_name, tb.logo_path AS team_b_logo
            FROM matches m JOIN teams ta ON m.team_a_id=ta.id JOIN teams tb ON m.team_b_id=tb.id WHERE m.id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) return [];

    $innings = [];
    $innNumbers = [1, 2];
    // Also check for super over
    $stmtSO = $db->prepare('SELECT COUNT(*) FROM ball_timeline WHERE match_id = ? AND innings_number = 99');
    $stmtSO->execute([$matchId]);
    if ((int) $stmtSO->fetchColumn() > 0) $innNumbers[] = 99;
    foreach ($innNumbers as $inn) {
        $stmt = $db->prepare(
            'SELECT SUM(runs_scored + extra_runs) AS total_runs,
                    SUM(CASE WHEN is_wicket = 1 THEN 1 ELSE 0 END) AS total_wickets,
                    SUM(extra_runs) AS total_extras,
                    COUNT(CASE WHEN is_legal_ball = 1 THEN 1 END) AS legal_balls
             FROM ball_timeline WHERE match_id = ? AND innings_number = ?'
        );
        $stmt->execute([$matchId, $inn]);
        $agg = $stmt->fetch();
        if (!$agg || $agg['total_runs'] === null) continue;
        $lb = (int)($agg['legal_balls'] ?? 0);

        // Determine which team batted this innings
        $btId = (int)($match['batting_first'] ?? 0);
        $innTeamId = ($inn === 2) ? (($btId === (int)$match['team_a_id']) ? (int)$match['team_b_id'] : (int)$match['team_a_id']) : $btId;
        $isTeamA = ($innTeamId === (int)$match['team_a_id']);
        $innTeamName = $isTeamA ? ($match['team_a_name'] ?? 'Team A') : ($match['team_b_name'] ?? 'Team B');
        $innTeamShort = $isTeamA ? ($match['team_a_short'] ?? $innTeamName) : ($match['team_b_short'] ?? $innTeamName);
        $innTeamLogo = $isTeamA ? ($match['team_a_logo'] ?? '') : ($match['team_b_logo'] ?? '');

        $innings[] = [
            'number'      => $inn,
            'runs'        => (int)$agg['total_runs'],
            'wickets'     => (int)$agg['total_wickets'],
            'extras'      => (int)($agg['total_extras'] ?? 0),
            'overs'       => intdiv($lb, 6) . '.' . ($lb % 6),
            'team_name'   => $innTeamName,
            'team_short'  => $innTeamShort,
            'team_logo'   => $innTeamLogo,
            'batting_card'=> getBattingCard($db, $matchId, $inn),
            'bowling_card'=> getBowlingCard($db, $matchId, $inn),
        ];
    }

    $result = '';
    $winnerName = '';
    if ($match['status'] === 'completed' && count($innings) >= 2) {
        $t1 = $innings[0]['runs'];
        $t2 = $innings[1]['runs'];
        $w2 = $innings[1]['wickets'];
        $target = (int)($match['target'] ?? 0);
        if ($t2 >= $target) {
            $result = 'won by ' . (10 - $w2) . ' wicket' . ($w2 !== 9 ? 's' : '');
        } elseif ($t2 === ($target - 1)) {
            $result = 'Match Tied';
        } else {
            $result = 'won by ' . (($target - 1) - $t2) . ' run' . (($target - 1 - $t2) !== 1 ? 's' : '');
        }
        if ($match['winner_id']) {
            $stmtW = $db->prepare('SELECT short_name, name FROM teams WHERE id = ?');
            $stmtW->execute([(int)$match['winner_id']]);
            $w = $stmtW->fetch();
            $winnerName = $w ? ($w['short_name'] ?: $w['name']) : '';
        }
    }

    return [
        'title'     => $match['match_title'],
        'status'    => $match['status'],
        'innings'   => $innings,
        'result'    => $result,
        'winner'    => $winnerName,
        'target'    => (int)($match['target'] ?? 0),
    ];
}

/**
 * Convert cricket decimal overs (e.g., 5.3 = 5 overs + 3 balls) to real float.
 * 5.3 → 5 + 3/6 = 5.5.  Used for economy / strike rate calculations.
 */
function oversToFloat($decimalOvers): float
{
    $decimalOvers = (float) $decimalOvers;
    $completed = floor($decimalOvers);
    $balls = (int) round(($decimalOvers - $completed) * 10);
    if ($balls >= 10) { $completed++; $balls -= 10; } // carry-over sanity
    if ($balls > 6)  { $balls = 6; }                   // max 6 balls
    return $completed + ($balls / 6.0);
}

/**
 * Format decimal overs for display (e.g., 1.6000001 → "1.6").
 * Always returns clean "O.B" format with no floating-point artifacts.
 */
function formatOvers($decimalOvers): string
{
    $decimalOvers = (float) $decimalOvers;
    $completed = floor($decimalOvers);
    $balls = (int) round(($decimalOvers - $completed) * 10);
    if ($balls >= 10) { $completed++; $balls -= 10; }
    if ($balls > 6)  { $balls = 6; }
    return $completed . '.' . $balls;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if a player belongs to a given team.
 */
function playerInTeam(PDO $db, int $playerId, int $teamId): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM players WHERE id = ? AND team_id = ? AND is_active = 1');
    $stmt->execute([$playerId, $teamId]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Get a compact player summary including innings stats.
 */
function playerSummary(PDO $db, int $playerId, int $matchId, int $inningsNumber): ?array
{
    if ($playerId <= 0) return null;

    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.role, p.photo_path,
                bi.runs_scored, bi.balls_faced, bi.fours, bi.sixes, bi.dismissal_type
         FROM players p
         LEFT JOIN batsman_innings bi ON bi.batsman_id = p.id
             AND bi.match_id = ? AND bi.innings_number = ?
         WHERE p.id = ?'
    );
    $stmt->execute([$matchId, $inningsNumber, $playerId]);
    $row = $stmt->fetch();

    if (!$row) return null;

    return [
        'id'              => (int) $row['id'],
        'name'            => $row['name'],
        'role'            => $row['role'],
        'photo'           => $row['photo_path'],
        'runs'            => (int) ($row['runs_scored'] ?? 0),
        'balls'           => (int) ($row['balls_faced'] ?? 0),
        'fours'           => (int) ($row['fours'] ?? 0),
        'sixes'           => (int) ($row['sixes'] ?? 0),
        'dismissal_type'  => $row['dismissal_type'],
        'strike_rate'     => ($row['balls_faced'] > 0)
            ? round(($row['runs_scored'] / $row['balls_faced']) * 100, 1)
            : 0.0,
    ];
}

/**
 * Get bowler figures for the current innings.
 */
function bowlerSummary(PDO $db, int $bowlerId, int $matchId, int $inningsNumber): ?array
{
    if ($bowlerId <= 0) return null;

    $stmt = $db->prepare(
        'SELECT p.id, p.name, p.photo_path,
                bs.overs_bowled, bs.maidens, bs.runs_conceded, bs.wickets_taken
         FROM players p
         LEFT JOIN bowler_spells bs ON bs.bowler_id = p.id
             AND bs.match_id = ? AND bs.innings_number = ?
         WHERE p.id = ?'
    );
    $stmt->execute([$matchId, $inningsNumber, $bowlerId]);
    $row = $stmt->fetch();

    if (!$row) return null;

    return [
        'id'             => (int) $row['id'],
        'name'           => $row['name'],
        'photo'          => $row['photo_path'],
        'overs_bowled'   => formatOvers($row['overs_bowled'] ?? 0),
        'maidens'        => (int) ($row['maidens'] ?? 0),
        'runs_conceded'  => (int) ($row['runs_conceded'] ?? 0),
        'wickets_taken'  => (int) ($row['wickets_taken'] ?? 0),
        'economy'        => (oversToFloat($row['overs_bowled'] ?? 0) > 0)
            ? round(($row['runs_conceded'] ?? 0) / oversToFloat($row['overs_bowled'] ?? 0), 1)
            : 0.0,
    ];
}

/**
 * Get player profile data for the broadcast overlay.
 * Returns player info from the players table (general details, not match stats).
 */
function getPlayerProfileData(PDO $db, int $matchId, int $playerId, array $match): ?array
{
    $stmt = $db->prepare('SELECT p.*, t.name AS team_name, t.short_name AS team_short, t.logo_path AS team_logo
        FROM players p JOIN teams t ON p.team_id = t.id
        WHERE p.id = ? AND p.is_active = 1');
    $stmt->execute([$playerId]);
    $player = $stmt->fetch();
    if (!$player) return null;

    return [
        'id' => (int)$player['id'],
        'name' => $player['name'],
        'role' => $player['role'] ?? 'player',
        'batting_style' => $player['batting_style'] ?? '',
        'bowling_style' => $player['bowling_style'] ?? '',
        'photo' => $player['photo_path'] ?? '',
        'age' => $player['age'] ?? null,
        'school' => $player['school_entry_year'] ?? '',
        'achievements' => $player['achievements'] ?? '',
        'team_name' => $player['team_name'],
        'team_short' => $player['team_short'],
        'team_logo' => $player['team_logo'],
        'is_team_a' => ((int)$player['team_id'] === (int)$match['team_a_id']),
    ];
}
