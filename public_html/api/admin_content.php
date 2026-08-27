<?php
/**
 * Every content write. Administrators only.
 *
 * POST { action, ... }
 *
 *   create_training  { training:{id,title,blurb,hue,published} }
 *   save_training    { training:{id,title,blurb,hue,published} }
 *   delete_training  { id }
 *
 *   create_series    { trainingId, series:{id,title,blurb,teacher,published} }
 *   save_series      { trainingId, series:{...} }
 *   delete_series    { trainingId, id }
 *   reorder_series   { trainingId, order:[id,...] }
 *
 *   create_topic     { trainingId, seriesId, topic:{id,title,yt,dur,note,published} }
 *   save_topic       { trainingId, seriesId, topic:{...} }
 *   delete_topic     { trainingId, seriesId, id }
 *   reorder_topics   { trainingId, seriesId, order:[id,...] }
 *
 * This file is the reason an admin's edit is seen by everyone: it writes to
 * MySQL rather than to the editing browser.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

require_post();
guard_same_origin();
require_admin();

$in     = body_json();
$action = (string) ($in['action'] ?? '');

/* Ids come from the browser, so they are treated as untrusted input: short,
   and limited to characters that cannot confuse a query or a URL. */
function clean_id(string $id): string {
    $id = trim($id);
    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id)) { fail('Bad id.'); }
    return $id;
}
function clean_text(?string $s, int $max): string {
    $s = trim((string) $s);
    return mb_substr($s, 0, $max);
}
function next_position(string $table, array $where): int {
    $sql = 'SELECT COALESCE(MAX(position), -1) + 1 AS n FROM ' . $table;
    $args = [];
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        $args = array_values($where);
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return (int) $st->fetchColumn();
}
function training_or_fail(string $id): void {
    $st = db()->prepare('SELECT 1 FROM content_trainings WHERE id = ?');
    $st->execute([$id]);
    if (!$st->fetchColumn()) { fail('That training no longer exists.', 404); }
}
function series_or_fail(string $tid, string $sid): void {
    $st = db()->prepare('SELECT 1 FROM content_series WHERE training_id = ? AND id = ?');
    $st->execute([$tid, $sid]);
    if (!$st->fetchColumn()) { fail('That series no longer exists.', 404); }
}

switch ($action) {

/* ── trainings ─────────────────────────────────────────────────────────── */
case 'create_training': {
    $t  = $in['training'] ?? [];
    $id = clean_id((string) ($t['id'] ?? ''));
    db()->prepare(
        'INSERT INTO content_trainings (id, title, blurb, hue, position, published)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $id,
        clean_text($t['title'] ?? '', 160) ?: 'Untitled training',
        clean_text($t['blurb'] ?? '', 4000),
        max(0, min(360, (int) ($t['hue'] ?? 220))),
        next_position('content_trainings', []),
        !empty($t['published']) ? 1 : 0,
    ]);
    log_line("content: created training $id");
    json_out(['ok' => true, 'id' => $id]);
}

case 'save_training': {
    $t  = $in['training'] ?? [];
    $id = clean_id((string) ($t['id'] ?? ''));
    training_or_fail($id);
    db()->prepare('UPDATE content_trainings SET title = ?, blurb = ?, hue = ?, published = ? WHERE id = ?')
        ->execute([
            clean_text($t['title'] ?? '', 160) ?: 'Untitled training',
            clean_text($t['blurb'] ?? '', 4000),
            max(0, min(360, (int) ($t['hue'] ?? 220))),
            !empty($t['published']) ? 1 : 0,
            $id,
        ]);
    json_out(['ok' => true]);
}

case 'delete_training': {
    $id = clean_id((string) ($in['id'] ?? ''));
    /* The foreign keys cascade, so its series and topics go with it. */
    db()->prepare('DELETE FROM content_trainings WHERE id = ?')->execute([$id]);
    log_line("content: deleted training $id");
    json_out(['ok' => true]);
}

/* ── series ────────────────────────────────────────────────────────────── */
case 'create_series': {
    $tid = clean_id((string) ($in['trainingId'] ?? ''));
    training_or_fail($tid);
    $s   = $in['series'] ?? [];
    $sid = clean_id((string) ($s['id'] ?? ''));
    db()->prepare(
        'INSERT INTO content_series (training_id, id, title, blurb, teacher, position, published)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $tid, $sid,
        clean_text($s['title'] ?? '', 160) ?: 'Untitled series',
        clean_text($s['blurb'] ?? '', 4000),
        clean_text($s['teacher'] ?? '', 120),
        next_position('content_series', ['training_id' => $tid]),
        !empty($s['published']) ? 1 : 0,
    ]);
    log_line("content: created series $tid/$sid");
    json_out(['ok' => true, 'id' => $sid]);
}

