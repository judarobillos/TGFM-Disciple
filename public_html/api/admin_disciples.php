<?php
/**
 * The Registered Disciples list. Administrators only.
 *
 * GET   ->  { disciples:[ {...} ], pastors:[ {...} ], counts:{...} }
 * GET ?csv=1  ->  the same list as a CSV download
 *
 * POST { action, ... }
 *   delete_disciple  { id }
 *   save_pastor      { id?, name, active }
 *   delete_pastor    { id }
 *
 * Every row here is somebody the ministry has met. It is not the accounts
 * table — a disciple may be registered for months before they ever subscribe —
 * so the two are shown side by side: whether they have a running pass, and how
 * much they have paid, joined onto the register by email.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

require_admin();

if (!disciples_table_ready()) {
    fail('The disciples register is missing. Re-import private/schema.sql, then run api/tools/migrate.php.', 503);
}

function disciple_rows(): array
{
    /* One query, not one per row. The joins are left joins because the whole
       point of the register is that it holds people who have not paid. */
    $sql = "SELECT d.*,
                   u.id            AS user_id,
                   u.plan          AS plan,
                   u.access_until  AS access_until,
                   COALESCE(p.paid_count, 0) AS paid_count,
                   COALESCE(p.paid_total, 0) AS paid_total
              FROM disciples d
              LEFT JOIN users u ON u.email = d.email
              LEFT JOIN (
                   SELECT email,
                          COUNT(*)      AS paid_count,
                          SUM(amount)   AS paid_total
                     FROM payments
                    WHERE status = 'paid'
                    GROUP BY email
              ) p ON p.email = d.email
             ORDER BY d.created_at DESC, d.id DESC";

    $today = date('Y-m-d');
    return array_map(static function (array $r) use ($today): array {
        $until = (string) ($r['access_until'] ?? '');
        return [
            'id'       => (int) $r['id'],
            'name'     => $r['name'],
            'email'    => $r['email'],
            'phone'    => $r['phone'],
            'gender'   => $r['gender'],
            'location' => $r['location'],
            'pastor'   => $r['pastor'],
            'deYear'   => $r['de_year'],
            'joined'   => substr((string) $r['created_at'], 0, 10),
            /* Derived, never stored: a subscription is running or it is not,
               and the only thing that decides it is today's date. */
            'hasAccount' => $r['user_id'] !== null,
            'plan'       => (string) ($r['plan'] ?? ''),
            'until'      => $until,
            'subscribed' => $until !== '' && $until >= $today,
            'paid'       => (int) $r['paid_count'],
            'spent'      => (float) $r['paid_total'],
        ];
    }, db()->query($sql)->fetchAll());
}

/* ── read ──────────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $rows = disciple_rows();

    /* A spreadsheet is how a ministry office actually works — this is the one
       screen most likely to be printed, sorted and handed round. */
    if (!empty($_GET['csv'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tgfm-disciples-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        /* Excel opens UTF-8 CSV as mojibake without this. The ministry's names
           carry ñ, so the BOM is not optional. */
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Name', 'Email', 'Mobile', 'Gender', 'Location', 'Pastor',
                       'Divine Encounter', 'Registered', 'Subscription', 'Access until', 'Payments', 'Total paid']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['name'], $r['email'], $r['phone'], $r['gender'], $r['location'], $r['pastor'],
                $r['deYear'] !== '' ? $r['deYear'] : 'Not yet',
                $r['joined'],
                $r['subscribed'] ? $r['plan'] : ($r['hasAccount'] ? 'lapsed' : 'none'),
                $r['until'], $r['paid'], number_format($r['spent'], 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }

    $subscribed = 0;
    $byPastor   = [];
    foreach ($rows as $r) {
        if ($r['subscribed']) { $subscribed++; }
        $byPastor[$r['pastor']] = ($byPastor[$r['pastor']] ?? 0) + 1;
    }
    arsort($byPastor);

    json_out([
        'disciples' => $rows,
        'pastors'   => all_pastors(true),
        'counts'    => [
            'total'      => count($rows),
            'subscribed' => $subscribed,
            'byPastor'   => $byPastor,
        ],
    ]);
}

/* ── writes ────────────────────────────────────────────────────────────── */
require_post();
guard_same_origin();

$in     = body_json();
$action = (string) ($in['action'] ?? '');

switch ($action) {

case 'delete_disciple': {
    $id = (int) ($in['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM disciples WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) { fail('That disciple is not on the register.', 404); }

    /* Removing somebody from the register does NOT touch their account or
       their payments — it only means the ministry no longer holds their
       details. Their pass keeps running, because they paid for it. What it
       does mean is that they will be asked to register again before their
       next subscription, which is the point. */
    db()->prepare('DELETE FROM disciples WHERE id = ?')->execute([$id]);
    log_line("disciples: removed {$d['email']} from the register");
    json_out(['ok' => true,
        'note' => 'Removed from the register. Their account and payments are untouched — they will be asked to register again before subscribing next time.']);
}

case 'save_pastor': {
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') { fail('Give the pastor a name.'); }
    $name   = mb_substr($name, 0, 120);
    $active = !empty($in['active']) ? 1 : 0;
    $id     = (int) ($in['id'] ?? 0);

    if ($id > 0) {
        $st = db()->prepare('SELECT name FROM disciple_pastors WHERE id = ?');
        $st->execute([$id]);
        $was = (string) $st->fetchColumn();
        if ($was === '') { fail('That pastor is no longer on the list.', 404); }

        db()->prepare('UPDATE disciple_pastors SET name = ?, active = ? WHERE id = ?')
            ->execute([$name, $active, $id]);

        /* Renaming has to carry the disciples with it, or the records point at
           a name that no longer exists anywhere. */
        if ($was !== $name) {
            db()->prepare('UPDATE disciples SET pastor = ? WHERE pastor = ?')->execute([$name, $was]);
            log_line("pastors: renamed \"$was\" to \"$name\" (records moved with it)");
        }
        json_out(['ok' => true, 'created' => false]);
    }

    $next = (int) db()->query('SELECT COALESCE(MAX(position), -1) + 1 FROM disciple_pastors')->fetchColumn();
    try {
        db()->prepare('INSERT INTO disciple_pastors (name, active, position) VALUES (?, ?, ?)')
            ->execute([$name, $active, $next]);
    } catch (Throwable $e) {
        fail('That name is already on the list.', 409);
    }
    log_line("pastors: added \"$name\"");
    json_out(['ok' => true, 'created' => true]);
}

case 'delete_pastor': {
    $id = (int) ($in['id'] ?? 0);
    $st = db()->prepare('SELECT name FROM disciple_pastors WHERE id = ?');
    $st->execute([$id]);
    $name = (string) $st->fetchColumn();
    if ($name === '') { fail('That pastor is already gone.', 404); }

    /* The same rule the plans follow: a name somebody's record points at
       cannot be deleted, only retired. Otherwise the register quietly loses
       who a disciple belongs to. */
    $st = db()->prepare('SELECT COUNT(*) FROM disciples WHERE pastor = ?');
    $st->execute([$name]);
    $n = (int) $st->fetchColumn();
    if ($n > 0) {
        fail(sprintf('%d disciple%s registered under %s, so the name cannot be deleted. Set it inactive instead and it disappears from the sign-up form.',
            $n, $n === 1 ? ' is' : 's are', $name), 409);
    }

    db()->prepare('DELETE FROM disciple_pastors WHERE id = ?')->execute([$id]);
    log_line("pastors: deleted \"$name\"");
    json_out(['ok' => true]);
}

default:
    fail('Unknown action.');
}
