<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class LogCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'logs';
    }
    public static function label(): string
    {
        return 'Logs';
    }
    public static function icon(): string
    {
        return '📝';
    }
    public static function order(): int
    {
        return 130;
    }

    public function collect(DebugState $state): array
    {
        // $state->logs: [ ['time'=>ms,'level','message','context'=>[]], ... ]
        return ['items' => $state->logs];
    }

    public function renderHtml(array $data): string
    {
        return $this->renderLogs($data['items'] ?? []);
    }

    /**
     * @param list<array{time:float,level:string,message:string,context:array<string,mixed>}> $logs
     */
    private function renderLogs(array $logs): string
    {
        //$logs = $p['logs'] ?? [];
        if (!$logs) {
            return '<div style="color:#9ca3af">No logs.</div>';
        }

        $rows = '';
        foreach ($logs as $l) {
            $rows .= $this->tr([
                $this->num($l['time']) . ' ms',
                '<span class="mw-badgel">' . $this->e($l['level']) . '</span>',
                $this->e($l['message']),
                '<span class="mw-mono">' . $this->e($this->encodeJson($l['context'])) . '</span>',
            ], ['right',null,null,null]);
        }

        return $this->table(['Time','Level','Message','Context'], $rows, ['right',null,null,null]);
    }
}
