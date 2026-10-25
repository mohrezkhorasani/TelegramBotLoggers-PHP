<?php

declare(strict_types=1);

namespace TelegramLogger;

/**
 * Lightweight .env loader and environment reader.
 */
class Env
{
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file.
     */
    public static function load(?string $filePath = null): void
    {
        if (self::$loaded && $filePath === null) {
            return;
        }

        if ($filePath === null) {
            $candidates = [
                dirname(__DIR__) . '/.env',
                dirname(__DIR__, 2) . '/.env',
                getcwd() . '/.env',
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate) && is_readable($candidate)) {
                    $filePath = $candidate;
                    break;
                }
            }
        }

        if ($filePath === null || !file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false || $pos === 0) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Strip surrounding double or single quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Only set if not already present in environment
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();

        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }
}
