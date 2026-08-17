<?php

/**
 * Convert a Philippine mobile number to the 639XXXXXXXXX format expected by
 * Semaphore. Returns null when the value is not a valid mobile number.
 */
function normalizePhilippineMobile(string $number): ?string
{
    $digits = preg_replace('/\D+/', '', $number);
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '63' . substr($digits, 1);
    }
    if (preg_match('/^639\d{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

/**
 * Save an SMS to the outbox and immediately send it when SEMAPHORE_API_KEY is
 * configured. The returned status is sent, queued, or failed.
 */
function sendOrQueueSms(PDO $conn, string $applicationId, string $recipient, string $message): array
{
    $normalized = normalizePhilippineMobile($recipient);
    if ($normalized === null) {
        return ['status' => 'failed', 'message' => 'The cellphone number is not a valid Philippine mobile number.'];
    }

    $insert = $conn->prepare(
        'INSERT INTO sms_notifications (application_id, recipient, message, status) VALUES (?, ?, ?, ?)'
    );
    $insert->execute([$applicationId, $normalized, $message, 'queued']);
    $notificationId = (int)$conn->lastInsertId();

    $apiKey = trim((string)getenv('SEMAPHORE_API_KEY'));
    if ($apiKey === '') {
        return ['status' => 'queued', 'message' => 'SMS queued; configure SEMAPHORE_API_KEY to send it.'];
    }

    if (!function_exists('curl_init')) {
        $error = 'PHP cURL is unavailable.';
        $update = $conn->prepare('UPDATE sms_notifications SET status = ?, error_message = ? WHERE id = ?');
        $update->execute(['queued', $error, $notificationId]);
        return ['status' => 'queued', 'message' => $error];
    }

    $payload = [
        'apikey' => $apiKey,
        'number' => $normalized,
        'message' => $message,
    ];
    $senderName = trim((string)getenv('SEMAPHORE_SENDER_NAME'));
    if ($senderName !== '') {
        $payload['sendername'] = $senderName;
    }

    $curl = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    $decoded = json_decode((string)$response, true);
    $providerReference = is_array($decoded)
        ? (string)($decoded[0]['message_id'] ?? $decoded['message_id'] ?? '')
        : '';
    $providerAccepted = is_array($decoded) && $decoded !== [];
    if ($providerAccepted) {
        $items = isset($decoded[0]) ? $decoded : [$decoded];
        foreach ($items as $item) {
            if (!is_array($item) || strtolower((string)($item['status'] ?? '')) === 'failed') {
                $providerAccepted = false;
                break;
            }
        }
    }

    if ($curlError === '' && $httpCode >= 200 && $httpCode < 300 && $providerAccepted) {
        $update = $conn->prepare(
            'UPDATE sms_notifications SET status = ?, provider_reference = ?, sent_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $update->execute(['sent', $providerReference ?: null, $notificationId]);
        return ['status' => 'sent', 'message' => 'SMS sent successfully.'];
    }

    $error = $curlError !== '' ? $curlError : 'SMS provider returned HTTP ' . $httpCode;
    $update = $conn->prepare('UPDATE sms_notifications SET status = ?, error_message = ? WHERE id = ?');
    $update->execute(['queued', substr($error, 0, 500), $notificationId]);
    return ['status' => 'queued', 'message' => $error];
}
