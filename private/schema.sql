-- TGFM Discipleship Hub — minimum schema for accounts and payments.
-- Import once via hPanel → Databases → phpMyAdmin → Import.
--
-- Content lives here too: Training -> Series -> Topic. Once these tables exist
-- and index.html has API.enabled = true, an admin's edits are stored on the
-- server and every disciple sees them. Before that, content lived only in the
-- editing browser's localStorage, which is why edits vanished in incognito.
--
-- Note there is no free tier: a row in `users` only ever exists because a
-- payment cleared and the buyer then chose a password.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(190)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('disciple','admin') NOT NULL DEFAULT 'disciple',
  plan          VARCHAR(20)   NOT NULL,          -- week | month | year
  -- The moment access lapses. This one column is the whole subscription:
  -- no card is stored, nothing renews by itself.
  -- NULL means "no pass". Somebody who has only ever bought a single teaching
  -- holds no window at all, and a date would have to lie about that one way or
  -- the other — either granting a day of everything or reading as expired.
  access_until  DATE          NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference     VARCHAR(36)   NOT NULL,          -- our id, sent to the gateway
  user_id       INT UNSIGNED  NULL,
  email         VARCHAR(190)  NOT NULL,
  name          VARCHAR(120)  NOT NULL,
  plan          VARCHAR(20)   NOT NULL,          -- week | month | year
  -- week | month | year | once  (VARCHAR, not ENUM: 'once' arrived later and
  -- widening an ENUM on a live table is exactly the migration people skip)
  period        VARCHAR(10)   NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,          -- set from PLANS, never from the browser
  currency      CHAR(3)       NOT NULL DEFAULT 'PHP',
  method        ENUM('maya','paypal') NOT NULL,
  status        ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  gateway_id    VARCHAR(190)  NULL,              -- Maya checkoutId / PayPal order id
  gateway_state VARCHAR(60)   NULL,              -- raw status word from the gateway
  -- Handed back on the return from the gateway. Whoever holds it may create
  -- the account this payment bought — once. Cleared the moment it is used.
  claim_token   CHAR(32)      NULL,
  claimed_at    DATETIME      NULL,
  access_until  DATE          NULL,              -- the window this payment bought
  raw           MEDIUMTEXT    NULL,              -- last webhook body, for disputes
  -- Which topic a one-off purchase is for, as "training/series/topic".
  -- Recorded at checkout, before the money moves, so the entitlement can be
  -- granted later without trusting anything the browser says.
  topic_ref     VARCHAR(104)  NULL,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at       DATETIME      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_reference (reference),
  KEY ix_payments_user (user_id),
  KEY ix_payments_gateway (gateway_id),
  KEY ix_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every webhook that arrives, stored before it is acted on. If a payment is
