<?php
/**
 * Shared helpers: database, JSON replies, HTTP calls, access granting.
 * Every endpoint in this folder starts by requiring this file.
 */

declare(strict_types=1);

/* Find config.php wherever it was put. __DIR__ is always this api folder, no
   matter which file did the require — including the ones in api/tools/.

   Three levels of "up" are tried because a subdomain often lives in a folder
   inside public_html, which pushes the account root one step further away than
   it is for a main domain. */
$tgfm_config_candidates = [
    __DIR__ . '/../../../private/config.php',  // subdomain in a folder: account root
    __DIR__ . '/../../private/config.php',     // main domain: outside public_html
    __DIR__ . '/../private/config.php',        // beside index.html — fine, .htaccess denies it
    __DIR__ . '/config.php',                   // last resort
];
$tgfm_loaded = false;
foreach ($tgfm_config_candidates as $path) {
    if (is_readable($path)) { require_once $path; $tgfm_loaded = true; break; }
}
if (!$tgfm_loaded) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    /* Say WHERE it looked. "Not found" on its own leaves you guessing at a
       folder layout you cannot see from a browser. */
    echo "config.php not found.\n\n";
    echo "This api folder is:\n  " . __DIR__ . "\n\n";
    echo "Looked for config.php in each of these, in order:\n";
    /* realpath() returns false for a folder that is not there, which is exactly
       the case worth showing — so tidy the ".." out by hand instead. */
    $tidy = static function (string $p): string {
        $out = [];
        foreach (explode('/', $p) as $seg) {
            if ($seg === '..') { array_pop($out); }
            elseif ($seg !== '.') { $out[] = $seg; }
        }
        return implode('/', $out);
    };
    foreach ($tgfm_config_candidates as $path) {
        $exists = is_dir(dirname($path));
        echo '  ' . $tidy($path) . ($exists ? '' : '   (no such folder)') . "\n";
    }
    echo "\nUpload the private folder to whichever of those you can reach, then\n";
    echo "reload this page. The private/.htaccess that ships with it blocks web\n";
    echo "access to config.php, so any of them is safe. See api/README.md.\n";
    exit;
}

session_start();

/* ── database ──────────────────────────────────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* ── replies ───────────────────────────────────────────────────────────── */
function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $message, int $code = 400): never {
    json_out(['error' => $message], $code);
}
function body_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ── logging ───────────────────────────────────────────────────────────── */
function log_line(string $message): void {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
    @file_put_contents($dir . '/tgfm-' . date('Y-m') . '.log',
        '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/* ── outbound HTTP ─────────────────────────────────────────────────────── */
/**
 * @return array{status:int, body:array, raw:string}
 */
function http_json(string $method, string $url, array $headers = [], ?array $payload = null): array {
    $ch = curl_init($url);
    $headers[] = 'Accept: application/json';
    if ($payload !== null) { $headers[] = 'Content-Type: application/json'; }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        log_line("HTTP $method $url failed: $err");
        return ['status' => 0, 'body' => [], 'raw' => ''];
    }
    $decoded = json_decode($raw, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : [], 'raw' => $raw];
}

/* ── plans and money ───────────────────────────────────────────────────── */
function plan_or_fail(string $planId): array {
    if (!isset(PLANS[$planId])) { fail('Unknown pass.'); }
    return PLANS[$planId];
}
function price_for(string $planId): float {
    return (float) plan_or_fail($planId)['price'];
}
function period_for(string $planId): string {
    return (string) plan_or_fail($planId)['period'];
}
function new_reference(): string {
    // Short, unique, and safe for Maya's 36-character requestReferenceNumber.
    return 'TGFM-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/* ── users and access ──────────────────────────────────────────────────── */
function find_user_by_email(string $email): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([strtolower(trim($email))]);
    $row = $st->fetch();
    return $row ?: null;
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) { return null; }
    $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([$_SESSION['user_id']]);
    $row = $st->fetch();
    return $row ?: null;
}
function require_user(): array {
    $user = current_user();
    if (!$user) { fail('Please sign in first.', 401); }
    return $user;
}
function require_admin(): array {
    $user = require_user();
    if ($user['role'] !== 'admin') { fail('Administrators only.', 403); }
    return $user;
}

/** Access still valid today? There is no free tier — this is the whole test. */
function has_access(array $user): bool {
    if (empty($user['access_until'])) { return false; }
    return $user['access_until'] >= date('Y-m-d');
}

