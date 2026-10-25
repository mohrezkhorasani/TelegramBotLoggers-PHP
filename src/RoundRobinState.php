<?php

declare(strict_types=1);

namespace TelegramLogger;

/**
 * Concurrency-safe round-robin state manager using atomic file locking (flock).
 */
class RoundRobinState
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        if ($filePath === null) {
            $dir = dirname(__DIR__) . '/storage';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $this->filePath = $dir . '/bot_index.txt';
        } else {
            $this->filePath = $filePath;
        }
    }

    /**
     * Atomically get the current bot index and advance the pointer for the next request.
     *
     * @param int $botCount Total number of available bots
     * @return int The index of the bot to use
     */
    public function getNextIndex(int $botCount): int
    {
        if ($botCount <= 1) {
            return 0;
        }

        $fp = @fopen($this->filePath, 'c+');
        if ($fp === false) {
            // Fallback in case storage is read-only
            return 0;
        }

        flock($fp, LOCK_EX);

        $content = stream_get_contents($fp);
        $currentIndex = 0;

        if ($content !== false && trim($content) !== '') {
            $parsed = (int)trim($content);
            if ($parsed >= 0) {
                $currentIndex = $parsed % $botCount;
            }
        }

        $nextIndex = ($currentIndex + 1) % $botCount;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string)$nextIndex);
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);

        return $currentIndex;
    }

    /**
     * Read the current index without modifying it (inspect only).
     */
    public function peekIndex(int $botCount): int
    {
        if ($botCount <= 1 || !file_exists($this->filePath)) {
            return 0;
        }

        $content = @file_get_contents($this->filePath);
        if ($content !== false && trim($content) !== '') {
            return ((int)trim($content)) % $botCount;
        }

        return 0;
    }

    /**
     * Reset the index back to 0.
     */
    public function reset(): void
    {
        @file_put_contents($this->filePath, '0');
    }
}
