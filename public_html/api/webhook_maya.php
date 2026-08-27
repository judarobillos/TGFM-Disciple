<?php
/**
 * Maya webhook receiver — this is what actually grants access.
 *
 * Register it once with api/tools/register_maya_webhooks.php. Maya posts here
 * for PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED and PAYMENT_CANCELLED.
 *
 * A webhook body is just an HTTP request — anyone could post one. So we never
 * take its word for it: we re-read the payment straight from Maya's API using
 * the secret key, and only that answer decides whether access is granted.
 *
 * Docs: https://developers.maya.ph/reference/configuring-your-webhook-for-maya-checkout
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

$rawBody = file_get_contents('php://input') ?: '';
$body    = json_decode($rawBody, true);
$body    = is_array($body) ? $body : [];

$paymentId = (string) ($body['id'] ?? '');
$reference = (string) ($body['requestReferenceNumber'] ?? '');

db()->prepare('INSERT INTO webhook_log (source, event, reference, verified, body)
               VALUES ("maya", ?, ?, 0, ?)')
    ->execute([(string) ($body['status'] ?? $body['paymentStatus'] ?? ''), $reference, $rawBody]);

/* Always answer 200 quickly — a gateway that gets an error keeps retrying. */
function done(string $note): never { log_line('maya webhook: ' . $note); json_out(['ok' => true]); }

if ($paymentId === '') { done('no payment id'); }

/* Re-read the truth from Maya. Both calls walk the candidate hosts — pointing
   these at the wrong host is what silently broke every verification before. */
$res = maya_api_json('GET', '/payments/v1/payments/' . rawurlencode($paymentId), maya_secret_key());

if ($res['status'] !== 200) {
    /* The status endpoint is authorised with the PUBLIC key, not the secret. */
    $res = maya_api_json('GET', '/payments/v1/payments/' . rawurlencode($paymentId) . '/status', maya_public_key());
}
if ($res['status'] !== 200) {
    done("could not verify $paymentId (HTTP {$res['status']} via {$res['host']}) {$res['raw']}");
}

$verified  = $res['body'];
$status    = (string) ($verified['status'] ?? $verified['paymentStatus'] ?? '');
$reference = $reference !== '' ? $reference : (string) ($verified['requestReferenceNumber'] ?? '');

if ($reference === '') { done("no reference on $paymentId"); }

$payment = find_payment($reference);
if (!$payment) { done("unknown reference $reference"); }

/* Amount check: never grant a ₱599 plan for a ₱1 payment. */
$paidAmount = (float) ($verified['amount'] ?? $verified['totalAmount']['value'] ?? 0);
$expected   = (float) $payment['amount'];

db()->prepare('UPDATE webhook_log SET verified = 1 WHERE reference = ? ORDER BY id DESC LIMIT 1')
    ->execute([$reference]);

if (in_array($status, ['PAYMENT_SUCCESS', 'CAPTURED', 'DONE'], true)) {
    if ($paidAmount > 0 && abs($paidAmount - $expected) > 0.01) {
        log_line("maya amount mismatch on $reference: paid $paidAmount expected $expected");
        mark_unpaid($reference, 'failed', 'AMOUNT_MISMATCH', $res['raw']);
        done("amount mismatch $reference");
    }
    mark_paid($reference, $status, $res['raw']);
    done("paid $reference");
}

if (in_array($status, ['PAYMENT_FAILED', 'AUTH_FAILED'], true))   { mark_unpaid($reference, 'failed', $status, $res['raw']); }
if (in_array($status, ['PAYMENT_EXPIRED', 'PAYMENT_CANCELLED', 'VOIDED'], true)) { mark_unpaid($reference, 'cancelled', $status, $res['raw']); }
if ($status === 'REFUNDED') { mark_unpaid($reference, 'refunded', $status, $res['raw']); }

done("$reference -> $status");
