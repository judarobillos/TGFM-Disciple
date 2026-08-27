# TGFM backend — Hostinger setup

Two jobs: it takes payments, and it stores the trainings.

Manual renewal: one payment buys one window of access. No card is stored, nothing
renews by itself, and there is no subscription for a disciple to cancel — access
simply ends on its date unless they pay again.

**There is no sign-up.** A payment is what creates an account: the buyer pays as
a guest, and the receipt hands them a form to choose a password. No user row can
exist without a cleared payment behind it, bar the seeded admin.

---

## Read this first: where your trainings are stored

The app has two storage modes, and **only one of them is any use on a live site**.

```js
const API = { base:"/api", enabled:false };   // near the top of index.html
```

| | `enabled: false` | `enabled: true` |
|---|---|---|
| Where a training is saved | that one browser's localStorage | your MySQL database |
| Who sees an edit you make | only you, only in that browser | everyone |
| In an incognito window | the demo content, always | your real content |
| Survives clearing browsing data | no | yes |

`false` is the demo. It ships that way so the app runs off a bare file with
nothing behind it — but on a live site it means **an admin's edits are visible
only to the admin, in the browser they were typed in.** Open the site in
incognito and you get the seed data back, because incognito has its own empty
localStorage. That is the whole explanation for "I edited a training and the
change is not showing."

**Import `schema.sql`, fill in `config.php`, set `enabled: true`.** After that the
admin writes to `api/admin_content.php` → MySQL, and every visitor reads the same
tree from `api/content.php`. The admin screens carry a red warning banner
whenever `enabled` is still `false`, so you can always tell which mode you are in.

---

Needs **PHP 8.1 or newer** (hPanel → Advanced → PHP Configuration) with cURL and
PDO MySQL, both on by default at Hostinger.

## What goes where

```
/home/uXXXXXXXX/
├── private/                        ← OUTSIDE public_html
│   ├── config.php                  ← your keys and prices
│   └── schema.sql
└── domains/yourdomain.com/public_html/
    ├── index.html                  ← the app
    ├── .htaccess                   ← forces HTTPS
    ├── uploads/                    ← featured images, written by the admin
    └── api/
        ├── .htaccess
        ├── _lib.php  _paypal.php
        ├── auth.php  create_account.php  payments.php
        ├── content.php                 ← the trainings, read by everyone
        ├── admin_content.php           ← the trainings, written by the admin
        ├── receipt.php                 ← one payment, for the buyer's own browser
        ├── _mail.php  _notify.php      ← receipts and the new-subscription alert
        ├── admin_members.php           ← the disciples, admin-only
        ├── admin_plans.php             ← subscription prices, admin-only
        ├── admin_image.php             ← featured image upload, admin-only
        ├── disciple_signup.php         ← the free register, public
        ├── admin_disciples.php         ← the register + pastors, admin-only
        ├── checkout_maya.php  checkout_paypal.php
        ├── paypal_capture.php  return.php
        ├── webhook_maya.php  webhook_paypal.php
        ├── logs/
        └── tools/                  ← setup helpers — DELETE after setup
```

**On a subdomain** — `disciple.yourdomain.com`, say — the layout is one level
deeper, and the simplest thing that works is to put `private` **beside** the
`api` folder:

```
domains/yourdomain.com/public_html/disciple/
├── index.html
├── api/
└── private/                        ← config.php, schema.sql, .htaccess
```

`_lib.php` looks in four places — three levels up, two levels up, beside `api`,
and inside `api` — and takes the first it finds. The `.htaccess` that ships in
the `private` folder denies web access to it, so any of those is safe.

**If you see "config.php not found"**, the page now prints the exact folders it
searched, as full paths. Put `private` in whichever one you can reach in File
Manager and reload. This one file is required by *every* endpoint, so until it is
found, nothing works — not the payments, and not the trainings.

---

## Setup, in order

**1. Database.** hPanel → Databases → MySQL. Create a database and user, note the
four values. phpMyAdmin → Import → `private/schema.sql`.

It creates ten tables. `users`, `payments` and `webhook_log` handle the money;
`content_trainings`, `content_series` and `content_topics` hold the teaching. The
import also seeds your three trainings and their series, so the admin opens onto
something real — add the topics (the videos) through the admin screens.

If you imported an earlier `schema.sql` that only had three tables, import this
one again. Every statement is `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`, so
re-importing adds the missing tables and leaves your accounts and payments alone.

