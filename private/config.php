<?php
/**
 * TGFM — configuration and secrets.
 *
 * Keep this file OUTSIDE public_html. On Hostinger your account root looks like:
 *
 *   /home/uXXXXXXXX/
 *   ├── domains/yourdomain.com/public_html/   <- the website (index.html, api/)
 *   └── private/                              <- this file lives here
 *
 * If you cannot place it outside the web root, put it in public_html/private/
 * and keep the .htaccess file that ships alongside it — that denies web access.
 * Never commit real keys to git.
 */

declare(strict_types=1);

/* ── environment ───────────────────────────────────────────────────────── */
// 'sandbox' while testing, 'live' when you go into production.
const TGFM_ENV = 'sandbox';

// Public base URL of the site, no trailing slash. Must be https:// in production.
const SITE_URL = 'https://disciple.transformglobalfaithministries.com/';

/* ── database (Hostinger hPanel → Databases → MySQL) ───────────────────── */
const DB_HOST = 'localhost';
const DB_NAME = 'u487184060_discipleship';
const DB_USER = 'u487184060_discipleship';
const DB_PASS = 'Uz85#cTDP?';

/* ── Maya ──────────────────────────────────────────────────────────────
   Public key (pk-...) creates checkouts. Secret key (sk-...) reads payment
   status and registers webhooks.

   PUT TGFM'S OWN SANDBOX KEYS HERE. Getting them is not self-serve: email Maya
   onboarding (or your Maya relationship manager) and ask for sandbox access to
   Maya Manager 1.0. Once they provision your nominated representative:

     1. Sign in at https://manager-sandbox.paymaya.com/
     2. API Keys → pick TGFM from the nav → Generate API Key
     3. Create one Public and one Secret policy key
     4. Copy both immediately — the unencrypted values are shown once only

   Leave these two as REPLACE-ME and the code falls back to Maya's shared public
   sandbox keys, so the site keeps working while you wait for onboarding. */
const MAYA_PUBLIC_KEY_SANDBOX = 'pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah';
const MAYA_SECRET_KEY_SANDBOX = 'sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcwMYk39jInSl';

const MAYA_PUBLIC_KEY_LIVE    = 'pk-REPLACE-ME';
const MAYA_SECRET_KEY_LIVE    = 'sk-REPLACE-ME';

/* Maya's shared sandbox keys — the fallback, used only while the two constants
   above are still REPLACE-ME. Published by Maya, belong to nobody, and are used
   by every developer testing against Maya at the same time. Fine for a first
   look; not fine once you care about webhooks, because registering them
   repoints that party's callbacks for everyone (see api/README.md).
   Five interchangeable pairs, in case one is being abused: */
const MAYA_SHARED_SANDBOX = [
    ['pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah', 'sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcwMYk39jInSl'],
    ['pk-eo4sL393CWU5KmveJUaW8V730TTei2zY8zE4dHJDxkF', 'sk-KfmfLJXFdV5t1inYN8lIOwSrueC1G27SCAklBqYCdrU'],
    ['pk-lNAUk1jk7VPnf7koOT1uoGJoZJjmAxrbjpj6urB8EIA', 'sk-fzukI3GXrzNIUyvXY3n16cji8VTJITfzylz5o5QzZMC'],
    ['pk-yaj6GVzYkce52R193RIWpuRR5tTZKqzBWsUeCkP9EAf', 'sk-VGDKY3P90NYZZ0kSWqBFaD1NTIXQCxtdS7SbQXvcA4g'],
    ['pk-NCLk7JeDbX1m22ZRMDYO9bEPowNWT5J4aNIKIbcTy2a', 'sk-8MqXdZYWV9UJB92Mc0i149CtzTWT7BYBQeiarM27iAi'],
];
/* Which of the five to fall back to (0-4). Change if that party misbehaves. */
const MAYA_SHARED_PARTY = 0;

/* Sandbox buyers — what to type on Maya's hosted page while TGFM_ENV is 'sandbox'.

   Maya e-wallet
     +639900100900  password Password@1  OTP 123456   -> pays successfully
     +639900100916  password Password@1  OTP 123456   -> fails, insufficient balance

   Cards (any name, any billing address)
     5123456789012346  12/2030  CVV 111   Mastercard, no 3-D Secure step
     5453010000064154  12/2030  CVV 111   Mastercard, 3DS password: secbarry1
     4123450131001381  12/2030  CVV 123   Visa,       3DS password: mctest1
     4123450131001522  12/2030  CVV 123   Visa,       3DS password: mctest1
     4123450131004443  12/2030  CVV 123   Visa,       3DS password: mctest1
     4123450131000508  12/2030  CVV 111   Visa,       no 3-D Secure step

   Use the failing wallet account at least once: it is the only easy way to see
   that a declined payment leaves no account behind. */

/* ── PayPal ────────────────────────────────────────────────────────────
   PayPal publishes no shared sandbox keys — you create your own at
   developer.paypal.com → Apps & Credentials → Sandbox → Create App, and the
   test buyer under Testing Tools → Sandbox Accounts. Both are free and take
   about five minutes. Until they are filled in, the PayPal button will fail
   and Maya will still work. */
const PAYPAL_CLIENT_ID_SANDBOX = 'REPLACE-ME';
const PAYPAL_SECRET_SANDBOX    = 'REPLACE-ME';
const PAYPAL_CLIENT_ID_LIVE    = 'REPLACE-ME';
const PAYPAL_SECRET_LIVE       = 'REPLACE-ME';
// From PayPal → Webhooks. Needed to verify that a webhook really came from PayPal.
const PAYPAL_WEBHOOK_ID        = 'REPLACE-ME';

