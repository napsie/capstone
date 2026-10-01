<?php
declare(strict_types=1);

final class EmailDeliveryException extends RuntimeException {}

/** Send one transactional email through Brevo's HTTPS API. */
function sendBrevoEmail(
    string $apiKey,
    array $sender,
    array $recipient,
    string $subject,
    string $htmlBody,
    string $textBody
): string {
    if (!function_exists('curl_init')) {
        throw new EmailDeliveryException('The PHP cURL extension is required to send email.');
    }
    if (!filter_var($sender['email'] ?? '', FILTER_VALIDATE_EMAIL)
        || !filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        throw new EmailDeliveryException('A valid sender and recipient email address are required.');
    }

    $payload = json_encode([
        'sender' => ['email' => $sender['email'], 'name' => (string)($sender['name'] ?? '')],
        'to' => [['email' => $recipient['email'], 'name' => (string)($recipient['name'] ?? '')]],
        'subject' => $subject,
        'htmlContent' => $htmlBody,
        'textContent' => $textBody,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($curl === false) throw new EmailDeliveryException('Unable to initialize the email request.');

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $transportError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        throw new EmailDeliveryException('Brevo connection failed: ' . $transportError);
    }

    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        $providerMessage = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
        throw new EmailDeliveryException('Brevo rejected the email request (HTTP ' . $status . ')' . ($providerMessage !== '' ? ': ' . $providerMessage : '.'));
    }

    $messageId = is_array($decoded) ? trim((string)($decoded['messageId'] ?? '')) : '';
    if ($messageId === '') throw new EmailDeliveryException('Brevo accepted the request without returning a message ID.');
    return $messageId;
}
