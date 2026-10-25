<?php

declare(strict_types=1);

namespace TelegramLogger;

use RuntimeException;

/**
 * Handles communication with the Telegram Bot API via cURL.
 */
class TelegramClient
{
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->timeout = $timeout;
    }

    /**
     * Send message through Telegram Bot API.
     *
     * @param string $token Telegram Bot Token
     * @param int|string $chatId Target Chat or Channel ID
     * @param string $text HTML-formatted message text
     * @param int|null $messageId Optional message ID to reply to
     * @param int|null $topicId Optional forum topic / message thread ID
     * @return array Telegram API response result (including message_id)
     * @throws RuntimeException On cURL network failure or Telegram API error
     */
    public function sendMessage(
        string $token,
        int|string $chatId,
        string $text,
        ?int $messageId = null,
        ?int $topicId = null
    ): array {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        // Note: In Telegram Bot API, the General topic does NOT use message_thread_id.
        // If message_thread_id: 1 is sent, Telegram returns 400 'Bad Request: message thread not found'.
        // Topics with ID > 1 are specific forum topics; topic 1 or null goes to General topic.
        if ($topicId !== null && $topicId > 1) {
            $payload['message_thread_id'] = $topicId;
        }

        if ($messageId !== null && $messageId > 0) {
            $payload['reply_parameters'] = [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'allow_sending_without_reply' => true,
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new RuntimeException("cURL network error (#{$curlErrno}): {$curlError}");
        }

        if (!is_string($responseBody) || $responseBody === '') {
            throw new RuntimeException("Empty response received from Telegram (HTTP {$httpCode})");
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON response from Telegram (HTTP {$httpCode}): {$responseBody}");
        }

        if (empty($decoded['ok'])) {
            $errorCode = $decoded['error_code'] ?? $httpCode;
            $description = $decoded['description'] ?? 'Unknown Telegram API error';
            throw new RuntimeException("Telegram API Error [{$errorCode}]: {$description}");
        }

        return $decoded['result'] ?? [];
    }
}
