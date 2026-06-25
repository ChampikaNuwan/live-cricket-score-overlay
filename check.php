<?php
// ============================================================
// check.php — CricketLive Environment Diagnostic
// Upload to hosting and open in browser
// ============================================================
$status = [];
$error = false;

function result($label, $ok, $detail = '') {
    global $status;
    $status[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    if (!$ok) global $error; $error = true;
}

// PHP Version
$phpVer = PHP_VERSION;
result('PHP Version', version_compare($phpVer, '7.4', '>='), $phpVer);

// Extensions
$exts = ['pdo', 'pdo_mysql', 'json', 'session', 'mbstring', 'openssl'];
foreach ($exts as $ext) {
    result("Extension: $ext", extension_loaded($ext));
}

// MySQL connection test
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'cricket_live';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'root';

result('DB_HOST', true, "$host:$port");
result('DB_NAME', true, $name);
result('DB_USER', true, $user);

$pdo = null;
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    result('PDO Connection', true, 'localhost (socket)');
} catch (PDOException $e) {
    result('PDO Connection (localhost)', false, $e->getMessage());
    try {
        $dsnTcp = "mysql:host=127.0.0.1;port=$port;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsnTcp, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        result('PDO Connection (127.0.0.1)', true, 'Connected OK! Use this in config.php');
    } catch (PDOException $e2) {
        result('PDO Connection (127.0.0.1)', false, $e2->getMessage());
    }
}

// Check tables if connected
if ($pdo) {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $tables = $driver === 'pgsql'
            ? $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN)
            : $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        result('Database tables exist', in_array('users', $tables), 'Tables: ' . implode(', ', $tables));

        if (in_array('users', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            result('Users in table', $count > 0, "Found $count user(s)");
            if ($count > 0) {
                $admin = $pdo->prepare("SELECT username, role FROM users WHERE username = ?");
                $admin->execute(['admin']);
                $a = $admin->fetch();
                if ($a) {
                    result('Admin account', true, "User: {$a['username']}, Role: {$a['role']}");
                } else {
                    result('Admin account', false, 'No user named "admin" found. Run database.sql import.');
                }
            } else {
                result('Users needed', false, 'Users table is empty. Import database.sql');
            }
        }
    } catch (Exception $e) {
        result('Query Error', false, $e->getMessage());
    }
}

// Write permissions
$dirs = ['assets', 'assets/logos', 'assets/photos'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    result("Write: $dir", is_writable($dir));
}

// Session test
$sid = session_id();
result('Session active', !empty($sid));
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>CricketLive — Diagnostic</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:#050914;color:#f1f5f9;padding:40px 20px;max-width:700px;margin:0 auto}
h1{font-size:22px;margin-bottom:4px}
p{font-size:12px;color:#94a3b8;margin-bottom:24px}
table{width:100%;border-collapse:collapse}
td{padding:8px 12px;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.06)}
td:first-child{font-weight:600;width:200px}
.ok{color:#22c55e}.fail{color:#ef4444}.dim{color:#64748b;font-size:11px}
.fix{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);border-radius:8px;padding:12px 16px;margin-top:20px;font-size:13px}
.fix strong{color:#f97316}
</style></head><body>
<h1>&#128295; CricketLive Diagnostic</h1>
<p>Uploaded: <?= date('Y-m-d H:i:s') ?> &middot; <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></p>
<table>
<?php foreach ($status as $s): ?>
<tr>
  <td><?= htmlspecialchars($s['label']) ?></td>
  <td class="<?= $s['ok'] ? 'ok' : 'fail' ?>"><?= $s['ok'] ? '&#10003;' : '&#10007;' ?></td>
  <td class="dim"><?= htmlspecialchars($s['detail']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($error): ?>
<div class="fix">
  <strong>&#9888; Issues Found</strong><br>
  <strong>DB Connection failed?</strong> — Check these in <code>config.php</code>:<br>
  &bull; <code>DB_HOST</code> — usually <code>localhost</code> (not <code>127.0.0.1</code>)<br>
  &bull; <code>DB_PORT</code> — hosting uses <code>3306</code> (not <code>8889</code>)<br>
  &bull; <code>DB_NAME</code> — cPanel hosting needs prefix like <code>cpanel_user_dbname</code><br>
  &bull; <code>DB_USER</code> — cPanel hosting needs prefix like <code>cpanel_user_dbuser</code><br>
  &bull; <code>DB_PASS</code> — verify password is correct<br><br>
  <strong>Users table empty / No admin?</strong> — Import <code>database.sql</code> then <code>seed.sql</code> via phpMyAdmin
</div>
<?php else: ?>
<div class="fix" style="background:rgba(34,197,94,0.12);border-color:rgba(34,197,94,0.25)">
  <strong style="color:#22c55e">&#10003; Everything looks good!</strong><br>
  <a href="index.php" style="color:#f97316">&#8594; Go to Login</a>
</div>
<?php endif; ?>
</body></html>
<?php
