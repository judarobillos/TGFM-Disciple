<?php
/**
 * What does Maya actually say about one payment?
 *
 *   https://yourdomain.com/api/tools/check_payment.php?key=YOUR_MAYA_SECRET_KEY&ref=TGFM-2608-XXXXXXXX
 *
 * Add &settle=1 to act on the answer — mark it paid (or failed) exactly as the
 * webhook would. Without it this only looks and reports.
 *
 * Written because "still confirming" on its own tells you nothing about WHY.
 * This prints the database row, every host tried, the HTTP status from each,
 * and Maya's raw reply. Delete it with the rest of the tools folder.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= the Maya secret key currently in use to run this.\n");
}

$ref = trim((string) ($_GET['ref'] ?? ''));
echo "TGFM payment check — " . date('c') . "\n";
echo str_repeat('=', 60) . "\n\n";
echo "Keys in use:  " . maya_key_source() . "\n";
echo "Checkout host: " . maya_base() . "\n";
echo "Payments hosts to try: " . implode(', ', maya_api_hosts()) . "\n\n";

if ($ref === '') {
    /* No reference given — list what is waiting, so you can pick one. */
    echo "No ?ref= given. The most recent payments:\n\n";
    $rows = db()->query('SELECT reference, email, plan, amount, method, status, created_at
                         FROM payments ORDER BY id DESC LIMIT 20')->fetchAll();
    if (!$rows) { exit("  (none yet)\n"); }
    foreach ($rows as $r) {
        printf("  %-22s %-9s %8s  %-8s %-26s %s\n",
            $r['reference'], $r['status'], $r['amount'], $r['method'], $r['email'], $r['created_at']);
    }
    echo "\nRe-run with &ref=ONE-OF-THESE\n";
    exit;
}

$payment = find_payment($ref);
if (!$payment) { exit("No payment with reference $ref in the database.\n"); }

echo "In the database\n";
foreach (['reference', 'email', 'name', 'plan', 'amount', 'method', 'status',
          'gateway_id', 'gateway_state', 'access_until', 'created_at', 'paid_at'] as $f) {
    printf("  %-14s %s\n", $f, (string) ($payment[$f] ?? ''));
}
echo '  claim_token    ' . (empty($payment['claim_token']) ? '(none)' : 'set — the create-account link works') . "\n\n";

/* ── ask Maya, host by host, and show every answer ─────────────────────── */
echo "Asking Maya (Retrieve Payment via RRN)\n";
$auth  = 'Authorization: Basic ' . base64_encode(maya_secret_key() . ':');
$path  = '/payments/v1/payment-rrns/' . rawurlencode($ref);
$best  = null;

foreach (maya_api_hosts() as $host) {
    $res = http_json('GET', $host . $path, [$auth]);
    printf("  %-40s HTTP %d\n", $host, $res['status']);
    if ($res['status'] === 0) {
        echo "      could not connect — DNS, firewall, or the host is wrong\n";
    } else {
        echo '      ' . substr(preg_replace('/\s+/', ' ', $res['raw']) ?? '', 0, 600) . "\n";
    }
    if ($res['status'] === 200 && $best === null) { $best = $res; }
}
echo "\n";

if ($best === null) {
    echo "No host returned 200.\n";
    echo "  401  -> the secret key is wrong, or belongs to a different environment\n";
    echo "  404  -> Maya has no payment under this reference. If the buyer really\n";
    echo "          paid, the checkout was created with a DIFFERENT key pair than\n";
    echo "          the one in config.php now.\n";
    echo "  0    -> no outbound HTTPS from this server; ask Hostinger\n";
    exit;
}

$rows = $best['body'];
if (isset($rows['status']) || isset($rows['id'])) { $rows = [$rows]; }
echo 'Maya knows of ' . count($rows) . " payment(s) under this reference:\n";
foreach ($rows as $i => $row) {
    if (!is_array($row)) { continue; }
    printf("  [%d] status=%-18s amount=%-10s id=%s\n", $i,
        (string) ($row['status'] ?? '?'),
        (string) ($row['amount'] ?? $row['totalAmount']['value'] ?? '?'),
        (string) ($row['id'] ?? '?'));
}
echo "\n";

if (empty($_GET['settle'])) {
    echo "Nothing was changed. Add &settle=1 to apply this answer to the database.\n";
    exit;
}

$before = $payment['status'];
$after  = maya_confirm($ref);
$now    = $after['status'] ?? $before;

echo "Settled: $before -> $now\n";
if ($now === 'paid') {
    echo "Access runs until " . (string) ($after['access_until'] ?? '') . ".\n";
    if (!empty($after['claim_token'])) {
        echo "\nThe buyer can now create their account at:\n";
        echo '  ' . SITE_URL . '/#/create-account/' . rawurlencode($ref) . '/' . $after['claim_token'] . "\n";
        echo "\nThat link works ONCE. Send it only to the person who paid.\n";
    } else {
        echo "Already a member — their access was extended instead.\n";
    }
} else {
    echo "Maya did not report a successful payment, so nothing was granted.\n";
}
