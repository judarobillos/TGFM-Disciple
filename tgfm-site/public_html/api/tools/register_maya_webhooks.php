<?php
/**
 * Run this ONCE after uploading, then delete it (or the whole tools folder).
 *
 * It tells Maya where to send payment notifications. Without it, payments will
 * succeed at Maya but nobody's access will ever open.
 *
 * Open in a browser:  https://yourdomain.com/api/tools/register_maya_webhooks.php?key=YOUR_SECRET_KEY
 * The key you pass must match your Maya secret key — a small lock so a stranger
 * cannot re-point your webhooks at their own server.
 */

declare(strict_types=1);
require __DIR__ . '/../_lib.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== maya_secret_key()) {
    http_response_code(403);
    exit("Pass ?key= your Maya secret key to run this.\n");
}

$auth   = 'Authorization: Basic ' . base64_encode(maya_secret_key() . ':');
$target = SITE_URL . '/api/webhook_maya.php';
$events = ['PAYMENT_SUCCESS', 'PAYMENT_FAILED', 'PAYMENT_EXPIRED', 'PAYMENT_CANCELLED'];

echo "Environment: " . TGFM_ENV . "\n";
echo "Callback:    $target\n\n";

/* Remove anything already registered so re-running is safe. */
$existing = maya_api_json('GET', '/payments/v1/webhooks', maya_secret_key());
echo "Maya payments API answering on: {$existing['host']}\n\n";
foreach (($existing['body'] ?? []) as $hook) {
    if (!empty($hook['id'])) {
        maya_api_json('DELETE', '/payments/v1/webhooks/' . rawurlencode($hook['id']), maya_secret_key());
        echo "removed old webhook {$hook['name']}\n";
    }
}

foreach ($events as $event) {
    $res = maya_api_json('POST', '/payments/v1/webhooks', maya_secret_key(), [
        'name'        => $event,
        'callbackUrl' => $target,
    ]);
    $ok = $res['status'] >= 200 && $res['status'] < 300;
    echo str_pad($event, 20) . ($ok ? 'registered' : 'FAILED — ' . $res['raw']) . "\n";
}

echo "\nDone. Delete this file now.\n";
