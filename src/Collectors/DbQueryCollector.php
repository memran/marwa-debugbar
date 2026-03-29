<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class DbQueryCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'queries';
    }

    public static function label(): string
    {
        return 'Queries';
    }

    public static function icon(): string
    {
        return '🗄️';
    }

    public static function order(): int
    {
        return 140;
    }

    /**
     * @return array{total_ms:float,count:int,items:list<array{sql:string,params:array<int|string,mixed>,duration_ms:float,connection:?string}>}
     */
    public function collect(DebugState $state): array
    {
        $queries = $state->queries;
        $total = 0.0;

        foreach ($queries as $query) {
            $total += $query['duration_ms'];
        }

        return [
            'total_ms' => round($total, 2),
            'count' => count($queries),
            'items' => $queries,
        ];
    }

    /**
     * @param array{items?:list<array{sql:string,params:array<int|string,mixed>,duration_ms:float,connection:?string}>} $data
     */
    public function renderHtml(array $data): string
    {
        return $this->renderQueries($data['items'] ?? []);
    }

    /**
     * @param list<array{sql:string,params:array<int|string,mixed>,duration_ms:float,connection:?string}> $queries
     */
    private function renderQueries(array $queries): string
    {
        if ($queries === []) {
            return '<div style="color:#9ca3af">No queries.</div>';
        }

        $rows = '';
        foreach ($queries as $query) {
            $rows .= $this->tr(
                [
                    '<span class="mw-mono">' . $this->e($query['sql']) . '</span>',
                    '<span class="mw-mono">' . $this->e($this->encodeJson($query['params'])) . '</span>',
                    $this->num($query['duration_ms']) . ' ms',
                    $this->e((string) ($query['connection'] ?? '')),
                ],
                [null, null, 'right', null]
            );
        }

        return $this->table(['SQL', 'Params', 'Duration', 'Conn'], $rows, [null, null, 'right', null]);
    }
}
