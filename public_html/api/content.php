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

/* Two ways in, and a topic only needs one of them:
     - a running pass, which opens everything until its date
     - an Individual Teaching purchase, which opens exactly one topic forever
   Admins see everything regardless. */
$hasPass = $isAdmin || ($user && has_access($user));
$owned   = $isAdmin ? [] : owned_topics($user);
$mayPlay = $hasPass;   // the blanket answer, for the summary field below

$onlyLive = $isAdmin ? '' : ' WHERE published = 1';

$trainings = db()->query(
    'SELECT id, title, blurb, hue, image, published FROM content_trainings' . $onlyLive . ' ORDER BY position, title'
)->fetchAll();

$seriesRows = db()->query(
    'SELECT training_id, id, title, blurb, teacher, image, published FROM content_series'
    . ($isAdmin ? '' : ' WHERE published = 1') . ' ORDER BY position, title'
)->fetchAll();

$topicRows = db()->query(
    'SELECT training_id, series_id, id, title, yt_id, duration, notes, image, published FROM content_topics'
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
            $key  = $t['id'] . '/' . $s['id'] . '/' . $v['id'];
            /* The gate that actually matters: no pass and no purchase, no
               video id. Decided per topic, because an Individual Teaching
               purchase opens one and only one. */
            $open = $hasPass || isset($owned[$key]);
            $topics[] = [
                'id'        => $v['id'],
                'title'     => $v['title'],
                'yt'        => $open ? $v['yt_id'] : '',
                'locked'    => !$open,
                /* True when this one is open *because they bought it*, so the
                   app can say "yours" rather than implying a running pass. */
                'owned'     => isset($owned[$key]),
                'dur'       => $v['duration'],
                'note'      => (string) $v['notes'],
                'image'     => (string) ($v['image'] ?? ''),
                'published' => (bool) (int) $v['published'],
            ];
        }
        $series[] = [
            'id'        => $s['id'],
            'title'     => $s['title'],
            'blurb'     => (string) $s['blurb'],
            'teacher'   => $s['teacher'],
            'image'     => (string) ($s['image'] ?? ''),
            'published' => (bool) (int) $s['published'],
            'topics'    => $topics,
        ];
    }
    $out[] = [
        'id'        => $t['id'],
        'title'     => $t['title'],
        'blurb'     => (string) $t['blurb'],
        'hue'       => (int) $t['hue'],
        'image'     => (string) ($t['image'] ?? ''),
        'published' => (bool) (int) $t['published'],
        'series'    => $series,
    ];
}

/* Plans travel with the tree so the pricing page and the checkout always show
   what the database currently says, not what was hard-coded when the page was
   built. */
json_out([
    'trainings' => $out,
    'canPlay'   => $mayPlay,
    'plans'     => all_plans($isAdmin),
    'owned'     => array_keys($owned),
]);