**2. Config.** Edit `private/config.php`: `SITE_URL`, the four `DB_*` values, and
your gateway keys. Leave `TGFM_ENV` as `'sandbox'` until everything works.

**3. SSL.** hPanel → Security → SSL. Install the free certificate and turn on
Force HTTPS. Neither gateway will send webhooks to a plain `http://` address.

**4. The backend switch — already on in this package.** Open `index.html` and
check the line near the top reads:

```js
const API = { base:"/api", enabled:true };
```

The copy in this zip already says `true`, because this zip ships with the
backend. It is what makes the checkout buttons open the real Maya and PayPal
pages instead of the simulator, **and what makes your trainings live in MySQL
instead of in one browser** (see the table at the top).

If the `api` folder or the database is not in place, the admin screens show a red
banner saying the server did not answer and that nothing is being saved. That is
deliberate: the alternative — quietly saving to one browser — is the failure this
whole section exists to prevent.

Just below it is `const SANDBOX_HELP = false;`. Set it to `true` while you are
testing and the checkout page shows the sandbox wallet and cards inline, so you
are not hunting through this file mid-payment. **Set it back to `false` before
real disciples see the site.**

**5. Register the Maya webhooks.** Visit once, then delete the file. **Do this
again whenever you change the Maya keys** — webhooks belong to the account the
keys belong to, so keys from your own Maya Manager have no webhooks registered
until you register them:

```
https://yourdomain.com/api/tools/register_maya_webhooks.php?key=YOUR_MAYA_SECRET_KEY
```

**5b. Email.** Two messages go out the moment a payment clears: a receipt to
the disciple, and an alert to the ministry.

Make a real mailbox first — hPanel → Emails → Create. Then in `config.php`:

```php
const MAIL_SMTP_HOST   = 'smtp.hostinger.com';
const MAIL_SMTP_PORT   = 465;          // 465 goes with 'ssl', 587 goes with 'tls'
const MAIL_SMTP_SECURE = 'ssl';
const MAIL_SMTP_USER   = 'no-reply@yourdomain.com';
const MAIL_SMTP_PASS   = 'the mailbox password';
const MAIL_FROM        = 'no-reply@yourdomain.com';   // same mailbox
const MAIL_ADMIN_TO    = 'pastor@yourdomain.com, office@yourdomain.com';
```

Leaving `MAIL_SMTP_HOST` blank falls back to PHP's `mail()`. It works, but on
shared hosting that mail server is shared with every other site on the box, so
receipts often land in spam. Fine for a first test; use a real mailbox before
disciples depend on it.

Send yourself one of each to see them:

```
.../api/tools/send_test_email.php?key=YOUR_MAYA_SECRET_KEY&to=you@example.com&kind=buyer
.../api/tools/send_test_email.php?key=YOUR_MAYA_SECRET_KEY&to=you@example.com&kind=admin
```

**6. Register the PayPal webhook.** developer.paypal.com → your app → Webhooks →
Add. URL `https://yourdomain.com/api/webhook_paypal.php`, events:
`PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`.
Copy the Webhook ID it generates into `PAYPAL_WEBHOOK_ID` in config.php.

**7. First admin password.** Visit once, then delete the file:

```
https://yourdomain.com/api/tools/set_password.php?key=YOUR_MAYA_SECRET_KEY&email=admin@tgfm.org&password=a-long-one
```

**8. Check your work.** Visit once:

```
https://yourdomain.com/api/tools/check_setup.php?key=YOUR_MAYA_SECRET_KEY
```

It reports PHP version and extensions, whether the database and every table are
there, whether the admin has a password, **which Maya keys are actually in play**
(own vs shared), whether both keys really work against Maya, where your webhooks
currently point, and whether PayPal is configured. It writes nothing and charges
nothing. Run it again after pasting your own keys in.

**9. Delete the `tools` folder.** All four scripts in it are powerful and none is
meant to live on a running site:

| | |
|---|---|
| `set_password.php` | resets **any** account's password |
| `check_payment.php` | `&settle=1` marks payments paid, and prints claim tokens |
| `register_maya_webhooks.php` | re-points TGFM's payment callbacks |
| `check_setup.php` | prints database and key configuration |
| `send_test_email.php` | sends mail as your domain to any address |

The Maya secret key gates all four, but a key passed in a URL ends up in browser
history, server logs and referrer headers. Delete the folder; re-upload it for
an afternoon if you ever need it again.

---

## Testing in sandbox

`TGFM_ENV` is `'sandbox'` out of the box.

