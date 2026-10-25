<?php

declare(strict_types=1);

namespace TelegramLogger;

use Throwable;

/**
 * Controller handling incoming log requests, round-robin dispatching,
 * and automatic failover across the pool of bots.
 */
class LoggerController
{
    private BotPool $botPool;
    private RoundRobinState $state;
    private TelegramClient $client;
    private int|string|null $defaultChatId;
    private ?int $defaultTopicId;

    public function __construct(
        ?BotPool $botPool = null,
        ?RoundRobinState $state = null,
        ?TelegramClient $client = null
    ) {
        $this->botPool = $botPool ?? new BotPool();
        $this->state = $state ?? new RoundRobinState();

        $timeout = (int)Env::get('TELEGRAM_TIMEOUT', 10);
        $this->client = $client ?? new TelegramClient($timeout > 0 ? $timeout : 10);

        $envDefaultChat = Env::get('DEFAULT_CHAT_ID');
        $this->defaultChatId = !empty($envDefaultChat) ? $envDefaultChat : null;

        $envDefaultTopic = Env::get('DEFAULT_TOPIC_ID');
        $this->defaultTopicId = !empty($envDefaultTopic) && is_numeric($envDefaultTopic)
            ? (int)$envDefaultTopic
            : null;
    }

    /**
     * Handle the SendMessage request.
     *
     * @param array $data Parsed JSON payload
     * @return array [ 'status' => int, 'body' => array ]
     */
    public function handleSend(array $data): array
    {
        $botCount = $this->botPool->count();
        if ($botCount === 0) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'No bot tokens configured. Please check TELEGRAM_BOTS in .env.',
                ],
            ];
        }

        // Use provided chat_id or fall back to default from .env
        $chatId = !empty($data['chat_id']) ? $data['chat_id'] : $this->defaultChatId;
        if (empty($chatId)) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'chat_id is required or configure DEFAULT_CHAT_ID in .env.',
                ],
            ];
        }

        $message = $data['message'] ?? '';
        if (!is_string($message) || trim($message) === '') {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'message cannot be empty.',
                ],
            ];
        }

        $messageId = isset($data['message_id']) && (int)$data['message_id'] > 0
            ? (int)$data['message_id']
            : null;

        // Use provided topic_id or fall back to default topic (defaults to 1)
        $topicId = isset($data['topic_id']) && is_numeric($data['topic_id']) && (int)$data['topic_id'] > 0
            ? (int)$data['topic_id']
            : ($this->defaultTopicId ?? 1);

        // 🔄 Thread/Process-safe Round-Robin Pointer Advancement
        $startingIndex = $this->state->getNextIndex($botCount);

        $failureAttempts = [];

        // 🔁 Sequential Fallback Retry Loop
        for ($attempt = 0; $attempt < $botCount; $attempt++) {
            $botIndex = ($startingIndex + $attempt) % $botCount;
            $botToken = $this->botPool->getBot($botIndex);

            if ($botToken === null) {
                continue;
            }

            try {
                $result = $this->client->sendMessage(
                    token: $botToken,
                    chatId: $chatId,
                    text: $message,
                    messageId: $messageId,
                    topicId: $topicId
                );

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'usedBotIndex' => $botIndex,
                        'attemptCount' => $attempt + 1,
                        'chatId' => $chatId,
                        'topicId' => $topicId,
                        'messageId' => $result['message_id'] ?? null,
                        'message' => "sent with {$botIndex}th",
                    ],
                ];
            } catch (Throwable $e) {
                $failureAttempts[] = [
                    'botIndex' => $botIndex,
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Returned back to the starting bot with 0 successes (all bots failed)
        return [
            'status' => 502,
            'body' => [
                'success' => false,
                'error' => 'All configured Telegram bots failed to send the message after a full round-robin retry cycle.',
                'totalBotsTried' => $botCount,
                'attempts' => $failureAttempts,
            ],
        ];
    }
}
