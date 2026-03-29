<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests\Fixtures;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class LateCollector implements Collector
{
    public static function key(): string
    {
        return 'late';
    }

    public static function label(): string
    {
        return 'Late';
    }

    public static function icon(): string
    {
        return 'L';
    }

    public static function order(): int
    {
        return 30;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(DebugState $state): array
    {
        unset($state);

        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        unset($data);

        return '<div>late</div>';
    }
}
