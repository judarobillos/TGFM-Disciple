<?php
/**
 * Start a PayPal order.
 *
 * POST { plan }  ->  { redirectUrl, reference }
 *
 * Docs: https://developer.paypal.com/docs/api/orders/v2/
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_paypal.php';

require_post();
guard_same_origin();

/* No sign-in required: buying is what creates the account. An existing member
   who is signed in gets their pass extended instead. */
$user = current_user();
$in   = body_json();

$planId = (string) ($in['plan'] ?? '');
$plan   = plan_or_fail($planId);
$period = period_for($planId);
$amount = price_for($planId);

$name  = trim((string) ($in['name']  ?? ($user['name']  ?? '')));
$email = trim((string) ($in['email'] ?? ($user['email'] ?? '')));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('A name and a valid email are needed for the receipt.');
}
/* A signed-in member always buys against their own account. */
if ($user) { $email = $user['email']; }

$reference = new_reference();
$start     = window_start($user, $planId);
$until     = window_end($start, $period);

create_payment([
    'reference'    => $reference,
    'user_id'      => $user['id'] ?? null,
    'email'        => strtolower($email),
    'name'         => $name,
    'plan'         => $planId,
    'period'       => $period,
    'amount'       => number_format($amount, 2, '.', ''),
    'currency'     => CURRENCY,
    'method'       => 'paypal',
    'status'       => 'pending',
    'access_until' => $until,
]);

/* So this browser can read its own receipt later, before any account exists. */
remember_reference($reference);

$token = paypal_token();
if ($token === null) { fail('Could not reach PayPal. Please try again.', 502); }

$payload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => $reference,
        'custom_id'    => $reference,          // comes back on the webhook
        'description'  => 'TGFM ' . $plan['name'] . ' — ' . $plan['span'],
        'amount'       => [
            'currency_code' => CURRENCY,
            'value'         => number_format($amount, 2, '.', ''),
        ],
    ]],
    'payment_source' => [
        'paypal' => [
            'experience_context' => [
                'brand_name'          => 'TGFM Discipleship Hub',
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => SITE_URL . '/api/paypal_capture.php?r=' . urlencode($reference),
                'cancel_url' => SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=cancel',
            ],
        ],
    ],
];

$res = http_json('POST', paypal_base() . '/v2/checkout/orders', [
    'Authorization: Bearer ' . $token,
    'PayPal-Request-Id: ' . $reference,        // makes a retry idempotent
], $payload);

if ($res['status'] < 200 || $res['status'] >= 300 || empty($res['body']['id'])) {
    log_line('PayPal create order failed (' . $res['status'] . '): ' . $res['raw']);
    mark_unpaid($reference, 'failed', 'CREATE_FAILED', $res['raw']);
    fail('PayPal could not start this payment. Please try again in a moment.', 502);
}

set_gateway_id($reference, (string) $res['body']['id']);

$approve = paypal_link($res['body'], ['payer-action', 'approve']);
if ($approve === null) {
    log_line('PayPal order had no approval link: ' . $res['raw']);
    fail('PayPal did not return an approval link.', 502);
}

json_out(['redirectUrl' => $approve, 'reference' => $reference]);
