<?php
/**
 * Sending mail from shared hosting, with no Composer and no dependencies.
 *
 * Two ways out:
 *
 *   SMTP  — when MAIL_SMTP_HOST is set. Authenticated, from a real mailbox on
 *           the domain, so SPF and DKIM line up and the message reaches the
 *           inbox rather than the spam folder. This is what you want.
 *   mail() — the fallback. Works, but Hostinger's shared mail server is shared
 *           with every other site on it, so delivery is a lottery. Fine for a
 *           first test; not fine for receipts a disciple needs to keep.
 *
 * Nothing in here is allowed to break a payment. Every failure is logged and
 * swallowed: a receipt that does not arrive is a nuisance, a cleared payment
 * that does not grant access is a disaster.
 */

declare(strict_types=1);

/* ── the public entry point ────────────────────────────────────────────── */

/**
 * @param string $to      one recipient address
 * @param string $subject plain text, may contain non-ASCII
 * @param string $html    the HTML body
 * @param string $text    the plain-text alternative (built from $html if empty)
 * @return bool           true if handed off successfully
 */
function send_mail(string $to, string $subject, string $html, string $text = ''): bool
{
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        log_line("mail: disabled, not sending \"$subject\" to $to");
        return false;
    }
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_line("mail: refusing to send to an invalid address: $to");
        return false;
    }
    if ($text === '') { $text = html_to_text($html); }

    try {
        $ok = (defined('MAIL_SMTP_HOST') && MAIL_SMTP_HOST !== '')
            ? smtp_send($to, $subject, $html, $text)
            : php_mail_send($to, $subject, $html, $text);
        log_line('mail: ' . ($ok ? 'sent' : 'FAILED') . " \"$subject\" to $to");
        return $ok;
    } catch (Throwable $e) {
        /* Never rethrow. The caller is usually mid-payment. */
        log_line('mail: FAILED "' . $subject . '" to ' . $to . ' — ' . $e->getMessage());
        return false;
    }
}

/* ── message assembly ──────────────────────────────────────────────────── */

function mail_from(): array {
    $addr = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : ('no-reply@' . mail_domain());
    $name = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : 'TGFM';
    return [$addr, $name];
}
function mail_domain(): string {
    $host = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    return preg_replace('/^www\./', '', $host);
}
/** RFC 2047 for anything that is not plain ASCII. */
function mime_header(string $s): string {
    return preg_match('/[\x80-\xFF]/', $s)
        ? '=?UTF-8?B?' . base64_encode($s) . '?='
        : $s;
}
function mail_headers(string $to, string $boundary): array {
    [$fromAddr, $fromName] = mail_from();
    $reply = defined('MAIL_REPLY_TO') && MAIL_REPLY_TO !== '' ? MAIL_REPLY_TO : $fromAddr;
    return [
        'Date'         => date('r'),
        'From'         => mime_header($fromName) . ' <' . $fromAddr . '>',
        'To'           => $to,
        'Reply-To'     => $reply,
        'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . mail_domain() . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        /* Bounces and auto-replies should not loop back into the ministry's
           inbox for a machine-generated receipt. */
        'Auto-Submitted' => 'auto-generated',
    ];
}
function mail_body(string $html, string $text, string $boundary): string
{
    $nl = "\r\n";
    return
        '--' . $boundary . $nl .
        'Content-Type: text/plain; charset=UTF-8' . $nl .
        'Content-Transfer-Encoding: base64' . $nl . $nl .
        chunk_split(base64_encode($text)) . $nl .
        '--' . $boundary . $nl .
        'Content-Type: text/html; charset=UTF-8' . $nl .
        'Content-Transfer-Encoding: base64' . $nl . $nl .
        chunk_split(base64_encode($html)) . $nl .
        '--' . $boundary . '--' . $nl;
}

