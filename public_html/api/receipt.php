<?php
/**
 * One payment, for the screen that shows it.
 *
 * GET ?ref=TGFM-2608-XXXXXXXX  ->  { payment: {...} }
 *
 * The receipt and create-account screens are public by necessity: at that point
 * in the funnel there is no account to sign in to. So the gate is the PHP
 * session instead — a reference is returned only to the browser that started
 * that payment (recorded by the checkout endpoints and again on the way back
 * from the gateway), to the member it belongs to, or to an admin.
 *
 * A reference on its own is therefore not enough to read anybody's receipt.
 *
 * The claim token is included ONLY for the browser that owns the session. It is
 * what lets someone create the account this payment bought, so it is never
 * handed to a signed-in stranger or logged.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

guard_same_origin();

$reference = trim((string) ($_GET['ref'] ?? ''));
if ($reference === '') { fail('No reference given.'); }

$payment = find_payment($reference);
if (!$payment) { fail('That reference is not on file.', 404); }

/* If it is still pending, ask Maya before answering — the buyer may have landed
   here directly, or reloaded before the webhook arrived. */
if ($payment['status'] === 'pending' && $payment['method'] === 'maya') {
    $payment = maya_confirm($reference) ?: $payment;
}

$user  = current_user();
$mine  = session_owns_reference($reference);
$owner = $user && (
    ((int) ($payment['user_id'] ?? 0) === (int) $user['id'])
    || strtolower((string) $payment['email']) === strtolower((string) $user['email'])
    || $user['role'] === 'admin'
);

if (!$mine && !$owner) { fail('That receipt is not yours to view.', 403); }

$out = [
    'ref'     => $payment['reference'],
    'member'  => $payment['name'],
    'email'   => $payment['email'],
    'plan'    => $payment['plan'],
    'period'  => $payment['period'],
    'amount'  => (float) $payment['amount'],
    'method'  => $payment['method'],
    'status'  => $payment['status'],
    'created' => substr((string) $payment['created_at'], 0, 10),
    'until'   => (string) ($payment['access_until'] ?? ''),
];

/* Only the buyer's own browser is ever told the claim token. */
if ($mine && $payment['status'] === 'paid' && !empty($payment['claim_token'])) {
    $out['claim'] = (string) $payment['claim_token'];
}

json_out(['payment' => $out]);
