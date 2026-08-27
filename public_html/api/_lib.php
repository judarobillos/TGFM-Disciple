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

/* Receipts and the ministry's alert. Loaded here so every path that can grant
   access — webhook, return page, PayPal capture — has them without thinking
   about it. Neither file does anything on include. */
require_once __DIR__ . '/_mail.php';
require_once __DIR__ . '/_notify.php';

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

/* ── the site's own addresses ──────────────────────────────────────────────
   Every link the server builds goes through here.

   SITE_URL is typed by hand into config.php, and half the people who type a
   URL end it with a slash. Concatenating '/create-account/...' onto that gives
   "https://example.com//create-account/..." — a double slash, which is a
   different path as far as the web server is concerned and is exactly the kind
   of thing that turns into a 404 nobody can explain. It cost TGFM a broken
   create-account link on a real payment.

   So the trailing slash is stripped once, here, and nothing else concatenates
   a URL by hand. */
function site_url(string $path = ''): string
{
    $base = rtrim(SITE_URL, '/');
    if ($path === '') { return $base; }
    return $base . '/' . ltrim($path, '/');
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

/* ── plans and money ───────────────────────────────────────────────────────
   Prices live in the content_plans table so the ministry can change them
   without a developer. This does NOT loosen the rule that the server states
   the price: the browser still sends only a plan id, and the amount is read
   from the row here. What moved is who may edit it, not who is trusted.

   PLANS in config.php survives as the fallback for a database that predates
   the table, so an install mid-upgrade keeps taking payments. */

function plans_table_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try { db()->query('SELECT 1 FROM content_plans LIMIT 1'); $ready = true; }
        catch (Throwable $e) { $ready = false; }
    }
    return $ready;
}

/** Every plan, newest prices, in display order. Admins also see inactive ones. */
function all_plans(bool $includeInactive = false): array {
    if (!plans_table_ready()) {
        $out = [];
        foreach (PLANS as $id => $p) {
            $out[] = ['id' => $id, 'name' => $p['name'], 'price' => (float) $p['price'],
                      'blurb' => '', 'billing' => $p['period'], 'scope' => 'all',
                      'active' => true, 'span' => $p['span'] ?? ''];
        }
        return $out;
    }
    $sql = 'SELECT id, name, price, blurb, billing, scope, active FROM content_plans'
         . ($includeInactive ? '' : ' WHERE active = 1') . ' ORDER BY position, price';
    return array_map(function (array $r): array {
        $r['price']  = (float) $r['price'];
        $r['active'] = (bool) (int) $r['active'];
        $r['blurb']  = (string) $r['blurb'];
        $r['span']   = plan_span($r['billing']);
        return $r;
    }, db()->query($sql)->fetchAll());
}

function plan_span(string $billing): string {
    return [
        'once'  => 'one teaching, kept',
        'week'  => '7 days',
        'month' => '1 month',
        'year'  => '1 year',
    ][$billing] ?? '';
}

/**
 * The plan a checkout is for. Inactive plans are refused: taking a payment for
 * something the ministry has withdrawn is worse than a failed checkout.
 */
function plan_or_fail(string $planId): array {
    if (!plans_table_ready()) {
        if (!isset(PLANS[$planId])) { fail('Unknown pass.'); }
        $p = PLANS[$planId];
        return ['id' => $planId, 'name' => $p['name'], 'price' => (float) $p['price'],
                'blurb' => '', 'billing' => $p['period'], 'scope' => 'all', 'active' => true,
                'span' => $p['span'] ?? plan_span($p['period'])];
    }
    $st = db()->prepare('SELECT id, name, price, blurb, billing, scope, active FROM content_plans WHERE id = ?');
    $st->execute([$planId]);
    $row = $st->fetch();
    if (!$row)            { fail('Unknown pass.'); }
    if (!(int) $row['active']) { fail('That subscription is not on sale at the moment.'); }
    $row['price']  = (float) $row['price'];
    $row['active'] = true;
    $row['span']   = plan_span((string) $row['billing']);
    return $row;
}

