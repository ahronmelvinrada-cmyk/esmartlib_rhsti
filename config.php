<?php
// IMPORTANT: Walang kahit anong espasyo, blank line, o BOM bago ang opening tag sa itaas.
// Walang closing tag sa dulo ng file na ito (sinasadya, para walang stray whitespace).

declare(strict_types=1);

// Huwag ipakita ang errors sa browser (mapupunta sa log); pigilan din ang "headers already sent"
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// I-buffer ang output para hindi masira ang header()/session kahit may stray output
if (ob_get_level() === 0) {
    ob_start();
}

// Session (isang beses lang, may secure na cookie settings)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

date_default_timezone_set('Asia/Manila');

const LOAN_DAYS     = 7;
const MAX_LOANS     = 3;
const SCAN_COOLDOWN = 10;

/**
 * Database configuration
 * Priority: 1) config.local.php  2) environment variables  3) defaults
 *
 * Gumawa ng file na config.local.php (katabi nito) para siguradong gumagana
 * kahit hindi umaabot ang getenv() sa Apache/PHP-FPM:
 *
 * <?php
 * return [
 *     'host' => 'localhost',
 *     'port' => '3306',
 *     'name' => 'pangalan_ng_database',
 *     'user' => 'db_user',
 *     'pass' => 'db_password',
 * ];
 */
$local = [];
$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

// Defaults ay para sa XAMPP: root, walang password, database na esmartlib_rhsti
$host     = $local['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1');
$port     = $local['port'] ?? (getenv('DB_PORT') ?: '3306');
$dbname   = $local['name'] ?? (getenv('DB_NAME') ?: 'esmartlib_rhsti');
$username = $local['user'] ?? (getenv('DB_USER') ?: 'root');
$password = $local['pass'] ?? (getenv('DB_PASSWORD') ?: '');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Ang totoong dahilan ay nasa error log, hindi sa browser
    error_log('Database connection failed: ' . $e->getMessage()
        . " (host={$host}, port={$port}, db={$dbname}, user={$username})");
    http_response_code(500);
    exit('Database connection failed. Please check your database configuration.');
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function auth(array $roles = ['admin', 'librarian']): void
{
    if (empty($_SESSION['u'])) {
        header('Location: login.php');
        exit;
    }

    $role = $_SESSION['u']['role'] ?? '';

    if (!in_array($role, $roles, true)) {
        if ($role === 'student') {
            header('Location: student.php');
            exit;
        }

        http_response_code(403);
        exit('Forbidden');
    }
}

function head(string $title): void
{
    $p    = basename($_SERVER['PHP_SELF']);
    $role = $_SESSION['u']['role'] ?? '';

    $nav = [
        'index.php'      => 'Dashboard',
        'students.php'   => 'Students',
        'books.php'      => 'Books',
        'borrow.php'     => 'Borrow',
        'return.php'     => 'Return',
        'attendance.php' => 'Time-In / Time-Out',
    ];

    if ($role === 'student') {
        $nav = ['student.php' => 'My Dashboard'];
    }

    echo '<!doctype html><html><head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>' . h($title) . ' - eSmartlib RHSTI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="assets/style.css">
    </head><body><div class="app"><aside>
    <div class="brand"><i>e</i><span>eSmartlib<small>RHSTI Library</small></span></div>
    <nav>';

    foreach ($nav as $f => $l) {
        echo '<a href="' . h($f) . '"' . ($p === $f ? ' class="on"' : '') . '>' . h($l) . '</a>';
    }

    echo '</nav><div class="who">
    <b>' . h($_SESSION['u']['username'] ?? '') . '</b>
    <small>' . h(ucfirst($role)) . '</small>
    <a href="logout.php">Log out</a>
    </div></aside><main><h1>' . h($title) . '</h1>';
}

function foot(string $scripts = ''): void
{
    echo '</main></div><script src="assets/app.js"></script>' . $scripts . '</body></html>';
}
