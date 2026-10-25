<?php

declare(strict_types=1);

require_once __DIR__ . '/src/autoload.php';

use TelegramLogger\Env;
use TelegramLogger\BotPool;
use TelegramLogger\RoundRobinState;
use TelegramLogger\LoggerController;

// Load environment variables from .env
Env::load(__DIR__ . '/.env');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$defaultChat = Env::get('DEFAULT_CHAT_ID', '');
$defaultTopic = Env::get('DEFAULT_TOPIC_ID', '1');

// Health check and root status endpoint
if ($requestMethod === 'GET') {
    $pool = new BotPool();
    $state = new RoundRobinState(__DIR__ . '/storage/bot_index.txt');

    echo json_encode([
        'status' => 'online',
        'service' => 'Telegram Bot Logger API (PHP)',
        'configuredBots' => $pool->count(),
        'nextBotIndex' => $state->peekIndex($pool->count()),
        'defaults' => [
            'chat_id' => !empty($defaultChat) ? $defaultChat : 'Not configured (pass in request)',
            'topic_id' => !empty($defaultTopic) ? (int)$defaultTopic : 1,
        ],
        'endpoints' => [
            'POST /api/telegram/send' => [
                'description' => 'Send log/message via round-robin Telegram bot pool',
                'parameters' => [
                    'chat_id' => 'int|string (optional if DEFAULT_CHAT_ID set in .env)',
                    'message' => 'string (required, HTML supported)',
                    'topic_id' => 'int (optional, default: 1)',
                    'message_id' => 'int (optional, reply to message)',
                ],
            ],
        ],
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Handle POST request (send log)
if ($requestMethod === 'POST') {
    // Read raw input
    $rawInput = file_get_contents('php://input');
    $data = [];

    if (!empty($rawInput)) {
        // Tolerant parser: handle accidental malformed JSON like '"topic_id":' or '"chat_id":' with missing value
        $cleanInput = preg_replace('/"topic_id"\s*:\s*(,|}|\s*$)/', '"topic_id": ' . ($defaultTopic ?: '1') . '$1', $rawInput);
        if (!empty($defaultChat)) {
            $cleanInput = preg_replace('/"chat_id"\s*:\s*(,|}|\s*$)/', '"chat_id": ' . $defaultChat . '$1', $cleanInput);
        }

        $decoded = json_decode($cleanInput, true);
        if (is_array($decoded)) {
            $data = $decoded;
        } else {
            // Try raw decode
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // Merge with $_POST if provided as form data or x-www-form-urlencoded
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }

    $controller = new LoggerController(
        state: new RoundRobinState(__DIR__ . '/storage/bot_index.txt')
    );
    $response = $controller->handleSend($data);

    http_response_code($response['status']);
    echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Method Not Allowed for other HTTP methods
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => "Method {$requestMethod} Not Allowed. Use POST /api/telegram/send or GET /.",
]);
