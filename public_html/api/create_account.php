<?php
/**
 * Turn a cleared payment into an account.
 *
 * POST { reference, claim, name, password }  ->  { user }
 *
 * There is no open sign-up on this site. A user row is only ever created here,
 * and only when the payment named by `reference` has actually been confirmed
 * paid by Maya or PayPal. The claim token is 128 bits, handed back only on the
 * buyer's own return from the gateway, and is cleared the first time it works —
 * so a stranger cannot guess a reference and take someone else's pass.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

require_post();
guard_same_origin();

$in = body_json();

$reference = trim((string) ($in['reference'] ?? ''));
$claim     = trim((string) ($in['claim']     ?? ''));
$name      = trim((string) ($in['name']      ?? ''));
$password  = (string) ($in['password'] ?? '');

if ($reference === '' || $claim === '') { fail('That link is incomplete.'); }

/* Slow down anyone working through references in bulk. */
usleep(250000);

$user = claim_account($reference, $claim, $name, $password);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

json_out(['user' => [
    'name'        => $user['name'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'plan'        => $user['plan'],
    'accessUntil' => $user['access_until'],
    'active'      => has_access($user),
]]);
