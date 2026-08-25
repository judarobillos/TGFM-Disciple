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
            'success' => SITE_URL . '/api/return.php?r=' . $probeRef . '&outcome=success',
            'failure' => SITE_URL . '/api/return.php?r=' . $probeRef . '&outcome=failure',
            'cancel'  => SITE_URL . '/api/return.php?r=' . $probeRef . '&outcome=cancel',
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
