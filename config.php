
<?php
session_start();
date_default_timezone_set('Asia/Manila');

const LOAN_DAYS = 7;
const MAX_LOANS = 3;
const SCAN_COOLDOWN = 10;

// Database configuration
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'database';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please check your database configuration.');
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}

function auth($roles = ['admin', 'librarian']) {
    if (empty($_SESSION['u'])) {
        header('Location: login.php');
        exit;
    }

    if (!in_array($_SESSION['u']['role'], $roles, true)) {
        if ($_SESSION['u']['role'] == 'student') {
            header('Location: student.php');
            exit;
        }

        http_response_code(403);
        exit('Forbidden');
    }
}

function head($title) {
    $p = basename($_SERVER['PHP_SELF']);
    $role = $_SESSION['u']['role'] ?? '';

    $nav = [
        'index.php' => 'Dashboard',
        'students.php' => 'Students',
        'books.php' => 'Books',
        'borrow.php' => 'Borrow',
        'return.php' => 'Return',
        'attendance.php' => 'Time-In / Time-Out'
    ];

    if ($role == 'student') {
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
        echo '<a href="' . h($f) . '"' . ($p == $f ? ' class="on"' : '') . '>' . h($l) . '</a>';
    }

    echo '</nav><div class="who">
    <b>' . h($_SESSION['u']['username'] ?? '') . '</b>
    <small>' . h(ucfirst($role)) . '</small>
    <a href="logout.php">Log out</a>
    </div></aside><main><h1>' . h($title) . '</h1>';
}

function foot($scripts = '') {
    echo '</main></div><script src="assets/app.js"></script>' . $scripts . '</body></html>';
}
