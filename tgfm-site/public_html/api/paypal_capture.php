<?php
/**
 * PayPal sends the buyer back here after they approve. Capturing is what
 * actually moves the money — approval alone charges nothing.
 *
 * The webhook does the same job independently, so if the buyer closes the tab
 * before this page loads the payment is still recorded.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_paypal.php';

$reference = (string) ($_GET['r'] ?? '');
$payment   = $reference !== '' ? find_payment($reference) : null;

if (!$payment) {
    header('Location: ' . SITE_URL . '/#/pricing');
    exit;
}
if ($payment['status'] === 'paid') {                       // webhook beat us here
    header('Location: ' . SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=success');
    exit;
}

$orderId = (string) ($_GET['token'] ?? $payment['gateway_id']);
$token   = paypal_token();

if ($orderId === '' || $token === null) {
    mark_unpaid($reference, 'failed', 'NO_ORDER');
    header('Location: ' . SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=failure');
    exit;
}

$res = http_json('POST', paypal_base() . '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', [
    'Authorization: Bearer ' . $token,
    'PayPal-Request-Id: cap-' . $reference,
], []);

$capture = $res['body']['purchase_units'][0]['payments']['captures'][0] ?? [];
$state   = (string) ($capture['status'] ?? $res['body']['status'] ?? 'UNKNOWN');

/* Confirm the amount that was actually captured matches what we asked for.
   A mismatch means something was tampered with — do not grant access. */
$captured = (float) ($capture['amount']['value'] ?? 0);
$expected = (float) $payment['amount'];

if ($state === 'COMPLETED' && abs($captured - $expected) < 0.01) {
    mark_paid($reference, $state, $res['raw']);
} else {
    log_line("PayPal capture not completed for $reference: state=$state captured=$captured expected=$expected");
    mark_unpaid($reference, 'failed', $state, $res['raw']);
}

header('Location: ' . SITE_URL . '/api/return.php?r=' . urlencode($reference)
    . '&outcome=' . ($payment['status'] === 'paid' || $state === 'COMPLETED' ? 'success' : 'failure'));
exit;
