<?php
/**
 * Prove the mail settings work before a real disciple depends on them.
 *
 *   .../api/tools/send_test_email.php?key=YOUR_MAYA_SECRET_KEY&to=you@example.com
 *
 * Add &kind=buyer or &kind=admin to send the actual receipt or alert against a
 * made-up payment, so you can see exactly what each one looks like. Nothing is
 * written to the database and no real payment is touched.
 *
 * Delete this with the rest of the tools folder.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= the Maya secret key currently in use to run this.\n");
}

$to = trim((string) ($_GET['to'] ?? ''));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { exit("Give a valid ?to= address.\n"); }

echo "TGFM mail check — " . date('c') . "\n";
echo str_repeat('=', 60) . "\n\n";

echo "Transport   : " . (MAIL_SMTP_HOST !== '' ? 'SMTP ' . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PORT
                          . ' (' . MAIL_SMTP_SECURE . ')' : 'PHP mail() — deliverability is a lottery') . "\n";
echo "From        : " . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\n";
echo "Reply-To    : " . (MAIL_REPLY_TO !== '' ? MAIL_REPLY_TO : MAIL_FROM) . "\n";
echo "Admin alerts: " . (implode(', ', admin_notify_addresses()) ?: '(nobody — set MAIL_ADMIN_TO or create an admin)') . "\n";
echo "Enabled     : " . (MAIL_ENABLED ? 'yes' : 'NO — MAIL_ENABLED is false, nothing will send') . "\n\n";

if (MAIL_SMTP_HOST === '') {
    echo "NOTE: with no SMTP host set this uses PHP mail(). Check the spam folder,\n";
    echo "      and set up a real mailbox before launch.\n\n";
}
if (str_contains(MAIL_FROM, 'yourdomain.com')) {
    echo "WARNING: MAIL_FROM is still the placeholder. Receipts will look forged.\n\n";
}

/* A payment that does not exist, purely so the templates have something real
   to render. Nothing here touches the database. */
$fake = [
    'reference'    => 'TGFM-TEST-' . strtoupper(bin2hex(random_bytes(3))),
    'name'         => 'Test Disciple',
    'email'        => $to,
    'plan'         => array_key_first(PLANS),
    'amount'       => (float) PLANS[array_key_first(PLANS)]['price'],
    'method'       => 'maya',
    'access_until' => date('Y-m-d', strtotime('+7 days')),
];

$kind = (string) ($_GET['kind'] ?? 'plain');
$ok   = false;

if ($kind === 'buyer') {
    echo "Sending the disciple's receipt (with a create-account link) to $to …\n";
    $ok = notify_buyer($fake, bin2hex(random_bytes(16)));
} elseif ($kind === 'admin') {
    echo "Sending the ministry alert to the configured admin addresses …\n";
    $ok = notify_admin($fake, true);
} else {
    echo "Sending a plain test message to $to …\n";
    $ok = send_mail($to, 'TGFM test message',
        mail_shell('It works', '<p style="margin:0;color:#25304A;font-size:15px;line-height:1.6;">'
          . 'If you are reading this, TGFM can send email. Try <code>&amp;kind=buyer</code> and '
          . '<code>&amp;kind=admin</code> to preview the two real messages.</p>'));
}

echo $ok
    ? "\nHanded to the mail server. Check the inbox — AND the spam folder.\n"
    : "\nFAILED. The reason is in api/logs/tgfm-" . date('Y-m') . ".log.\n"
      . "Most common causes:\n"
      . "  - wrong port/secure pair (465 is 'ssl', 587 is 'tls')\n"
      . "  - mailbox password wrong, or MAIL_FROM is not the same mailbox as MAIL_SMTP_USER\n"
      . "  - the host blocks outbound SMTP — then use PHP mail() instead\n";

echo "\nDelete this file when you are done.\n";
