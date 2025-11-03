<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class DbQueryCollector implements Collector
{
  use HtmlKit;

    public static function key(): string   { return 'queries'; }
    public static function label(): string { return 'Queries'; }
    public static function icon(): string  { return '🗄️'; }
    public static function order(): int    { return 140; }

    public function collect(DebugState $state): array
    {
        $qs = $state->queries; // [ ['sql','params','duration_ms','connection'], ... ]
        $total = 0.0;
        foreach ($qs as $q) $total += (float)($q['duration_ms'] ?? 0.0);
        return ['total_ms' => round($total, 2), 'count' => \count($qs), 'items' => $qs];
    }

    public function renderHtml(array $data): string
    {
        return $this->renderQueries($data['items']);

    }

     private function renderQueries(array $qs): string
    {
        //$qs = $p['queries'] ?? [];
        if (!$qs) return '<div style="color:#9ca3af">No queries.</div>';

        $rows = '';
        foreach ($qs as $q) {
            $rows .= $this->tr([
                '<span class="mw-mono">'.$this->e((string)($q['sql'] ?? '')).'</span>',
                '<span class="mw-mono">'. $this->e(json_encode($q['params'] ?? [], JSON_UNESCAPED_SLASHES)) .'</span>',
                $this->num((float)($q['duration_ms'] ?? 0)).' ms',
                $this->e((string)($q['connection'] ?? '')),
            ], [null,null,'right',null]);
        }

        return $this->table(['SQL','Params','Duration','Conn'], $rows, [null,null,'right',null]);
    }
}
