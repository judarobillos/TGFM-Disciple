<?php
/**
 * Start a Maya Checkout.
 *
 * POST { plan: "week"|"month"|"year" }
 *  ->  { redirectUrl, reference }
 *
 * The browser sends only a pass id. We look the price and the duration up from
 * PLANS, record a pending payment, then ask Maya for a hosted checkout page.
 *
 * Docs: https://developers.maya.ph/reference/createv1checkout
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

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
$phone = trim((string) ($in['phone'] ?? ''));
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
    'method'       => 'maya',
    'status'       => 'pending',
    'access_until' => $until,
]);

/* So this browser can read its own receipt later, before any account exists. */
remember_reference($reference);

/* Split the name the way Maya expects. */
$parts     = preg_split('/\s+/', $name) ?: [$name];
$lastName  = count($parts) > 1 ? array_pop($parts) : '';
$firstName = implode(' ', $parts);

$payload = [
    'totalAmount' => [
        'value'    => round($amount, 2),
        'currency' => CURRENCY,
    ],
    'buyer' => array_filter([
        'firstName' => $firstName,
        'lastName'  => $lastName,
        'contact'   => array_filter(['email' => $email, 'phone' => $phone]),
    ]),
    'items' => [[
        'name'        => 'TGFM ' . $plan['name'] . ' — ' . $plan['span'],
        'quantity'    => 1,
        'totalAmount' => ['value' => round($amount, 2), 'currency' => CURRENCY],
    ]],
    'redirectUrl' => [
        'success' => SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=success',
        'failure' => SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=failure',
        'cancel'  => SITE_URL . '/api/return.php?r=' . urlencode($reference) . '&outcome=cancel',
    ],
    'requestReferenceNumber' => $reference,
];

/* Maya authenticates with HTTP Basic: the key as the username, empty password. */
$auth = 'Authorization: Basic ' . base64_encode(maya_public_key() . ':');
$res  = http_json('POST', maya_base() . '/checkout/v1/checkouts', [$auth], $payload);

if ($res['status'] < 200 || $res['status'] >= 300 || empty($res['body']['redirectUrl'])) {
    log_line('Maya create checkout failed (' . $res['status'] . '): ' . $res['raw']);
    mark_unpaid($reference, 'failed', 'CREATE_FAILED', $res['raw']);
    fail('Maya could not start this payment. Please try again in a moment.', 502);
}

set_gateway_id($reference, (string) ($res['body']['checkoutId'] ?? ''));

json_out([
    'redirectUrl' => $res['body']['redirectUrl'],
    'reference'   => $reference,
]);