function price_for(string $planId): float {
    return (float) plan_or_fail($planId)['price'];
}
/** The `payments.period` column predates plans and still uses week/month/year. */
function period_for(string $planId): string {
    $b = (string) plan_or_fail($planId)['billing'];
    return $b === 'once' ? 'once' : $b;
}
function plan_is_single_topic(string $planId): bool {
    return (string) plan_or_fail($planId)['scope'] === 'topic';
}

/**
 * Which topic a one-off purchase is for.
 *
 * Returns null for a plan that opens everything. For a single-topic plan it
 * returns ["ref" => "training/series/topic", "label" => "Series — Topic"],
 * having checked that the topic actually exists and is published. The check
 * matters twice over: it stops a payment being taken for a video that is not
 * there, and it stops a hand-made request buying a draft.
 *
 * Resolved BEFORE the money moves, and stored on the payment row, so granting
 * the entitlement afterwards never has to trust the browser again.
 *
 * @return array{ref:string,label:string}|null
 */
function topic_choice(array $plan, array $in): ?array
{
    if ((string) ($plan['scope'] ?? 'all') !== 'topic') { return null; }

    $ids = [];
    foreach (['trainingId', 'seriesId', 'topicId'] as $k) {
        $v = trim((string) ($in[$k] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $v)) {
            fail('Choose which teaching you are buying before you pay.');
        }
        $ids[] = $v;
    }
    [$tid, $sid, $vid] = $ids;

    $st = db()->prepare(
        'SELECT v.title AS topic_title, s.title AS series_title
           FROM content_topics v
           JOIN content_series s ON s.training_id = v.training_id AND s.id = v.series_id
           JOIN content_trainings t ON t.id = v.training_id
          WHERE v.training_id = ? AND v.series_id = ? AND v.id = ?
            AND v.published = 1 AND s.published = 1 AND t.published = 1
          LIMIT 1'
    );
    $st->execute([$tid, $sid, $vid]);
    $row = $st->fetch();
    if (!$row) { fail('That teaching is not available. Pick another from the list.', 404); }

    return [
        'ref'   => "$tid/$sid/$vid",
        'label' => $row['series_title'] . ' — ' . $row['topic_title'],
    ];
}
/* ── the disciples register ────────────────────────────────────────────────
   Free, and now the first step: TGFM wants to know who is on the other side of
   a payment, so nobody subscribes until they are on this list.

   A `disciples` row is not an account. It has no password and grants no
   access — it is the ministry's record of a person: their pastor, where they
   are, the year they attended Divine Encounter. The account still arrives the
   old way, when a payment clears. Email is what ties the three together. */

function disciples_table_ready(): bool {
    static $ready = null;
    if ($ready === null) {
        try { db()->query('SELECT 1 FROM disciples LIMIT 1'); $ready = true; }
        catch (Throwable $e) { $ready = false; }
    }
    return $ready;
}