/**
 * Where a new window should start: today, or the end of the window they are
 * already inside, so buying early never loses days. Any pass stacks on any
 * other — they all open the same library.
 */
function window_start(?array $user, string $planId): string
{
    if ($user && !empty($user['access_until']) && $user['access_until'] >= date('Y-m-d')) {
        return $user['access_until'];
    }
    return date('Y-m-d');
}

/**
 * Turn a cleared payment into an account. This is the ONLY way a user row is
 * ever created (bar the seeded admin), and it works once: the claim token is
 * cleared inside the same transaction, so a replayed request finds nothing.
 */
function claim_account(string $reference, string $claimToken, string $name, string $password): array
{
    if (strlen($password) < 8) { fail('Use at least 8 characters for your password.'); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM payments WHERE reference = ? FOR UPDATE');
        $st->execute([$reference]);
        $payment = $st->fetch();

        if (!$payment
            || $payment['status'] !== 'paid'
            || empty($payment['claim_token'])
            || !hash_equals($payment['claim_token'], $claimToken)) {
            $pdo->rollBack();
            fail('This link is no longer valid. Try signing in, or send TGFM your reference number.', 410);
        }

        $existing = find_user_by_email($payment['email']);
        if ($existing) {
            /* Raced with something else that made the account — just attach. */
            $userId = (int) $existing['id'];
            $pdo->prepare('UPDATE users SET plan = ?, access_until = ? WHERE id = ?')
                ->execute([$payment['plan'], $payment['access_until'], $userId]);
        } else {
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, plan, access_until)
                 VALUES (?, ?, ?, "disciple", ?, ?)'
            )->execute([
                $name !== '' ? $name : $payment['name'],
                $payment['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $payment['plan'],
                $payment['access_until'],
            ]);
            $userId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('UPDATE payments SET user_id = ?, claim_token = NULL, claimed_at = NOW()
                       WHERE reference = ?')->execute([$userId, $reference]);

        $pdo->commit();
        log_line("ACCOUNT $reference -> user $userId ({$payment['email']})");

        $st = db()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$userId]);
        return $st->fetch();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
/**
 * Add one pass length, clamped to the end of the target month.
 *
 * PHP's "+1 month" on 31 January lands on 3 March, which would quietly hand out
 * three free days. 31 Jan + 1 month is 28 Feb here; 29 Feb + 1 year is 28 Feb.
 */
function window_end(string $startDate, string $period): string
{
    $d = new DateTimeImmutable($startDate);
    if ($period === 'week') { return $d->modify('+7 days')->format('Y-m-d'); }

    $day   = (int) $d->format('j');
    $first = $d->modify($period === 'year' ? 'first day of this month +1 year'
                                           : 'first day of next month');
    $last  = (int) $first->format('t');

    return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last))
                 ->format('Y-m-d');
}

/* ── payment records ───────────────────────────────────────────────────── */
function create_payment(array $row): void {
    $st = db()->prepare(
        'INSERT INTO payments (reference, user_id, email, name, plan, period, amount, currency,
                               method, status, access_until)
         VALUES (:reference, :user_id, :email, :name, :plan, :period, :amount, :currency,
                 :method, :status, :access_until)'
    );
    $st->execute($row);
}
function new_claim_token(): string { return bin2hex(random_bytes(16)); }
function find_payment(string $reference): ?array {
    $st = db()->prepare('SELECT * FROM payments WHERE reference = ? LIMIT 1');
    $st->execute([$reference]);
    $row = $st->fetch();
    return $row ?: null;
}
function find_payment_by_gateway(string $gatewayId): ?array {
    $st = db()->prepare('SELECT * FROM payments WHERE gateway_id = ? LIMIT 1');
    $st->execute([$gatewayId]);
    $row = $st->fetch();
    return $row ?: null;
}
function set_gateway_id(string $reference, string $gatewayId): void {
    $st = db()->prepare('UPDATE payments SET gateway_id = ? WHERE reference = ?');
    $st->execute([$gatewayId, $reference]);
}

/**
 * The only place access is ever granted. Called from the webhook handlers and
 * from the PayPal capture — never from anything the browser can reach directly.
 * Safe to call twice: a payment already marked paid is left alone.
 */