### Use TGFM's own sandbox keys (recommended)

Maya sandbox access is **not self-serve**. Email Maya onboarding, or your Maya
relationship manager, and ask for sandbox access to Maya Manager 1.0 for TGFM.
Once they provision your nominated representative:

1. Sign in at `https://manager-sandbox.paymaya.com/`
2. **API Keys** → choose TGFM in the nav → **Generate API Key**
3. Create one **Public** and one **Secret** policy key
4. Copy both straight away — the unencrypted values are shown once and never again

Paste them into `private/config.php`:

```php
const MAYA_PUBLIC_KEY_SANDBOX = 'pk-your-own-key';
const MAYA_SECRET_KEY_SANDBOX = 'sk-your-own-key';
```

That is the whole switch. Everything else picks them up automatically, and
`maya_key_source()` will report `own sandbox` in the setup check.

### Until then: the shared keys

Leave those two as `REPLACE-ME` and the code falls back to **Maya's published
sandbox keys**, so the site takes sandbox payments from the moment it is
uploaded. Fine for a first look. Not fine once you care about webhooks — see the
gotcha below. Five interchangeable pairs are in `config.php`; `MAYA_SHARED_PARTY`
picks which one.

**PayPal has no shared sandbox.** Create your own app at developer.paypal.com →
Apps & Credentials → Sandbox → Create App, and a test buyer under Testing Tools →
Sandbox Accounts. Until those keys are pasted in, the PayPal button fails and
Maya works fine — so start with Maya.

### What to type on Maya's page

**E-wallet** (the quickest path)

| Mobile | Password | OTP | Result |
|---|---|---|---|
| `+639900100900` | `Password@1` | `123456` | Pays successfully |
| `+639900100916` | `Password@1` | `123456` | Fails — insufficient balance |

**Cards** — any name, any billing address.

| Number | Expiry | CVV | 3-D Secure |
|---|---|---|---|
| `5123456789012346` | 12/2030 | 111 | Mastercard, no 3DS step |
| `5453010000064154` | 12/2030 | 111 | Mastercard, 3DS password `secbarry1` |
| `4123450131001381` | 12/2030 | 123 | Visa, 3DS password `mctest1` |
| `4123450131001522` | 12/2030 | 123 | Visa, 3DS password `mctest1` |
| `4123450131004443` | 12/2030 | 123 | Visa, 3DS password `mctest1` |
| `4123450131000508` | 12/2030 | 111 | Visa, no 3DS step |

### The run-through

Buy a **Weekly Pass** (₱140, the cheapest thing to repeat) with a brand-new email
address, using the successful wallet account. Check four things:

1. You land on **Create your account**, not the receipt.
2. `payments` has one row: `status = paid`, `amount = 140.00`, `claim_token` set.
3. After choosing a password: a `users` row exists, `claim_token` is NULL,
   `claimed_at` stamped, `access_until` seven days out.
4. Re-opening that same create-account URL now refuses — the link is spent.

Then three more passes:

- **Pay again while signed in as that member.** `access_until` should move
  forward from its old value, not from today.
- **Use the failing wallet account** (`+639900100916`). No `users` row should
  appear, and the receipt should say the payment did not go through.
- **Abandon one.** Close the tab on Maya's page. The row stays `pending` and no
  access is granted.

### Two sandbox gotchas

**The shared sandbox keys are shared.** This is the reason to get your own.
Every developer testing against Maya uses the same five key pairs, so
`webhook_log` may show transactions that are not yours — harmless, since every
handler looks the reference up in your own `payments` table and ignores anything
it does not recognise. But webhook *registration* is shared too: running
`register_maya_webhooks.php` repoints that party's webhooks at your server for
everyone, and someone else can repoint them away from you an hour later. If
payments succeed at Maya but nothing arrives at your webhook, that is almost
always why. Change `MAYA_SHARED_PARTY` and register again — or better, paste in
TGFM's own keys, where nobody can touch your webhooks.

**Webhooks need a public HTTPS URL.** Maya cannot reach `localhost`. Test on the
real Hostinger domain, or the receipt will sit at "pending" forever.

## Going live

Set `TGFM_ENV` to `'live'` and paste the production keys — Maya's from the live
Maya Manager once TGFM's merchant application is approved, PayPal's from the Live
tab of the same app. Re-run the Maya webhook registration (production
webhooks are separate from sandbox and no longer shared with anyone), then repeat
the run-through with a real card for ₱140 before announcing it.

