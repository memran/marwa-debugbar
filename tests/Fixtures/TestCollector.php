<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests\Fixtures;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class TestCollector implements Collector
{
    public static function key(): string
    {
        return 'test';
    }

    public static function label(): string
    {
        return 'Test';
    }

    public static function icon(): string
    {
        return 'T';
    }

    public static function order(): int
    {
        return 10;
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(DebugState $state): array
    {
        unset($state);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        unset($data);

        return '<div>ok</div>';
    }
}
