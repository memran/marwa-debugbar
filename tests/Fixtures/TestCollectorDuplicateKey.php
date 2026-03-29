<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests\Fixtures;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class TestCollectorDuplicateKey implements Collector
{
    public static function key(): string
    {
        return 'test';
    }

    public static function label(): string
    {
        return 'Duplicate';
    }

    public static function icon(): string
    {
        return 'D';
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

        return [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderHtml(array $data): string
    {
        unset($data);

        return '<div>duplicate</div>';
    }
}