-- ever disputed, this table is the evidence.
CREATE TABLE IF NOT EXISTS webhook_log (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source     ENUM('maya','paypal') NOT NULL,
  event      VARCHAR(80)  NULL,
  reference  VARCHAR(36)  NULL,
  verified   TINYINT(1)   NOT NULL DEFAULT 0,
  body       MEDIUMTEXT   NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_webhook_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The one account that is not created by a payment. Change the email, then set
-- a real password with api/tools/set_password.php and delete that file.
INSERT IGNORE INTO users (name, email, password_hash, role, plan, access_until)
VALUES ('TGFM Admin', 'admin@tgfm.org', '', 'admin', 'year', '2099-12-31');


-- ─────────────────────────────────────────────────────────────────────────
-- CONTENT.  Training -> Series -> Topic.
--
-- Ids are the same short strings the front end uses, and they are scoped to
-- their parent: two trainings may each have a series called "se1". That is why
-- the keys are composite rather than a single global id.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS content_trainings (
  id         VARCHAR(32)  NOT NULL,
  title      VARCHAR(160) NOT NULL,
  blurb      TEXT         NULL,
  hue        SMALLINT     NOT NULL DEFAULT 220,   -- generated cover art tone
  image       VARCHAR(255) NULL,           -- featured image; falls back to generated art
  position   INT          NOT NULL DEFAULT 0,
  published  TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_series (
  training_id VARCHAR(32)  NOT NULL,
  id          VARCHAR(32)  NOT NULL,
  title       VARCHAR(160) NOT NULL,
  blurb       TEXT         NULL,
  teacher     VARCHAR(120) NOT NULL DEFAULT '',
  image       VARCHAR(255) NULL,           -- featured image; falls back to generated art
  position    INT          NOT NULL DEFAULT 0,
  published   TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (training_id, id),
  CONSTRAINT fk_series_training FOREIGN KEY (training_id)
    REFERENCES content_trainings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_topics (
  training_id VARCHAR(32)  NOT NULL,
  series_id   VARCHAR(32)  NOT NULL,
  id          VARCHAR(32)  NOT NULL,
  title       VARCHAR(200) NOT NULL,
  yt_id       VARCHAR(20)  NOT NULL,          -- the 11-char YouTube id, nothing else
  duration    VARCHAR(12)  NOT NULL DEFAULT '00:00',
  image       VARCHAR(255) NULL,           -- featured image; falls back to generated art
  notes       TEXT         NULL,
  position    INT          NOT NULL DEFAULT 0,
  published   TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (training_id, series_id, id),
  CONSTRAINT fk_topic_series FOREIGN KEY (training_id, series_id)
    REFERENCES content_series (training_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starting content: the three trainings and their series, so the admin opens
-- onto something real. Add topics through the admin — do not paste video ids
-- in here by hand unless you enjoy SQL.
INSERT IGNORE INTO content_trainings (id, title, blurb, hue, position, published) VALUES
 ('t1','Kingdom Life Training','The standing training programme — what it means to live under the King, taught in order from the beginning.',206,0,1),
 ('t2','Ambassadors Briefing','Sending and equipping — for those carrying the ministry outside the four walls.',232,1,1),
 ('t3','Simbalive','The live gathering, recorded and kept — so a message preached once can be worked through slowly.',248,2,1);

INSERT IGNORE INTO content_series (training_id, id, title, blurb, teacher, position, published) VALUES
 ('t1','se1','Discipleship Series 1','The ground every believer stands on: repentance, faith, baptism, the Word.','Pastor Roy Oliveros',0,1),
 ('t1','se2','Discipleship Series 2','What daily obedience looks like on a Tuesday: prayer, the Word, and staying when it costs.','Rochel Oliveros',1,1),
 ('t2','se1','Ambassadors Briefing Series 1','PLACEHOLDER — replace with TGFM own description.','Pastor Roy Oliveros',0,1),
 ('t2','se2','Ambassadors Briefing Series 2','PLACEHOLDER — rename this in the admin.','Rochel Oliveros',1,0),
 ('t3','se1','The Seven Churches Series','Revelation 2 and 3, one letter at a time.','Pastor Roy Oliveros',0,1),
 ('t3','se2','Life Issues','The things people carry into a Sunday: money, forgiveness, anxiety, family.','Rochel Oliveros',1,1);


-- ─────────────────────────────────────────────────────────────────────────
-- PLANS.  Prices live here, not in code, so TGFM can change them without a
-- developer. The server still reads the price from THIS TABLE and never from
-- the browser — moving prices into the database changes who can edit them, not
-- who is trusted to state them.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS content_plans (
  id          VARCHAR(32)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  price       DECIMAL(10,2) NOT NULL,
  blurb       TEXT         NULL,
  -- 'once' buys a single topic; the others buy a window of full access.
  billing     ENUM('once','week','month','year') NOT NULL DEFAULT 'month',
  -- What a buyer gets: 'all' opens everything for the window, 'topic' opens
  -- exactly one topic forever.
  scope       ENUM('all','topic') NOT NULL DEFAULT 'all',
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  position    INT          NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO content_plans (id, name, price, blurb, billing, scope, active, position) VALUES
 ('single','Individual Teaching',   49.00,'One teaching, yours to keep. Choose the topic at checkout and it stays open in your account.','once','topic',1,0),
 ('week',  'Weekly Pass',           99.00,'Everything TGFM teaches, open for seven days.','week','all',1,1),
 ('month', 'Monthly Pass',         299.00,'Every training, series and topic, open for a month.','month','all',1,2),
 ('year',  'Annual Pass',         2990.00,'A year of everything, at the lowest monthly rate TGFM offers.','year','all',1,3);

-- ─────────────────────────────────────────────────────────────────────────
-- ENTITLEMENTS.  What a one-off purchase bought.
--
-- A pass is a window on the users row; an entitlement is a permanent grant of
-- one topic. They accumulate, and they never expire — somebody who bought a
-- teaching for PHP49 owns it whether or not they later hold a pass.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS entitlements (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,        -- NULL until the account is created
  email       VARCHAR(190) NOT NULL,    -- what the purchase is held against
  training_id VARCHAR(32)  NOT NULL,
  series_id   VARCHAR(32)  NOT NULL,
  topic_id    VARCHAR(32)  NOT NULL,
  reference   VARCHAR(36)  NOT NULL,    -- the payment that bought it
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ent (email, training_id, series_id, topic_id),
  KEY ix_ent_user (user_id),
  KEY ix_ent_ref (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- DISCIPLES.  The ministry's own record of who is walking with them.
--
-- Free, and separate from `users`. A `users` row is an account with a
-- password, created by a payment; a `disciples` row is a person TGFM knows —
-- their pastor, their location, the year they attended Divine Encounter.
-- Registering is now the first step: nobody can subscribe until they are on
-- this list, so the ministry always knows who is on the other side of a
-- payment.
--
-- Keyed by email, because that is what the payment, the account and the
-- registration all have in common. Registering twice updates the record
-- rather than making a second one.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS disciples (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  gender      VARCHAR(20)  NOT NULL DEFAULT '',
  location    VARCHAR(160) NOT NULL DEFAULT '',
  pastor      VARCHAR(120) NOT NULL DEFAULT '',
  -- The year they attended Divine Encounter, or '' when they have not yet.
  de_year     VARCHAR(10)  NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_disciple_email (email),
  KEY ix_disciple_pastor (pastor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- PASTORS.  The names in the registration dropdown.
--
-- A table rather than a list in the code, for the same reason the prices are:
-- the ministry sends people out and calls them home, and neither should need
-- a developer. `active` retires a name without erasing it from the records of
-- the disciples who chose it.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS disciple_pastors (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  position    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pastor_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO disciple_pastors (name, active, position) VALUES
 ('Ps Roy and Rochel',              1,  0),
 ('Ps Dan Ramsis and RubyJane',     1,  1),
 ('Ps Joki and Marlen',             1,  2),
 ('Ps Josh and Nove Nerez',         1,  3),
 ('Ps Jun and Irish Quino',         1,  4),
 ('Ps Kris and Jen Alicante',       1,  5),
 ('Ps Robel and Mau Bello',         1,  6),
 ('Ps Bebith Baste',                1,  7),
 ('Ps Daisery Hangad',              1,  8),
 ('Ps Ella Suan',                   1,  9),
 ('Ps Flong Bernales',              1, 10),
 ('Ps Grace Migriño',               1, 11),
 ('Ps Aaron Lorilla',               1, 12),
 ('Ps Don Frias',                   1, 13);
