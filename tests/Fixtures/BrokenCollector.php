<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests\Fixtures;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;
use RuntimeException;

final class BrokenCollector implements Collector
{
    public static function key(): string
    {
        return 'broken';
    }

    public static function label(): string
    {
        return 'Broken';
    }

    public static function icon(): string
    {
        return 'B';
    }

    public static function order(): int
    {
        return 20;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(DebugState $state): array
    {
        unset($state);

        throw new RuntimeException('collect failed');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        unset($data);

        return '<div>broken</div>';
    }
}
