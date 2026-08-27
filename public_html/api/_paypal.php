<?php
/** PayPal helpers: access token, link picking, webhook signature check. */

declare(strict_types=1);

/** OAuth2 client-credentials token, cached for the life of the request. */
function paypal_token(): ?string
{
    static $token = null;
    if ($token !== null) { return $token; }

    $ch = curl_init(paypal_base() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => paypal_client_id() . ':' . paypal_secret(),
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $status !== 200) {
        log_line('PayPal token request failed (' . $status . '): ' . (string) $raw);
        return null;
    }
    $body = json_decode((string) $raw, true);
    $token = $body['access_token'] ?? null;
    return $token;
}

/** Pull the first matching rel out of a PayPal links array. */
function paypal_link(array $body, array $rels): ?string
{
    foreach ($rels as $rel) {
        foreach (($body['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === $rel && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }
    }
    return null;
}

/**
 * Ask PayPal whether a webhook really came from PayPal.
 * Never trust a webhook body that fails this.
 */
function paypal_verify_webhook(array $headers, string $rawBody): bool
{
    $token = paypal_token();
    if ($token === null) { return false; }

    $need = ['PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID',
             'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME'];
    foreach ($need as $h) {
        if (empty($headers[$h])) { log_line("PayPal webhook missing header $h"); return false; }
    }

    $payload = [
        'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'],
        'cert_url'          => $headers['PAYPAL-CERT-URL'],
        'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'],
        'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'],
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'],
        'webhook_id'        => PAYPAL_WEBHOOK_ID,
        'webhook_event'     => json_decode($rawBody, true),
    ];

    $res = http_json('POST', paypal_base() . '/v1/notifications/verify-webhook-signature',
        ['Authorization: Bearer ' . $token], $payload);

    return ($res['body']['verification_status'] ?? '') === 'SUCCESS';
}

/** Normalised, uppercase request headers. */
function request_headers_upper(): array
{
    $out = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $out[str_replace('_', '-', substr($key, 5))] = $value;
        }
    }
    return array_change_key_case($out, CASE_UPPER);
}
