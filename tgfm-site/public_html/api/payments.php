<?php
/**
 * GET ?mine=1   the signed-in disciple's own payments (for the billing page)
 * GET           every payment — administrators only (for the admin Payments page)
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
    ];
}

if (!empty($_GET['mine'])) {
    $user = require_user();
    $st = db()->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 200');
    $st->execute([$user['id']]);
    json_out(['payments' => array_map('shape', $st->fetchAll())]);
}

require_admin();
$rows = db()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 500')->fetchAll();
json_out(['payments' => array_map('shape', $rows)]);
