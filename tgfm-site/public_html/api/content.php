<?php
/**
 * The content tree — Training -> Series -> Topic.
 *
 * GET  ->  { trainings: [ { id, title, blurb, hue, published, series:[ ... ] } ] }
 *
 * Public. Anyone may read it, because the titles and descriptions are what
 * sells a subscription. What is NOT public:
 *   - drafts (published = 0) are returned only to a signed-in admin
 *   - the YouTube id of a topic is withheld unless the viewer has a running
 *     subscription. Titles and lengths still come through so the outline is
 *     browsable, but the video itself cannot be lifted from the JSON.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

$user    = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$mayPlay = $isAdmin || ($user && has_access($user));

$onlyLive = $isAdmin ? '' : ' WHERE published = 1';

$trainings = db()->query(
    'SELECT id, title, blurb, hue, published FROM content_trainings' . $onlyLive . ' ORDER BY position, title'
)->fetchAll();

$seriesRows = db()->query(
    'SELECT training_id, id, title, blurb, teacher, published FROM content_series'
    . ($isAdmin ? '' : ' WHERE published = 1') . ' ORDER BY position, title'
)->fetchAll();

$topicRows = db()->query(
    'SELECT training_id, series_id, id, title, yt_id, duration, notes, published FROM content_topics'
    . ($isAdmin ? '' : ' WHERE published = 1') . ' ORDER BY position, id'
)->fetchAll();

/* Group children by parent once, rather than querying per row. */
$byTraining = [];
foreach ($seriesRows as $s) { $byTraining[$s['training_id']][] = $s; }

$bySeries = [];
foreach ($topicRows as $t) { $bySeries[$t['training_id'] . '/' . $t['series_id']][] = $t; }

$out = [];
foreach ($trainings as $t) {
    $series = [];
    foreach (($byTraining[$t['id']] ?? []) as $s) {
        $topics = [];
        foreach (($bySeries[$t['id'] . '/' . $s['id']] ?? []) as $v) {
            $topics[] = [
                'id'        => $v['id'],
                'title'     => $v['title'],
                /* The gate that actually matters: no subscription, no video id. */
                'yt'        => $mayPlay ? $v['yt_id'] : '',
                'locked'    => !$mayPlay,
                'dur'       => $v['duration'],
                'note'      => (string) $v['notes'],
                'published' => (bool) (int) $v['published'],
            ];
        }
        $series[] = [
            'id'        => $s['id'],
            'title'     => $s['title'],
            'blurb'     => (string) $s['blurb'],
            'teacher'   => $s['teacher'],
            'published' => (bool) (int) $s['published'],
            'topics'    => $topics,
        ];
    }
    $out[] = [
        'id'        => $t['id'],
        'title'     => $t['title'],
        'blurb'     => (string) $t['blurb'],
        'hue'       => (int) $t['hue'],
        'published' => (bool) (int) $t['published'],
        'series'    => $series,
    ];
}

json_out(['trainings' => $out, 'canPlay' => $mayPlay]);