function find_disciple(string $email): ?array {
    if (!disciples_table_ready()) { return null; }
    $st = db()->prepare('SELECT * FROM disciples WHERE email = ? LIMIT 1');
    $st->execute([strtolower(trim($email))]);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * The gate on every checkout. Refuses politely and says exactly where to go —
 * a buyer who is turned away at the payment step with no explanation is a
 * buyer the ministry loses.
 *
 * Left open when the table does not exist yet, so an install that has not run
 * migrate.php keeps taking payments rather than refusing everybody.
 */
function disciple_or_fail(string $email): void {
    if (!disciples_table_ready()) {
        log_line('disciples table missing — checkout gate is open; run api/tools/migrate.php');
        return;
    }
    if (find_disciple($email)) { return; }
    fail('That email is not on the disciples register yet. Sign up as a disciple first — it is free — then come back to subscribe.', 403);
}

/** The pastors offered in the registration dropdown, in the ministry's order. */
function all_pastors(bool $includeInactive = false): array {
    try {
        $sql = 'SELECT id, name, active FROM disciple_pastors'
             . ($includeInactive ? '' : ' WHERE active = 1') . ' ORDER BY position, name';
        return array_map(static function (array $r): array {
            $r['id'] = (int) $r['id'];
            $r['active'] = (bool) (int) $r['active'];
            return $r;
        }, db()->query($sql)->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Which years the Divine Encounter dropdown offers.
 *
 * Generated rather than typed, so the list does not quietly go stale the year
 * nobody remembers to edit it. "" means "not yet" and is a legitimate answer —
 * plenty of new disciples register before their first encounter.
 */
function de_years(): array {
    $now = (int) date('Y');
    $out = [];
    for ($y = $now; $y >= 2010; $y--) { $out[] = (string) $y; }
    return $out;
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

/** A running pass? There is no free tier — this is the whole test for one. */
function has_access(array $user): bool {
    if (empty($user['access_until'])) { return false; }
    return $user['access_until'] >= date('Y-m-d');
}

/**
 * The topics this person owns outright, from Individual Teaching purchases.
 * Returned as a set of "training/series/topic" keys so a lookup is O(1).
 *
 * These never expire. Somebody who paid ₱49 for one teaching keeps it whether
 * or not they later hold a pass — which is the point of selling it separately.
 */
function owned_topics(?array $user): array {
    if (!$user) { return []; }
    static $cache = [];
    $key = (string) ($user['id'] ?? $user['email'] ?? '');
    if (isset($cache[$key])) { return $cache[$key]; }

    try {
        $st = db()->prepare(
            'SELECT training_id, series_id, topic_id FROM entitlements
             WHERE user_id = ? OR email = ?'
        );
        $st->execute([$user['id'] ?? 0, strtolower((string) ($user['email'] ?? ''))]);
        $set = [];
        foreach ($st->fetchAll() as $r) {
            $set[$r['training_id'] . '/' . $r['series_id'] . '/' . $r['topic_id']] = true;
        }
        return $cache[$key] = $set;
    } catch (Throwable $e) {
        /* No entitlements table yet — an install mid-upgrade still works. */
        return $cache[$key] = [];
    }
}

/**
 * Record a one-off purchase. Idempotent through the unique key, so a webhook
 * and the return-page confirmation both firing grants it once.
 */
function grant_entitlement(array $payment): void
{
    $ref = (string) ($payment['topic_ref'] ?? '');
    if ($ref === '') {
        log_line("entitlement: {$payment['reference']} has no topic_ref — nothing granted");
        return;
    }
    $parts = explode('/', $ref);
    if (count($parts) !== 3) {
        log_line("entitlement: {$payment['reference']} has a malformed topic_ref \"$ref\"");
        return;
    }
    try {
        db()->prepare(
            'INSERT IGNORE INTO entitlements (user_id, email, training_id, series_id, topic_id, reference)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $payment['user_id'] ?: null,
            strtolower((string) $payment['email']),
            $parts[0], $parts[1], $parts[2],
            $payment['reference'],
        ]);
        log_line("entitlement: {$payment['email']} now owns $ref (ref {$payment['reference']})");
    } catch (Throwable $e) {
        log_line('entitlement failed for ' . $payment['reference'] . ' — ' . $e->getMessage());
    }
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

        /* A one-off bought one teaching, not a window. It must not write a
           date onto the account — doing so would either hand out a day of
           everything or overwrite a pass the person is already paying for. */
        $isOnce = ($payment['period'] === 'once');

        $existing = find_user_by_email($payment['email']);
        if ($existing) {
            /* Raced with something else that made the account — just attach. */
            $userId = (int) $existing['id'];
            if (!$isOnce) {
                $pdo->prepare('UPDATE users SET plan = ?, access_until = ? WHERE id = ?')
                    ->execute([$payment['plan'], $payment['access_until'], $userId]);
            }
        } else {
            $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, plan, access_until)
                 VALUES (?, ?, ?, "disciple", ?, ?)'
            )->execute([
                $name !== '' ? $name : $payment['name'],
                $payment['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $payment['plan'],
                $isOnce ? null : $payment['access_until'],
            ]);
            $userId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('UPDATE payments SET user_id = ?, claim_token = NULL, claimed_at = NOW()
                       WHERE reference = ?')->execute([$userId, $reference]);

        /* Anything they bought before this account existed — an Individual
           Teaching purchase, or several — was held against their email. Attach
           it now so it shows up as theirs rather than floating. */
        try {
            $pdo->prepare('UPDATE entitlements SET user_id = ? WHERE user_id IS NULL AND email = ?')
                ->execute([$userId, strtolower((string) $payment['email'])]);
        } catch (Throwable $e) { /* no entitlements table on an older install */ }

        $pdo->commit();
        log_line("ACCOUNT $reference -> user $userId ({$payment['email']})");

        /* Paid AND registered — the moment the ministry asked for. Sent after
           the commit, and it cannot throw: an account that exists must not fail
           because a mail server was slow. */
        notify_account_created($reference);

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
    /* A one-off teaching buys no window at all — it buys one topic, forever,
       recorded as an entitlement instead. Returning the start date keeps
       access_until untouched by the purchase. */
    if ($period === 'once') { return $d->format('Y-m-d'); }
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
    $row += ['topic_ref' => null];
    $st = db()->prepare(
        'INSERT INTO payments (reference, user_id, email, name, plan, period, amount, currency,
                               method, status, access_until, topic_ref)
         VALUES (:reference, :user_id, :email, :name, :plan, :period, :amount, :currency,
                 :method, :status, :access_until, :topic_ref)'
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

        $user   = find_user_by_email($payment['email']);
        $isOnce = ($payment['period'] === 'once');
        $start  = window_start($user, $payment['plan']);
        $until  = $isOnce
            ? ($user['access_until'] ?? null)   // a one-off never moves the window
            : window_end($start, $payment['period']);

        if ($user) {
            /* Already a member — a pass extends them; a one-off does not. */
            $pdo->prepare(
                'UPDATE payments SET status = "paid", gateway_state = ?, access_until = ?,
                                     user_id = ?, paid_at = NOW(), raw = ? WHERE reference = ?'
            )->execute([$gatewayState, $until, $user['id'], $rawBody, $reference]);

            if (!$isOnce) {
                $pdo->prepare('UPDATE users SET plan = ?, access_until = ? WHERE id = ?')
                    ->execute([$payment['plan'], $until, $user['id']]);
            }
            $claimToken = null;
        } else {
            /* Nobody by that email. The pass is paid and held; the account is
               created when the buyer chooses a password on the next screen. */
            $claimToken = new_claim_token();
            $pdo->prepare(
                'UPDATE payments SET status = "paid", gateway_state = ?, access_until = ?,
                                     claim_token = ?, paid_at = NOW(), raw = ? WHERE reference = ?'
            )->execute([$gatewayState, $until, $claimToken, $rawBody, $reference]);
        }

        $pdo->commit();
        log_line("PAID $reference {$payment['method']} {$payment['amount']} -> {$payment['email']} "
            . ($isOnce ? "for topic {$payment['topic_ref']}" : "until $until")
            . ($user ? '' : ' (awaiting account)'));

        /* A one-off buys one topic, permanently. Granted after the commit and
           keyed uniquely, so both confirmation paths firing grants it once. If
           there is no account yet it is held against the email and attached
           when they register. */
        if ($isOnce) {
            $fresh = find_payment($reference) ?: $payment;
            grant_entitlement($fresh);
        }

        /* Receipts go out AFTER the commit, and only on the transition into
           paid — the early return above means a webhook and the return-page
           confirmation both firing still sends exactly one. A mail server that
           hangs must never hold a row lock, and a mail failure must never roll
           back a payment that genuinely cleared, so this cannot throw. */
        notify_payment_cleared($reference, $claimToken);
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
