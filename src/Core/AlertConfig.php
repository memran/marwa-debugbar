<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Core;

final class AlertConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly float $slowQueryMs,
        public readonly float $slowRequestMs,
        public readonly float $slowSpanMs,
        public readonly float $highMemoryMb,
        public readonly int $largeResponseBytes
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            enabled: self::envBool('DEBUGBAR_ALERTS_ENABLED', true),
            slowQueryMs: self::envFloat('DEBUGBAR_SLOW_QUERY_MS', 100.0),
            slowRequestMs: self::envFloat('DEBUGBAR_SLOW_REQUEST_MS', 1000.0),
            slowSpanMs: self::envFloat('DEBUGBAR_SLOW_SPAN_MS', 250.0),
            highMemoryMb: self::envFloat('DEBUGBAR_HIGH_MEMORY_MB', 64.0),
            largeResponseBytes: self::envInt('DEBUGBAR_LARGE_RESPONSE_BYTES', 1048576),
        );
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function envFloat(string $name, float $default): float
    {
        $value = getenv($name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return max(0.0, round((float) $value, 2));
    }

    private static function envInt(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $default;
        }

        return max(0, (int) $value);
    }
}
