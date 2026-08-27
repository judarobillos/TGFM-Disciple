<?php
/**
 * Bring an existing database up to date.
 *
 *   https://yourdomain.com/api/tools/migrate.php?key=YOUR_MAYA_SECRET_KEY
 *
 * Re-importing schema.sql adds missing TABLES (every CREATE is IF NOT EXISTS),
 * but it cannot add a missing COLUMN to a table you already have. That is what
 * this does. It is written against INFORMATION_SCHEMA rather than
 * "ADD COLUMN IF NOT EXISTS", which MariaDB understands and MySQL 8 does not —
 * betting on one would break half the installs it is meant to fix.
 *
 * Safe to run as often as you like: it only ever adds what is missing, and it
 * never drops or rewrites anything. Run it AFTER re-importing schema.sql.
 *
 * Delete it with the rest of the tools folder.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= the Maya secret key currently in use to run this.\n");
}

$added = 0; $already = 0; $problems = 0;

function has_table(string $table): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}
function has_column(string $table, string $column): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $column]);
    return (bool) $st->fetchColumn();
}
function add_column(string $table, string $column, string $definition): void {
    global $added, $already, $problems;
    if (!has_table($table)) {
        echo "  skip  `$table` does not exist — re-import schema.sql first\n";
        $problems++;
        return;
    }
    if (has_column($table, $column)) {
        echo "  ok    `$table`.`$column` already there\n";
        $already++;
        return;
    }
    try {
        /* Table and column names are fixed literals in this file, never input. */
        db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "  ADDED `$table`.`$column`\n";
        $added++;
    } catch (Throwable $e) {
        echo "  FAIL  `$table`.`$column` — " . $e->getMessage() . "\n";
        $problems++;
    }
}

echo "TGFM database migration — " . date('c') . "\n";
echo str_repeat('=', 60) . "\n\n";

echo "Tables\n";
foreach (['users', 'payments', 'webhook_log',
          'content_trainings', 'content_series', 'content_topics',
          'content_plans', 'entitlements',
          'disciples', 'disciple_pastors'] as $t) {
    if (has_table($t)) { echo "  ok    `$t`\n"; }
    else { echo "  MISSING `$t` — re-import private/schema.sql, then run this again\n"; $problems++; }
}
echo "\n";

echo "Columns\n";
add_column('payments',          'topic_ref', 'VARCHAR(104) NULL');

/* `period` shipped as ENUM('week','month','year'). The one-off Individual
   Teaching plan needs 'once', and inserting a value an ENUM does not list is a
   hard error, so widen it to VARCHAR. */
if (has_table('payments')) {
    $st = db()->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'period'");
    $st->execute();
    $type = strtolower((string) $st->fetchColumn());
    if (str_starts_with($type, 'enum') && !str_contains($type, "'once'")) {
        try {
            db()->exec("ALTER TABLE `payments` MODIFY `period` VARCHAR(10) NOT NULL");
            echo "  ADDED `payments`.`period` widened to VARCHAR(10) so 'once' fits\n";
            $added++;
        } catch (Throwable $e) {
            echo "  FAIL  could not widen `payments`.`period` — " . $e->getMessage() . "\n";
            $problems++;
        }
    } else {
        echo "  ok    `payments`.`period` already accepts 'once'\n";
        $already++;
    }
}
/* `users.access_until` shipped NOT NULL, when every account came with a pass.
   An Individual Teaching buyer holds no pass at all, and NULL is the only
   honest way to say so — any date either grants a day of everything or shows
   up in the Disciples list as an expired subscription they never had. */