/** A readable plain-text version, so the message is not blank without HTML. */
function html_to_text(string $html): string
{
    $s = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $s = preg_replace('#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $s) ?? $s;
    /* A label cell and its value cell are two elements; without a separator
       they run together as "ReferenceTGFM-2608-…". */
    $s = preg_replace('#</td>\s*<td[^>]*>#i', ': ', $s) ?? $s;
    $s = preg_replace('#</(p|div|tr|h1|h2|h3|li)>#i', "\n", $s) ?? $s;
    $s = preg_replace('#<br\s*/?>#i', "\n", $s) ?? $s;
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace("/[ \t]+/", ' ', $s) ?? $s;
    $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;
    return trim(implode("\n", array_map('trim', explode("\n", $s))));
}

/* ── transport 1: php mail() ───────────────────────────────────────────── */

function php_mail_send(string $to, string $subject, string $html, string $text): bool
{
    $boundary = 'tgfm' . bin2hex(random_bytes(12));
    $headers  = mail_headers($to, $boundary);
    unset($headers['To']);                       // mail() takes it separately
    $lines = [];
    foreach ($headers as $k => $v) { $lines[] = "$k: $v"; }

    [$fromAddr] = mail_from();
    /* -f sets the envelope sender, which is what SPF is checked against. */
    return mail($to, mime_header($subject), mail_body($html, $text, $boundary),
        implode("\r\n", $lines), '-f' . $fromAddr);
}

/* ── transport 2: SMTP ─────────────────────────────────────────────────── */

function smtp_send(string $to, string $subject, string $html, string $text): bool
{
    $host   = MAIL_SMTP_HOST;
    $port   = defined('MAIL_SMTP_PORT') ? (int) MAIL_SMTP_PORT : 587;
    $secure = defined('MAIL_SMTP_SECURE') ? strtolower((string) MAIL_SMTP_SECURE) : 'tls';
    $user   = defined('MAIL_SMTP_USER') ? MAIL_SMTP_USER : '';
    $pass   = defined('MAIL_SMTP_PASS') ? MAIL_SMTP_PASS : '';

    /* Port 465 is implicit TLS — encrypted from the first byte. Port 587 opens
       in the clear and upgrades with STARTTLS. Getting these two the wrong way
       round is the usual cause of "connection reset". */
    $target = ($secure === 'ssl') ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;

    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => true,
        'verify_peer_name'  => true,
        'SNI_enabled'       => true,
    ]]);

    $fp = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { throw new RuntimeException("SMTP connect to $target failed: $errstr ($errno)"); }
    stream_set_timeout($fp, 20);

    try {
        smtp_expect($fp, 220);
        $ehloName = mail_domain();
        smtp_cmd($fp, "EHLO $ehloName", 250);

        if ($secure === 'tls') {
            smtp_cmd($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            smtp_cmd($fp, "EHLO $ehloName", 250);   // must re-greet after upgrading
        }

        if ($user !== '') {
            smtp_cmd($fp, 'AUTH LOGIN', 334);
            smtp_cmd($fp, base64_encode($user), 334);
            smtp_cmd($fp, base64_encode($pass), 235);
        }

        [$fromAddr] = mail_from();
        smtp_cmd($fp, 'MAIL FROM:<' . $fromAddr . '>', 250);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', 354);

        $boundary = 'tgfm' . bin2hex(random_bytes(12));
        $headers  = mail_headers($to, $boundary);
        $headers  = ['Subject' => mime_header($subject)] + $headers;

        $msg = '';
        foreach ($headers as $k => $v) { $msg .= "$k: $v\r\n"; }
        $msg .= "\r\n" . mail_body($html, $text, $boundary);

        /* A line that is just "." would end the message early, so any line
           starting with a dot gets another one. */
        $msg = preg_replace('/^\./m', '..', $msg) ?? $msg;

        fwrite($fp, $msg . "\r\n.\r\n");
        smtp_expect($fp, 250);
        smtp_cmd($fp, 'QUIT', [221, 250]);
        return true;
    } finally {
        @fclose($fp);
    }
}

function smtp_line($fp): string {
    $line = fgets($fp, 2048);
    if ($line === false) {
        $info = stream_get_meta_data($fp);
        throw new RuntimeException(!empty($info['timed_out']) ? 'SMTP timed out' : 'SMTP closed the connection');
    }
    return $line;
}
/** Reads a full reply, including multi-line 250-XXX continuations. */
function smtp_expect($fp, $codes): string {
    $codes = (array) $codes;
    $all   = '';
    do {
        $line = smtp_line($fp);
        $all .= $line;
    } while (strlen($line) >= 4 && $line[3] === '-');

    $code = (int) substr($all, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP expected ' . implode('/', $codes) . ', got: ' . trim($all));
    }
    return $all;
}
function smtp_cmd($fp, string $cmd, $codes): string {
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $codes);
}
