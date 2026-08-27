<?php
/**
 * Setup check — tells you what is actually configured and what is not.
 *
 * Open once after uploading, and again after pasting your own Maya keys in:
 *
 *   https://yourdomain.com/api/tools/check_setup.php?key=YOUR_MAYA_SECRET_KEY
 *
 * It never writes anything and never takes a payment. Delete it with the rest
 * of the tools folder when you go live.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= the Maya secret key currently in use to run this.\n");
}

$pass = 0; $warn = 0; $fail = 0;
function ok(string $m):   void { global $pass; $pass++; echo "  ok    $m\n"; }
function note(string $m): void { global $warn; $warn++; echo "  note  $m\n"; }
function bad(string $m):  void { global $fail; $fail++; echo "  FAIL  $m\n"; }

echo "TGFM setup check — " . date('c') . "\n";
echo str_repeat('=', 60) . "\n\n";

/* ── environment ───────────────────────────────────────────────────────── */
echo "Environment\n";
echo "  TGFM_ENV  " . TGFM_ENV . "\n";
echo "  SITE_URL  " . SITE_URL . "\n";
if (SITE_URL !== rtrim(SITE_URL, '/')) {
    note('SITE_URL ends with a slash. Every link the server builds strips it now, '
       . 'so nothing is broken — but links made before this fix came out double, '
       . 'like ".com//create-account/...", and those 404. Tidy it to "'
       . rtrim(SITE_URL, '/') . '" so what you see in config matches what is sent.');
}
PHP_VERSION_ID >= 80100
    ? ok('PHP ' . PHP_VERSION)
    : bad('PHP ' . PHP_VERSION . ' — 8.1 or newer is needed');
extension_loaded('curl') ? ok('cURL available') : bad('cURL missing — no gateway calls can be made');
extension_loaded('pdo_mysql') ? ok('PDO MySQL available') : bad('PDO MySQL missing');
str_starts_with(SITE_URL, 'https://')
    ? ok('SITE_URL is https')
    : bad('SITE_URL is not https — neither gateway will send webhooks');
str_contains(SITE_URL, 'yourdomain.com')
    ? bad('SITE_URL is still the placeholder')
    : ok('SITE_URL has been set');
echo "\n";

