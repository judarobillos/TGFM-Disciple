<?php
/**
 * The two messages TGFM sends when a payment clears.
 *
 *   to the disciple — their receipt, and the link to finish their account
 *   to the ministry — someone just subscribed
 *
 * Both are triggered from mark_paid(), which is the single place access is
 * ever granted and is idempotent — so a webhook and the return-page
 * confirmation both firing sends one email, not two.
 *
 * The claim link is included deliberately. The account is still created on the
 * website, exactly as before; this is the safety net for a buyer who closed the
 * tab before choosing a password, who otherwise has no way back in. It is
 * single-use and expires the moment it is used.
 */

declare(strict_types=1);
require_once __DIR__ . '/_mail.php';

/* ── shared shell ──────────────────────────────────────────────────────── */

/**
 * Inline styles only, and a table for the frame — email clients strip <style>
 * blocks and have no flexbox. The palette is TGFM's, held to the colours that
 * survive a dark-mode client without inverting into mud.
 */
function mail_shell(string $heading, string $inner, string $footNote = ''): string
{
    $site = site_url();
    $foot = $footNote !== '' ? '<p style="margin:0 0 10px">' . $footNote . '</p>' : '';
    return
    '<!doctype html><html><body style="margin:0;padding:0;background:#F3F6FB;">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3F6FB;padding:24px 12px;">'
  . '<tr><td align="center">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #D8E0EE;border-radius:14px;overflow:hidden;font-family:Helvetica,Arial,sans-serif;">'

  . '<tr><td style="background:#0B1226;padding:22px 26px;">'
  . '<div style="color:#FFB35C;font-size:12px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;">TGFM</div>'
  . '<div style="color:#FFFFFF;font-size:19px;font-weight:600;margin-top:4px;">Discipleship Trainings</div>'
  . '</td></tr>'

  . '<tr><td style="padding:26px;">'
  . '<h1 style="margin:0 0 14px;font-size:22px;line-height:1.3;color:#0B1226;font-weight:600;">' . esc_mail($heading) . '</h1>'
  . $inner
  . '</td></tr>'

  . '<tr><td style="padding:18px 26px 24px;border-top:1px solid #D8E0EE;color:#5A6478;font-size:12px;line-height:1.6;">'
  . $foot
  . '<p style="margin:0;">Transform Global Faith Ministries — Transforming Lives Transforming Nations.<br>'
  . '<a href="' . esc_mail($site) . '" style="color:#2F5B96;">' . esc_mail(preg_replace('#^https?://#', '', $site)) . '</a></p>'
  . '</td></tr>'

  . '</table></td></tr></table></body></html>';
}

