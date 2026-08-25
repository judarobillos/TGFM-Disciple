<?php
/**
 * Minimal accounts so the checkout has someone to bill.
 *
 * POST ?do=login   { email, password }
 * POST ?do=logout
 * GET  ?do=me      -> the signed-in user, their pass, and whether it is still valid
 *
 * There is deliberately no register endpoint. Accounts are created only by
 * api/create_account.php, and only against a payment the gateway has confirmed.
 * Passwords are hashed with password_hash(); plain passwords are never stored.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

guard_same_origin();
$do = (string) ($_GET['do'] ?? 'me');

function public_user(array $u): array {
    return [
        'name'        => $u['name'],
        'email'       => $u['email'],
        'role'        => $u['role'],
        'plan'        => $u['plan'],
        'accessUntil' => $u['access_until'],
        'active'      => has_access($u),
    ];
}

if ($do === 'me') {
    $user = current_user();
    json_out($user ? ['user' => public_user($user)] : ['user' => null]);
}

require_post();
$in = body_json();

if ($do === 'login') {
    $email    = strtolower(trim((string) ($in['email'] ?? '')));
    $password = (string) ($in['password'] ?? '');
    $user     = find_user_by_email($email);

    /* Same message either way, so this cannot be used to discover which
       email addresses have accounts. */
    if (!$user || $user['password_hash'] === '' || !password_verify($password, $user['password_hash'])) {
        usleep(300000);
        fail('That email and password do not match.', 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    json_out(['user' => public_user($user)]);
}

if ($do === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
}

fail('Unknown action.', 404);