The sandbox keys must not survive the switch. They are in the file as a
convenience for testing, not a fallback.

---

## How the trainings are stored

Three levels, nested:

```
Training            Kingdom Life Training
└── Series          Discipleship Series 1
    └── Topic       "Who Is Jesus To You?"   ← one YouTube video
```

A topic stores the **11-character YouTube id only** — never a whole URL. Paste a
link into the admin and the front end pulls the id out of it; a request that
tries to store anything else is refused outright.

Ids are scoped to their parent, so two trainings may each have a series called
`se1`. That is why the primary keys are composite.

**`GET api/content.php`** returns the whole tree, and decides who sees what:

- Unpublished trainings, series and topics come back **only to a signed-in admin**.
- **The YouTube id is withheld unless the viewer has a running subscription.**
  Titles, lengths and descriptions still come through, so anyone can browse the
  outline and see what they would be paying for — but there is no video id in the
  JSON to lift. This is the paywall, and it is enforced on the server. Hiding the
  player in the browser would not be a paywall.

**`POST api/admin_content.php`** is every write. It requires an admin session and
refuses anything else with a 403 — a disciple's own session cannot write content
even by hand-crafting the request. Ids are checked against
`^[A-Za-z0-9_-]{1,32}$`, video ids against `^[A-Za-z0-9_-]{11}$`, durations
against `m:ss`. Deleting a training cascades to its series and their topics.

After every write the app re-reads the tree from the server rather than trusting
what it just sent, so the screen shows what the database actually holds.

---

## Addresses

Every page has a real address:

```
/                       /pricing              /trainings
/disciple-signup        /checkout/month       /receipt/TGFM-2608-XXXX
/app  /app/library      /app/watch/t1/se1/v3
/admin  /admin/plans    /admin/disciples      /admin/payments
```

The whole site is still one `index.html`. What makes those work is four lines
in `public_html/.htaccess`: anything that is not a real file or folder is
handed to `index.html`, which reads the path as its route. **`/api/` and
`/uploads/` are excluded from that rule — without those two lines it swallows
the backend and nothing answers.** If you ever rewrite that file, keep them.

Three things worth knowing:

- **Old `#/` links still work.** A bookmark or an old email that says
  `/#/pricing` lands on `/pricing` — the app rewrites it on arrival. Nothing
  TGFM has already sent out is broken.
- **Opened from the filesystem, it falls back to `#/`.** Double-clicking
  `index.html` with no server means no rewrite rule, so the app uses hashes
  instead. The prototype and any emailed copy keep working.
- **In a subfolder, the prefix is taken from `API.base`.** If the site lives at
  `/disciple/`, set `API.base = "/disciple/api"` and every address picks up the
  `/disciple` prefix by itself.

---

## The disciples register

**The first step, and free.** `disciple-signup` collects name, email, phone,
gender, location, the disciple's pastor and the year they attended Divine
Encounter. It creates no account and opens no teaching — it is TGFM's own
record of who they are discipling.

It is also the gate. **A subscription can only be bought by somebody on the
register**: `checkout_maya.php` and `checkout_paypal.php` both call
`disciple_or_fail()` before the gateway is touched, so an unregistered email is
turned away before any money moves, with a message that says where to go. The
website asks first as a courtesy, but the rule lives on the server where the
browser cannot argue with it. The funnel is now:

```
register (free)  →  subscribe  →  pay  →  choose a password
```

**The address is a real one** — `/disciple-signup`, no `#`. So is every other
page; see *Addresses* below.

**Registering twice updates the record.** Somebody who mistyped their phone
number fixes it by filling the form in again; two rows for one disciple is
worse than either version of the truth. Email is the key, because email is what
the registration, the payment and the account all have in common.

**Admin → TGFM Nations → Registered Disciples** is the list: every field, plus
whether that person has an account, whether a subscription is running, and how
much they have paid. There is a **Download CSV** for the office — with a byte
order mark, so Excel opens the ñ in *Ps Grace Migriño* correctly instead of as
mojibake.

**The pastors on the dropdown are editable** on the same screen, for the same
reason the prices are: the ministry sends people out and calls them home.
Renaming one moves every disciple registered under them to the new name, so no
record is left pointing at a name that no longer exists. A pastor somebody is
registered under cannot be deleted — retire them and they leave the form
without leaving the records.

Removing a disciple from the register does **not** touch their account or their
payments. It means TGFM no longer holds their details, and that they will be
asked to register again before their next subscription.

