<?php
/**
 * Subscription plans — read, create, edit, reorder, delete. Administrators only.
 *
 * GET   ->  { plans: [ { id, name, price, blurb, billing, scope, active, span, sold } ] }
 *
 * POST  { action, ... }
 *   save_plan    { plan:{ id, name, price, blurb, billing, scope, active } }
 *   delete_plan  { id }
 *   reorder      { order:[id,...] }
 *
 * The point of this file is the last line of the ministry's request: change
 * pricing without touching code. Nothing here lets the BROWSER set a price for
 * a checkout — checkout_*.php reads the row back out of this table itself.
 * What an admin changes here is what the next buyer is charged, and that is the
 * only path a price can travel.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

require_admin();

/* Plans predate the table on installs that have not run migrate.php. Editing
   them would silently write to nothing, so say so plainly instead. */
if (!plans_table_ready()) {
    fail('The plans table is missing. Re-import private/schema.sql, then run api/tools/migrate.php.', 503);
}

/* ── read ──────────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $plans = all_plans(true);          // admins see the withdrawn ones too

    /* How many payments each plan has behind it. Shown in the admin so the
       "this one cannot be deleted" message is not a surprise, and so nobody
       edits the price of a live plan without knowing people bought it. */
    $sold = [];
    foreach (db()->query('SELECT plan, COUNT(*) AS n FROM payments GROUP BY plan')->fetchAll() as $r) {
        $sold[(string) $r['plan']] = (int) $r['n'];
    }
    foreach ($plans as &$p) { $p['sold'] = $sold[$p['id']] ?? 0; }
    unset($p);

    json_out(['plans' => $plans]);
}

require_post();
guard_same_origin();

$in     = body_json();
$action = (string) ($in['action'] ?? '');

const BILLINGS = ['once', 'week', 'month', 'year'];
const SCOPES   = ['all', 'topic'];

function plan_id_or_fail(string $id): string {
    $id = strtolower(trim($id));
    if (!preg_match('/^[a-z0-9_-]{1,32}$/', $id)) {
        fail('A plan id may only use letters, numbers, dashes and underscores.');
    }
    return $id;
}
function plan_row(string $id): ?array {
    $st = db()->prepare('SELECT * FROM content_plans WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function payments_against(string $id): int {
    $st = db()->prepare('SELECT COUNT(*) FROM payments WHERE plan = ?');
    $st->execute([$id]);
    return (int) $st->fetchColumn();
}

switch ($action) {

case 'save_plan': {
    $p  = is_array($in['plan'] ?? null) ? $in['plan'] : [];
    $id = plan_id_or_fail((string) ($p['id'] ?? ''));

    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') { fail('Give the plan a name — it is what buyers see.'); }
    $name = mb_substr($name, 0, 120);

    /* Money is read as a number and rounded to centavos here, so "1,299" or
       "299.999" from a hand-typed field cannot become a wrong charge. */
    $price = (float) str_replace([',', ' ', '₱'], '', (string) ($p['price'] ?? '0'));
    if (!is_finite($price) || $price < 0)  { fail('A price cannot be negative.'); }
    if ($price > 999999.99)                { fail('That price is beyond what the gateway will take.'); }
    $price = round($price, 2);

    $billing = strtolower(trim((string) ($p['billing'] ?? 'month')));
    if (!in_array($billing, BILLINGS, true)) { fail('Billing must be one-time, weekly, monthly or annual.'); }

    /* Scope follows billing rather than being a second thing to get wrong: a
       one-time payment buys one topic, a recurring window buys everything.
       An explicit scope is honoured only where it makes sense. */
    $scope = strtolower(trim((string) ($p['scope'] ?? '')));
    if (!in_array($scope, SCOPES, true)) { $scope = $billing === 'once' ? 'topic' : 'all'; }
    if ($billing === 'once' && $scope !== 'topic') {
        fail('A one-time plan opens a single topic. Choose a weekly, monthly or annual plan for full access.');
    }
    if ($billing !== 'once' && $scope === 'topic') {
        fail('A single-topic plan has to be one-time — a recurring charge for one video would keep billing for something already owned.');
    }

    $blurb  = mb_substr(trim((string) ($p['blurb'] ?? '')), 0, 2000);
    $active = !empty($p['active']) ? 1 : 0;

    $existing = plan_row($id);

    if ($existing) {
        db()->prepare('UPDATE content_plans SET name = ?, price = ?, blurb = ?, billing = ?, scope = ?, active = ?
                       WHERE id = ?')
            ->execute([$name, number_format($price, 2, '.', ''), $blurb, $billing, $scope, $active, $id]);

        $was = (float) $existing['price'];
        if (abs($was - $price) > 0.001) {
            log_line(sprintf('plans: %s price %s -> %s', $id, number_format($was, 2), number_format($price, 2)));
        }
        if ((int) $existing['active'] !== $active) {
            log_line("plans: $id " . ($active ? 'back on sale' : 'withdrawn from sale'));
        }
        json_out(['ok' => true, 'id' => $id, 'created' => false]);
    }

    $st = db()->query('SELECT COALESCE(MAX(position), -1) + 1 FROM content_plans');
    db()->prepare('INSERT INTO content_plans (id, name, price, blurb, billing, scope, active, position)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$id, $name, number_format($price, 2, '.', ''), $blurb, $billing, $scope, $active,
                   (int) $st->fetchColumn()]);
    log_line("plans: created $id at " . number_format($price, 2) . " ($billing/$scope)");
    json_out(['ok' => true, 'id' => $id, 'created' => true]);
}

case 'delete_plan': {
    $id  = plan_id_or_fail((string) ($in['id'] ?? ''));
    $row = plan_row($id);
    if (!$row) { fail('That plan is already gone.', 404); }

    /* The business rule the ministry asked for. A payment row names its plan by
       id; deleting the plan would leave those receipts pointing at nothing, and
       a receipt is the one record a dispute turns on. Withdrawing it from sale
       does everything deleting was meant to do. */
    $n = payments_against($id);
    if ($n > 0) {
        fail(sprintf(
            '%s has %d payment%s against it, so it cannot be deleted — its receipts name it. Set it inactive instead and it disappears from the pricing page.',
            $row['name'], $n, $n === 1 ? '' : 's'
        ), 409);
    }

    /* Never leave the pricing page with nothing on it. */
    $liveOthers = (int) db()->query('SELECT COUNT(*) FROM content_plans WHERE active = 1')->fetchColumn()
                - ((int) $row['active'] ? 1 : 0);
    if ($liveOthers < 1) {
        fail('That is the last plan on sale. Add another before removing this one, or nobody can subscribe.', 409);
    }

    db()->prepare('DELETE FROM content_plans WHERE id = ?')->execute([$id]);
    log_line("plans: deleted $id");
    json_out(['ok' => true]);
}

case 'reorder': {
    $order = is_array($in['order'] ?? null) ? $in['order'] : [];
    $st = db()->prepare('UPDATE content_plans SET position = ? WHERE id = ?');
    foreach ($order as $i => $id) { $st->execute([$i, plan_id_or_fail((string) $id)]); }
    json_out(['ok' => true]);
}

default:
    fail('Unknown action.');
}
