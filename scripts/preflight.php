<?php
/**
 * Check a MedXpert install before going live.
 *   php scripts/preflight.php
 * It prints PASS, WARN or FAIL for each check and exits with 1 when anything fails.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$results = [];
$add = function (string $level, string $text) use (&$results) {
    $results[] = [$level, $text];
};

// PHP and extensions
version_compare(PHP_VERSION, '8.1.0', '>=') ? $add('PASS', 'PHP ' . PHP_VERSION) : $add('FAIL', 'PHP 8.1 or newer is needed. This is ' . PHP_VERSION);
foreach (['mysqli', 'mbstring', 'json', 'fileinfo'] as $ext) {
    extension_loaded($ext) ? $add('PASS', "Extension $ext") : $add($ext === 'fileinfo' ? 'WARN' : 'FAIL', "Extension $ext is missing");
}

// Settings
$localFile = $root . '/config/config.local.php';
$config = [];
if (is_file($localFile)) {
    $config = require $localFile;
    $add('PASS', 'config/config.local.php exists');
} else {
    $add('WARN', 'config/config.local.php is missing. The MX_DB_* environment variables must be set instead.');
}
$setting = function (string $key, $default = '') use ($config) {
    $env = getenv('MX_' . strtoupper($key));
    return ($env !== false && $env !== '') ? $env : ($config[$key] ?? $default);
};

$dbUser = (string)$setting('db_user', 'root');
$dbUser === 'root' ? $add('FAIL', 'The database user is root. Create a user with rights on this database only.') : $add('PASS', "Database user is $dbUser");
((string)$setting('db_pass', '')) === '' ? $add('FAIL', 'The database password is empty.') : $add('PASS', 'Database password is set');

$mailMode = (string)$setting('mail_mode', 'log');
$appUrl = (string)$setting('app_url', '');
if ($mailMode === 'log') {
    $add('WARN', "mail_mode is 'log'. Emails are written to logs/mail.log and nobody receives them. Use 'smtp' or 'mail' on a live site.");
} elseif ($mailMode === 'mail' || $mailMode === 'smtp') {
    if ($mailMode === 'smtp') {
        foreach (['smtp_host', 'smtp_user', 'smtp_pass'] as $k) {
            (string)$setting($k, '') === '' ? $add('FAIL', "mail_mode is smtp but $k is empty.") : $add('PASS', "$k is set");
        }
    }
    $appUrl === '' ? $add('FAIL', 'mail_mode is mail but app_url is empty.') : $add('PASS', 'app_url is set');
    filter_var((string)$setting('mail_from', ''), FILTER_VALIDATE_EMAIL) ? $add('PASS', 'mail_from is set') : $add('FAIL', 'mail_from is not a valid email address.');
}
if ($appUrl !== '') {
    stripos($appUrl, 'https://') === 0 ? $add('PASS', 'app_url uses HTTPS') : $add('WARN', 'app_url does not use HTTPS.');
}

// Folders
foreach (['uploads', 'logs'] as $dir) {
    is_writable("$root/$dir") ? $add('PASS', "$dir/ is writable") : $add('FAIL', "$dir/ is not writable by this user");
}
foreach (['uploads/.htaccess', 'config/.htaccess', 'uploads/prescriptions/.htaccess', 'logs/.htaccess'] as $file) {
    is_file("$root/$file") ? $add('PASS', "$file exists") : $add('FAIL', "$file is missing. Private files could be public.");
}
is_dir("$root/tests") && is_file("$root/tests/.htaccess") ? $add('PASS', 'tests/ is closed to the web') : $add('WARN', 'Remove the tests/ folder from the live site or close it.');

// Database
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli((string)$setting('db_host', 'localhost'), $dbUser, (string)$setting('db_pass', ''), (string)$setting('db_name', 'medxpert'), (int)$setting('db_port', 3306));
if ($conn->connect_errno) {
    $add('FAIL', 'Cannot connect to the database: ' . $conn->connect_error);
} else {
    $add('PASS', 'Database connection works');
    $tables = ['users', 'doctors', 'doctor_schedules', 'medicine_orders', 'order_status_history', 'notifications', 'user_tokens', 'login_attempts', 'audit_log'];
    foreach ($tables as $table) {
        $conn->query("SELECT 1 FROM `$table` LIMIT 1") ? null : $add('FAIL', "Table $table is missing. Run the migrations in migrations/.");
    }
    foreach ([['users', 'consented_at'], ['users', 'email_verified_at'], ['medicine_orders', 'prescription_image']] as [$table, $column]) {
        $res = @$conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        ($res && $res->num_rows) ? null : $add('FAIL', "Column $table.$column is missing. Run the migrations in migrations/.");
    }
    // The seed admin password must be changed
    $res = @$conn->query("SELECT email, password FROM users WHERE role = 'admin'");
    $seedHash = '$2y$12$OvfK4umC9bA/dEDhlMGgf.mz6szVsMrNG1B0gxUI7TdezBvaEZ5dW';
    $seedAdmin = false;
    while ($res && ($row = $res->fetch_assoc())) {
        if ($row['password'] === $seedHash) {
            $seedAdmin = true;
        }
    }
    $seedAdmin ? $add('FAIL', 'An admin account still has the password from the seed file. Change it on the Profile page.') : $add('PASS', 'No admin account uses the seed password');
    $res = @$conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
    $res && (int)$res->fetch_assoc()['c'] > 0 ? $add('PASS', 'An admin account exists') : $add('FAIL', 'No admin account exists.');
}

// Print
$worst = 'PASS';
foreach ($results as [$level, $text]) {
    printf("%-5s %s\n", $level, $text);
    if ($level === 'FAIL') {
        $worst = 'FAIL';
    } elseif ($level === 'WARN' && $worst !== 'FAIL') {
        $worst = 'WARN';
    }
}
echo "\nResult: $worst\n";
exit($worst === 'FAIL' ? 1 : 0);
