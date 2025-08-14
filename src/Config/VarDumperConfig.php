<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Config;

final class VarDumperConfig
{
    public function __construct(
        public readonly int $maxItems = 1000,
        public readonly int $maxString = 16000,
        public readonly int $maxDumps = 100,
        public readonly string $theme = 'dark'
    ) {
        if ($maxItems <= 0 || $maxString <= 0 || $maxDumps <= 0) {
            throw new \InvalidArgumentException('VarDumperConfig values must be positive.');
        }
        if (!in_array($theme, ['light', 'dark'], true)) {
            throw new \InvalidArgumentException('theme must be light|dark');
        }
    }
}