/* ── database ──────────────────────────────────────────────────────────── */
echo "Database\n";
try {
    db()->query('SELECT 1');
    ok('connected to ' . DB_NAME);
    /* Each table is checked on its own. A missing one must not abort the rest
       of the report — a half-finished import is exactly when you need to see
       the whole picture. */
    foreach (['users', 'payments', 'webhook_log'] as $t) {
        try { db()->query("SELECT 1 FROM `$t` LIMIT 1"); ok("table `$t` present"); }
        catch (Throwable $e) { bad("table `$t` MISSING — re-import schema.sql"); }
    }
    try {
        $cols = db()->query('SHOW COLUMNS FROM payments LIKE "claim_token"')->fetchAll();
        $cols ? ok('payments.claim_token present')
              : bad('payments.claim_token missing — re-import schema.sql');
    } catch (Throwable $e) { /* payments itself is missing; already reported */ }

    /* The content tables. Without these, an admin's edits stay in the browser
       they were typed in — which is exactly the failure this checks for. */
    $missing = [];
    foreach (['content_trainings', 'content_series', 'content_topics'] as $t) {
        try { db()->query("SELECT 1 FROM `$t` LIMIT 1"); ok("table `$t` present"); }
        catch (Throwable $e) { $missing[] = $t; bad("table `$t` MISSING"); }
    }
    if ($missing) {
        bad('Re-import private/schema.sql. Until these three tables exist, every '
          . 'training the admin adds lives only in that one browser and nobody '
          . 'else can see it. Re-importing is safe — your accounts and payments '
          . 'are left alone.');
    } else {
        $n = db()->query('SELECT
              (SELECT COUNT(*) FROM content_trainings) t,
              (SELECT COUNT(*) FROM content_series)    s,
              (SELECT COUNT(*) FROM content_topics)    v')->fetch();
        echo "  content: {$n['t']} trainings, {$n['s']} series, {$n['v']} topics\n";
        if ((int) $n['v'] === 0) {
            note('no topics yet — add the videos through Admin → Trainings');
        }
    }

    try {
        /* Look at every admin, not just the seeded address — the email is often
           changed to a real one. */
        $admins = db()->query("SELECT email, password_hash FROM users WHERE role = 'admin'")->fetchAll();
        if (!$admins) {
            bad('no admin account at all — did schema.sql import?');
        }
        foreach ($admins as $a) {
            $hash = (string) $a['password_hash'];
            if ($hash === '') {
                bad("admin {$a['email']} has no password yet — run tools/set_password.php");
            } elseif (!preg_match('/^\$(2y|2a|2b|argon2)/', $hash)) {
                /* Typed straight into phpMyAdmin. The column holds the *hash*,
                   not the password, so sign-in can never succeed. */
                bad("admin {$a['email']} has a password_hash that is not a hash — it looks like "
                  . 'plain text. Sign-in will always fail, because the app hashes what you type '
                  . 'and compares hashes. Do not edit this column by hand: run '
                  . 'tools/set_password.php instead, then delete the tools folder.');
            } else {
                ok("admin {$a['email']} has a usable password");
            }
        }
    } catch (Throwable $e) { /* users missing; already reported */ }

    try {
        $counts = db()->query('SELECT status, COUNT(*) n FROM payments GROUP BY status')->fetchAll();
        echo "  payments: " . ($counts
            ? implode(', ', array_map(fn($r) => "{$r['status']}={$r['n']}", $counts))
            : 'none yet') . "\n";
    } catch (Throwable $e) { /* payments missing; already reported */ }
} catch (Throwable $e) {
    bad('database: ' . $e->getMessage());
}
echo "\n";

/* ── the switch in index.html ──────────────────────────────────────────── */
/* Everything above can be perfect and the site still store nothing, because
   the app has to be told to use it. */
echo "The app\n";
$indexPath = null;
foreach ([__DIR__ . '/../../index.html', __DIR__ . '/../../../index.html'] as $p) {
    if (is_readable($p)) { $indexPath = $p; break; }
}
if (!$indexPath) {
    note('index.html not found from here — check the API line by hand.');
} else {
    $head = (string) file_get_contents($indexPath, false, null, 0, 400000);
    if (preg_match('/const\s+API\s*=\s*\{\s*base:\s*"([^"]*)"\s*,\s*enabled:\s*(true|false)/', $head, $m)) {
        if ($m[2] === 'true') {
            ok('index.html has API.enabled = true (base "' . $m[1] . '")');
        } else {
            bad('index.html still has API.enabled = false. The trainings your admin '
              . 'adds are being saved in that one browser and NOBODY ELSE CAN SEE '
              . 'THEM. Open index.html, find const API = { base:"/api", '
              . 'enabled:false }, and change false to true.');
        }
    } else {
        note('could not find the API line in index.html — check it by hand.');
    }
    if (preg_match('/const\s+SANDBOX_HELP\s*=\s*true/', $head)) {
        note('SANDBOX_HELP is true — the checkout page is showing test card '
           . 'numbers. Set it back to false before real disciples see the site.');
    }
}
echo "\n";

/* ── the .htaccess rewrite ─────────────────────────────────────────────────
   Every page has a real address now — /pricing, /trainings, /disciple-signup.
   That only works if the rewrite in public_html/.htaccess is live. When it is
   not, clicking a link inside the app still works (the browser never asks the
   server) but RELOADING that page, or opening it in a new tab, gives a 404 —
   which is exactly the kind of intermittent fault nobody can describe. So ask
   the site the question directly. */
echo "Addresses\n";
$root = site_url();
$probe = static function (string $path) use ($root): array {
    $ch = curl_init($root . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body   = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'error' => $err];
};

$r = $probe('/trainings');
if ($r['status'] === 0) {
    note('could not reach ' . $root . '/trainings from the server itself ('
       . ($r['error'] ?: 'no reply') . ') — check it in a browser instead: it must NOT be a 404.');
} elseif ($r['status'] === 404) {
    bad('GET ' . $root . '/trainings returns 404. The rewrite in '
      . 'public_html/.htaccess is missing or not being read, so every page '
      . 'except the home page breaks on reload. Upload the .htaccess that came '
      . 'with this package. If it is already there, your host may have '
      . 'AllowOverride off — ask them to enable it for this domain.');
} elseif ($r['status'] >= 200 && $r['status'] < 400 && str_contains($r['body'], '<div id="root">')) {
    ok('/trainings serves the app — the .htaccess rewrite is working');
} else {
    note('GET ' . $root . '/trainings answered ' . $r['status']
       . ' but did not look like the app. Check it in a browser.');
}

/* The address a buyer is actually sent to after paying. This is the one that
   broke on a live payment, so it is checked by name rather than by category. */
$r = $probe('/create-account/PROBE/PROBE');
if ($r['status'] === 404) {
    bad('GET ' . site_url('/create-account/...') . ' returns 404. This is where '
      . 'Maya sends a buyer the moment their payment clears, so a disciple who '
      . 'has just paid cannot make their account. Same cause as above: the '
      . 'rewrite in public_html/.htaccess.');
} elseif ($r['status'] >= 200 && $r['status'] < 400 && str_contains($r['body'], '<div id="root">')) {
    ok('/create-account/... serves the app — a buyer can finish after paying');
}

/* And the other half of the same rule: the rewrite must NOT swallow the API. */
$r = $probe('/api/content.php');
if ($r['status'] === 0) {
    note('could not reach the api from the server itself — check it in a browser.');
} elseif (str_contains($r['body'], '<div id="root">')) {
    bad('GET ' . $root . '/api/content.php returned the PAGE instead of JSON. '
      . 'The rewrite is swallowing the backend — the .htaccess is missing its '
      . '"RewriteCond %{REQUEST_URI} !^/api/" line. Nothing will save until '
      . 'that is put back.');
} elseif ($r['status'] === 200 && str_starts_with(ltrim($r['body']), '{')) {
    ok('/api/ still answers with JSON — the rewrite is not swallowing it');
} else {
    note('GET /api/content.php answered ' . $r['status'] . ' — look at it by hand.');
}
echo "\n";

/* ── email ─────────────────────────────────────────────────────────────── */
echo "Email\n";
if (!defined('MAIL_ENABLED')) {
    note('no mail settings in config.php — no receipts will be sent');
} elseif (!MAIL_ENABLED) {
    note('MAIL_ENABLED is false — no receipts, no admin alerts');
} else {
    if (defined('MAIL_SMTP_HOST') && MAIL_SMTP_HOST !== '') {
        ok('SMTP ' . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PORT . ' (' . MAIL_SMTP_SECURE . ')');
        /* 465 is implicit TLS, 587 upgrades with STARTTLS. Swapping the two is
           the usual cause of a connection that resets immediately. */
        $port = (int) MAIL_SMTP_PORT; $sec = strtolower((string) MAIL_SMTP_SECURE);
        if ($port === 465 && $sec !== 'ssl') { bad("port 465 needs MAIL_SMTP_SECURE = 'ssl', not '$sec'"); }
        if ($port === 587 && $sec !== 'tls') { bad("port 587 needs MAIL_SMTP_SECURE = 'tls', not '$sec'"); }
        if (MAIL_SMTP_USER === '')           { note('no SMTP username — most hosts will refuse to relay'); }
        elseif (MAIL_SMTP_USER !== MAIL_FROM) {
            note('MAIL_FROM (' . MAIL_FROM . ') is not the SMTP mailbox (' . MAIL_SMTP_USER . ') — '
               . 'many hosts reject that, and it hurts deliverability');
        }
    } else {
        note("using PHP mail() — it works, but on shared hosting receipts often land in spam. "
           . "Make a mailbox in hPanel -> Emails and fill in the MAIL_SMTP_* settings.");
    }

    if (!defined('MAIL_FROM') || MAIL_FROM === '' || str_contains(MAIL_FROM, 'yourdomain.com')) {
        bad('MAIL_FROM is not set to a real address on your domain — receipts will look forged');
    } else {
        $fromDomain = substr(strrchr(MAIL_FROM, '@') ?: '', 1);
        $siteDomain = preg_replace('/^www\./', '', (string) parse_url(SITE_URL, PHP_URL_HOST));
        if ($fromDomain && $siteDomain && !str_ends_with($siteDomain, $fromDomain)) {
            note("MAIL_FROM is on $fromDomain but the site is $siteDomain — expect spam filtering");
        } else {
            ok('sending as ' . MAIL_FROM);
        }
    }

    $admins = admin_notify_addresses();
    $admins ? ok('new-subscription alerts go to: ' . implode(', ', $admins))
            : bad('nobody will hear about new subscriptions — set MAIL_ADMIN_TO');

    note('send yourself one: tools/send_test_email.php?key=...&to=you@example.com&kind=buyer');
}
echo "\n";

/* ── Maya ──────────────────────────────────────────────────────────────── */
echo "Maya (" . maya_key_source() . ")\n";
if (!tgfm_is_live() && !maya_own_sandbox_keys()) {
    note("using Maya's shared sandbox keys — fine for a first look, but webhook");
    note("registration is shared with every other developer. Paste TGFM's own");
    note("sandbox keys into config.php when onboarding comes through.");
}

/* A real call: create a ₱140 checkout, then read it back. Nothing is charged
   unless somebody actually opens the returned URL and pays. */
$probeRef = 'TGFM-CHECK-' . strtoupper(bin2hex(random_bytes(3)));
$res = http_json('POST', maya_base() . '/checkout/v1/checkouts',
    ['Authorization: Basic ' . base64_encode(maya_public_key() . ':')],
    [
        'totalAmount' => ['value' => 140.00, 'currency' => CURRENCY],
        'requestReferenceNumber' => $probeRef,
        'items' => [['name' => 'Setup check — not a real order', 'quantity' => 1,
                     'totalAmount' => ['value' => 140.00, 'currency' => CURRENCY]]],
        'redirectUrl' => [
            'success' => site_url('/api/return.php?r=') . $probeRef . '&outcome=success',
            'failure' => site_url('/api/return.php?r=') . $probeRef . '&outcome=failure',
            'cancel'  => site_url('/api/return.php?r=') . $probeRef . '&outcome=cancel',
        ],
    ]);

if ($res['status'] === 0)                       { bad('could not reach Maya at all — check outbound network'); }
elseif ($res['status'] === 401)                 { bad('Maya rejected the public key (401)'); }
elseif (!empty($res['body']['redirectUrl']))    { ok('public key works — Maya returned a checkout URL'); }
else                                            { bad('Maya said HTTP ' . $res['status'] . ': ' . substr($res['raw'], 0, 200)); }

/* Secret key: list the webhooks registered against it. */
$hooks = maya_api_json('GET', '/payments/v1/webhooks', maya_secret_key());
echo '  payments API host: ' . ($hooks['host'] ?: 'none answered') . "\n";

if ($hooks['status'] === 401) {
    bad('Maya rejected the secret key (401)');
} elseif ($hooks['status'] !== 200) {
    note('could not list webhooks (HTTP ' . $hooks['status'] . ')');
} else {
    ok('secret key works');
    $mine = 0;
    foreach (($hooks['body'] ?? []) as $h) {
        $url  = (string) ($h['callbackUrl'] ?? '');
        $here = str_starts_with($url, rtrim(SITE_URL, '/'));
        if ($here) { $mine++; }
        echo '        ' . str_pad((string) ($h['name'] ?? '?'), 20)
            . ($here ? '-> this site' : '-> ' . $url) . "\n";
    }
    if ($mine === 0) {
        bad('no webhooks point at this site — run tools/register_maya_webhooks.php');
    } elseif ($mine < 4) {
        note("only $mine of the 4 payment events point here — re-run registration");
    } else {
        ok('all four payment webhooks point at this site');
    }
}
echo "\n";

/* ── PayPal ────────────────────────────────────────────────────────────── */
echo "PayPal\n";
if (str_contains(paypal_client_id(), 'REPLACE')) {
    note('not configured yet — the PayPal button will fail, Maya still works');
} else {
    require __DIR__ . '/../_paypal.php';
    paypal_token() !== null ? ok('client id and secret work')
                            : bad('PayPal rejected the credentials');
    str_contains(PAYPAL_WEBHOOK_ID, 'REPLACE')
        ? bad('PAYPAL_WEBHOOK_ID not set — webhooks cannot be verified and will be ignored')
        : ok('webhook id set');
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "$pass ok · $warn to look at · $fail to fix\n";
echo $fail === 0
    ? "\nReady to take a sandbox payment. Delete this tools folder before going live.\n"
    : "\nFix the FAIL lines above, then run this again.\n";
