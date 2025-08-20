<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class MemoryCollector implements Collector
{
    use HtmlKit;

    public static function key(): string { return 'memory'; }
    public static function label(): string { return 'Memory'; }
    public static function icon(): string { return '💾'; }
    public static function order(): int { return 150; }

    public function collect(DebugState $state): array
    {
        return [
            'usage_mb'      => round(memory_get_usage(true) / 1048576, 2),
            'peak_usage_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'limit'         => ini_get('memory_limit'),
        ];
    }

    public function renderHtml(array $m): string
    {
        $cards = [
            $this->stat('Usage', $this->num((float)($m['usage_mb'] ?? 0)).' MB'),
            $this->stat('Peak',  $this->num((float)($m['peak_usage_mb'] ?? 0)).' MB'),
            $this->stat('Limit', '<span class="mw-mono">'.$this->e($m['limit'] ?? '').'</span>'),
        ];
        return '<div class="mw-grid-3">'.implode('', $cards).'</div>';

   
    }

}