function mark_paid(string $reference, string $gatewayState, string $rawBody = ''): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM payments WHERE reference = ? FOR UPDATE');
        $st->execute([$reference]);
        $payment = $st->fetch();

        if (!$payment) { $pdo->rollBack(); log_line("mark_paid: unknown reference $reference"); return false; }
        if ($payment['status'] === 'paid') { $pdo->commit(); return true; }   // already handled

        $user  = find_user_by_email($payment['email']);
        $start = window_start($user, $payment['plan']);
        $until = window_end($start, $payment['period']);

        if ($user) {
            /* Already a member — this pass simply extends them. */
            $pdo->prepare(
                'UPDATE payments SET status = "paid", gateway_state = ?, access_until = ?,
                                     user_id = ?, paid_at = NOW(), raw = ? WHERE reference = ?'
            )->execute([$gatewayState, $until, $user['id'], $rawBody, $reference]);

            $pdo->prepare('UPDATE users SET plan = ?, access_until = ? WHERE id = ?')
                ->execute([$payment['plan'], $until, $user['id']]);
        } else {
            /* Nobody by that email. The pass is paid and held; the account is
               created when the buyer chooses a password on the next screen. */
            $pdo->prepare(
                'UPDATE payments SET status = "paid", gateway_state = ?, access_until = ?,
                                     claim_token = ?, paid_at = NOW(), raw = ? WHERE reference = ?'
            )->execute([$gatewayState, $until, new_claim_token(), $rawBody, $reference]);
        }

        $pdo->commit();
        log_line("PAID $reference {$payment['method']} {$payment['amount']} -> {$payment['email']} until $until"
            . ($user ? '' : ' (awaiting account)'));
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_line('mark_paid failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Which host actually answers Maya's payments API.
 *
 * This bit me. The Checkout API lives on pg-sandbox.paymaya.com, and so does
 * the *payments* API — /payments/v1/... — but config.php pointed the payments
 * calls at sandbox-api.paymaya.com, an older host. Every verification call
 * therefore failed, which is why a webhook could arrive and still grant nobody
 * anything, and why asking Maya on the return did nothing either.
 *
 * Rather than pick one and be wrong again, the calls try each host in turn.
 * Whatever an already-installed config.php says is kept as a last candidate, so
 * nobody has to re-edit a file they have already put their keys in.
 *
 * https://developers.maya.ph/reference/getpaymentstatusviapaymentid-1
 */
function maya_api_hosts(): array {
    $hosts = tgfm_is_live()
        ? ['https://pg.maya.ph', 'https://pg.paymaya.com', 'https://api.paymaya.com']
        : ['https://pg-sandbox.paymaya.com', 'https://sandbox-api.paymaya.com'];

    if (function_exists('maya_api_base')) {
        $installed = rtrim(maya_api_base(), '/');
        if ($installed !== '' && !in_array($installed, $hosts, true)) { $hosts[] = $installed; }
    }
    return $hosts;
}

/**
 * One call against Maya's payments API, tried across the candidate hosts.
 * Returns the first real answer; the extra 'host' key says which one gave it,
 * which is what the diagnostics print.
 *
 * @return array{status:int, body:array, raw:string, host:string}
 */
function maya_api_json(string $method, string $path, string $key, ?array $payload = null): array
{
    $auth  = 'Authorization: Basic ' . base64_encode($key . ':');
    $first = null;

    foreach (maya_api_hosts() as $host) {
        $res = http_json($method, $host . $path, [$auth], $payload);
        $res['host'] = $host;
        if ($first === null) { $first = $res; }

        /* A 200 settles it. So does any deliberate error the API itself raised
           — 401, 422 and friends mean we reached the right service. Only a 0
           (could not connect) or a 404 (wrong host, or genuinely nothing there)
           is worth trying the next host for. */
        if ($res['status'] === 200) { return $res; }
        if ($res['status'] !== 0 && $res['status'] !== 404) { return $res; }
    }
    return $first ?? ['status' => 0, 'body' => [], 'raw' => '', 'host' => ''];
}

/**
 * Ask Maya directly what happened to one payment, and settle it.
 *
 * The webhook is the designed path, but it is not the only one that has to
 * work: webhooks need to be registered, and a registration can be missing,
 * pointed elsewhere, or simply late. Without this, a buyer whose money has left
 * their wallet lands on a receipt that says "pending" and can never make an
 * account. So the return page asks as well.
 *
 * Uses Retrieve Payment via RRN — our own reference is the RRN — which is
 * authenticated with the SECRET key and answers with an ARRAY of payments.
 * https://developers.maya.ph/reference/getpaymentviarequestreferencenumber-1
 *
 * Safe to call repeatedly: mark_paid() is idempotent and a settled payment
 * returns early.
 */
function maya_confirm(string $reference): ?array
{
    $payment = find_payment($reference);
    if (!$payment || $payment['method'] !== 'maya') { return $payment; }
    if ($payment['status'] !== 'pending') { return $payment; }   // already settled

    $res = maya_api_json('GET', '/payments/v1/payment-rrns/' . rawurlencode($reference), maya_secret_key());

    if ($res['status'] !== 200) {
        log_line("maya_confirm $reference: HTTP {$res['status']} via {$res['host']} {$res['raw']}");
        return $payment;
    }

    /* The endpoint answers with an array; a single object is accepted too, in
       case the shape ever changes under us. */
    $rows = $res['body'];
    if (isset($rows['status']) || isset($rows['id'])) { $rows = [$rows]; }
    if (!is_array($rows) || !$rows) { return $payment; }

    /* Prefer an entry that actually succeeded — a buyer who failed once and
       retried has more than one row against the same reference. */
    $chosen = null;
    foreach ($rows as $row) {
        if (!is_array($row)) { continue; }
        $s = (string) ($row['status'] ?? '');
        if (in_array($s, ['PAYMENT_SUCCESS', 'CAPTURED', 'DONE'], true)) { $chosen = $row; break; }
        if ($chosen === null) { $chosen = $row; }
    }
    if ($chosen === null) { return $payment; }

    $status = (string) ($chosen['status'] ?? '');
    $paid   = (float) ($chosen['amount'] ?? $chosen['totalAmount']['value'] ?? 0);
    $raw    = json_encode($chosen, JSON_UNESCAPED_SLASHES) ?: '';

    if (!empty($chosen['id'])) { set_gateway_id($reference, (string) $chosen['id']); }

    if (in_array($status, ['PAYMENT_SUCCESS', 'CAPTURED', 'DONE'], true)) {
        /* Same amount check the webhook makes. The server is the only authority
           on price, here as everywhere. */
        if ($paid > 0 && abs($paid - (float) $payment['amount']) > 0.01) {
            log_line("maya_confirm amount mismatch on $reference: paid $paid expected {$payment['amount']}");
            mark_unpaid($reference, 'failed', 'AMOUNT_MISMATCH', $raw);
            return find_payment($reference);
        }
        mark_paid($reference, $status, $raw);
        log_line("maya_confirm settled $reference on return ($status)");
        return find_payment($reference);
    }

    if (in_array($status, ['PAYMENT_FAILED', 'AUTH_FAILED'], true)) {
        mark_unpaid($reference, 'failed', $status, $raw);
    } elseif (in_array($status, ['PAYMENT_EXPIRED', 'PAYMENT_CANCELLED', 'VOIDED'], true)) {
        mark_unpaid($reference, 'cancelled', $status, $raw);
    }
    return find_payment($reference);
}

/**
 * Remember, in this browser's own session, which references it started. The
 * receipt page is public — there is no account yet — so this is what lets it
 * show a payment to the person who made it and to nobody else.
 */
function remember_reference(string $reference): void {
    if (!isset($_SESSION['tgfm_refs']) || !is_array($_SESSION['tgfm_refs'])) { $_SESSION['tgfm_refs'] = []; }
    if (!in_array($reference, $_SESSION['tgfm_refs'], true)) { $_SESSION['tgfm_refs'][] = $reference; }
    $_SESSION['tgfm_refs'] = array_slice($_SESSION['tgfm_refs'], -20);
}
function session_owns_reference(string $reference): bool {
    return isset($_SESSION['tgfm_refs'])
        && is_array($_SESSION['tgfm_refs'])
        && in_array($reference, $_SESSION['tgfm_refs'], true);
}

function mark_unpaid(string $reference, string $status, string $gatewayState, string $rawBody = ''): void
{
    $allowed = ['failed', 'cancelled', 'refunded'];
    if (!in_array($status, $allowed, true)) { $status = 'failed'; }
    db()->prepare('UPDATE payments SET status = ?, gateway_state = ?, raw = ?
                   WHERE reference = ? AND status <> "paid"')
        ->execute([$status, $gatewayState, $rawBody, $reference]);
    log_line("$status $reference ($gatewayState)");
}

/* ── CORS: same-origin only ────────────────────────────────────────────── */
function guard_same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && rtrim($origin, '/') !== rtrim(SITE_URL, '/')) {
        fail('Cross-origin requests are not allowed.', 403);
    }
}
function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { fail('POST only.', 405); }
}
