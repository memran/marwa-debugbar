<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class MemoryCollector implements Collector
{
    use CollectorsTrait;
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

     private function renderHTML(array $p): string
    {
        $m = $p['memory'] ?? [];
        $cards = [
            $this->stat('Usage', $this->num((float)($m['usage_mb'] ?? 0)).' MB'),
            $this->stat('Peak',  $this->num((float)($m['peak_usage_mb'] ?? 0)).' MB'),
            $this->stat('Limit', '<span class="mw-mono">'.$this->e((string)($m['limit'] ?? '')).'</span>'),
        ];
        return '<div class="mw-grid-3">'.implode('', $cards).'</div>';
    }
}
