<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class ExceptionCollector implements Collector
{
    public static function key(): string
    {
        return 'exceptions';
    }

    public static function label(): string
    {
        return 'Exceptions';
    }

    public static function icon(): string
    {
        return '🧯';
    }

    public static function order(): int
    {
        return 135;
    }

    /**
     * @return array{items:list<array{
     *   type:string,
     *   message:string,
     *   code:int,
     *   file:string,
     *   line:int,
     *   time_ms:float,
     *   trace:string,
     *   chain:list<array{type:string,message:string,code:int,file:string,line:int}>
     * }>}
     */
    public function collect(DebugState $state): array
    {
        return ['items' => $state->exceptions];
    }

    /**
     * @param array{items?:list<array{
     *   type:string,
     *   message:string,
     *   code:int,
     *   file:string,
     *   line:int,
     *   time_ms:float,
     *   trace:string,
     *   chain:list<array{type:string,message:string,code:int,file:string,line:int}>
     * }>} $data
     */
    public function renderHtml(array $data): string
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            return '<div class="db-exception-empty">No exceptions captured.</div>';
        }

        $header = '<div class="db-exception-header-bar">'
            . '<div class="db-exception-pill"><span class="db-exception-pill-label">Count:</span> <span class="db-exception-pill-value">'
            . count($items)
            . '</span></div></div>';

        $cards = '';
        foreach ($items as $exception) {
            $title = $this->escape($exception['type']) . ': ' . $this->escape($exception['message']);
            $meta = [
                number_format($exception['time_ms'], 2) . ' ms',
                $this->escape($exception['file']) . ':' . $exception['line'],
                'code ' . $exception['code'],
            ];

            $cards .= '<div class="db-exception-card">'
                . '<div class="db-exception-header">'
                . '<span class="db-exception-badge">EXCEPTION</span>'
                . '<span class="db-exception-title">' . $title . '</span>'
                . '<span class="db-exception-meta">(' . implode(' · ', $meta) . ')</span>'
                . '</div>'
                . '<div class="db-exception-body">'
                . '<details class="db-exception-details" open>'
                . '<summary class="db-exception-summary">Stack trace</summary>'
                . '<pre class="db-exception-trace">' . $this->escape($exception['trace']) . '</pre>'
                . '</details>'
                . $this->renderChain($exception['chain'])
                . '</div></div>';
        }

        return $header . $cards;
    }

    /**
     * @param list<array{type:string,message:string,code:int,file:string,line:int}> $chain
     */
    private function renderChain(array $chain): string
    {
        if ($chain === []) {
            return '';
        }

        $items = '';
        foreach ($chain as $item) {
            $items .= '<li class="db-exception-chain-item"><span class="db-exception-chain-type">'
                . $this->escape($item['type'])
                . '</span>: <span class="db-exception-chain-message">'
                . $this->escape($item['message'])
                . '</span> <span class="db-exception-chain-location">('
                . $this->escape($item['file'])
                . ':'
                . $item['line']
                . ')</span></li>';
        }

        return '<div class="db-exception-chain"><b>Previous exceptions:</b><ol class="db-exception-chain-list">'
            . $items
            . '</ol></div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
