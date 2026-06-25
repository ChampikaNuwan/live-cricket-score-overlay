<?php
/**
 * ============================================================================
 * config.php — Secure PDO Database Connection & Session Configuration
 * Live Cricket Scoreboard & Broadcast Overlay System
 * ============================================================================
 * Establishes a singleton PDO connection with hardened security defaults,
 * enforces HttpOnly + Secure session flags, and provides helper constants.
 * ============================================================================
 */

// Load .env file if it exists (for environment-specific config)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Remove surrounding quotes if present
            if ((str_starts_with($val, "'") && str_ends_with($val, "'")) ||
                (str_starts_with($val, '"') && str_ends_with($val, '"'))) {
                $val = substr($val, 1, -1);
            }
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

// Production error handling — suppress display to prevent JSON breakage
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ============================================================================
// Database Configuration

// ---------------------------------------------------------------------------
// Environment / Deployment Constants
// ---------------------------------------------------------------------------
define('APP_ENV', 'production');           // 'development' | 'production'
define('APP_ROOT', __DIR__);
define('ASSETS_DIR', APP_ROOT . '/assets');
define('LOGOS_DIR',  ASSETS_DIR . '/logos');
define('PHOTOS_DIR', ASSETS_DIR . '/photos');
define('MAX_UPLOAD_BYTES', 500 * 1024 * 1024); // 500 MB
define('ALLOWED_IMAGE_MIME', ['image/png', 'image/jpeg']);

// ---------------------------------------------------------------------------
// Database Configuration
// ---------------------------------------------------------------------------
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql'); // 'mysql' or 'pgsql'
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: (DB_DRIVER === 'pgsql' ? '5432' : '8889'));
define('DB_NAME', getenv('DB_NAME') ?: 'cricket_live');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
define('DB_CHARSET', 'utf8');
define('DB_ENGINE', DB_DRIVER === 'pgsql' ? 'pgsql' : 'mysql');

// ---------------------------------------------------------------------------
// Session Security Configuration
// ---------------------------------------------------------------------------
ini_set('session.cookie_httponly', 1);          // Prevent JS access to session cookie
ini_set('session.cookie_secure',   1);          // Only transmit over HTTPS
ini_set('session.cookie_samesite', 'Lax');      // CSRF mitigation
ini_set('session.use_strict_mode', 1);          // Prevent session fixation
ini_set('session.use_only_cookies', 1);         // Never pass session in URL
ini_set('session.gc_maxlifetime', 86400);       // 24-hour session lifetime
ini_set('session.cookie_lifetime', 86400);

// For local dev over plain HTTP, relax Secure flag
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
if (!$isHttps || APP_ENV === 'development') {
    ini_set('session.cookie_secure', 0);
}

// Start the session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_name('CRICKET_SID');
    session_start();
}

// 24-hour session expiry from login time
if (!empty($_SESSION['user_id']) && !empty($_SESSION['login_time'])) {
    if (time() - $_SESSION['login_time'] > 86400) {
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION = [];
        // Don't redirect here — each page handles redirect via auth gate
    }
}

// Regenerate session ID periodically to mitigate session hijacking
if (!isset($_SESSION['_regenerated_at']) || (time() - $_SESSION['_regenerated_at']) > 300) {
    session_regenerate_id(true);
    $_SESSION['_regenerated_at'] = time();
}

// ---------------------------------------------------------------------------
// PDO Singleton — Lazy-initialised, persistent connection
// ---------------------------------------------------------------------------

