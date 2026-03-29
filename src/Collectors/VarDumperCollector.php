<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class VarDumperCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'dumps';
    }

    public static function label(): string
    {
        return 'Dumps';
    }

    public static function icon(): string
    {
        return '🧪';
    }

    public static function order(): int
    {
        return 120;
    }

    public function collect(DebugState $state): array
    {
        return ['items' => $state->dumps];
    }

    public function renderHtml(array $data): string
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return '<div class="mw-text-dim">No dumps collected.</div>';
        }

        $html = '';
        foreach ($items as $index => $dump) {
            $title = $this->e((string) ($dump['name'] ?? ('Dump #' . ($index + 1))));
            $meta = [];
            if (!empty($dump['time'])) {
                $meta[] = $this->num((float) $dump['time']) . ' ms';
            }
            if (!empty($dump['file'])) {
                $location = $this->e((string) $dump['file']);
                if (!empty($dump['line'])) {
                    $location .= ':' . $this->e((string) $dump['line']);
                }
                $meta[] = $location;
            }

            $metaHtml = $meta === [] ? '' : ' <small style="color:#9ca3af">' . implode(' · ', $meta) . '</small>';
            $html .= '<div class="mw-card" style="margin-bottom: 15px;"><div class="mw-card-h">' . $title . $metaHtml . '</div><div class="mw-card-b">' . (string) ($dump['html'] ?? '') . '</div></div>';
        }

        return $html;
    }
}
