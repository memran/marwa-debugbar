<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Config;

final class HistoryConfig
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly int $maxSnapshots = 200,
        public readonly int $uiListLimit = 20
    ) {
        if ($maxSnapshots <= 0 || $uiListLimit <= 0) {
            throw new \InvalidArgumentException('HistoryConfig values must be positive.');
        }
    }
}
