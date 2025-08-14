<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class PhpCollector implements Collector
{
    public function name(): string
    {
        return 'php';
    }
    public function collect(): array
    {
        return [
            'version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
        ];
    }
}
