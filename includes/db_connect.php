<?php
/** Connection-only database bootstrap. Run schema changes with scripts/migrate.php. */
$dbUrl = trim((string)getenv('DATABASE_URL'));
$servername = 'localhost';
$port = 3306;
$dbname = 'capstone1';
$pdo_username = 'root';
$pdo_password = '';
$driver = 'mysql';

if ($dbUrl !== '') {
    $dbopts = parse_url($dbUrl);
    if ($dbopts === false || empty($dbopts['host']) || empty($dbopts['path'])) {
        throw new RuntimeException('DATABASE_URL is not valid.');
    }
    $scheme = strtolower((string)($dbopts['scheme'] ?? 'mysql'));
    $driver = in_array($scheme, ['postgres', 'postgresql', 'pgsql'], true) ? 'pgsql' : 'mysql';
    $servername = (string)$dbopts['host'];
    $port = (int)($dbopts['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $dbname = ltrim((string)$dbopts['path'], '/');
    $pdo_username = rawurldecode((string)($dbopts['user'] ?? ''));
    $pdo_password = rawurldecode((string)($dbopts['pass'] ?? ''));
}
$conn_str = $driver === 'pgsql'
    ? "pgsql:host={$servername};port={$port};dbname={$dbname}"
    : "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false,
];

try {
    $conn = new PDO($conn_str, $pdo_username, $pdo_password, $pdoOptions);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    throw $e;
}

// Repair older/incomplete authenticated sessions once, without adding a query
// to normal requests whose profile data is already present.
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])
    && (!isset($_SESSION['first_name'], $_SESSION['last_name'], $_SESSION['role']))) {
    try {
        $sessionUserStmt = $conn->prepare(
            'SELECT username, first_name, last_name, role, barangay, profile_picture
             FROM users WHERE id = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1'
        );
        $sessionUserStmt->execute([(int)$_SESSION['user_id']]);
        $sessionUser = $sessionUserStmt->fetch();
        if ($sessionUser) {
            foreach ($sessionUser as $key => $value) {
                $_SESSION[$key] = $value;
            }
        }
    } catch (PDOException $e) {
        error_log('Unable to refresh incomplete session profile: ' . $e->getMessage());
    }
}
