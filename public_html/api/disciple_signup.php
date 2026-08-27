<?php
/**
 * The free disciples register.
 *
 * GET   ->  { pastors: [...], years: [...], me: {...}|null }
 *             what the form needs to draw itself, plus this browser's own
 *             registration if it has one.
 *
 * POST  { name, email, phone, gender, location, pastor, deYear }
 *   ->  { ok:true, disciple:{...}, updated:bool }
 *
 * Public and free — this is the front door, not a paywall. It is also the
 * gate: checkout_maya.php and checkout_paypal.php refuse an email that is not
 * on this list, so the ministry always knows who is on the other side of a
 * payment.
 *
 * Registering twice with the same email UPDATES the record. A person who
 * mistyped their phone number should be able to fix it by filling the form in
 * again, and two rows for one disciple is worse than either version of the
 * truth.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

if (!disciples_table_ready()) {
    fail('The disciples register is not set up yet. Re-import private/schema.sql, then run api/tools/migrate.php.', 503);
}

/* ── what the form needs ───────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    /* Only ever this browser's own registration, or the signed-in member's.
       Never "is this email registered?" for an arbitrary address — that would
       turn the register into a way to test whether somebody is a member. */
    $me = null;
    $user = current_user();
    if ($user)                              { $me = find_disciple((string) $user['email']); }
    elseif (!empty($_SESSION['tgfm_disciple'])) { $me = find_disciple((string) $_SESSION['tgfm_disciple']); }

    json_out([
        'pastors' => array_column(all_pastors(), 'name'),
        'years'   => de_years(),
        'me'      => $me ? [
            'name'     => $me['name'],
            'email'    => $me['email'],
            'phone'    => $me['phone'],
            'gender'   => $me['gender'],
            'location' => $me['location'],
            'pastor'   => $me['pastor'],
            'deYear'   => $me['de_year'],
        ] : null,
    ]);
}

require_post();
guard_same_origin();

$in = body_json();

$name = trim((string) ($in['name'] ?? ''));
if (mb_strlen($name) < 2) { fail('Please give your full name.'); }
$name = mb_substr($name, 0, 120);

$email = strtolower(trim((string) ($in['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fail('That email address does not look right.');
}

/* Who you are is settled before anything else is judged. A signed-in member
   may only register their own email — otherwise one account could quietly
   rewrite another disciple's record — and checking it here means a request for
   somebody else's record never gets field-by-field feedback about it. */
$user = current_user();
if ($user && $user['role'] !== 'admin' && strtolower((string) $user['email']) !== $email) {
    fail('You are signed in as ' . $user['email'] . '. Sign out to register a different email.', 403);
}

/* Phone is kept as typed, minus anything that is plainly not a number: the
   ministry calls these people, and +63 9XX and 09XX are both correct here. */
$phone = trim((string) ($in['phone'] ?? ''));
$phone = preg_replace('/[^0-9+\-() ]/', '', $phone) ?? '';
$phone = trim(mb_substr($phone, 0, 40));
if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
    fail('Please give a mobile number the ministry can reach you on.');
}

$gender = trim((string) ($in['gender'] ?? ''));
if (!in_array($gender, ['Male', 'Female'], true)) { fail('Please choose male or female.'); }

$location = trim((string) ($in['location'] ?? ''));
if ($location === '') { fail('Please tell us where you are — city and province, or the country you are in.'); }
$location = mb_substr($location, 0, 160);

/* The pastor has to be one of the ministry's own names, checked against the
   table rather than against a list in this file. A dropdown is only a
   suggestion once the request leaves the browser. */
$pastor = trim((string) ($in['pastor'] ?? ''));
$known  = array_column(all_pastors(true), 'name');
if (!in_array($pastor, $known, true)) { fail('Please choose your pastor from the list.'); }

/* "" is a real answer: plenty of new disciples register before their first
   Divine Encounter. Anything else has to be a year we actually offered. */
$deYear = trim((string) ($in['deYear'] ?? ''));
if ($deYear !== '' && !in_array($deYear, de_years(), true)) {
    fail('Please choose the year you attended Divine Encounter, or leave it as "not yet".');
}

$existing = find_disciple($email);

if ($existing) {
    db()->prepare('UPDATE disciples SET name = ?, phone = ?, gender = ?, location = ?, pastor = ?, de_year = ?
                   WHERE email = ?')
        ->execute([$name, $phone, $gender, $location, $pastor, $deYear, $email]);
    log_line("disciple: updated $email ($pastor)");
} else {
    db()->prepare('INSERT INTO disciples (name, email, phone, gender, location, pastor, de_year)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $email, $phone, $gender, $location, $pastor, $deYear]);
    log_line("disciple: registered $email ($pastor)");
}

/* Remember it on the session so this browser can go straight to a subscription
   without being asked to prove itself again. The gate on the checkout still
   re-checks the database — this is a convenience, never the authority. */
$_SESSION['tgfm_disciple'] = $email;

/* Tell the ministry someone new has come in. After the write, and it cannot
   throw: a registration that succeeded must not fail because mail was slow. */
if (!$existing) { notify_disciple_registered($email); }

json_out([
    'ok'      => true,
    'updated' => (bool) $existing,
    'disciple' => [
        'name' => $name, 'email' => $email, 'phone' => $phone, 'gender' => $gender,
        'location' => $location, 'pastor' => $pastor, 'deYear' => $deYear,
    ],
]);