/* ── passes ────────────────────────────────────────────────────────────
   Three durations, one library — every pass opens everything, and differs
   only in how long it runs.

   THE SERVER IS THE ONLY AUTHORITY ON PRICE. The browser sends a pass id and
   nothing else — otherwise anyone could pay ₱1 for a year. */
const PLANS = [
    'week'  => ['name' => 'Weekly Pass',       'price' =>  99.00, 'period' => 'week',  'span' => '7 days'],
    'month' => ['name' => 'Monthly Access',    'price' =>  299.00, 'period' => 'month', 'span' => '1 month'],
    'year'  => ['name' => 'Annual Membership', 'price' => 2990.00, 'period' => 'year',  'span' => '1 year'],
];
const CURRENCY = 'PHP';

/* ── email ──────────────────────────────────────────────────────────────
   Two messages go out the moment a payment clears: a receipt to the disciple
   (carrying the one-time link to finish their account, in case they closed the
   tab), and an alert to the ministry.

   STRONGLY prefer SMTP over PHP's mail(). Hostinger's shared mail server is
   shared with every other site on the box, so plain mail() lands in spam often
   enough to matter for something a disciple needs to keep. Make a real mailbox
   in hPanel → Emails, then put its details below: the message is then sent by
   your own domain, and SPF and DKIM line up.

   hPanel → Emails → your mailbox → Configuration gives you the host. Hostinger
   is usually smtp.hostinger.com, port 465 with 'ssl'. Port 587 with 'tls' also
   works; 465 is 'ssl' and 587 is 'tls' — swapping those two is the usual cause
   of a connection that resets immediately. */

const MAIL_ENABLED   = true;

// Leave MAIL_SMTP_HOST empty to fall back to PHP mail(). Fine for a first test.
const MAIL_SMTP_HOST   = 'smtp.hostinger.com';                        // e.g. 'smtp.hostinger.com'
const MAIL_SMTP_PORT   = 465;
const MAIL_SMTP_SECURE = 'ssl';                     // 'ssl' for 465, 'tls' for 587
const MAIL_SMTP_USER   = 'info@transformglobalfaithministries.com';                        // the full mailbox address
const MAIL_SMTP_PASS   = '>kA||tjY8';                        // its password

/* Must be a mailbox on your own domain, or receipts will be treated as forged.
   With SMTP, this normally has to equal MAIL_SMTP_USER. */
const MAIL_FROM       = 'info@transformglobalfaithministries.com';
const MAIL_FROM_NAME  = 'Transform Global Faith Ministries';

// Where a disciple's reply lands. Use a mailbox somebody actually reads.
const MAIL_REPLY_TO   = '';                         // blank = same as MAIL_FROM

/* Who hears about every new subscription. Comma-separated for several people.
   Leave blank and it goes to every admin account in the database instead. */
const MAIL_ADMIN_TO   = '';

/* ── derived values — nothing below needs editing ──────────────────────── */
function tgfm_is_live(): bool { return TGFM_ENV === 'live'; }

function maya_base(): string {
    return tgfm_is_live() ? 'https://pg.paymaya.com' : 'https://pg-sandbox.paymaya.com';
}
/* The payments API (/payments/v1/...) lives on the same host as Checkout, not
   on the older api.paymaya.com. api/_lib.php tries a list of candidates anyway
   and keeps whatever this returns as a last resort, so an already-edited
   config.php from an earlier download still works — but this is the right one.
   https://developers.maya.ph/reference/getpaymentstatusviapaymentid-1 */
function maya_api_base(): string {
    return tgfm_is_live() ? 'https://pg.maya.ph' : 'https://pg-sandbox.paymaya.com';
}
/** True once TGFM's own sandbox keys have been pasted in above. */
function maya_own_sandbox_keys(): bool {
    return str_starts_with(MAYA_PUBLIC_KEY_SANDBOX, 'pk-')
        && str_starts_with(MAYA_SECRET_KEY_SANDBOX, 'sk-');
}
function maya_public_key(): string {
    if (tgfm_is_live())            { return MAYA_PUBLIC_KEY_LIVE; }
    if (maya_own_sandbox_keys())   { return MAYA_PUBLIC_KEY_SANDBOX; }
    return MAYA_SHARED_SANDBOX[MAYA_SHARED_PARTY][0];
}
function maya_secret_key(): string {
    if (tgfm_is_live())            { return MAYA_SECRET_KEY_LIVE; }
    if (maya_own_sandbox_keys())   { return MAYA_SECRET_KEY_SANDBOX; }
    return MAYA_SHARED_SANDBOX[MAYA_SHARED_PARTY][1];
}
/** For the setup check: which set of keys is actually in play right now. */
function maya_key_source(): string {
    if (tgfm_is_live())          { return 'live'; }
    if (maya_own_sandbox_keys()) { return 'own sandbox'; }
    return 'shared sandbox (party ' . (MAYA_SHARED_PARTY + 1) . ')';
}
function paypal_base(): string {
    return tgfm_is_live() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}
function paypal_client_id(): string {
    return tgfm_is_live() ? PAYPAL_CLIENT_ID_LIVE : PAYPAL_CLIENT_ID_SANDBOX;
}
function paypal_secret(): string {
    return tgfm_is_live() ? PAYPAL_SECRET_LIVE : PAYPAL_SECRET_SANDBOX;
}
