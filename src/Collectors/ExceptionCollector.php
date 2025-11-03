<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class ExceptionCollector implements Collector
{
    public static function key(): string   { return 'exceptions'; }
    public static function label(): string { return 'Exceptions'; }
    public static function icon(): string  { return '🧯'; }
    public static function order(): int    { return 135; } // after Logs, before Queries

    public function collect(DebugState $state): array
    {
        // Already structured by DebugBar::addException()
        return ['items' => $state->exceptions];
    }

    public function renderHtml(array $data): string
    {
        $items = $data['items'] ?? [];
        $count = \count($items);

        $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $json = static fn($v) => htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8');

        if ($count === 0) {
            return '<div class="db-exception-empty">No exceptions captured.</div>';
        }

        $rows = array_map(function(array $ex, int $idx) use ($esc) {
            $title = $esc($ex['type'] ?? 'Throwable') . ': ' . $esc($ex['message'] ?? '');
            $meta  = [];
            if (isset($ex['time_ms'])) $meta[] = $esc(number_format((float)$ex['time_ms'], 2)).' ms';
            if (!empty($ex['file'])) $meta[] = $esc($ex['file']).(!empty($ex['line'])?':'.$esc((string)$ex['line']):'');
            if (isset($ex['code'])) $meta[] = 'code '.$esc((string)$ex['code']);

            $trace = $esc((string)($ex['trace'] ?? ''));
            $chain = $ex['chain'] ?? [];

            $chainHtml = '';
            if ($chain) {
                $chainHtml = '<div class="db-exception-chain"><b>Previous exceptions:</b><ol class="db-exception-chain-list">';
                foreach ($chain as $c) {
                    $chainHtml .= '<li class="db-exception-chain-item">'
                                . '<span class="db-exception-chain-type">' . $esc((string)($c['type'] ?? 'Throwable')) . '</span>: '
                                . '<span class="db-exception-chain-message">' . $esc((string)($c['message'] ?? '')) . '</span>'
                                . ' <span class="db-exception-chain-location">('
                                . $esc((string)($c['file'] ?? '')) . (!empty($c['line'])?':'.$esc((string)$c['line']):'')
                                . ')</span></li>';
                }
                $chainHtml .= '</ol></div>';
            }

            return '<div class="db-exception-card">'
                 .   '<div class="db-exception-header">'
                 .     '<span class="db-exception-badge">EXCEPTION</span>'
                 .     '<span class="db-exception-title">' . $title . '</span>'
                 .     ($meta ? '<span class="db-exception-meta">(' . implode(' · ', $meta) . ')</span>' : '')
                 .   '</div>'
                 .   '<div class="db-exception-body">'
                 .     '<details class="db-exception-details" open>'
                 .       '<summary class="db-exception-summary">Stack trace</summary>'
                 .       '<pre class="db-exception-trace">' . $trace . '</pre>'
                 .     '</details>'
                 .     $chainHtml
                 .   '</div>'
                 . '</div>';
        }, $items, array_keys($items));

        // Head pills
        $pill = static fn($label,$val) => '<div class="db-exception-pill"><span class="db-exception-pill-label">'.$esc($label).':</span> <span class="db-exception-pill-value">'.$esc($val).'</span></div>';
        $head = '<div class="db-exception-header-bar">'
              . $pill('Count', (string)$count)
              . '</div>';

        return $head . implode('', $rows);
    }
}