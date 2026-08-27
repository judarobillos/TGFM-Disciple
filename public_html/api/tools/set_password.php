<?php
/**
 * Set (or reset) the password for one account. Run once, then DELETE THIS FILE.
 *
 *   https://yourdomain.com/api/tools/set_password.php?key=YOUR_MAYA_SECRET&email=admin@tgfm.org&password=...
 *
 * Anyone who can reach this file can take over any account, so it must not stay
 * on the server. It exists only so you can create the first admin login.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= your Maya secret key to run this.\n");
}

$email    = strtolower(trim((string) ($_GET['email'] ?? '')));
$password = (string) ($_GET['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { exit("Give a valid ?email=\n"); }
if (strlen($password) < 10)                     { exit("Use at least 10 characters for ?password=\n"); }

$st = db()->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
$st->execute([password_hash($password, PASSWORD_DEFAULT), $email]);

echo $st->rowCount()
    ? "Password set for $email.\n\nNOW DELETE THIS FILE.\n"
    : "No account found with that email.\n";