function esc_mail($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mail_button(string $href, string $label): string {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;"><tr>'
         . '<td style="background:#FFB35C;border-radius:9px;">'
         . '<a href="' . esc_mail($href) . '" style="display:inline-block;padding:13px 22px;'
         . 'color:#0B1226;font-weight:700;font-size:15px;text-decoration:none;">' . esc_mail($label) . '</a>'
         . '</td></tr></table>';
}
function mail_rows(array $pairs): string {
    $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
         . 'style="margin:18px 0;border-top:1px solid #D8E0EE;">';
    foreach ($pairs as $k => $v) {
        $out .= '<tr>'
             . '<td style="padding:9px 0;border-bottom:1px solid #D8E0EE;color:#5A6478;font-size:13px;">' . esc_mail($k) . '</td>'
             . '<td style="padding:9px 0;border-bottom:1px solid #D8E0EE;color:#0B1226;font-size:13px;font-weight:600;" align="right">' . $v . '</td>'
             . '</tr>';
    }
    return $out . '</table>';
}
function money_php(float $n): string { return '&#8369;' . number_format($n, 2); }
function pretty_day(string $ymd): string {
    $t = strtotime($ymd);
    return $t ? date('j F Y', $t) : $ymd;
}
function plan_label(string $id): string {
    return (string) (PLANS[$id]['name'] ?? ucfirst($id));
}

/* ── 1. the disciple's receipt ─────────────────────────────────────────── */

function notify_buyer(array $payment, bool $isNewAccount): bool
{
    $site  = site_url();
    $first = trim(explode(' ', trim((string) $payment['name']))[0] ?? '');
    $greet = $first !== '' ? 'Hi ' . esc_mail($first) . ',' : 'Hello,';
    $plan  = plan_label((string) $payment['plan']);
    $until = pretty_day((string) $payment['access_until']);

    $rows = mail_rows([
        'Reference'    => '<span style="font-family:monospace">' . esc_mail($payment['reference']) . '</span>',
        'Subscription' => esc_mail($plan),
        'Amount'       => money_php((float) $payment['amount']),
        'Paid with'    => $payment['method'] === 'maya' ? 'Maya' : 'PayPal',
        'Runs until'   => esc_mail($until),
    ]);

    if ($isNewAccount) {
        /* They have paid AND chosen a password. Nothing is left to do, so this
           is a welcome and a receipt in one — no claim link, because the token
           it would carry has already been spent. */
        $heading = 'Welcome to TGFM';
        $subject = 'Welcome to TGFM Discipleship Trainings';
        $inner =
            '<p style="margin:0 0 14px;color:#25304A;font-size:15px;line-height:1.6;">' . $greet . '</p>'
          . '<p style="margin:0 0 4px;color:#25304A;font-size:15px;line-height:1.6;">Your account is ready and your '
          . esc_mail($plan) . ' is open. Sign in any time with <b>' . esc_mail($payment['email']) . '</b> and the '
          . 'password you just chose.</p>'
          . mail_button($site . '/app', 'Start watching')
          . $rows;
        $foot = 'Keep this email — the reference above is what TGFM needs if you ever ask about this payment.';
    } else {
        /* An existing member whose pass was extended. */
        $heading = 'Payment received';
        $subject = 'Your TGFM subscription is active until ' . $until;
        $inner =
            '<p style="margin:0 0 14px;color:#25304A;font-size:15px;line-height:1.6;">' . $greet . '</p>'
          . '<p style="margin:0 0 4px;color:#25304A;font-size:15px;line-height:1.6;">Your payment came through — thank you. '
          . 'Your access now runs to <b>' . esc_mail($until) . '</b>.</p>'
          . mail_button($site . '/app', 'Start watching')
          . $rows;
        $foot = 'Keep this email — the reference above is what TGFM needs if you ever ask about this payment.';
    }

    return send_mail((string) $payment['email'], $subject, mail_shell($heading, $inner, $foot));
}

/* ── 2. the ministry's alert ───────────────────────────────────────────── */

function notify_admin(array $payment, bool $awaitingAccount): bool
{
    $recipients = admin_notify_addresses();
    if (!$recipients) { return false; }

    $site  = site_url();
    $plan  = plan_label((string) $payment['plan']);
    $state = $awaitingAccount
        ? 'New disciple — paid and account created'
        : 'Existing disciple renewed';

    $inner =
        '<p style="margin:0 0 4px;color:#25304A;font-size:15px;line-height:1.6;">'
      . '<b>' . esc_mail($payment['name']) . '</b> paid for ' . esc_mail($plan) . '.</p>'
      . '<p style="margin:0;color:#5A6478;font-size:13px;">' . esc_mail($state) . '</p>'
      . mail_rows([
          'Disciple'    => esc_mail($payment['name']),
          'Email'       => esc_mail($payment['email']),
          'Subscription'=> esc_mail($plan),
          'Amount'      => money_php((float) $payment['amount']),
          'Paid with'   => $payment['method'] === 'maya' ? 'Maya' : 'PayPal',
          'Reference'   => '<span style="font-family:monospace">' . esc_mail($payment['reference']) . '</span>',
          'Access until'=> esc_mail(pretty_day((string) $payment['access_until'])),
        ])
      . mail_button($site . '/admin/payments', 'Open Payments');

    $subject = 'TGFM: ' . $payment['name'] . ' subscribed (' . $plan . ')';
    $html    = mail_shell('Someone subscribed', $inner,
        'You are getting this because your address is in MAIL_ADMIN_TO in config.php.');

    $sentAny = false;
    foreach ($recipients as $addr) {
        if (send_mail($addr, $subject, $html)) { $sentAny = true; }
    }
    return $sentAny;
}

/* ── 3. a new disciple registered ──────────────────────────────────────────
   The register is free and comes before any money, so this one has no receipt
   in it — it is the ministry being told that somebody new has walked in, with
   enough detail for the right pastor to reach them. */

function notify_disciple_registered(string $email): void
{
    try {
        $d = find_disciple($email);
        if (!$d) { return; }

        $recipients = admin_notify_addresses();
        if (!$recipients) { return; }

        $site  = site_url();
        $inner =
            '<p style="margin:0 0 4px;color:#25304A;font-size:15px;line-height:1.6;">'
          . '<b>' . esc_mail($d['name']) . '</b> has registered as a disciple.</p>'
          . '<p style="margin:0;color:#5A6478;font-size:13px;">'
          . 'Free registration — no payment yet.</p>'
          . mail_rows([
              'Disciple'         => esc_mail($d['name']),
              'Email'            => esc_mail($d['email']),
              'Mobile'           => esc_mail($d['phone']),
              'Gender'           => esc_mail($d['gender']),
              'Location'         => esc_mail($d['location']),
              'Pastor'           => esc_mail($d['pastor']),
              'Divine Encounter' => esc_mail($d['de_year'] !== '' ? $d['de_year'] : 'Not yet attended'),
            ])
          . mail_button($site . '/admin/disciples', 'Open Registered Disciples');

        $subject = 'TGFM: ' . $d['name'] . ' registered as a disciple';
        $html    = mail_shell('A new disciple', $inner,
            'You are getting this because your address is in MAIL_ADMIN_TO in config.php.');

        foreach ($recipients as $addr) { send_mail($addr, $subject, $html); }
    } catch (Throwable $e) {
        /* A registration that succeeded must never fail because mail did. */
        log_line('notify_disciple_registered failed: ' . $e->getMessage());
    }
}

/** Comma-separated in config; falls back to every admin account in the database. */
function admin_notify_addresses(): array
{
    $list = [];
    if (defined('MAIL_ADMIN_TO') && MAIL_ADMIN_TO !== '') {
        $list = preg_split('/[,;]+/', (string) MAIL_ADMIN_TO) ?: [];
    } else {
        try {
            $list = db()->query("SELECT email FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) { $list = []; }
    }
    $clean = [];
    foreach ($list as $addr) {
        $addr = trim((string) $addr);
        if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) { $clean[strtolower($addr)] = $addr; }
    }
    return array_values($clean);
}

/**
 * Called by mark_paid() once the transaction has committed — never inside it.
 *
 * Nothing is sent for a brand-new buyer at this point. They have paid, but they
 * have no account yet, and the ministry's rule is that both emails go out once
 * somebody has paid AND created their account. For a new buyer that moment is
 * notify_account_created(), below.
 *
 * An existing member renewing is different: their account already exists, so
 * both conditions are already met and the mail goes now.
 */
function notify_payment_cleared(string $reference, ?string $claimToken): void
{
    if ($claimToken !== null) {
        /* New buyer — hold everything until they choose a password. */
        log_line("notify: $reference paid, holding email until the account is created");
        return;
    }
    try {
        $payment = find_payment($reference);
        if (!$payment) { return; }
        notify_buyer($payment, false);
        notify_admin($payment, false);
    } catch (Throwable $e) {
        log_line('notify: failed for ' . $reference . ' — ' . $e->getMessage());
    }
}

/**
 * Called by claim_account() after the user row exists and the transaction has
 * committed. This is the moment the ministry asked for: paid AND registered.
 *
 * Like the other path, it cannot throw — an account that was created must not
 * fail because a mail server was slow.
 */
function notify_account_created(string $reference): void
{
    try {
        $payment = find_payment($reference);
        if (!$payment) { return; }
        notify_buyer($payment, true);
        notify_admin($payment, true);
    } catch (Throwable $e) {
        log_line('notify: account email failed for ' . $reference . ' — ' . $e->getMessage());
    }
}
