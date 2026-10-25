<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use TelegramLogger\Env;
use TelegramLogger\BotPool;
use TelegramLogger\RoundRobinState;
use TelegramLogger\LoggerController;
use TelegramLogger\TelegramClient;

echo "=== Telegram Bot Logger Test Suite ===\n\n";

// 1. Test Env & BotPool
Env::load(dirname(__DIR__) . '/.env');
$pool = new BotPool();
echo "[TEST 1] Loading BotPool...\n";
echo "Found {$pool->count()} configured bots.\n";
if ($pool->count() < 1) {
    echo "❌ FAILED: Expected at least 1 bot token loaded from .env, got {$pool->count()}\n";
    exit(1);
}
echo "✅ PASSED: Successfully loaded {$pool->count()} bot tokens from .env.\n\n";

// 2. Test RoundRobinState cycling
echo "[TEST 2] Testing Round-Robin Pointer Advancement...\n";
$tempStateFile = sys_get_temp_dir() . '/test_rr_state_' . uniqid() . '.txt';
$state = new RoundRobinState($tempStateFile);
$botCount = 11;

$expectedSequence = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 0, 1];
$actualSequence = [];

foreach ($expectedSequence as $expected) {
    $idx = $state->getNextIndex($botCount);
    $actualSequence[] = $idx;
}

if ($actualSequence === $expectedSequence) {
    echo "✅ PASSED: Round-robin sequence matched: " . implode(' -> ', $actualSequence) . "\n\n";
} else {
    echo "❌ FAILED: Sequence mismatch. Expected " . implode(',', $expectedSequence) . " but got " . implode(',', $actualSequence) . "\n";
    @unlink($tempStateFile);
    exit(1);
}
@unlink($tempStateFile);

// 3. Test Controller Input Validation & Defaults
echo "[TEST 3] Testing Defaults & Validation...\n";
$controller = new LoggerController($pool);

$resNoMsg = $controller->handleSend(['chat_id' => -1001234567890]);
if ($resNoMsg['status'] === 400 && str_contains($resNoMsg['body']['error'], 'message')) {
    echo "✅ PASSED: Correctly rejected missing message.\n\n";
} else {
    echo "❌ FAILED: Did not reject missing message.\n";
    exit(1);
}

// 4. Test Fallback Retry Simulation
echo "[TEST 4] Testing Fallback Retry Mechanism...\n";
// Create a mock TelegramClient that fails on bot 0, 1, 2 but succeeds on bot 3
class MockTelegramClient extends TelegramClient
{
    public array $attempts = [];

    public function sendMessage(
        string $token,
        int|string $chatId,
        string $text,
        ?int $messageId = null,
        ?int $topicId = null
    ): array {
        $this->attempts[] = $token;
        // Simulate failure on first 3 attempts
        if (count($this->attempts) < 4) {
            throw new RuntimeException("Simulated network timeout for token " . substr($token, 0, 10) . "...");
        }

        return [
            'message_id' => 99999,
            'text' => $text,
        ];
    }
}

$mockClient = new MockTelegramClient();
$testStateFile = sys_get_temp_dir() . '/test_failover_' . uniqid() . '.txt';
$testState = new RoundRobinState($testStateFile);
$testController = new LoggerController($pool, $testState, $mockClient);

$result = $testController->handleSend([
    'chat_id' => -1001234567890,
    'message' => 'Test fallback log message',
]);

if ($result['status'] === 200 && $result['body']['success'] === true && $result['body']['attemptCount'] === 4) {
    echo "✅ PASSED: Fallback cascade succeeded on attempt 4 using bot #{$result['body']['usedBotIndex']}.\n";
} else {
    echo "❌ FAILED: Expected success on attempt 4, got: " . json_encode($result) . "\n";
    @unlink($testStateFile);
    exit(1);
}
@unlink($testStateFile);

// 5. Test Full Loop Failure (when all bots fail)
echo "\n[TEST 5] Testing Full Cycle Exhaustion (All Bots Fail)...\n";
class AllFailMockClient extends TelegramClient
{
    public int $callCount = 0;

    public function sendMessage(
        string $token,
        int|string $chatId,
        string $text,
        ?int $messageId = null,
        ?int $topicId = null
    ): array {
        $this->callCount++;
        throw new RuntimeException("Simulated complete Telegram network blackout");
    }
}

$allFailClient = new AllFailMockClient();
$testFailAllFile = sys_get_temp_dir() . '/test_failall_' . uniqid() . '.txt';
$testFailAllState = new RoundRobinState($testFailAllFile);
$testFailAllController = new LoggerController($pool, $testFailAllState, $allFailClient);

$failResult = $testFailAllController->handleSend([
    'chat_id' => -1001234567890,
    'message' => 'Test full failure',
]);

$expectedBots = $pool->count();
if ($failResult['status'] === 502 && $failResult['body']['totalBotsTried'] === $expectedBots && $allFailClient->callCount === $expectedBots) {
    echo "✅ PASSED: Completed full {$expectedBots}-bot loop, correctly caught all errors and returned HTTP 502.\n\n";
} else {
    echo "❌ FAILED: Unexpected result for all-fail scenario: " . json_encode($failResult) . "\n";
    @unlink($testFailAllFile);
    exit(1);
}
@unlink($testFailAllFile);

echo "🎉 ALL TESTS COMPLETED SUCCESSFULLY!\n";