if (has_table('users')) {
    $st = db()->prepare("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'access_until'");
    $st->execute();
    if (strtoupper((string) $st->fetchColumn()) === 'NO') {
        try {
            db()->exec("ALTER TABLE `users` MODIFY `access_until` DATE NULL");
            echo "  ADDED `users`.`access_until` may now be empty, for buyers who hold no pass\n";
            $added++;
        } catch (Throwable $e) {
            echo "  FAIL  could not relax `users`.`access_until` — " . $e->getMessage() . "\n";
            $problems++;
        }
    } else {
        echo "  ok    `users`.`access_until` already allows no-pass accounts\n";
        $already++;
    }
}
add_column('content_trainings', 'image',     'VARCHAR(255) NULL');
add_column('content_series',    'image',     'VARCHAR(255) NULL');
add_column('content_topics',    'image',     'VARCHAR(255) NULL');
echo "\n";

/* The pastors dropdown is seeded by schema.sql. If the table arrived empty —
   an import that skipped the INSERTs, say — the sign-up form would have an
   empty dropdown and nobody could register, so fill it here too. */
echo "Pastors\n";
if (has_table('disciple_pastors')) {
    $have = (int) db()->query('SELECT COUNT(*) FROM disciple_pastors')->fetchColumn();
    if ($have > 0) {
        echo "  ok    $have name" . ($have === 1 ? '' : 's') . " on the sign-up dropdown\n";
        $already++;
    } else {
        $names = ['Ps Roy and Rochel', 'Ps Dan Ramsis and RubyJane', 'Ps Joki and Marlen',
                  'Ps Josh and Nove Nerez', 'Ps Jun and Irish Quino', 'Ps Kris and Jen Alicante',
                  'Ps Robel and Mau Bello', 'Ps Bebith Baste', 'Ps Daisery Hangad',
                  'Ps Ella Suan', 'Ps Flong Bernales', 'Ps Grace Migriño',
                  'Ps Aaron Lorilla', 'Ps Don Frias'];
        try {
            $st = db()->prepare('INSERT INTO disciple_pastors (name, active, position) VALUES (?, 1, ?)');
            foreach ($names as $i => $n) { $st->execute([$n, $i]); }
            echo '  ADDED ' . count($names) . " pastors seeded\n";
            $added++;
        } catch (Throwable $e) {
            echo '  FAIL  could not seed the pastors — ' . $e->getMessage() . "\n";
            $problems++;
        }
    }
} else {
    echo "  MISSING `disciple_pastors` — re-import private/schema.sql\n";
    $problems++;
}
echo "\n";

/* The uploads folder has to exist and be writable before the admin can put a
   featured image anywhere. */
echo "Uploads\n";
$dir = __DIR__ . '/../../uploads';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
if (!is_dir($dir))            { echo "  FAIL  could not create $dir — make it by hand in File Manager\n"; $problems++; }
elseif (!is_writable($dir))   { echo "  FAIL  $dir is not writable — set its permissions to 755\n"; $problems++; }
else                          { echo "  ok    " . realpath($dir) . " is writable\n"; }

/* Uploads are re-encoded from decoded pixels, so a script cannot arrive in one.
   This is the second lock on the same door: even if something ever did land
   there, the folder will not run it. */
if (is_dir($dir)) {
    $ht = $dir . '/.htaccess';
    $rule = "# Pictures only. Never execute anything from this folder.\n"
          . "php_flag engine off\n"
          . "<FilesMatch \"\\.(php|phtml|php[0-9]|pl|py|cgi|sh|htaccess)$\">\n"
          . "  Require all denied\n"
          . "</FilesMatch>\n";
    if (is_file($ht)) { echo "  ok    uploads/.htaccess already there\n"; $already++; }
    elseif (@file_put_contents($ht, $rule) !== false) { echo "  ADDED uploads/.htaccess — scripts cannot run there\n"; $added++; }
    else { echo "  FAIL  could not write uploads/.htaccess\n"; $problems++; }
}
echo "\n";

echo str_repeat('=', 60) . "\n";
echo "$added added · $already already in place · $problems to look at\n";
echo $problems
    ? "\nFix the lines above and run this again.\n"
    : "\nNothing left to do. Delete the tools folder when setup is finished.\n";