---

## Prices, teachings for sale, and pictures

Three things the ministry can now change without a developer.

**Prices.** Admin → Subscriptions. Name, price, description, billing period and
on-sale/withdrawn, for each plan — and *New subscription* if TGFM ever wants a
fifth. The prices live in the `content_plans` table, and this is the only place
they live: the browser still sends nothing but a plan id to the checkout, and
`checkout_maya.php` reads the amount out of that table itself. Changing a price
changes what the next buyer is charged and leaves every receipt already written
exactly as it was.

Two rules the screen will enforce for you:

* A plan **with payments against it cannot be deleted** — those receipts name
  it. Set it *Withdrawn* instead; it disappears from the pricing page and is
  refused at checkout, and the records stay readable.
* A **one-time** plan sells a single teaching and a **weekly / monthly / annual**
  plan sells a window of everything. The two cannot be mixed, because a
  recurring charge for one video would keep billing for something already owned.

**Individual Teaching (₱49).** The one-time plan asks the buyer which topic they
are buying, before the money moves. The choice is checked against the published
tree and written onto the payment row, so the grant afterwards trusts the record
rather than the page. What it buys never expires and never grants anything else:
the topic they paid for opens, the one beside it stays shut, and buying several
adds them up. None of it moves a pass — somebody who holds a monthly pass *and*
bought a teaching keeps the teaching when the pass runs out.

**Featured images.** Trainings, series and topics each take one, uploaded in the
admin with a preview, a *Replace* and a *Remove*. JPG, PNG and WebP only, and
"only" is decided by decoding the file rather than by trusting its name — a PHP
script called `photo.png` is refused. What gets stored is not the file that was
sent: the picture is re-encoded from the decoded pixels at a sane width, which
is what keeps the page quick on mobile data and also means nothing but image
data survives the trip. Uploads land in `public_html/uploads/`, which ships with
an `.htaccess` that will not execute anything.

If you are upgrading an existing install, re-import `schema.sql` (every CREATE
is `IF NOT EXISTS`, so it only adds what is missing) and then run
`api/tools/migrate.php?key=YOUR_MAYA_SECRET_KEY` once. It adds the new columns,
makes the uploads folder, and tells you what it did.

**Deleting a payment record.** Admin → Payments now has a delete on every row,
behind a confirm. Two things worth knowing before you use it: a checkout started
in the last hour is protected, because it may still be at the gateway and
deleting it would leave the webhook with nothing to mark; and deleting a record
does **not** take back access. A one-off's teaching closes with its receipt —
they are two halves of one thing — but a running pass keeps running. Change that
on the Disciples page.

---

## Two ways a payment gets confirmed

The webhook is the designed path, and it is the one that works when the buyer
closes the tab. But a webhook has to be *registered*, and a registration can be
missing, late, or — with the shared sandbox keys — pointed at somebody else's
server. On its own that leaves a buyer whose money has left their wallet looking
at a receipt that says "pending" forever, with no way to make an account.

