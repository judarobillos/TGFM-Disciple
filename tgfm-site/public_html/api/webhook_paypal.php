<?php
/**
 * PayPal webhook receiver.
 *
 * Subscribe this URL in PayPal → Apps & Credentials → your app → Webhooks, to:
 *   PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED, PAYMENT.CAPTURE.REFUNDED
 * then paste the generated Webhook ID into config.php as PAYPAL_WEBHOOK_ID.
 *
 * Every body is signature-verified with PayPal before it is acted on.
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';
require __DIR__ . '/_paypal.php';

$rawBody = file_get_contents('php://input') ?: '';
$body    = json_decode($rawBody, true);
$body    = is_array($body) ? $body : [];

$event     = (string) ($body['event_type'] ?? '');
$resource  = $body['resource'] ?? [];
$reference = (string) ($resource['custom_id'] ?? $resource['invoice_id'] ?? '');

db()->prepare('INSERT INTO webhook_log (source, event, reference, verified, body)
               VALUES ("paypal", ?, ?, 0, ?)')
    ->execute([$event, $reference, $rawBody]);

function done(string $note): never { log_line('paypal webhook: ' . $note); json_out(['ok' => true]); }

if (!paypal_verify_webhook(request_headers_upper(), $rawBody)) {
    done('signature did not verify — ignored');
}
db()->prepare('UPDATE webhook_log SET verified = 1 WHERE id = ?')->execute([db()->lastInsertId()]);

if ($reference === '') { done("no reference on $event"); }
$payment = find_payment($reference);
if (!$payment) { done("unknown reference $reference"); }

$state  = (string) ($resource['status'] ?? '');
$amount = (float) ($resource['amount']['value'] ?? 0);

switch ($event) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        if ($amount > 0 && abs($amount - (float) $payment['amount']) > 0.01) {
            log_line("paypal amount mismatch on $reference: got $amount expected {$payment['amount']}");
            mark_unpaid($reference, 'failed', 'AMOUNT_MISMATCH', $rawBody);
            done("amount mismatch $reference");
        }
        mark_paid($reference, $state ?: 'COMPLETED', $rawBody);
        done("paid $reference");

    case 'PAYMENT.CAPTURE.DENIED':
    case 'PAYMENT.CAPTURE.REVERSED':
        mark_unpaid($reference, 'failed', $state ?: $event, $rawBody);
        done("failed $reference");

    case 'PAYMENT.CAPTURE.REFUNDED':
        mark_unpaid($reference, 'refunded', $state ?: $event, $rawBody);
        done("refunded $reference");
}

done("ignored $event");
