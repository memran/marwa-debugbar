<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class VarDumperCollector implements Collector
{
    use HtmlKit;

    public static function key(): string   { return 'dumps'; }
    public static function label(): string { return 'Dumps'; }
    public static function icon(): string  { return '🧪'; }
    public static function order(): int    { return 120; }

    public function collect(DebugState $state): array
    {
        // $state->dumps = [ ['name'=>?, 'file'=>?, 'line'=>?, 'html'=>string, 'time'=>float], ...]
        return ['items' => $state->dumps];
    }

    public function renderHtml(array $data): string
    {
        return $this->renderVarDumper($data['items']);
       
    }

    private function renderVarDumper(array $dumps): string
    {
        if (empty($dumps)) {
            return '<div class="mw-text-dim">No dumps collected</div>';
        }

        $html = '';
        $meta=[];
        foreach ($dumps as $i => $dump) {
            $title = htmlspecialchars($dump['name'] ?? 'Dump #'.($i + 1));
            $time = isset($dump['time']) ? $dump['time'].' ms' : '';
            if (!empty($dump['file'])) $meta[] = $this->e($dump['file']).(!empty($dump['line'])?':'.$this->e((string)$dump['line']):'');
            $time.= $meta ? ' <span style="opacity:.75">('.implode(' · ', $meta).')</span>' : '';

            $html .= <<<HTML
    <div class="mw-card" style="margin-bottom: 15px;">
        <div class="mw-card-h">{$title} <small style="color: #999;">{$time}</small></div>
        <div class="mw-card-b">{$dump['html']}</div>
    </div>
    HTML;
        }

        return $html;
    }
}
