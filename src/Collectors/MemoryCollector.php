<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class MemoryCollector implements Collector
{
    public function name(): string
    {
        return 'memory';
    }
    public function collect(): array
    {
        return [
            'peak_usage_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'usage_mb'      => round(memory_get_usage(true) / 1048576, 2),
            'limit'         => ini_get('memory_limit'),
        ];
    }
}