So `return.php` also asks Maya outright, using
[Retrieve Payment via RRN](https://developers.maya.ph/reference/getpaymentviarequestreferencenumber-1)
— our own reference is the RRN — authenticated with the secret key. Same amount
check, same `mark_paid()`, which is idempotent, so the webhook arriving a minute
later changes nothing. `receipt.php` asks again if the payment is still pending,
and the receipt screen has a **Check again** button that does the same.

Register the webhooks anyway. This is a safety net, not a replacement — it does
nothing for a buyer who closed the tab.

### If a payment sticks on "Still confirming"

```
https://yourdomain.com/api/tools/check_payment.php?key=YOUR_MAYA_SECRET_KEY&ref=TGFM-2608-XXXXXXXX
```

Leave `&ref=` off to list the most recent payments. It prints the database row,
tries every payments host in turn, and shows Maya's raw reply and HTTP status
for each — so you can see *why*, not just that it did not work:

| | |
|---|---|
| **401** | the secret key is wrong, or is a live key in sandbox (or the reverse) |
| **404** | Maya has no payment under that reference — the checkout was created with a *different* key pair than the one in `config.php` now |
| **0** | no outbound HTTPS from the server at all |

Add **`&settle=1`** to apply Maya's answer: it marks the payment paid exactly as
the webhook would, and prints the one-time create-account link to send to the
buyer. It looks and reports without it.

### A note on hosts

**Confirmed in production, 25 Aug 2026** — this was the whole problem, and
fixing it made both the webhook and the return-page confirmation start working.


Maya's payments API (`/payments/v1/...`) is on the **same host as Checkout** —
`pg-sandbox.paymaya.com` in sandbox, `pg.maya.ph` live — not the older
`api.paymaya.com`. Earlier copies of `config.php` pointed the verification calls
at the old host, so every one of them failed quietly: webhooks arrived and
granted nothing. The code now tries a list of hosts and keeps whatever your
`config.php` says as a last candidate, so **you do not need to re-edit a
config.php you have already put your keys into.**

## What gets emailed, and when

Both messages go out **once the disciple has paid AND created their account** —
not at the moment the payment clears.

For a **new buyer** that means nothing is sent when the payment lands; the
emails are triggered by `claim_account()`, when they choose their password. Both
`mark_paid()` and `claim_account()` are idempotent, so a webhook and the
return-page confirmation both firing still produces exactly one of each.

For an **existing member renewing**, the account already exists, so both
conditions are met the moment the payment clears and the mail goes then.

They go out *after* the transaction commits. A mail server that hangs must not
hold a row lock, and a mail failure must never roll back a payment that
genuinely cleared — so nothing in the mail path can throw. If sending fails, it
is logged in `api/logs/` and the payment stands.

**To the disciple:** a welcome and receipt in one — which email to sign in with,
the plan, the amount, the reference, and the date their access runs to. No claim
link: by the time it is sent, that token has already been spent.

⚠️ **The gap this leaves:** a buyer who pays and then closes the tab without
choosing a password gets no email at all, and neither do you. Their payment is
recorded and the receipt page still carries the link, but nothing chases them.
Watch Admin → Payments for rows that are `paid` with no matching disciple.

**To the ministry:** who subscribed, their email, which plan, how much, and
whether they are new or renewing. Sent to every address in `MAIL_ADMIN_TO`, or
to every admin account in the database if that is blank. The admin copy
deliberately **does not** contain the claim token — that belongs to the buyer
alone.

---

## Why the receipt has its own endpoint

**The server mints the reference, not the browser.** So after a real gateway
redirect the buyer's browser has never seen that reference — a local lookup
finds nothing, and the screen would tell someone who has just paid that their
receipt is not on file.

`receipt.php` answers with one payment, and only to:

- the browser that started it (its own PHP session — `remember_reference()`),
- the member whose email it belongs to, or
- an admin.

A reference on its own is therefore not enough to read anyone's receipt. The
**claim token is returned only to the buyer's own browser** — never to a member
reading someone else's, never to an admin, and it is never logged.

---

## How access is actually granted

The browser is never trusted with any of this.

1. The app asks for a **pass id** — never an amount, never a duration. Both come
   from `PLANS` in config.php.
2. A `pending` row is written, and the gateway is given our reference.
3. The buyer pays on Maya's or PayPal's own page. Card details never touch your
   server, which is what keeps you out of PCI scope.
4. The gateway calls the webhook. The handler ignores the body's claims and
   **re-reads the payment from the gateway's API**, checks the amount matches,
   and only then calls `mark_paid()`.
5. `mark_paid()` either extends an existing member, or — if that email has no
   account — mints a 128-bit `claim_token` and leaves the pass held. It is the
   only function that grants access, and running it twice is harmless.
6. `return.php` sends the buyer to the create-account screen carrying that
   token. `create_account.php` checks it with `hash_equals`, creates the user
   inside a transaction, and clears the token, so the link works exactly once.

The redirect back to the site decides nothing about money — a buyer who closes
the tab still has their payment recorded, and can finish the account later from
the receipt. A buyer who forges a return URL gets nothing: without the claim
token there is no account to make, and the token is only ever sent to the
browser that actually paid.

### Why there is no register endpoint

`auth.php` can log in and log out, and that is all. If you ever add open
sign-up, remember that `has_access()` is what gates content — an account with no
payment behind it would have `access_until` in the past and see nothing, but it
would also let anyone fill your `users` table.

### The check you still have to add

The front end's `canWatch()` decides what a disciple sees. That is convenience
only — anyone can edit it in their own browser. When you move videos server-side,
gate the video ID behind `has_access($user)` in PHP. Until then, treat the
lesson list as public information.

## Refunds

`mark_unpaid($ref, 'refunded', ...)` records the refund but deliberately leaves
`access_until` alone, so nobody loses access mid-teaching by accident. Shorten it
by hand in phpMyAdmin when you mean to.
