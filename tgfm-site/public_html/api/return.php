<?php
/**
 * Where Maya sends the buyer back to. It decides nothing about money — the
 * webhook does that. This only chooses which screen the disciple lands on.
 *
 * If the webhook has not arrived yet the receipt shows "pending", which is
 * honest: the payment really is still being confirmed.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

$reference = (string) ($_GET['r'] ?? '');
$outcome   = (string) ($_GET['outcome'] ?? 'success');

if ($reference !== '' && $outcome === 'cancel') {
    mark_unpaid($reference, 'cancelled', 'BUYER_CANCELLED');
}
if ($reference !== '' && $outcome === 'failure') {
    mark_unpaid($reference, 'failed', 'GATEWAY_FAILURE');
}

/* Do not wait for the webhook to decide whether the buyer gets a screen they
   can use. Ask Maya outright — the answer comes from Maya's own API with the
   secret key, so it is exactly as trustworthy as the webhook path, and it means
   a missing or misdirected webhook registration no longer strands a buyer whose
   money has already left their wallet. Idempotent, so the webhook arriving
   later changes nothing. */
if ($reference !== '' && $outcome === 'success') {
    maya_confirm($reference);
}

/* Let this browser see its own receipt afterwards. */
if ($reference !== '') { remember_reference($reference); }

/* Hand the claim token to the buyer's own browser, and only theirs — this is
   the single moment it is ever transmitted. */
$claim = '';
if ($reference !== '') {
    $payment = find_payment($reference);
    if ($payment && $payment['status'] === 'paid' && !empty($payment['claim_token'])) {
        $claim = (string) $payment['claim_token'];
    }
}

$target = $reference === ''
    ? SITE_URL . '/#/pricing'
    : ($claim !== ''
        ? SITE_URL . '/#/create-account/' . rawurlencode($reference) . '/' . rawurlencode($claim)
        : SITE_URL . '/#/receipt/' . rawurlencode($reference));

header('Location: ' . $target);
exit;
