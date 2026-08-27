<?php
/**
 * GET ?mine=1   the signed-in disciple's own payments (for the billing page)
 * GET           every payment — administrators only (for the admin Payments page)
 *
 * POST { action:'delete', ref, force? }   remove one record — administrators only
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

guard_same_origin();

function shape(array $p): array {
    return [
        'ref'     => $p['reference'],
        'member'  => $p['name'],
        'email'   => $p['email'],
        'plan'    => $p['plan'],
        'period'  => $p['period'],
        'amount'  => (float) $p['amount'],
        'method'  => $p['method'],
        'status'  => $p['status'],
        'created' => substr((string) $p['created_at'], 0, 10),
        'until'   => $p['access_until'] ?? '',
        /* Which teaching a one-off bought, so the list is readable. */
        'topic'   => (string) ($p['topic_ref'] ?? ''),
    ];
}

/* ── writes ────────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_admin();
    $in = body_json();
    if ((string) ($in['action'] ?? '') !== 'delete') { fail('Unknown action.'); }

    $ref = trim((string) ($in['ref'] ?? ''));
    $p   = $ref === '' ? null : find_payment($ref);
    if (!$p) { fail('That payment is not on file.', 404); }

    /* A pending row is not junk while its buyer is still on the gateway's page.
       Deleting it there leaves the webhook with nothing to mark, and the money
       moves with no record at all — the worst outcome this file can produce.
       Older pending rows are abandoned checkouts and clear away freely. */
    if ($p['status'] === 'pending' && empty($in['force'])) {
        $age = time() - strtotime((string) $p['created_at']);
        if ($age < 3600) {
            fail('This checkout was started less than an hour ago and may still be at the gateway. Wait for it to clear or fail, then delete it.', 409);
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        /* An entitlement is the receipt's other half. Left behind, it would keep
           a topic open with nothing on file saying it was paid for. */
        $freed = 0;
        try {
            $st = $pdo->prepare('DELETE FROM entitlements WHERE reference = ?');
            $st->execute([$ref]);
            $freed = $st->rowCount();
        } catch (Throwable $e) { /* no entitlements table on an older install */ }

        $pdo->prepare('DELETE FROM payments WHERE reference = ?')->execute([$ref]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_line("payments: delete $ref failed — " . $e->getMessage());
        fail('That record could not be deleted.', 500);
    }

    log_line(sprintf('payments: deleted %s (%s, %s %s)', $ref, $p['status'], $p['plan'], $p['amount']));

    /* A pass already granted is NOT taken back here. Deleting the paper does
       not undo the sale, and cutting off a paying disciple by tidying a list
       would be a nasty surprise. The Disciples page is where access is changed. */
    $note = '';
    if ($p['status'] === 'paid') {
        $note = $freed
            ? 'Record removed, and the teaching it bought is closed again.'
            : 'Record removed. Any pass it granted is still running — change that on the Disciples page.';
    }
    json_out(['ok' => true, 'note' => $note]);
}

/* ── reads ─────────────────────────────────────────────────────────────── */
if (!empty($_GET['mine'])) {
    $user = require_user();
    $st = db()->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 200');
    $st->execute([$user['id']]);
    json_out(['payments' => array_map('shape', $st->fetchAll())]);
}

require_admin();
$rows = db()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 500')->fetchAll();
json_out(['payments' => array_map('shape', $rows)]);
