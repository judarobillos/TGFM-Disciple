<?php
/**
 * The disciples, for the admin screens. Administrators only.
 *
 * GET                      -> { members: [...] }
 * POST { action:"set_plan", id, plan }    move someone onto another pass
 * POST { action:"set_until", id, until }  correct an access date by hand
 * POST { action:"delete", id }            remove an account
 *
 * Without this the Disciples tab showed the seeded demo people forever — real
 * sign-ups never appeared, because there was no endpoint to fetch them from.
 *
 * `status` is DERIVED here, not stored. A disciple is active while their
 * access_until is today or later, and lapsed once it passes; there is no state
 * to keep in sync and nothing to go stale, because there is no auto-renewal.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

guard_same_origin();
require_admin();

function shape_member(array $u): array {
    $until  = (string) ($u['access_until'] ?? '');
    $active = $until !== '' && $until >= date('Y-m-d');
    return [
        'id'     => (string) $u['id'],
        'name'   => $u['name'],
        'email'  => $u['email'],
        'plan'   => $u['plan'],
        'status' => $active ? 'active' : 'lapsed',
        'until'  => $until,
        'joined' => substr((string) $u['created_at'], 0, 10),
        'role'   => $u['role'],
        'paid'   => (int) ($u['paid_count'] ?? 0),
        'spent'  => (float) ($u['spent'] ?? 0),
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    /* One query, with each disciple's payment history folded in — the admin
       list wants "how many passes have they bought" beside every row. */
    $rows = db()->query(
        'SELECT u.*,
                (SELECT COUNT(*)            FROM payments p WHERE p.user_id = u.id AND p.status = "paid") AS paid_count,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.user_id = u.id AND p.status = "paid") AS spent
         FROM users u
         ORDER BY u.created_at DESC, u.id DESC
         LIMIT 1000'
    )->fetchAll();

    json_out(['members' => array_map('shape_member', $rows)]);
}

require_post();
$in     = body_json();
$action = (string) ($in['action'] ?? '');
$id     = (int) ($in['id'] ?? 0);
if ($id <= 0) { fail('Which disciple?'); }

$st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([$id]);
$target = $st->fetch();
if (!$target) { fail('No such account.', 404); }

$me = current_user();

switch ($action) {

case 'set_plan': {
    $plan = (string) ($in['plan'] ?? '');
    plan_or_fail($plan);                       // rejects anything not in PLANS
    db()->prepare('UPDATE users SET plan = ? WHERE id = ?')->execute([$plan, $id]);
    log_line("admin: {$me['email']} moved {$target['email']} to $plan");
    json_out(['ok' => true]);
}

case 'set_until': {
    /* The manual lever behind refunds and goodwill extensions. */
    $until = (string) ($in['until'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) { fail('Use a date like 2026-12-31.'); }
    db()->prepare('UPDATE users SET access_until = ? WHERE id = ?')->execute([$until, $id]);
    log_line("admin: {$me['email']} set {$target['email']} access_until to $until");
    json_out(['ok' => true]);
}

case 'delete': {
    /* Two guards. Deleting yourself locks you out of your own site, and
       deleting the last admin locks everyone out of it. */
    if ((int) $target['id'] === (int) $me['id']) {
        fail('You cannot delete the account you are signed in with.');
    }
    if ($target['role'] === 'admin') {
        $admins = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($admins <= 1) { fail('That is the only administrator account. Make another one first.'); }
    }

    /* Their payments are kept — they are the ministry's financial record, and
       deleting a person must not delete evidence of what they paid. The rows
       are simply detached from the account. */
    db()->prepare('UPDATE payments SET user_id = NULL WHERE user_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    log_line("admin: {$me['email']} deleted account {$target['email']} (payments kept)");
    json_out(['ok' => true]);
}

}

fail('Unknown action.', 404);