case 'save_series': {
    $tid = clean_id((string) ($in['trainingId'] ?? ''));
    $s   = $in['series'] ?? [];
    $sid = clean_id((string) ($s['id'] ?? ''));
    series_or_fail($tid, $sid);
    db()->prepare('UPDATE content_series SET title = ?, blurb = ?, teacher = ?, published = ?
                   WHERE training_id = ? AND id = ?')
        ->execute([
            clean_text($s['title'] ?? '', 160) ?: 'Untitled series',
            clean_text($s['blurb'] ?? '', 4000),
            clean_text($s['teacher'] ?? '', 120),
            !empty($s['published']) ? 1 : 0,
            $tid, $sid,
        ]);
    json_out(['ok' => true]);
}

case 'delete_series': {
    $tid = clean_id((string) ($in['trainingId'] ?? ''));
    $sid = clean_id((string) ($in['id'] ?? ''));
    db()->prepare('DELETE FROM content_series WHERE training_id = ? AND id = ?')->execute([$tid, $sid]);
    log_line("content: deleted series $tid/$sid");
    json_out(['ok' => true]);
}

case 'reorder_series': {
    $tid   = clean_id((string) ($in['trainingId'] ?? ''));
    $order = is_array($in['order'] ?? null) ? $in['order'] : [];
    $st = db()->prepare('UPDATE content_series SET position = ? WHERE training_id = ? AND id = ?');
    foreach ($order as $i => $sid) { $st->execute([$i, $tid, clean_id((string) $sid)]); }
    json_out(['ok' => true]);
}

/* ── topics ────────────────────────────────────────────────────────────── */
case 'create_topic':
case 'save_topic': {
    $tid = clean_id((string) ($in['trainingId'] ?? ''));
    $sid = clean_id((string) ($in['seriesId'] ?? ''));
    series_or_fail($tid, $sid);

    $v   = $in['topic'] ?? [];
    $vid = clean_id((string) ($v['id'] ?? ''));

    /* Only ever store the 11-character YouTube id, never a whole URL — the
       front end parses it, but a hand-crafted request must not slip a link in. */
    $yt = trim((string) ($v['yt'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $yt)) { fail('That is not a YouTube video id.'); }

    $dur = trim((string) ($v['dur'] ?? '00:00'));
    if (!preg_match('/^\d{1,3}:\d{2}$/', $dur)) { $dur = '00:00'; }

    $title = clean_text($v['title'] ?? '', 200) ?: 'Untitled topic';
    $note  = clean_text($v['note'] ?? '', 4000);
    $pub   = !empty($v['published']) ? 1 : 0;

    if ($action === 'create_topic') {
        db()->prepare(
            'INSERT INTO content_topics (training_id, series_id, id, title, yt_id, duration, notes, position, published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tid, $sid, $vid, $title, $yt, $dur, $note,
            next_position('content_topics', ['training_id' => $tid, 'series_id' => $sid]), $pub]);
        log_line("content: created topic $tid/$sid/$vid ($yt)");
    } else {
        db()->prepare(
            'UPDATE content_topics SET title = ?, yt_id = ?, duration = ?, notes = ?, published = ?
             WHERE training_id = ? AND series_id = ? AND id = ?'
        )->execute([$title, $yt, $dur, $note, $pub, $tid, $sid, $vid]);
    }
    json_out(['ok' => true, 'id' => $vid]);
}

case 'delete_topic': {
    $tid = clean_id((string) ($in['trainingId'] ?? ''));
    $sid = clean_id((string) ($in['seriesId'] ?? ''));
    $vid = clean_id((string) ($in['id'] ?? ''));
    db()->prepare('DELETE FROM content_topics WHERE training_id = ? AND series_id = ? AND id = ?')
        ->execute([$tid, $sid, $vid]);
    log_line("content: deleted topic $tid/$sid/$vid");
    json_out(['ok' => true]);
}

case 'reorder_topics': {
    $tid   = clean_id((string) ($in['trainingId'] ?? ''));
    $sid   = clean_id((string) ($in['seriesId'] ?? ''));
    $order = is_array($in['order'] ?? null) ? $in['order'] : [];
    $st = db()->prepare('UPDATE content_topics SET position = ? WHERE training_id = ? AND series_id = ? AND id = ?');
    foreach ($order as $i => $vid) { $st->execute([$i, $tid, $sid, clean_id((string) $vid)]); }
    json_out(['ok' => true]);
}

}

fail('Unknown action.', 404);
