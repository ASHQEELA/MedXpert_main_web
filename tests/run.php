<?php
/**
 * Run all tests:  php tests/run.php
 *
 * The unit tests need nothing. The database tests need a database that has
 * database.sql imported. Set MX_DB_HOST, MX_DB_USER, MX_DB_PASS, MX_DB_NAME
 * and MX_DB_PORT, or create config/config.local.php. Use a test database,
 * never your live one.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/harness.php';
require __DIR__ . '/../src/helpers.php';

// The tests do not load config/bootstrap.php, so they get a small stand-in for settings.
// Mail is off, so no test sends or writes an email.
function mx_config(string $key, $default = null)
{
    return $key === 'mail_mode' ? 'off' : $default;
}

echo "Unit tests\n";
require __DIR__ . '/helpers_test.php';

$conn = null;
$env = fn(string $k, $d) => getenv($k) !== false ? getenv($k) : $d;
$local = is_file(__DIR__ . '/../config/config.local.php') ? require __DIR__ . '/../config/config.local.php' : [];

mysqli_report(MYSQLI_REPORT_OFF);
if ($env('MX_DB_NAME', $local['db_name'] ?? '') !== '') {
    $conn = @new mysqli(
        $env('MX_DB_HOST', $local['db_host'] ?? 'localhost'),
        $env('MX_DB_USER', $local['db_user'] ?? ''),
        $env('MX_DB_PASS', $local['db_pass'] ?? ''),
        $env('MX_DB_NAME', $local['db_name'] ?? ''),
        (int)$env('MX_DB_PORT', $local['db_port'] ?? 3306)
    );
    if ($conn->connect_errno) {
        $conn = null;
    }
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($conn === null) {
    echo "\nDatabase tests skipped: no database connection. Set the MX_DB_* variables to run them.\n";
} else {
    $conn->set_charset('utf8mb4');
    require __DIR__ . '/../src/assets.php';
    require __DIR__ . '/../src/audit.php';
    require __DIR__ . '/../src/privacy.php';
    require __DIR__ . '/../src/i18n.php';
    require __DIR__ . '/../src/mail.php';
    require __DIR__ . '/../src/notifications.php';
    require __DIR__ . '/../src/accounts.php';
    require __DIR__ . '/../src/charts.php';
    require __DIR__ . '/../src/orders.php';
    require __DIR__ . '/../src/schedule.php';
    require __DIR__ . '/../src/appointments.php';
    require __DIR__ . '/../src/auth_session.php';
    require __DIR__ . '/services_test.php';
    require __DIR__ . '/auth_session_test.php';
}

echo "\n";
exit(run_tests());