/**
 * Returns a singleton PDO instance with hardened defaults.
 *
 * @return PDO
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $isPgsql = (DB_ENGINE === 'pgsql');
        $dsn = $isPgsql
            ? sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];
        if (!$isPgsql) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if ($isPgsql) {
                // PG fallback
                try {
                    $dsn2 = sprintf('pgsql:host=%s;dbname=%s', DB_HOST, DB_NAME);
                    $pdo = new PDO($dsn2, DB_USER, DB_PASS, $options);
                } catch (PDOException $e2) {
                    pgConnError($e, $e2);
                }
            } else {
                // MySQL fallbacks
                try {
                    $dsnSock = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
                    $pdo = new PDO($dsnSock, DB_USER, DB_PASS, $options);
                } catch (PDOException $e2) {
                    try {
                        $dsnTcp = sprintf('mysql:host=127.0.0.1;port=%s;dbname=%s;charset=%s', DB_PORT, DB_NAME, DB_CHARSET);
                        $pdo = new PDO($dsnTcp, DB_USER, DB_PASS, $options);
                    } catch (PDOException $e3) {
                        myConnError($e, $e2, $e3);
                    }
                }
            }
        }
    }

    return $pdo;
}

function pgConnError(PDOException $e1, PDOException $e2): void {
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('[CONFIG] PDO pgsql: ' . $e1->getMessage() . ' | ' . $e2->getMessage());
    echo json_encode(['error' => 'Database connection failed.', 'hint' => 'Check Supabase credentials in DB_HOST/DB_PORT/DB_USER/DB_PASS.']);
    exit;
}

function myConnError(PDOException $e1, PDOException $e2, PDOException $e3): void {
    error_log('[CONFIG] PDO (localhost): ' . $e1->getMessage());
    error_log('[CONFIG] PDO (socket): ' . $e2->getMessage());
    error_log('[CONFIG] PDO (127.0.0.1): ' . $e3->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    $msg = $e1->getMessage();
    $hint = 'Database connection failed.';
    if (strpos($msg, 'Connection refused') !== false) $hint = 'MySQL not reachable. Check DB_HOST/DB_PORT.';
    elseif (strpos($msg, 'Access denied') !== false) $hint = 'Wrong DB_USER/DB_PASS.';
    elseif (strpos($msg, 'Unknown database') !== false) $hint = 'Database "' . DB_NAME . '" does not exist.';
    elseif (strpos($msg, 'could not find driver') !== false) $hint = 'PHP pdo_mysql extension missing.';
    echo json_encode(['error' => $hint]);
    exit;
}

// ---------------------------------------------------------------------------
// Security Helper Functions
// ---------------------------------------------------------------------------

/**
 * Verify the current user is authenticated and (optionally) holds a required role.
 * Exits with 401/403 JSON if checks fail — used by api.php and admin panels.
 *
 * @param string|null $requiredRole  'super_admin' | 'scorer' | null (any authenticated)
 * @return array{id:int, username:string, role:string}  The authenticated user row
 */
function requireAuth(?string $requiredRole = null): array
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Authentication required.']);
        exit;
    }

    $user = [
        'id'       => (int) $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ];

    // Validate session data is complete
    if (empty($user['role'])) {
        // Re-fetch from database
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, username, role FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$user['id']]);
        $row  = $stmt->fetch();

        if (!$row) {
            session_destroy();
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session invalid. Please log in again.']);
            exit;
        }

        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        $user['username']     = $row['username'];
        $user['role']         = $row['role'];
    }

    // Role-based access control (BOLA/IDOR prevention)
    if ($requiredRole !== null && $user['role'] !== $requiredRole) {
        // super_admin and company_admin inherit scorer permissions
        if ($requiredRole === 'scorer' && in_array($user['role'], ['super_admin', 'company_admin'], true)) {
            // Allowed
        } else {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Insufficient permissions.']);
            exit;
        }
    }

    return $user;
}

/**
 * Generate a cryptographically random filename for uploads.
 *
 * @param string $extension  e.g. '.png'
 * @return string  Random hex filename with extension
 */
function randomFilename(string $extension): string
{
    return bin2hex(random_bytes(20)) . $extension;
}

/**
 * Validate an uploaded image file against MIME and size constraints.
 *
 * @param array $file  Single $_FILES element
 * @return string|null  Error message or null if valid
 */
function validateImageUpload(array $file): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        return $errors[$file['error']] ?? 'Unknown upload error.';
    }

    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return 'File exceeds maximum size of 500 MB.';
    }

    // Validate MIME via finfo (trusts actual content, not client-supplied type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_MIME, true)) {
        return 'Invalid file type. Only PNG and JPEG images are allowed.';
    }

    return null; // Valid
}

/**
 * Send a JSON response and exit.
 *
 * @param mixed  $data        Data to encode
 * @param int    $statusCode  HTTP status code (default 200)
 */
function jsonResponse($data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
