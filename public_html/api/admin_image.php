<?php
/**
 * Featured images for a training, a series or a topic. Administrators only.
 *
 * POST multipart/form-data
 *      { scope: training|series|topic, trainingId, seriesId?, topicId?, image: <file> }
 *   -> { ok:true, url:"uploads/xxxx.jpg", w, h, bytes }
 *
 * POST application/json
 *      { action:'remove', scope, trainingId, seriesId?, topicId? }
 *   -> { ok:true }
 *
 * Two things are worth saying about what happens to the file.
 *
 * The type is decided by DECODING the image, not by its name or by the
 * Content-Type the browser attached — both of those are just text a caller
 * chooses. A file that will not decode as a JPEG, PNG or WebP is refused.
 *
 * And the bytes that are kept are ours, not theirs: the picture is re-encoded
 * from the decoded pixels at a sane width. That is what makes the page quick on
 * a Philippine mobile connection, and it also means nothing but image data
 * survives the trip — an EXIF block with a PHP payload in it does not.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

require_post();
guard_same_origin();
require_admin();

const IMG_MAX_BYTES = 8 * 1024 * 1024;   // what may be uploaded
const IMG_MAX_W     = 1600;              // what is kept
const IMG_MAX_H     = 1200;
const IMG_QUALITY   = 82;

$uploadDir = __DIR__ . '/../uploads';

/* Where the image column lives, per scope. */
function target(array $in): array {
    $scope = (string) ($in['scope'] ?? '');
    $id = static function (string $v): string {
        $v = trim($v);
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $v)) { fail('Bad id.'); }
        return $v;
    };
    $tid = $id((string) ($in['trainingId'] ?? ''));

    if ($scope === 'training') {
        return ['content_trainings', 'id = ?', [$tid], $tid];
    }
    if ($scope === 'series') {
        $sid = $id((string) ($in['seriesId'] ?? ''));
        return ['content_series', 'training_id = ? AND id = ?', [$tid, $sid], "$tid/$sid"];
    }
    if ($scope === 'topic') {
        $sid = $id((string) ($in['seriesId'] ?? ''));
        $vid = $id((string) ($in['topicId'] ?? ''));
        return ['content_topics', 'training_id = ? AND series_id = ? AND id = ?', [$tid, $sid, $vid], "$tid/$sid/$vid"];
    }
    fail('Say whether this is a training, a series or a topic.');
}

function current_image(string $table, string $where, array $args): string {
    $st = db()->prepare("SELECT image FROM $table WHERE $where LIMIT 1");
    $st->execute($args);
    $row = $st->fetch();
    if ($row === false) { fail('That no longer exists — reload the page.', 404); }
    return (string) ($row['image'] ?? '');
}

/** Delete a file we previously wrote, and only that: never an arbitrary path. */
function forget_image(string $stored, string $uploadDir): void {
    if ($stored === '' || !str_starts_with($stored, 'uploads/')) { return; }
    $name = basename($stored);
    if (!preg_match('/^[a-z0-9_-]+\.(jpg|png|webp)$/i', $name)) { return; }
    @unlink($uploadDir . '/' . $name);
}

/* ── remove ────────────────────────────────────────────────────────────── */
$json = body_json();
if (($json['action'] ?? '') === 'remove') {
    [$table, $where, $args, $label] = target($json);
    $old = current_image($table, $where, $args);
    db()->prepare("UPDATE $table SET image = NULL WHERE $where")->execute($args);
    forget_image($old, $uploadDir);
    log_line("image: removed from $label");
    json_out(['ok' => true, 'url' => '']);
}

/* ── upload ────────────────────────────────────────────────────────────── */
[$table, $where, $args, $label] = target($_POST);

$file = $_FILES['image'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    /* php.ini limits are the usual cause and the error code is the only clue. */
    $why = [
        UPLOAD_ERR_INI_SIZE   => 'That file is larger than this server accepts.',
        UPLOAD_ERR_FORM_SIZE  => 'That file is too large.',
        UPLOAD_ERR_PARTIAL    => 'The upload was cut short. Try again.',
        UPLOAD_ERR_NO_FILE    => 'Choose an image first.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server has nowhere to put uploads — ask the host about upload_tmp_dir.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the upload to disk.',
    ][$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'That upload did not arrive.';
    fail($why);
}
if (!is_uploaded_file($file['tmp_name'])) { fail('That upload did not arrive.'); }
if ($file['size'] > IMG_MAX_BYTES) {
    fail('Images must be under ' . (IMG_MAX_BYTES / 1024 / 1024) . ' MB. Most photos are, once exported for the web.');
}

/* Decide the type by reading the file. */
$info = @getimagesize($file['tmp_name']);
$type = is_array($info) ? ($info[2] ?? 0) : 0;
$allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
if (!isset($allowed[$type])) {
    fail('Use a JPG, PNG or WebP image.');
}
[$w, $h] = [(int) $info[0], (int) $info[1]];
if ($w < 1 || $h < 1) { fail('That image could not be read.'); }
/* A modest pixel ceiling: a decompression bomb is small on disk and enormous
   in memory, and shared hosting has very little of it to spare. */
if ($w * $h > 50_000_000) { fail('That image is too large to process. Resize it first.'); }

if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    log_line('image: uploads folder is not writable — ' . $uploadDir);
    fail('The uploads folder is missing or not writable. Run api/tools/migrate.php.', 500);
}

$name = bin2hex(random_bytes(8));
$hasGd = function_exists('imagecreatetruecolor') && function_exists('imagejpeg');

if (!$hasGd) {
    /* No GD on this host. The file is still a decoded, verified image; it just
       keeps its own bytes and its own size. Worth a log line, because slow
       pages later will look like a mystery otherwise. */
    log_line('image: GD is not installed — storing ' . $allowed[$type] . ' as uploaded');
    $stored = $name . '.' . $allowed[$type];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $stored)) {
        fail('The image could not be saved.', 500);
    }
    $outW = $w; $outH = $h;
} else {
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
    };
    if (!$src) { fail('That image could not be opened. Try exporting it again as a JPG.'); }

    /* Fit inside the box, never enlarge — upscaling a small photo only makes a
       bigger file out of the same detail. */
    $scale = min(1.0, IMG_MAX_W / $w, IMG_MAX_H / $h);
    $outW  = max(1, (int) round($w * $scale));
    $outH  = max(1, (int) round($h * $scale));

    $dst = imagecreatetruecolor($outW, $outH);
    /* Transparency has to be flattened, because the output is JPEG: without
       this, a transparent PNG logo comes out on black. White matches the page. */
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $outW, $outH, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $outW, $outH, $w, $h);
    imagedestroy($src);

    $stored = $name . '.jpg';
    $ok = imagejpeg($dst, $uploadDir . '/' . $stored, IMG_QUALITY);
    imagedestroy($dst);
    if (!$ok) { fail('The image could not be saved.', 500); }
    @chmod($uploadDir . '/' . $stored, 0644);
}

$url = 'uploads/' . $stored;
$old = current_image($table, $where, $args);
db()->prepare("UPDATE $table SET image = ? WHERE $where")->execute(array_merge([$url], $args));
/* Replace means replace: the file it displaced is no longer reachable from
   anywhere, so leaving it would just fill the account's disk quota. */
forget_image($old, $uploadDir);

log_line("image: $label -> $url ({$outW}x{$outH})");
json_out([
    'ok'    => true,
    'url'   => $url,
    'w'     => $outW,
    'h'     => $outH,
    'bytes' => (int) @filesize($uploadDir . '/' . $stored),
]);
