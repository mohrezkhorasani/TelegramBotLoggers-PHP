<?php

declare(strict_types=1);

namespace TelegramLogger;

/**
 * Manages the pool of configured Telegram bot tokens.
 */
class BotPool
{
    /** @var string[] */
    private array $bots = [];

    public function __construct(?array $tokens = null)
    {
        if ($tokens !== null) {
            $this->bots = array_values(array_filter(array_map('trim', $tokens)));
        } else {
            $this->loadFromEnvironment();
        }
    }

    private function loadFromEnvironment(): void
    {
        $raw = Env::get('TELEGRAM_BOTS', '');
        if (empty($raw)) {
            $this->bots = [];
            return;
        }

        $raw = trim((string)$raw);

        // Check if JSON array
        if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->bots = array_values(array_filter(array_map('trim', $decoded)));
                return;
            }
        }

        // Comma, semicolon, or newline delimited
        $items = preg_split('/[\r\n,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $this->bots = array_values(array_filter(array_map('trim', $items ?: [])));
    }

    /**
     * @return string[]
     */
    public function getBots(): array
    {
        return $this->bots;
    }

    public function count(): int
    {
        return count($this->bots);
    }

    public function isEmpty(): bool
    {
        return empty($this->bots);
    }

    public function getBot(int $index): ?string
    {
        if ($index < 0 || $index >= count($this->bots)) {
            return null;
        }
        return $this->bots[$index];
    }
}
