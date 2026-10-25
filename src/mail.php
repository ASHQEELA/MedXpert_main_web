<?php
/**
 * Outgoing email.
 * mail_mode in config.local.php:
 *   'log'  (default) writes each message to logs/mail.log. Use this on your own computer.
 *   'smtp' sends through an SMTP account such as Gmail. Use this on free hosts. Also set app_url,
 *          mail_from, smtp_host, smtp_port, smtp_user and smtp_pass. smtp_secure is 'tls' (port 587, default),
 *          'ssl' (port 465) or 'none' (a trusted local relay only).
 *   'mail' sends with PHP mail(). Works only on hosts with a working mail server. Also set app_url and mail_from.
 *   'off'  sends nothing.
 */

function mail_mode(): string
{
    $mode = (string)mx_config('mail_mode', 'log');
    return in_array($mode, ['log', 'smtp', 'mail', 'off'], true) ? $mode : 'log';
}

/** The public address of the site, with no trailing slash. */
function app_url(): string
{
    $configured = rtrim((string)mx_config('app_url', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    // Without app_url only the log mode is allowed to guess from the request,
    // because a forged Host header must never end up in a real email.
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $host) ? ($https ? 'https://' : 'http://') . $host : 'http://localhost';
}

/** The reason the last send_mail() call failed, or an empty string. */
function mail_last_error(): string
{
    return (string)($GLOBALS['mx_mail_error'] ?? '');
}

/** Send an email. Returns true when the message was handed off or logged. */
function send_mail(string $to, string $subject, string $body): bool
{
    $GLOBALS['mx_mail_error'] = '';
    $mode = mail_mode();
    if ($mode === 'off') {
        $GLOBALS['mx_mail_error'] = "mail_mode is 'off'. Nothing is sent.";
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['mx_mail_error'] = 'The recipient address is not valid.';
        return false;
    }
    $subject = str_replace(["\r", "\n"], ' ', $subject);

    if ($mode === 'log') {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $entry = '[' . date('Y-m-d H:i:s') . "] To: $to\nSubject: $subject\n$body\n" . str_repeat('-', 40) . "\n";
        return @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    if ((string)mx_config('app_url', '') === '') {
        $GLOBALS['mx_mail_error'] = 'Set app_url in config.local.php.';
        error_log('Email not sent: set app_url in config.local.php when mail_mode is ' . $mode . '.');
        return false;
    }
    $from = (string)mx_config('mail_from', '');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $GLOBALS['mx_mail_error'] = 'Set a valid mail_from in config.local.php.';
        error_log('Email not sent: set a valid mail_from in config.local.php.');
        return false;
    }
    if ($mode === 'smtp') {
        try {
            smtp_send($to, $subject, $body, $from);
            return true;
        } catch (Throwable $e) {
            // The message names the server reply, never the password
            $GLOBALS['mx_mail_error'] = $e->getMessage();
            error_log('Email to ' . $to . ' not sent: ' . $e->getMessage());
            return false;
        }
    }
    $headers = "From: MedXpert <$from>\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: MedXpert";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/** Read one full SMTP reply and check its code. */
function smtp_expect($socket, array $codes): string
{
    $reply = '';
    while (($line = fgets($socket, 515)) !== false) {
        $reply .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    if ($reply === '') {
        throw new RuntimeException('The mail server did not answer');
    }
    if (!in_array((int)substr($reply, 0, 3), $codes, true)) {
        throw new RuntimeException('Mail server said: ' . trim($reply));
    }
    return $reply;
}

function smtp_cmd($socket, string $command, array $codes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to the mail server');
    }
    return smtp_expect($socket, $codes);
}

/** Send one plain text message through the SMTP account in config.local.php. */
function smtp_send(string $to, string $subject, string $body, string $from): void
{
    $host = trim((string)mx_config('smtp_host', ''));
    $port = (int)mx_config('smtp_port', 587);
    $user = trim((string)mx_config('smtp_user', ''));
    $pass = preg_replace('/\s+/', '', (string)mx_config('smtp_pass', ''));
    $secure = strtolower((string)mx_config('smtp_secure', 'tls'));
    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Set smtp_host, smtp_user and smtp_pass in config.local.php');
    }
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException("smtp_secure must be tls, ssl or none");
    }

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
    $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new RuntimeException('Could not connect to ' . $host . ':' . $port . ' (' . ($errstr ?: 'blocked or wrong port') . ')');
    }
    stream_set_timeout($socket, 20);

    try {
        $helo = preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        smtp_expect($socket, [220]);
        smtp_cmd($socket, 'EHLO ' . $helo, [250]);
        if ($secure === 'tls') {
            smtp_cmd($socket, 'STARTTLS', [220]);
            if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('Could not start TLS with the mail server');
            }
            smtp_cmd($socket, 'EHLO ' . $helo, [250]);
        }
        smtp_cmd($socket, 'AUTH LOGIN', [334]);
        smtp_cmd($socket, base64_encode($user), [334]);
        smtp_cmd($socket, base64_encode($pass), [235]);
        smtp_cmd($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($socket, 'DATA', [354]);

        $encodedSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
            : '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: MedXpert <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $text = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $body));
        $text = preg_replace('/(^|\r\n)\./', '$1..', $text); // a line that starts with a dot is doubled
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $text . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        try {
            smtp_cmd($socket, 'QUIT', [221]);
        } catch (Throwable $ignore) {
        }
    } finally {
        fclose($socket);
    }
}
